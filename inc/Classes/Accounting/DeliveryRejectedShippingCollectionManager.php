<?php
declare(strict_types=1);

require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/FinancialEventManager.php';
require_once __DIR__ . '/AccountSettings.php';

/**
 * تحصيل رسوم الشحن عند رفض العميل للتوصيل بعد المحاولة.
 * يسجل رسوم الشحن مرة واحدة لكل طلب في عهدة المندوب.
 */
final class DeliveryRejectedShippingCollectionManager
{
    private PDO $db;
    private JournalManager $journal;
    private FinancialEventManager $events;
    private AccountSettings $accounts;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->journal = new JournalManager($db);
        $this->events = new FinancialEventManager($db);
        $this->accounts = new AccountSettings($db);
    }

    /**
     * يجب استدعاء الدالة داخل معاملة تأكيد الشحنة نفسها وبعد إنشاء shipping_movement.
     *
     * @return array{processed:int,posted:int,already_collected:int,no_charge:int}
     */
    public function collectPaidRejectedShipping(
        string $sessionId,
        int $expectedBranchId,
        int $actorId
    ): array {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || strlen($sessionId) > 64 || $expectedBranchId <= 0 || $actorId <= 0) {
            throw new InvalidArgumentException('بيانات تحصيل رسوم الشحن غير مكتملة.');
        }
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('يجب تسجيل تحصيل رسوم الشحن داخل معاملة تأكيد الشحنة.');
        }

        $stmt = $this->db->prepare(
            "SELECT sm.shipmov_id, sm.shipment_orders AS order_id, sm.delivery_id,
                    sm.MOVING_DATE, o.ORDER_TYPE_ID, o.SHIPPING_VAL, o.CLIENT_ID,
                    s.BRANCH_ID AS order_branch_id
             FROM shipping_movement sm
             INNER JOIN orders o ON o.ORDER_ID = sm.shipment_orders
             LEFT JOIN stores s ON s.STO_ID = o.STO_ID
             WHERE sm.UNIQUE_ID = ? AND sm.movement_type = 5
             ORDER BY sm.shipmov_id
             FOR UPDATE"
        );
        $stmt->execute([$sessionId]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$attempts) {
            throw new RuntimeException('لم يتم العثور على حركة رفض توصيل لهذه المحاولة؛ لم يتم تسجيل تحصيل الشحن.');
        }

        $result = [
            'processed' => count($attempts),
            'posted' => 0,
            'already_collected' => 0,
            'no_charge' => 0,
        ];

        $settings = null;
        foreach ($attempts as $attempt) {
            $shipMovementId = (int)$attempt['shipmov_id'];
            $orderId = (int)$attempt['order_id'];
            $deliveryId = (int)$attempt['delivery_id'];
            $orderBranchId = (int)($attempt['order_branch_id'] ?? 0);

            if ($shipMovementId <= 0 || $orderId <= 0 || $deliveryId <= 0) {
                throw new RuntimeException('بيانات حركة رفض التوصيل غير صالحة لتحصيل رسوم الشحن.');
            }
            if ((int)$attempt['ORDER_TYPE_ID'] === 5) {
                throw new RuntimeException('لا يجوز تحصيل رسوم شحن مستقلة عن البيع المباشر.');
            }
            if ($orderBranchId <= 0 || $orderBranchId !== $expectedBranchId) {
                throw new RuntimeException('فرع الطلب لا يطابق فرع تأكيد الشحنة.');
            }

            // مفتاح الطلب يمنع تكرار تحصيل رسوم الشحن في محاولات الرفض اللاحقة.
            $existingStmt = $this->db->prepare(
                "SELECT id, delivery_user_id, branch_id, shipping_amount_snapshot,
                        accounting_event_id, journal_entry_id, delivery_collection_id, status
                 FROM delivery_rejected_shipping_collections
                 WHERE order_id = ? LIMIT 1 FOR UPDATE"
            );
            $existingStmt->execute([$orderId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if (
                    $existing['status'] !== 'POSTED'
                    || (int)$existing['branch_id'] !== $orderBranchId
                    || (int)$existing['accounting_event_id'] <= 0
                    || (int)$existing['journal_entry_id'] <= 0
                    || (int)$existing['delivery_collection_id'] <= 0
                ) {
                    throw new RuntimeException('سجل تحصيل رسوم الشحن السابق غير مكتمل أو غير متسق؛ يلزم فحصه.');
                }
                $settlementAllocationStmt = $this->db->prepare(
                    "SELECT dc.status, dc.collection_source, a.customer_amount, a.commission_amount
                     FROM delivery_customer_collections dc
                     INNER JOIN delivery_collection_order_allocations a
                        ON a.collection_id = dc.id AND a.order_id = ?
                     WHERE dc.id = ? LIMIT 1"
                );
                $settlementAllocationStmt->execute([$orderId, (int)$existing['delivery_collection_id']]);
                $settlementAllocation = $settlementAllocationStmt->fetch(PDO::FETCH_ASSOC);
                if (
                    !$settlementAllocation
                    || $settlementAllocation['status'] !== 'POSTED'
                    || $settlementAllocation['collection_source'] !== 'REJECTED_SHIPPING'
                    || abs((float)$settlementAllocation['customer_amount'] - (float)$existing['shipping_amount_snapshot']) > 0.009
                    || abs((float)$settlementAllocation['commission_amount']) > 0.009
                ) {
                    throw new RuntimeException('تخصيص عهدة رسوم الشحن السابق غير مكتمل أو غير متسق.');
                }
                $result['already_collected']++;
                continue;
            }

            $shippingAmount = round((float)$attempt['SHIPPING_VAL'], 2);
            if (!is_finite($shippingAmount) || $shippingAmount < 0) {
                throw new RuntimeException('قيمة رسوم الشحن غير صالحة للطلب رقم ' . $orderId . '.');
            }
            if ($shippingAmount <= 0) {
                $result['no_charge']++;
                continue;
            }

            if ($settings === null) {
                $settingsStmt = $this->db->query(
                    "SELECT courier_custody_account_id
                     FROM delivery_accounting_settings
                     WHERE id = 1 LIMIT 1 FOR UPDATE"
                );
                $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$settings || (int)$settings['courier_custody_account_id'] <= 0) {
                    throw new RuntimeException('يجب تحديد حساب عهدة المندوب في إعدادات المحاسبة أولًا.');
                }
            }

            $custodyAccountId = (int)$settings['courier_custody_account_id'];
            $shippingRevenueAccountId = $this->accounts->shippingRevenue();
            $movingTimestamp = strtotime((string)$attempt['MOVING_DATE']);
            if ($movingTimestamp === false) {
                throw new RuntimeException('تاريخ محاولة رفض التوصيل غير صالح.');
            }
            $eventDate = date('Y-m-d H:i:s', $movingTimestamp);

            $event = $this->events->post([
                'idempotency_key' => 'DELIVERY_REJECTED_SHIPPING:' . $orderId,
                'event_type' => 'REJECTED_SHIPPING_COLLECTED',
                'source_type' => 'DELIVERY_REJECTED_SHIPPING',
                'source_id' => $orderId,
                'branch_id' => $orderBranchId,
                'event_date' => $eventDate,
                'created_by' => $actorId,
                'payload' => [
                    'order_id' => $orderId,
                    'shipmov_id' => $shipMovementId,
                    'delivery_id' => $deliveryId,
                    'branch_id' => $orderBranchId,
                    'shipping_amount_snapshot' => number_format($shippingAmount, 2, '.', ''),
                ],
            ], function () use (
                $orderId,
                $shipMovementId,
                $deliveryId,
                $orderBranchId,
                $actorId,
                $shippingAmount,
                $custodyAccountId,
                $shippingRevenueAccountId
            ): int {
                $this->journal->reset()->create([
                    'branch_id' => $orderBranchId,
                    'entry_date' => date('Y-m-d H:i:s'),
                    'reference_type' => 'DELIVERY_REJECTED_SHIPPING',
                    'reference_id' => $orderId,
                    'description' => 'تحصيل رسوم شحن من العميل للطلب ' . $orderId . ' بعد رفض التوصيل',
                    'created_by' => $actorId,
                ]);
                $this->journal->addLine([
                    'account_id' => $custodyAccountId,
                    'branch_id' => $orderBranchId,
                    'partner_type' => 'DELIVERY',
                    'partner_id' => $deliveryId,
                    'debit' => $shippingAmount,
                    'reference_type' => 'DELIVERY_REJECTED_SHIPPING',
                    'reference_id' => $shipMovementId,
                    'note' => 'رسوم شحن محصلة من العميل وما زالت بعهدة المندوب',
                ]);
                $this->journal->addLine([
                    'account_id' => $shippingRevenueAccountId,
                    'branch_id' => $orderBranchId,
                    'credit' => $shippingAmount,
                    'reference_type' => 'DELIVERY_REJECTED_SHIPPING',
                    'reference_id' => $shipMovementId,
                    'note' => 'إيراد رسوم شحن محصلة من العميل',
                ]);
                return $this->journal->post();
            });

            $customerId = (int)($attempt['CLIENT_ID'] ?? 0);
            if ($customerId <= 0) {
                throw new RuntimeException('تعذر تحديد العميل المرتبط بالطلب رقم ' . $orderId . '.');
            }

            // إنشاء مستند التحصيل ضمن نفس مصدر البيانات الذي تستخدمه شاشة تسوية عهدة المندوب.
            $receiptNo = 'DRS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $collectionInsert = $this->db->prepare(
                "INSERT INTO delivery_customer_collections
                    (receipt_no, delivery_user_id, customer_id, branch_id, payment_method_id,
                     collection_date, customer_paid_amount, commission_amount, commission_mode,
                     collection_journal_id, status, note, created_by, posted_at, collection_source)
                 VALUES (?, ?, ?, ?, NULL, NOW(), ?, 0, NULL, ?, 'POSTED', ?, ?, NOW(), 'REJECTED_SHIPPING')"
            );
            $collectionInsert->execute([
                $receiptNo,
                $deliveryId,
                $customerId,
                $orderBranchId,
                $shippingAmount,
                (int)$event['journal_entry_id'],
                'رسوم شحن دفعها العميل للمندوب بعد رفض التوصيل - الطلب ' . $orderId,
                $actorId,
            ]);
            $deliveryCollectionId = (int)$this->db->lastInsertId();

            $allocationInsert = $this->db->prepare(
                "INSERT INTO delivery_collection_order_allocations
                    (collection_id, order_id, customer_amount, commission_amount)
                 VALUES (?, ?, ?, 0)"
            );
            $allocationInsert->execute([$deliveryCollectionId, $orderId, $shippingAmount]);

            $insert = $this->db->prepare(
                "INSERT INTO delivery_rejected_shipping_collections
                    (order_id, shipmov_id, delivery_user_id, branch_id,
                     shipping_amount_snapshot, accounting_event_id, journal_entry_id,
                     delivery_collection_id, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'POSTED', ?)"
            );
            $insert->execute([
                $orderId,
                $shipMovementId,
                $deliveryId,
                $orderBranchId,
                $shippingAmount,
                (int)$event['event_id'],
                (int)$event['journal_entry_id'],
                $deliveryCollectionId,
                $actorId,
            ]);

            $result['posted']++;
        }

        return $result;
    }
}
