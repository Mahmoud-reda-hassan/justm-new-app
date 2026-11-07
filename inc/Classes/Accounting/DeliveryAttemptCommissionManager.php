<?php
declare(strict_types=1);

require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/FinancialEventManager.php';

/**
 * يرحّل عمولة مستقلة لكل حركة رفض توصيل فعلية.
 * مصدر المحاولة هو shipping_movement.shipmov_id، وليس رقم الطلب؛
 * لذلك يمكن استحقاق عمولة جديدة عند محاولة لاحقة دون تكرار نفس المحاولة.
 */
final class DeliveryAttemptCommissionManager
{
    private PDO $db;
    private JournalManager $journal;
    private FinancialEventManager $events;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->journal = new JournalManager($db);
        $this->events = new FinancialEventManager($db);
    }

    /**
     * يجب استدعاء الدالة داخل معاملة تأكيد الشحنة نفسها.
     * @return array{processed:int,posted:int,no_commission:int,already_recorded:int}
     */
    public function accrueRejectedAttempts(string $sessionId, int $expectedBranchId, int $actorId): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || strlen($sessionId) > 64 || $expectedBranchId <= 0 || $actorId <= 0) {
            throw new InvalidArgumentException('بيانات محاولة التوصيل غير مكتملة لترحيل العمولة.');
        }
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('يجب ترحيل عمولة محاولة الرفض داخل معاملة تأكيد الشحنة.');
        }

        $stmt = $this->db->prepare("SELECT
                sm.shipmov_id,
                sm.delivery_id,
                sm.shipment_orders AS order_id,
                sm.MOVING_DATE,
                o.SUB_ORDER_ID,
                o.ORDER_TYPE_ID,
                o.SHIPPING_VAL,
                s.BRANCH_ID AS ORDER_BRANCH_ID,
                ud.USER_ID AS DELIVERY_PROFILE_ID,
                ud.PERCENT AS DELIVERY_PERCENT
            FROM shipping_movement sm
            INNER JOIN orders o ON o.ORDER_ID = sm.shipment_orders
            LEFT JOIN stores s ON s.STO_ID = o.STO_ID
            LEFT JOIN users_delivery ud ON ud.USER_ID = sm.delivery_id
            WHERE sm.UNIQUE_ID = ? AND sm.movement_type = 5
              AND sm.shipmov_id = (SELECT MIN(sm2.shipmov_id) FROM shipping_movement sm2
                  WHERE sm2.UNIQUE_ID = sm.UNIQUE_ID AND sm2.movement_type = sm.movement_type
                    AND sm2.shipment_orders = sm.shipment_orders AND sm2.delivery_id = sm.delivery_id)
            ORDER BY sm.shipmov_id
            FOR UPDATE");
        $stmt->execute([$sessionId]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = ['processed' => count($attempts), 'posted' => 0, 'no_commission' => 0, 'already_recorded' => 0];
        if (!$attempts) {
            return $result;
        }

        $settings = null;
        foreach ($attempts as $attempt) {
            $shipMovementId = (int)$attempt['shipmov_id'];
            $orderId = (int)$attempt['order_id'];
            $deliveryId = (int)$attempt['delivery_id'];

            if ($shipMovementId <= 0 || $orderId <= 0) {
                throw new RuntimeException('سجل حركة رفض التوصيل يحتوي على معرف غير صالح.');
            }

            // البيع المباشر مستثنى من عمولة الشحن.
            if ((int)$attempt['ORDER_TYPE_ID'] === 5) {
                continue;
            }

            $existingStmt = $this->db->prepare("SELECT status, order_id, delivery_user_id, branch_id,
                    commission_amount, accounting_event_id, journal_entry_id
                FROM delivery_attempt_commissions
                WHERE shipmov_id = ? LIMIT 1 FOR UPDATE");
            $existingStmt->execute([$shipMovementId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $existingIsPosted = $existing['status'] === 'POSTED'
                    && (float)$existing['commission_amount'] > 0
                    && (int)$existing['accounting_event_id'] > 0
                    && (int)$existing['journal_entry_id'] > 0;
                $existingHasNoCommission = $existing['status'] === 'NO_COMMISSION'
                    && abs((float)$existing['commission_amount']) < 0.001
                    && empty($existing['accounting_event_id'])
                    && empty($existing['journal_entry_id']);
                if ((!$existingIsPosted && !$existingHasNoCommission)
                    || (int)$existing['order_id'] !== $orderId
                    || (int)$existing['delivery_user_id'] !== $deliveryId
                    || (int)$existing['branch_id'] !== $expectedBranchId) {
                    throw new RuntimeException('سجل عمولة محاولة الرفض السابق غير مكتمل أو غير متسق؛ يلزم فحصه.');
                }
                $result['already_recorded']++;
                continue;
            }

            if ($deliveryId <= 0 || empty($attempt['DELIVERY_PROFILE_ID'])) {
                throw new RuntimeException('تعذر تحديد ملف المندوب الفعلي لحركة الرفض رقم ' . $shipMovementId . '.');
            }
            $orderBranchId = (int)($attempt['ORDER_BRANCH_ID'] ?? 0);
            if ($orderBranchId <= 0 || $orderBranchId !== $expectedBranchId) {
                throw new RuntimeException('فرع أمر البيع رقم ' . $orderId . ' لا يطابق فرع تأكيد الشحنة.');
            }

            $shippingAmount = round((float)$attempt['SHIPPING_VAL'], 2);
            $commissionPercent = round((float)$attempt['DELIVERY_PERCENT'], 4);
            if (!is_finite($shippingAmount) || $shippingAmount < 0
                || !is_finite($commissionPercent) || $commissionPercent < 0 || $commissionPercent > 100) {
                throw new RuntimeException('قيمة الشحن أو نسبة عمولة المندوب غير صالحة للطلب رقم ' . $orderId . '.');
            }
            $commissionAmount = round($shippingAmount * $commissionPercent / 100, 2);
            $eventId = null;
            $journalId = null;
            $status = 'NO_COMMISSION';

            if ($commissionAmount > 0) {
                if ($settings === null) {
                    $settingsStmt = $this->db->query("SELECT courier_commission_expense_account_id,
                            courier_commission_payable_account_id
                        FROM delivery_accounting_settings WHERE id = 1 FOR UPDATE");
                    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$settings
                        || (int)$settings['courier_commission_expense_account_id'] <= 0
                        || (int)$settings['courier_commission_payable_account_id'] <= 0) {
                        throw new RuntimeException('يجب تحديد حساب مصروف عمولة المندوب وحساب العمولة المستحقة في إعدادات المحاسبة أولًا.');
                    }
                }

                $expenseAccountId = (int)$settings['courier_commission_expense_account_id'];
                $payableAccountId = (int)$settings['courier_commission_payable_account_id'];
                $movingTimestamp = strtotime((string)$attempt['MOVING_DATE']);
                if ($movingTimestamp === false) {
                    throw new RuntimeException('تاريخ حركة رفض التوصيل غير صالح للترحيل.');
                }
                $movingDate = date('Y-m-d H:i:s', $movingTimestamp);
                $event = $this->events->post([
                    'idempotency_key' => 'DELIVERY_ATTEMPT_COMMISSION:' . $shipMovementId,
                    'event_type' => 'COMMISSION_ACCRUED',
                    'source_type' => 'DELIVERY_ATTEMPT',
                    'source_id' => $shipMovementId,
                    'branch_id' => $orderBranchId,
                    'event_date' => $movingDate,
                    'created_by' => $actorId,
                    'payload' => [
                        'shipmov_id' => $shipMovementId,
                        'order_id' => $orderId,
                        'delivery_id' => $deliveryId,
                        'branch_id' => $orderBranchId,
                        'shipping_amount_snapshot' => number_format($shippingAmount, 2, '.', ''),
                        'commission_percent_snapshot' => number_format($commissionPercent, 4, '.', ''),
                        'commission_amount' => number_format($commissionAmount, 2, '.', ''),
                    ],
                ], function () use (
                    $orderBranchId, $shipMovementId, $attempt, $deliveryId,
                    $commissionAmount, $expenseAccountId, $payableAccountId, $actorId
                ): int {
                    $this->journal->reset()->create([
                        'branch_id' => $orderBranchId,
                        'entry_date' => date('Y-m-d H:i:s'),
                        'reference_type' => 'DELIVERY_REJECTION_ATTEMPT',
                        'reference_id' => $shipMovementId,
                        'description' => 'استحقاق عمولة محاولة رفض توصيل للطلب ' . (string)$attempt['SUB_ORDER_ID'],
                        'created_by' => $actorId,
                    ]);
                    $this->journal->addLine([
                        'account_id' => $expenseAccountId,
                        'branch_id' => $orderBranchId,
                        'debit' => $commissionAmount,
                        'reference_type' => 'DELIVERY_REJECTION_ATTEMPT',
                        'reference_id' => $shipMovementId,
                        'note' => 'مصروف عمولة محاولة توصيل مرفوضة',
                    ]);
                    $this->journal->addLine([
                        'account_id' => $payableAccountId,
                        'branch_id' => $orderBranchId,
                        'partner_type' => 'DELIVERY',
                        'partner_id' => $deliveryId,
                        'credit' => $commissionAmount,
                        'reference_type' => 'DELIVERY_REJECTION_ATTEMPT',
                        'reference_id' => $shipMovementId,
                        'note' => 'عمولة مستحقة للمندوب عن محاولة رفض',
                    ]);
                    return $this->journal->post();
                });
                $eventId = (int)$event['event_id'];
                $journalId = (int)$event['journal_entry_id'];
                $status = 'POSTED';
            }

            $insert = $this->db->prepare("INSERT INTO delivery_attempt_commissions
                (shipmov_id, order_id, delivery_user_id, branch_id,
                 shipping_amount_snapshot, commission_percent_snapshot, commission_amount,
                 accounting_event_id, journal_entry_id, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $shipMovementId, $orderId, $deliveryId, $orderBranchId,
                $shippingAmount, $commissionPercent, $commissionAmount,
                $eventId, $journalId, $status, $actorId,
            ]);

            if ($status === 'POSTED') $result['posted']++;
            else $result['no_commission']++;
        }

        return $result;
    }
}
