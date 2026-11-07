<?php
declare(strict_types=1);

require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/AccountSettings.php';
require_once __DIR__ . '/FinancialEventManager.php';

/**
 * تسوية عهدة المندوب على تحصيلات العملاء المسجلة في نظام المحاسبة الجديد.
 * لا يقرأ أو يحدّث partners_balance.
 * يتطلب تطبيق PHASE14 SQL بعد تعديل جدول الروابط ليشير إلى تخصيص الأمر.
 */
final class DeliverySettlementManager
{
    private PDO $db;
    private JournalManager $journal;
    private AccountSettings $accounts;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->journal = new JournalManager($db);
        $this->accounts = new AccountSettings($db);
    }

    private function actorId(): int
    {
        $id = (int)($_SESSION['USER_ID'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('تعذر تحديد المستخدم المنفذ للعملية.');
        }
        return $id;
    }

    public function getCommissionSettlementMode(): string
    {
        $stmt = $this->db->query('SELECT commission_settlement_mode FROM delivery_accounting_settings WHERE id = 1');
        $mode = $stmt->fetchColumn();
        if (!in_array($mode, ['DEDUCT_FROM_CUSTODY', 'SEPARATE', 'USER_CHOICE'], true)) {
            throw new RuntimeException('إعداد طريقة العمولة غير موجود أو غير صالح.');
        }
        return (string)$mode;
    }

    /** يستدعى من مسار إعدادات مقيد للمشرف فقط. */
    public function updateCommissionSettlementMode(string $mode, int $updatedBy): void
    {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, ['DEDUCT_FROM_CUSTODY', 'SEPARATE', 'USER_CHOICE'], true)) {
            throw new InvalidArgumentException('قيمة إعداد طريقة العمولة غير صحيحة.');
        }
        if ($updatedBy <= 0) throw new InvalidArgumentException('المستخدم المنفذ غير صالح.');
        $stmt = $this->db->prepare("UPDATE delivery_accounting_settings
            SET commission_settlement_mode = ?, updated_by = ? WHERE id = 1");
        $stmt->execute([$mode, $updatedBy]);
        if ($stmt->rowCount() === 0) {
            // rowCount قد يساوي صفرًا إذا كانت القيمة لم تتغير؛ تحقق أن سجل الإعداد موجود بالفعل.
            $exists = (int)$this->db->query('SELECT COUNT(*) FROM delivery_accounting_settings WHERE id = 1')->fetchColumn();
            if ($exists !== 1) throw new RuntimeException('تعذر حفظ إعداد طريقة العمولة.');
        }
    }

    private function getSettings(bool $lock = false): array
    {
        $sql = 'SELECT commission_settlement_mode, courier_custody_account_id,
                       courier_commission_expense_account_id, courier_commission_payable_account_id
                FROM delivery_accounting_settings WHERE id = 1' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('إعدادات محاسبة المندوب غير موجودة.');
        }
        foreach (['courier_custody_account_id', 'courier_commission_expense_account_id', 'courier_commission_payable_account_id'] as $key) {
            if (empty($row[$key])) {
                throw new RuntimeException('يجب تحديد الحسابات المحاسبية الخاصة بالمندوب في الإعدادات أولًا.');
            }
        }
        return $row;
    }

    /**
     * تسجيل تحصيل العميل بواسطة المندوب، وإثبات المبلغ في عهدته فورًا.
     * $orderAmounts = [order_id => amount, ...]. كل أمر يُحفظ كمستند مستقل لتسهيل التخصيص والمراجعة.
     * يتطلب أن تكون مديونية الأمر المرحلة في دفتر اليومية، وأن تكون التخصيصات السابقة صحيحة.
     */
    public function registerCustomerCollection(
        int $deliveryId,
        int $branchId,
        int $paymentMethodId,
        array $orderAmounts,
        string $requestKey,
        string $note = ''
    ): array {
        if ($deliveryId <= 0 || $branchId <= 0 || $paymentMethodId <= 0 || !$orderAmounts) {
            throw new InvalidArgumentException('المندوب والفرع وطريقة الدفع وأمر بيع واحد على الأقل مطلوبة.');
        }
        $actorId = $this->actorId();
        $requestKey = trim($requestKey);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)) {
            throw new InvalidArgumentException('مفتاح طلب التحصيل مطلوب ويجب أن يكون من 8 إلى 64 حرفًا آمنًا.');
        }
        $note = trim($note);
        // عدّ حروف UTF-8 الفعلية بدل عدد البايتات حتى لا تُرفض الملاحظات العربية مبكرًا.
        $noteCharacterCount = preg_match_all('/./us', $note, $noteCharacters);
        if ($noteCharacterCount === false || $noteCharacterCount > 500) {
            throw new InvalidArgumentException('ملاحظة التحصيل لا يمكن أن تتجاوز 500 حرف.');
        }
        $normalized = [];
        foreach ($orderAmounts as $orderId => $amount) {
            $orderId = (int)$orderId;
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException('مبلغ التحصيل يجب أن يكون رقمًا صحيحًا.');
            }
            $amount = round((float)$amount, 2);
            if ($orderId <= 0 || !is_finite($amount) || $amount <= 0) {
                throw new InvalidArgumentException('بيانات مبلغ التحصيل أو رقم أمر البيع غير صحيحة.');
            }
            if (isset($normalized[$orderId])) {
                throw new InvalidArgumentException('لا يمكن تكرار أمر البيع في نفس عملية التحصيل.');
            }
            $normalized[$orderId] = $amount;
        }
        ksort($normalized, SORT_NUMERIC);

        // بصمة ثابتة للطلب بالكامل؛ ترتيب الأوامر أو تنسيق المبلغ لا يغيران البصمة.
        $canonicalOrders = [];
        foreach ($normalized as $orderId => $amount) {
            $canonicalOrders[] = [
                'order_id' => (int)$orderId,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }
        $canonicalPayload = [
            'actor_id' => $actorId,
            'delivery_id' => $deliveryId,
            'branch_id' => $branchId,
            'payment_method_id' => $paymentMethodId,
            'orders' => $canonicalOrders,
            'note' => $note,
        ];
        $canonicalJson = json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($canonicalJson === false) {
            throw new RuntimeException('تعذر تكوين بصمة طلب التحصيل.');
        }
        $requestHash = hash('sha256', $canonicalJson);

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            // حجز المفتاح على مستوى الطلب الكامل قبل إنشاء أي حدث أو مستند فرعي.
            $requestId = 0;
            try {
                $requestInsert = $this->db->prepare("INSERT INTO delivery_collection_requests
                    (request_key, request_hash, delivery_user_id, branch_id, payment_method_id,
                     total_amount, order_count, note, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PROCESSING', ?)");
                $requestInsert->execute([
                    $requestKey, $requestHash, $deliveryId, $branchId, $paymentMethodId,
                    round(array_sum($normalized), 2), count($normalized), $note, $actorId
                ]);
                $requestId = (int)$this->db->lastInsertId();
            } catch (PDOException $insertException) {
                $driverCode = isset($insertException->errorInfo[1]) ? (int)$insertException->errorInfo[1] : 0;
                if ($insertException->getCode() !== '23000' && $driverCode !== 1062) {
                    throw $insertException;
                }

                // INSERT المتزامن بالمفتاح نفسه ينتظر المعاملة الأولى؛ ثم نقرأ نتيجتها بعد القفل.
                $existingRequestStmt = $this->db->prepare("SELECT id, request_hash, status, total_amount, order_count
                    FROM delivery_collection_requests WHERE request_key = ? LIMIT 1 FOR UPDATE");
                $existingRequestStmt->execute([$requestKey]);
                $existingRequest = $existingRequestStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingRequest) {
                    throw $insertException;
                }
                if (!hash_equals((string)$existingRequest['request_hash'], $requestHash)) {
                    throw new RuntimeException('مفتاح طلب التحصيل مستخدم بالفعل مع بيانات مختلفة؛ تم رفض الطلب دون إنشاء مستندات جديدة.');
                }
                if ($existingRequest['status'] !== 'POSTED') {
                    throw new RuntimeException('طلب التحصيل السابق غير مكتمل أو قيد المعالجة؛ لن تتم إعادة تنفيذه تلقائيًا.');
                }

                $replayStmt = $this->db->prepare("SELECT l.order_id, l.customer_amount, l.commission_amount,
                        c.id AS collection_id, c.receipt_no, c.customer_id,
                        c.customer_paid_amount AS document_customer_amount,
                        c.commission_amount AS document_commission_amount,
                        c.collection_journal_id, c.commission_journal_id, c.status
                    FROM delivery_collection_request_collections l
                    INNER JOIN delivery_customer_collections c ON c.id = l.collection_id
                    WHERE l.request_id = ? ORDER BY l.order_id");
                $replayStmt->execute([(int)$existingRequest['id']]);
                $replayRows = $replayStmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($replayRows) !== count($normalized)
                    || abs((float)$existingRequest['total_amount'] - round(array_sum($normalized), 2)) > 0.009
                    || (int)$existingRequest['order_count'] !== count($normalized)) {
                    throw new RuntimeException('نتيجة طلب التحصيل السابق غير مكتملة؛ يلزم فحصها قبل إعادة الإرسال.');
                }

                $replayed = [];
                foreach ($replayRows as $row) {
                    $orderId = (int)$row['order_id'];
                    if (!isset($normalized[$orderId]) || $row['status'] !== 'POSTED'
                        || abs((float)$row['customer_amount'] - $normalized[$orderId]) > 0.009
                        || abs((float)$row['document_customer_amount'] - $normalized[$orderId]) > 0.009
                        || abs((float)$row['commission_amount'] - (float)$row['document_commission_amount']) > 0.009
                        || empty($row['collection_journal_id'])) {
                        throw new RuntimeException('أحد مستندات طلب التحصيل السابق غير متسق؛ يلزم فحصه.');
                    }
                    $replayed[] = [
                        'collection_id' => (int)$row['collection_id'],
                        'receipt_no' => $row['receipt_no'],
                        'order_id' => $orderId,
                        'customer_id' => (int)$row['customer_id'],
                        'amount' => (float)$row['customer_amount'],
                        'commission' => (float)$row['commission_amount'],
                        'collection_journal_id' => (int)$row['collection_journal_id'],
                        'commission_journal_id' => $row['commission_journal_id'] ? (int)$row['commission_journal_id'] : null,
                        'already_posted' => true,
                    ];
                }
                if ($ownsTransaction) $this->db->commit();
                return [
                    'success' => true,
                    'collections' => $replayed,
                    'total_amount' => round(array_sum($normalized), 2),
                    'already_posted' => true,
                ];
            }

            // تحقق من طريقة الدفع للطلب الجديد فقط؛ إعادة الطلب المكتمل لا تعتمد على حالتها الحالية.
            $paymentMethodStmt = $this->db->prepare("SELECT id FROM payment_methods
                WHERE id = ? AND COALESCE(is_active, 0) = 1 LIMIT 1");
            $paymentMethodStmt->execute([$paymentMethodId]);
            if (!$paymentMethodStmt->fetchColumn()) {
                throw new RuntimeException('طريقة الدفع المحددة غير موجودة أو غير مفعلة.');
            }

            $settings = $this->getSettings(true);
            $custodyAccountId = (int)$settings['courier_custody_account_id'];
            $commissionExpenseAccountId = (int)$settings['courier_commission_expense_account_id'];
            $commissionPayableAccountId = (int)$settings['courier_commission_payable_account_id'];
            $customerAccountId = $this->accounts->customers();
            $created = [];

            foreach ($normalized as $orderId => $amount) {
                $orderStmt = $this->db->prepare("SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_TYPE_ID, o.DELIVERY_ID,
                        o.ORDER_BY, o.SALE_ENTRY_ID, o.ORDER_STATUS, COALESCE(o.SHIPPING_VAL,0) AS SHIPPING_VAL,
                        te.CLIENT_ID, COALESCE(ud.PERCENT,0) AS DELIVERY_PERCENT,
                        ud.USER_ID AS DELIVERY_PROFILE_ID,
                        custody.CUST_STAT,
                        COALESCE(affiliate.COMMISSION_SALES,0) AS MARKETER_COMMISSION_RATE,
                        affiliate.USER_ID AS MARKETER_ID,
                        affiliate.AFFILIATEED_WITH AS LEADER_ID,
                        COALESCE(leader.TEAM_COMMISSION,0) AS LEADER_COMMISSION_RATE,
                        COALESCE(delivered.DELIVERED_QTY,0) AS DELIVERED_QTY,
                        sale_journal.branch_id AS SALE_BRANCH_ID
                    FROM orders o
                    LEFT JOIN telephones te ON te.TEL_ID = o.TEL_ID
                    LEFT JOIN users_delivery ud ON ud.USER_ID = o.DELIVERY_ID
                    LEFT JOIN custody_orders custody ON custody.ORDER_ID = o.ORDER_ID AND custody.USER_ID = o.DELIVERY_ID
                    LEFT JOIN users_affiliate affiliate ON affiliate.USER_ID = o.ORDER_BY
                    LEFT JOIN users_affiliate leader ON leader.USER_ID = affiliate.AFFILIATEED_WITH
                    LEFT JOIN (
                        SELECT ORDER_ID, USER_ID, SUM(DELIVERED_QTY) AS DELIVERED_QTY
                        FROM custody_product GROUP BY ORDER_ID, USER_ID
                    ) delivered ON delivered.ORDER_ID = o.ORDER_ID AND delivered.USER_ID = o.DELIVERY_ID
                    LEFT JOIN journal_entries sale_journal ON sale_journal.id = o.SALE_ENTRY_ID
                    WHERE o.ORDER_ID = ? FOR UPDATE");
                $orderStmt->execute([$orderId]);
                $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                if (!$order) throw new RuntimeException('أمر البيع رقم ' . $orderId . ' غير موجود.');
                if ((int)$order['DELIVERY_ID'] !== $deliveryId
                    || empty($order['DELIVERY_PROFILE_ID'])) {
                    throw new RuntimeException('أمر البيع رقم ' . $orderId . ' ليس على عهدة مندوب صالح أو لا يطابق المندوب المحدد.');
                }
                if (!in_array((int)$order['ORDER_STATUS'], [3, 4], true)) {
                    throw new RuntimeException('لا يمكن تسجيل تحصيل لأمر بيع لم يتم تسليمه أو تسليمه جزئيًا.');
                }
                if (empty($order['SALE_ENTRY_ID']) || empty($order['CLIENT_ID']) || empty($order['SALE_BRANCH_ID'])) {
                    throw new RuntimeException('أمر البيع رقم ' . $orderId . ' لا يحتوي على قيد مديونية عميل صالح في النظام الجديد.');
                }
                if ((int)$order['SALE_BRANCH_ID'] !== $branchId) {
                    throw new RuntimeException('فرع أمر البيع رقم ' . $orderId . ' لا يطابق فرع عملية التحصيل.');
                }

                $requestEventKey = 'DELIVERY_COLLECTION:' . $requestKey . ':ORDER:' . $orderId;
                $eventManager = new FinancialEventManager($this->db);
                $eventData = [
                    'idempotency_key' => $requestEventKey,
                    'event_type' => 'COURIER_COLLECTION',
                    'source_type' => 'DELIVERY_COLLECTION',
                    'source_id' => $orderId,
                    'branch_id' => $branchId,
                    'event_date' => date('Y-m-d H:i:s'),
                    'created_by' => $actorId,
                    'payload' => [
                        'order_id' => $orderId,
                        'delivery_id' => $deliveryId,
                        'customer_id' => (int)$order['CLIENT_ID'],
                        'branch_id' => $branchId,
                        'payment_method_id' => $paymentMethodId,
                        'amount' => $amount,
                        'note' => trim($note),
                    ],
                ];

                // افحص إعادة الإرسال قبل حساب الرصيد المفتوح؛ فالتحصيل السابق أغلق جزءًا من المديونية بالفعل.
                $existingEventStmt = $this->db->prepare("SELECT id FROM accounting_events
                    WHERE idempotency_key = ? LIMIT 1 FOR UPDATE");
                $existingEventStmt->execute([$requestEventKey]);
                if ($existingEventStmt->fetchColumn()) {
                    $existingEvent = $eventManager->post($eventData, function (): int {
                        throw new RuntimeException('لا يجوز إعادة تنفيذ callback لحدث تحصيل موجود.');
                    });
                    $existingCollectionStmt = $this->db->prepare("SELECT id, receipt_no, customer_paid_amount,
                            commission_amount, commission_journal_id, status
                        FROM delivery_customer_collections
                        WHERE collection_journal_id = ? LIMIT 1 FOR UPDATE");
                    $existingCollectionStmt->execute([(int)$existingEvent['journal_entry_id']]);
                    $existingCollection = $existingCollectionStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$existingCollection || $existingCollection['status'] !== 'POSTED'
                        || abs((float)$existingCollection['customer_paid_amount'] - $amount) > 0.009) {
                        throw new RuntimeException('مستند التحصيل السابق غير متسق مع إعادة الإرسال.');
                    }
                    $existingAllocationStmt = $this->db->prepare("SELECT customer_amount
                        FROM delivery_collection_order_allocations
                        WHERE collection_id = ? AND order_id = ? LIMIT 1");
                    $existingAllocationStmt->execute([(int)$existingCollection['id'], $orderId]);
                    $existingAllocation = $existingAllocationStmt->fetchColumn();
                    if ($existingAllocation === false || abs((float)$existingAllocation - $amount) > 0.009) {
                        throw new RuntimeException('تخصيص التحصيل السابق غير متسق مع إعادة الإرسال.');
                    }
                    $created[] = [
                        'collection_id' => (int)$existingCollection['id'],
                        'receipt_no' => $existingCollection['receipt_no'],
                        'order_id' => $orderId,
                        'customer_id' => (int)$order['CLIENT_ID'],
                        'amount' => $amount,
                        'commission' => (float)$existingCollection['commission_amount'],
                        'collection_journal_id' => (int)$existingEvent['journal_entry_id'],
                        'commission_journal_id' => $existingCollection['commission_journal_id'] ? (int)$existingCollection['commission_journal_id'] : null,
                        'already_posted' => true,
                    ];
                    continue;
                }

                // مديونية الطلب تتكون من المنتجات، ورسوم الشحن في قيد مستقل للطلبات المشحونة.
                $receivableLines = [];
                $saleLineStmt = $this->db->prepare("SELECT d.id, d.debit
                    FROM journal_entry_details d
                    INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                    WHERE d.journal_entry_id = ? AND d.account_id = ?
                      AND d.partner_type = 'CLIENT' AND d.partner_id = ? AND d.debit > 0
                    ORDER BY d.id LIMIT 1 FOR UPDATE");
                $saleLineStmt->execute([(int)$order['SALE_ENTRY_ID'], $customerAccountId, (int)$order['CLIENT_ID']]);
                $saleLine = $saleLineStmt->fetch(PDO::FETCH_ASSOC);
                if (!$saleLine) throw new RuntimeException('لم يتم العثور على سطر مديونية المنتجات لأمر البيع رقم ' . $orderId . '.');
                $receivableLines[] = ['id' => (int)$saleLine['id'], 'debit' => (float)$saleLine['debit'], 'kind' => 'PRODUCTS'];

                // النوع 5 بيع مباشر ولا يحمل شحنًا؛ الأنواع الأخرى ذات رسوم الشحن تحتاج حدث الشحن المرحل.
                if ((int)$order['ORDER_TYPE_ID'] !== 5 && (float)$order['SHIPPING_VAL'] > 0) {
                    $shippingEventKey = 'ORDER_SHIPPING:ORDER:' . $orderId . ':SHIPPING_REVENUE';
                    $shippingEventStmt = $this->db->prepare("SELECT journal_entry_id
                        FROM accounting_events
                        WHERE idempotency_key = ? AND event_type = 'SHIPPING_REVENUE_RECOGNIZED'
                          AND status = 'POSTED' AND journal_entry_id IS NOT NULL
                        LIMIT 1 FOR UPDATE");
                    $shippingEventStmt->execute([$shippingEventKey]);
                    $shippingJournalId = (int)$shippingEventStmt->fetchColumn();
                    if ($shippingJournalId <= 0) {
                        throw new RuntimeException('لم يتم ترحيل مديونية رسوم الشحن لهذا الطلب؛ لا يمكن تسجيل التحصيل قبل إثباتها.');
                    }

                    $shippingLineStmt = $this->db->prepare("SELECT d.id, d.debit
                        FROM journal_entry_details d
                        INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                        WHERE d.journal_entry_id = ? AND d.account_id = ?
                          AND d.partner_type = 'CLIENT' AND d.partner_id = ? AND d.debit > 0
                        ORDER BY d.id LIMIT 1 FOR UPDATE");
                    $shippingLineStmt->execute([$shippingJournalId, $customerAccountId, (int)$order['CLIENT_ID']]);
                    $shippingLine = $shippingLineStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$shippingLine || abs((float)$shippingLine['debit'] - (float)$order['SHIPPING_VAL']) > 0.009) {
                        throw new RuntimeException('سطر مديونية الشحن غير مطابق لقيمة الشحن المسجلة على الطلب.');
                    }
                    $receivableLines[] = ['id' => (int)$shippingLine['id'], 'debit' => (float)$shippingLine['debit'], 'kind' => 'SHIPPING'];
                }

                $totalReceivableAmount = 0.0;
                $totalOpenAmount = 0.0;
                $lineOpenStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0)
                    FROM accounting_line_allocations
                    WHERE debit_line_id = ? AND status = 'ACTIVE'");
                foreach ($receivableLines as &$receivableLine) {
                    $lineOpenStmt->execute([$receivableLine['id']]);
                    $allocated = (float)$lineOpenStmt->fetchColumn();
                    $receivableLine['open_amount'] = max(0.0, round($receivableLine['debit'] - $allocated, 2));
                    $totalReceivableAmount += $receivableLine['debit'];
                    $totalOpenAmount += $receivableLine['open_amount'];
                }
                unset($receivableLine);
                $totalReceivableAmount = round($totalReceivableAmount, 2);
                $totalOpenAmount = round($totalOpenAmount, 2);
                if ($amount - $totalOpenAmount > 0.009) {
                    throw new RuntimeException('مبلغ التحصيل لأمر البيع رقم ' . $orderId . ' أكبر من إجمالي المديونية المفتوحة (' . number_format($totalOpenAmount, 2, '.', '') . ').');
                }

                // احتساب العمولة بنسبة المبلغ المحصل إلى إجمالي مديونية الطلب، مع سقف العمولة المتبقية.
                $expectedCommission = round(((float)$order['SHIPPING_VAL'] * (float)$order['DELIVERY_PERCENT']) / 100, 2);
                $commissionUsedStmt = $this->db->prepare("SELECT COALESCE(SUM(a.commission_amount),0)
                    FROM delivery_collection_order_allocations a
                    INNER JOIN delivery_customer_collections dc ON dc.id = a.collection_id AND dc.status = 'POSTED'
                    WHERE a.order_id = ?");
                $commissionUsedStmt->execute([$orderId]);
                $commissionAlreadyAccrued = (float)$commissionUsedStmt->fetchColumn();
                $commissionRemaining = max(0.0, round($expectedCommission - $commissionAlreadyAccrued, 2));
                $commission = min($commissionRemaining, round($expectedCommission * $amount / max($totalReceivableAmount, 0.01), 2));

                // عمولات المسوق وقائد الفريق تستحق فقط لطلبات المسوق غير المجمدة.
                // يتم توزيع الاستحقاق على التحصيلات الجزئية، مع خصم ما سبق ترحيله لنفس الطلب.
                $marketerCommission = 0.0;
                $leaderCommission = 0.0;
                $marketerId = (int)($order['MARKETER_ID'] ?? 0);
                $leaderId = (int)($order['LEADER_ID'] ?? 0);
                $marketingEligible = (int)$order['ORDER_TYPE_ID'] === 2
                    && $marketerId > 0
                    && (string)($order['CUST_STAT'] ?? '') !== 'FREEZE';
                if ($marketingEligible && (float)$order['DELIVERED_QTY'] > 0) {
                    $marketerExpected = round((float)$order['DELIVERED_QTY'] * (float)$order['MARKETER_COMMISSION_RATE'], 2);
                    $leaderExpected = ($leaderId > 0)
                        ? round((float)$order['DELIVERED_QTY'] * (float)$order['LEADER_COMMISSION_RATE'], 2)
                        : 0.0;
                    $payableAccountId = $this->accounts->marketerCommissionPayable();

                    $priorCommissionStmt = $this->db->prepare("SELECT COALESCE(SUM(d.credit),0)
                        FROM journal_entry_details d
                        INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                        WHERE d.account_id = ? AND d.partner_type = 'MARKETER' AND d.partner_id = ?
                          AND d.reference_type = 'MARKETER_COMMISSION_ACCRUAL'
                          AND d.reference_id = ?");
                    $priorCommissionStmt->execute([$payableAccountId, $marketerId, $orderId]);
                    $marketerAlreadyAccrued = (float)$priorCommissionStmt->fetchColumn();
                    $marketerRemaining = max(0.0, round($marketerExpected - $marketerAlreadyAccrued, 2));
                    $marketerCommission = min($marketerRemaining,
                        round($marketerExpected * $amount / max($totalReceivableAmount, 0.01), 2));

                    if ($leaderId > 0 && $leaderId !== $marketerId && $leaderExpected > 0) {
                        $priorCommissionStmt->execute([$payableAccountId, $leaderId, $orderId]);
                        $leaderAlreadyAccrued = (float)$priorCommissionStmt->fetchColumn();
                        $leaderRemaining = max(0.0, round($leaderExpected - $leaderAlreadyAccrued, 2));
                        $leaderCommission = min($leaderRemaining,
                            round($leaderExpected * $amount / max($totalReceivableAmount, 0.01), 2));
                    } elseif ($leaderId === $marketerId) {
                        // إذا كان المسوق هو قائد الفريق نفسه، يُجمع الاستحقاق في حساب شريك واحد.
                        $leaderCommission = 0.0;
                        $marketerCommission = min(
                            max(0.0, round($marketerExpected + $leaderExpected - $marketerAlreadyAccrued, 2)),
                            round(($marketerExpected + $leaderExpected) * $amount / max($totalReceivableAmount, 0.01), 2)
                        );
                    }
                }

                $collectionId = 0;
                $receipt = '';
                $eventResult = $eventManager->post($eventData, function () use (
                    &$collectionId, &$receipt, $deliveryId, $branchId, $paymentMethodId,
                    $orderId, $order, $amount, $commission, $custodyAccountId,
                    $customerAccountId, $actorId, $note
                ): int {
                    $receipt = 'DCR-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $insert = $this->db->prepare("INSERT INTO delivery_customer_collections
                        (receipt_no, delivery_user_id, customer_id, branch_id, payment_method_id,
                         collection_date, customer_paid_amount, commission_amount, commission_mode,
                         status, note, created_by)
                        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NULL, 'DRAFT', ?, ?)");
                    $insert->execute([$receipt, $deliveryId, (int)$order['CLIENT_ID'], $branchId, $paymentMethodId,
                        $amount, $commission, trim($note), $actorId]);
                    $collectionId = (int)$this->db->lastInsertId();

                    $this->journal->reset()->create([
                        'branch_id' => $branchId,
                        'entry_date' => date('Y-m-d H:i:s'),
                        'reference_type' => 'DELIVERY_CUSTOMER_COLLECTION',
                        'reference_id' => $collectionId,
                        'description' => 'تحصيل مندوب من العميل لأمر ' . $order['SUB_ORDER_ID'],
                        'created_by' => $actorId,
                    ]);
                    $this->journal->addLine([
                        'account_id' => $custodyAccountId,
                        'partner_type' => 'DELIVERY', 'partner_id' => $deliveryId,
                        'debit' => $amount, 'reference_type' => 'DELIVERY_CUSTOMER_COLLECTION',
                        'reference_id' => $collectionId, 'note' => 'مبلغ محصل لدى المندوب',
                    ]);
                    $this->journal->addLine([
                        'account_id' => $customerAccountId,
                        'partner_type' => 'CLIENT', 'partner_id' => (int)$order['CLIENT_ID'],
                        'credit' => $amount, 'reference_type' => 'DELIVERY_CUSTOMER_COLLECTION',
                        'reference_id' => $collectionId, 'note' => 'سداد العميل عن أمر ' . $order['SUB_ORDER_ID'],
                    ]);
                    return $this->journal->post();
                });
                $collectionJournalId = (int)$eventResult['journal_entry_id'];

                if (!empty($eventResult['already_posted'])) {
                    $existingStmt = $this->db->prepare("SELECT id, receipt_no, customer_paid_amount,
                            commission_amount, commission_journal_id, status
                        FROM delivery_customer_collections
                        WHERE collection_journal_id = ? LIMIT 1 FOR UPDATE");
                    $existingStmt->execute([$collectionJournalId]);
                    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$existing || $existing['status'] !== 'POSTED'
                        || abs((float)$existing['customer_paid_amount'] - $amount) > 0.009) {
                        throw new RuntimeException('تم العثور على حدث تحصيل سابق غير متسق مع مستنده؛ يلزم فحصه.');
                    }
                    $existingAllocationStmt = $this->db->prepare("SELECT customer_amount
                        FROM delivery_collection_order_allocations
                        WHERE collection_id = ? AND order_id = ? LIMIT 1");
                    $existingAllocationStmt->execute([(int)$existing['id'], $orderId]);
                    $existingAllocation = $existingAllocationStmt->fetchColumn();
                    if ($existingAllocation === false || abs((float)$existingAllocation - $amount) > 0.009) {
                        throw new RuntimeException('تخصيص التحصيل السابق غير متسق مع طلب إعادة الإرسال.');
                    }
                    $created[] = [
                        'collection_id' => (int)$existing['id'],
                        'receipt_no' => $existing['receipt_no'],
                        'order_id' => $orderId,
                        'customer_id' => (int)$order['CLIENT_ID'],
                        'amount' => $amount,
                        'commission' => (float)$existing['commission_amount'],
                        'collection_journal_id' => $collectionJournalId,
                        'commission_journal_id' => $existing['commission_journal_id'] ? (int)$existing['commission_journal_id'] : null,
                        'already_posted' => true,
                    ];
                    continue;
                }
                if ($collectionId <= 0) {
                    throw new RuntimeException('تعذر إنشاء مستند التحصيل المرتبط بالحدث المالي.');
                }

                $creditLineStmt = $this->db->prepare("SELECT id FROM journal_entry_details
                    WHERE journal_entry_id = ? AND account_id = ? AND partner_type = 'CLIENT'
                      AND partner_id = ? AND credit > 0
                      AND reference_type = 'DELIVERY_CUSTOMER_COLLECTION' AND reference_id = ? LIMIT 1");
                $creditLineStmt->execute([$collectionJournalId, $customerAccountId, (int)$order['CLIENT_ID'], $collectionId]);
                $creditLineId = (int)$creditLineStmt->fetchColumn();
                if ($creditLineId <= 0) throw new RuntimeException('تعذر تحديد سطر سداد العميل لتخصيصه على أمر البيع.');

                // توزيع التحصيل على مديونية المنتجات أولًا ثم مديونية الشحن، دون تجاوز الرصيد المفتوح لأي سطر.
                $remainingToAllocate = $amount;
                $allocation = $this->db->prepare("INSERT INTO accounting_line_allocations
                    (debit_line_id, credit_line_id, amount, allocation_date, status, note, created_by)
                    VALUES (?, ?, ?, NOW(), 'ACTIVE', ?, ?)");
                foreach ($receivableLines as $receivableLine) {
                    if ($remainingToAllocate <= 0.009) break;
                    $take = min($remainingToAllocate, (float)$receivableLine['open_amount']);
                    if ($take <= 0) continue;
                    $allocation->execute([
                        (int)$receivableLine['id'], $creditLineId, round($take, 2),
                        'تخصيص تحصيل مندوب (' . $receivableLine['kind'] . ') لأمر ' . $order['SUB_ORDER_ID'],
                        $actorId
                    ]);
                    $remainingToAllocate = round($remainingToAllocate - $take, 2);
                }
                if ($remainingToAllocate > 0.009) {
                    throw new RuntimeException('تعذر تخصيص كامل مبلغ التحصيل على مديونية الطلب.');
                }

                $commissionJournalId = null;
                if ($commission > 0) {
                    $commissionResult = $eventManager->post([
                        'idempotency_key' => 'DELIVERY_COMMISSION:COLLECTION:' . $collectionId . ':COMMISSION_ACCRUED',
                        'event_type' => 'COMMISSION_ACCRUED',
                        'source_type' => 'DELIVERY_COLLECTION',
                        'source_id' => $collectionId,
                        'branch_id' => $branchId,
                        'event_date' => date('Y-m-d H:i:s'),
                        'created_by' => $actorId,
                        'payload' => [
                            'collection_id' => $collectionId,
                            'delivery_id' => $deliveryId,
                            'commission_amount' => $commission,
                        ],
                    ], function () use ($branchId, $collectionId, $deliveryId, $commission,
                        $commissionExpenseAccountId, $commissionPayableAccountId, $actorId, $order): int {
                        $this->journal->reset()->create([
                            'branch_id' => $branchId,
                            'entry_date' => date('Y-m-d H:i:s'),
                            'reference_type' => 'DELIVERY_COMMISSION_ACCRUAL',
                            'reference_id' => $collectionId,
                            'description' => 'استحقاق عمولة مندوب عن أمر ' . $order['SUB_ORDER_ID'],
                            'created_by' => $actorId,
                        ]);
                        $this->journal->addLine([
                            'account_id' => $commissionExpenseAccountId,
                            'debit' => $commission,
                            'reference_type' => 'DELIVERY_CUSTOMER_COLLECTION', 'reference_id' => $collectionId,
                            'note' => 'مصروف عمولة المندوب',
                        ]);
                        $this->journal->addLine([
                            'account_id' => $commissionPayableAccountId,
                            'partner_type' => 'DELIVERY', 'partner_id' => $deliveryId,
                            'credit' => $commission,
                            'reference_type' => 'DELIVERY_CUSTOMER_COLLECTION', 'reference_id' => $collectionId,
                            'note' => 'عمولة مستحقة للمندوب',
                        ]);
                        return $this->journal->post();
                    });
                    $commissionJournalId = (int)$commissionResult['journal_entry_id'];
                }

                $marketingCommissionTotal = round($marketerCommission + $leaderCommission, 2);
                if ($marketingCommissionTotal > 0) {
                    $marketingExpenseAccountId = $this->accounts->marketerCommissionExpense();
                    $marketingPayableAccountId = $this->accounts->marketerCommissionPayable();
                    $marketingEvent = $eventManager->post([
                        'idempotency_key' => 'MARKETER_COMMISSION:COLLECTION:' . $collectionId . ':ORDER:' . $orderId,
                        'event_type' => 'MARKETER_COMMISSION_ACCRUED',
                        'source_type' => 'DELIVERY_COLLECTION',
                        'source_id' => $collectionId,
                        'branch_id' => $branchId,
                        'event_date' => date('Y-m-d H:i:s'),
                        'created_by' => $actorId,
                        'payload' => [
                            'collection_id' => $collectionId,
                            'order_id' => $orderId,
                            'marketer_id' => $marketerId,
                            'leader_id' => $leaderId,
                            'marketer_commission' => $marketerCommission,
                            'leader_commission' => $leaderCommission,
                        ],
                    ], function () use (
                        $branchId, $collectionId, $orderId, $order, $actorId,
                        $marketingCommissionTotal, $marketerCommission, $leaderCommission,
                        $marketerId, $leaderId, $marketingExpenseAccountId, $marketingPayableAccountId
                    ): int {
                        $this->journal->reset()->create([
                            'branch_id' => $branchId,
                            'entry_date' => date('Y-m-d H:i:s'),
                            'reference_type' => 'MARKETER_COMMISSION_ACCRUAL',
                            'reference_id' => $orderId,
                            'description' => 'استحقاق عمولات المسوقين وقائد الفريق عن أمر ' . $order['SUB_ORDER_ID'],
                            'created_by' => $actorId,
                        ]);
                        $this->journal->addLine([
                            'account_id' => $marketingExpenseAccountId,
                            'debit' => $marketingCommissionTotal,
                            'reference_type' => 'MARKETER_COMMISSION_ACCRUAL',
                            'reference_id' => $orderId,
                            'note' => 'مصروف عمولات المسوقين وقادة الفرق',
                        ]);
                        if ($marketerCommission > 0) {
                            $this->journal->addLine([
                                'account_id' => $marketingPayableAccountId,
                                'partner_type' => 'MARKETER',
                                'partner_id' => $marketerId,
                                'credit' => $marketerCommission,
                                'reference_type' => 'MARKETER_COMMISSION_ACCRUAL',
                                'reference_id' => $orderId,
                                'note' => 'عمولة مستحقة للمسوق',
                            ]);
                        }
                        if ($leaderCommission > 0) {
                            $this->journal->addLine([
                                'account_id' => $marketingPayableAccountId,
                                'partner_type' => 'MARKETER',
                                'partner_id' => $leaderId,
                                'credit' => $leaderCommission,
                                'reference_type' => 'MARKETER_COMMISSION_ACCRUAL',
                                'reference_id' => $orderId,
                                'note' => 'عمولة مستحقة لقائد الفريق',
                            ]);
                        }
                        return $this->journal->post();
                    });
                }

                $this->db->prepare("INSERT INTO delivery_collection_order_allocations
                    (collection_id, order_id, customer_amount, commission_amount)
                    VALUES (?, ?, ?, ?)")->execute([$collectionId, $orderId, $amount, $commission]);
                $postedUpdate = $this->db->prepare("UPDATE delivery_customer_collections
                    SET collection_journal_id = ?, commission_journal_id = ?, status = 'POSTED', posted_at = NOW()
                    WHERE id = ? AND status = 'DRAFT'");
                $postedUpdate->execute([$collectionJournalId, $commissionJournalId, $collectionId]);
                if ($postedUpdate->rowCount() !== 1) {
                    throw new RuntimeException('تعذر اعتماد مستند تحصيل المندوب بعد ترحيل القيود.');
                }

                $created[] = [
                    'collection_id' => $collectionId,
                    'receipt_no' => $receipt,
                    'order_id' => $orderId,
                    'customer_id' => (int)$order['CLIENT_ID'],
                    'amount' => $amount,
                    'commission' => $commission,
                    'collection_journal_id' => $collectionJournalId,
                    'commission_journal_id' => $commissionJournalId,
                ];
            }

            // ربط كل مستند فرعي بالطلب الرئيسي بعد اكتمال إنشاء جميع أوامر الطلب.
            $linkStmt = $this->db->prepare("INSERT INTO delivery_collection_request_collections
                (request_id, collection_id, order_id, customer_amount, commission_amount)
                VALUES (?, ?, ?, ?, ?)");
            foreach ($created as $collectionResult) {
                $linkStmt->execute([
                    $requestId,
                    (int)$collectionResult['collection_id'],
                    (int)$collectionResult['order_id'],
                    round((float)$collectionResult['amount'], 2),
                    round((float)$collectionResult['commission'], 2),
                ]);
            }
            $requestPosted = $this->db->prepare("UPDATE delivery_collection_requests
                SET status = 'POSTED', completed_at = NOW()
                WHERE id = ? AND status = 'PROCESSING'");
            $requestPosted->execute([$requestId]);
            if ($requestPosted->rowCount() !== 1) {
                throw new RuntimeException('تعذر اعتماد طلب التحصيل الرئيسي بعد اكتمال مستنداته.');
            }

            if ($ownsTransaction) $this->db->commit();
            return ['success' => true, 'collections' => $created, 'total_amount' => round(array_sum($normalized), 2)];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * الأوامر التي لديها مبالغ محصلة من العميل ولم تتم تسويتها مع الشركة بالكامل.
     * لا تظهر الأوامر المسددة بالكامل، وتعرض المتبقي من كل تخصيص.
     */
    public function listOpenOrders(int $deliveryId): array
    {
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException('يجب اختيار المندوب.');
        }

        $sql = "SELECT
                    a.order_id AS ORDER_ID,
                    o.SUB_ORDER_ID,
                    o.DATE_CREATED,
                    dc.customer_id AS CLIENT_ID,
                    SUM(a.customer_amount - COALESCE(x.settled_gross, 0)) AS OPEN_AMOUNT,
                    SUM(a.commission_amount - COALESCE(x.settled_commission, 0)) AS OPEN_COMMISSION
                FROM delivery_collection_order_allocations a
                INNER JOIN delivery_customer_collections dc ON dc.id = a.collection_id
                INNER JOIN orders o ON o.ORDER_ID = a.order_id
                LEFT JOIN (
                    SELECT l.allocation_id,
                           SUM(l.applied_gross_amount) AS settled_gross,
                           SUM(l.applied_commission_amount) AS settled_commission
                    FROM delivery_settlement_collection_links l
                    INNER JOIN delivery_company_settlements s ON s.id = l.settlement_id AND s.status = 'POSTED'
                    GROUP BY l.allocation_id
                ) x ON x.allocation_id = a.id
                WHERE dc.delivery_user_id = :delivery_id
                  AND dc.status = 'POSTED'
                GROUP BY a.order_id, o.SUB_ORDER_ID, o.DATE_CREATED, dc.customer_id
                HAVING ROUND(SUM(a.customer_amount - COALESCE(x.settled_gross, 0)), 2) > 0
                ORDER BY o.DATE_CREATED ASC, a.order_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['delivery_id' => $deliveryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * معاينة توزيع مبلغ على أقدم أوامر مفتوحة. لا تُسجّل أي حركة.
     */
    public function previewAllocation(int $deliveryId, float $amount, array $orderIds = []): array
    {
        if (!is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('أدخل مبلغًا أكبر من صفر.');
        }
        $orders = $this->listOpenOrders($deliveryId);
        if ($orderIds) {
            $allowed = array_fill_keys(array_map('intval', $orderIds), true);
            $orders = array_values(array_filter($orders, static fn($order) => isset($allowed[(int)$order['ORDER_ID']])));
        }
        $remaining = round($amount, 2);
        $allocation = [];
        foreach ($orders as $order) {
            if ($remaining <= 0) break;
            $open = round((float)$order['OPEN_AMOUNT'], 2);
            if ($open <= 0) continue;
            $take = min($remaining, $open);
            $allocation[] = [
                'order_id' => (int)$order['ORDER_ID'],
                'sub_order_id' => $order['SUB_ORDER_ID'],
                'open_before' => $open,
                'open_commission' => round((float)($order['OPEN_COMMISSION'] ?? 0), 2),
                'settlement_amount' => round($take, 2),
                'open_after' => round($open - $take, 2),
            ];
            $remaining = round($remaining - $take, 2);
        }
        return [
            'requested_amount' => round($amount, 2),
            'allocated_amount' => round($amount - $remaining, 2),
            'unallocated_amount' => $remaining,
            'allocations' => $allocation,
        ];
    }

    /** معاينة الأوامر المختارة يدويًا وإجمالي الرصيد المفتوح لها. */
    public function previewSelectedOrders(int $deliveryId, array $orderIds): array
    {
        if ($deliveryId <= 0 || !$orderIds) {
            throw new InvalidArgumentException('اختر المندوب وأمر بيع واحدًا على الأقل.');
        }
        $rows = $this->getOpenAllocationRows($deliveryId, $orderIds, false);
        $byOrder = [];
        foreach ($rows as $row) {
            $id = (int)$row['order_id'];
            if (!isset($byOrder[$id])) {
                $byOrder[$id] = [
                    'order_id' => $id,
                    'open_amount' => 0.0,
                    'open_commission' => 0.0,
                ];
            }
            $byOrder[$id]['open_amount'] += (float)$row['OPEN_GROSS'];
            $byOrder[$id]['open_commission'] += (float)$row['OPEN_COMMISSION'];
        }
        foreach ($byOrder as &$row) {
            $row['open_amount'] = round($row['open_amount'], 2);
            $row['open_commission'] = round($row['open_commission'], 2);
        }
        unset($row);
        return [
            'orders' => array_values($byOrder),
            'total_amount' => round(array_sum(array_column($byOrder, 'open_amount')), 2),
        ];
    }

    /** التحقق من مفتاح منع تكرار الطلب والبصمة التي تمثل بياناته الأصلية. */
    private function validateIdempotencyRequest(string $requestKey, string $requestHash): void
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', trim($requestKey))) {
            throw new InvalidArgumentException('مفتاح منع التكرار مطلوب ويجب أن يكون من 8 إلى 64 حرفًا آمنًا.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $requestHash)) {
            throw new InvalidArgumentException('بصمة طلب العملية غير صالحة.');
        }
    }

    /** يعيد نتيجة التسوية السابقة عند إعادة نفس الطلب، ويرفض استخدام المفتاح ببيانات مختلفة. */
    private function findExistingSettlement(string $requestKey, string $requestHash): ?array
    {
        $stmt = $this->db->prepare("SELECT id, settlement_no, journal_id, commission_mode,
                collected_gross_amount, commission_offset_amount, cash_received_amount, status, request_hash
            FROM delivery_company_settlements WHERE request_key = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$requestKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!hash_equals((string)$row['request_hash'], $requestHash)) {
            throw new RuntimeException('مفتاح التسوية مستخدم بالفعل مع بيانات مختلفة.');
        }
        if ($row['status'] !== 'POSTED' || empty($row['journal_id'])) {
            throw new RuntimeException('طلب التسوية السابق غير مكتمل؛ يلزم فحص المستند قبل إعادة المحاولة.');
        }
        $links = $this->db->prepare("SELECT l.allocation_id, l.collection_id, a.order_id,
                l.applied_gross_amount AS gross, l.applied_commission_amount AS commission
            FROM delivery_settlement_collection_links l
            INNER JOIN delivery_collection_order_allocations a ON a.id = l.allocation_id
            WHERE l.settlement_id = ? ORDER BY l.id");
        $links->execute([(int)$row['id']]);
        return [
            'success' => true, 'already_posted' => true,
            'settlement_id' => (int)$row['id'], 'settlement_no' => $row['settlement_no'],
            'journal_id' => (int)$row['journal_id'], 'commission_mode' => $row['commission_mode'],
            'gross_settled' => (float)$row['collected_gross_amount'],
            'commission_offset' => (float)$row['commission_offset_amount'],
            'cash_received' => (float)$row['cash_received_amount'],
            'allocations' => $links->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** يعيد نتيجة صرف العمولة السابقة عند إعادة نفس الطلب. */
    private function findExistingCommissionPayment(string $requestKey, string $requestHash): ?array
    {
        $stmt = $this->db->prepare("SELECT id, payment_no, journal_id, amount, status, request_hash
            FROM delivery_commission_payments WHERE request_key = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$requestKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!hash_equals((string)$row['request_hash'], $requestHash)) {
            throw new RuntimeException('مفتاح صرف العمولة مستخدم بالفعل مع بيانات مختلفة.');
        }
        if ($row['status'] !== 'POSTED' || empty($row['journal_id'])) {
            throw new RuntimeException('طلب صرف العمولة السابق غير مكتمل؛ يلزم فحص المستند قبل إعادة المحاولة.');
        }
        return [
            'success' => true, 'already_posted' => true,
            'payment_id' => (int)$row['id'], 'payment_no' => $row['payment_no'],
            'journal_id' => (int)$row['journal_id'], 'amount' => (float)$row['amount'],
        ];
    }

    /** استلام كامل الأرصدة المفتوحة للأوامر المحددة يدويًا. */
    public function settleSelectedOrders(
        int $deliveryId,
        int $branchId,
        int $paymentMethodId,
        array $orderIds,
        ?string $requestedMode = null,
        string $note = '',
        string $requestKey = '',
        string $requestHash = ''
    ): array {
        $this->validateIdempotencyRequest($requestKey, $requestHash);
        $existing = $this->findExistingSettlement($requestKey, $requestHash);
        if ($existing !== null) return $existing;
        $preview = $this->previewSelectedOrders($deliveryId, $orderIds);
        if ((float)$preview['total_amount'] <= 0) {
            throw new RuntimeException('الأوامر المحددة ليس عليها مبالغ مفتوحة للتسوية.');
        }
        return $this->settle($deliveryId, $branchId, $paymentMethodId,
            (float)$preview['total_amount'], $orderIds, $requestedMode, $note, $requestKey, $requestHash);
    }

    /**
     * استلام المبلغ من المندوب وتسوية العهدة.
     * $orderIds فارغ = توزيع مبلغ إجمالي على أقدم الأوامر؛ وإلا يوزع على الأوامر المحددة.
     * amount مطلوب في الحالتين؛ في الاختيار اليدوي يجب ألا يتجاوز مجموع أرصدتها المفتوحة.
     */
    public function settle(
        int $deliveryId,
        int $branchId,
        int $paymentMethodId,
        float $amount,
        array $orderIds = [],
        ?string $requestedMode = null,
        string $note = '',
        string $requestKey = '',
        string $requestHash = ''
    ): array {
        $this->validateIdempotencyRequest($requestKey, $requestHash);
        if ($deliveryId <= 0 || $branchId <= 0 || $paymentMethodId <= 0) {
            throw new InvalidArgumentException('المندوب والفرع وطريقة الدفع بيانات إلزامية.');
        }
        if (!is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('مبلغ الاستلام يجب أن يكون أكبر من صفر.');
        }
        $amount = round($amount, 2);
        $actorId = $this->actorId();
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $settings = $this->getSettings(true);
            $existingSettlement = $this->findExistingSettlement($requestKey, $requestHash);
            if ($existingSettlement !== null) {
                if ($ownsTransaction) $this->db->commit();
                return $existingSettlement;
            }
            $configuredMode = $settings['commission_settlement_mode'];
            if ($configuredMode === 'USER_CHOICE') {
                $mode = strtoupper((string)$requestedMode);
                if (!in_array($mode, ['DEDUCT_FROM_CUSTODY', 'SEPARATE'], true)) {
                    throw new InvalidArgumentException('اختر طريقة تسوية العمولة.');
                }
            } else {
                $mode = $configuredMode;
                if ($requestedMode !== null && $requestedMode !== '' && strtoupper($requestedMode) !== $mode) {
                    throw new RuntimeException('طريقة التسوية مفروضة من إعدادات النظام ولا يمكن تجاوزها من الشاشة.');
                }
            }

            $cashAccountId = $this->accounts->transactionAccount($paymentMethodId, $branchId);
            $custodyAccountId = (int)$settings['courier_custody_account_id'];
            $commissionPayableAccountId = (int)$settings['courier_commission_payable_account_id'];

            $rows = $this->getOpenAllocationRows($deliveryId, $orderIds, true);
            $available = array_sum(array_map(static fn($r) => (float)$r['OPEN_GROSS'], $rows));
            if ($amount - $available > 0.009) {
                throw new RuntimeException('المبلغ أكبر من إجمالي عهدة المندوب المفتوحة للأوامر المحددة.');
            }

            // لا تخصم عمولة سبق صرفها أو تسويتها؛ الرصيد الحقيقي من دفتر الأستاذ هو المرجع.
            $commissionBalanceStmt = $this->db->prepare("SELECT ROUND(COALESCE(SUM(d.credit - d.debit),0),2)
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                WHERE d.account_id = ? AND d.partner_type = 'DELIVERY' AND d.partner_id = ?");
            $commissionBalanceStmt->execute([$commissionPayableAccountId, $deliveryId]);
            $commissionPayableAvailable = max(0.0, round((float)$commissionBalanceStmt->fetchColumn(), 2));

            $left = $amount;
            $links = [];
            $commissionOffset = 0.0;
            foreach ($rows as $row) {
                if ($left <= 0) break;
                $openGross = round((float)$row['OPEN_GROSS'], 2);
                if ($openGross <= 0) continue;
                $take = min($left, $openGross);
                $openCommission = max(0.0, round((float)$row['OPEN_COMMISSION'], 2));
                $offset = $mode === 'DEDUCT_FROM_CUSTODY'
                    ? min($take, $openCommission, max(0.0, $commissionPayableAvailable - $commissionOffset))
                    : 0.0;
                $links[] = [
                    'allocation_id' => (int)$row['allocation_id'],
                    'collection_id' => (int)$row['collection_id'],
                    'order_id' => (int)$row['order_id'],
                    'gross' => round($take, 2),
                    'commission' => round($offset, 2),
                ];
                $commissionOffset += $offset;
                $left = round($left - $take, 2);
            }
            if ($left > 0.009) {
                throw new RuntimeException('تعذر توزيع كامل مبلغ الاستلام على الأوامر المفتوحة.');
            }

            $cashReceived = round($amount - $commissionOffset, 2);
            if ($cashReceived < 0) throw new RuntimeException('صافي المبلغ المطلوب غير صالح.');

            $insert = $this->db->prepare("INSERT INTO delivery_company_settlements
                (settlement_no, delivery_user_id, branch_id, payment_method_id, settlement_date,
                 commission_mode, collected_gross_amount, commission_offset_amount, cash_received_amount,
                 status, note, created_by, request_key, request_hash)
                VALUES (NULL, ?, ?, ?, NOW(), ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?)");
            $insert->execute([$deliveryId, $branchId, $paymentMethodId, $mode, $amount, $commissionOffset, $cashReceived, trim($note), $actorId, $requestKey, $requestHash]);
            $settlementId = (int)$this->db->lastInsertId();
            $settlementNo = 'DSET-' . date('Y') . '-' . str_pad((string)$settlementId, 7, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE delivery_company_settlements SET settlement_no = ? WHERE id = ?')->execute([$settlementNo, $settlementId]);

            $linkInsert = $this->db->prepare("INSERT INTO delivery_settlement_collection_links
                (settlement_id, allocation_id, collection_id, applied_gross_amount, applied_commission_amount)
                VALUES (?, ?, ?, ?, ?)");
            foreach ($links as $link) {
                $linkInsert->execute([$settlementId, $link['allocation_id'], $link['collection_id'], $link['gross'], $link['commission']]);
            }

            // القيد: مدين النقدية بالصافي، مدين مستحق العمولة بالمقاصة، دائن عهدة المندوب بإجمالي العهدة المسوّاة.
            $this->journal->reset()->create([
                'branch_id' => $branchId,
                'entry_date' => date('Y-m-d H:i:s'),
                'reference_type' => 'DELIVERY_SETTLEMENT',
                'reference_id' => $settlementId,
                'description' => 'استلام عهدة من المندوب ' . $deliveryId . ' — ' . $settlementNo,
                'created_by' => $actorId,
            ]);
            if ($cashReceived > 0) {
                $this->journal->addLine(['account_id' => $cashAccountId, 'debit' => $cashReceived, 'note' => 'النقد المستلم فعليًا']);
            }
            if ($commissionOffset > 0) {
                $this->journal->addLine([
                    'account_id' => $commissionPayableAccountId,
                    'partner_type' => 'DELIVERY', 'partner_id' => $deliveryId,
                    'debit' => $commissionOffset,
                    'note' => 'مقاصة عمولة مستحقة للمندوب دون صرف نقدي',
                ]);
            }
            $this->journal->addLine([
                'account_id' => $custodyAccountId,
                'partner_type' => 'DELIVERY', 'partner_id' => $deliveryId,
                'credit' => $amount,
                'note' => 'تسوية عهدة التحصيلات',
            ]);
            $journalId = $this->journal->post();

            $this->db->prepare("UPDATE delivery_company_settlements
                SET journal_id = ?, status = 'POSTED', posted_at = NOW() WHERE id = ? AND status = 'DRAFT'")
                ->execute([$journalId, $settlementId]);
            if ($this->db->query('SELECT ROW_COUNT()')->fetchColumn() != 1) {
                throw new RuntimeException('لم يتم اعتماد مستند التسوية.');
            }

            if ($ownsTransaction) $this->db->commit();
            return [
                'success' => true,
                'settlement_id' => $settlementId,
                'settlement_no' => $settlementNo,
                'journal_id' => $journalId,
                'commission_mode' => $mode,
                'gross_settled' => $amount,
                'commission_offset' => round($commissionOffset, 2),
                'cash_received' => $cashReceived,
                'allocations' => $links,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * صرف عمولة مستحقة للمندوب نقدًا/تحويلًا بصورة مستقلة.
     * لا يُستدعى هذا المسار لمقاصة العمولة مع عهدة التحصيل؛ المقاصة تتم داخل settle().
     */
    public function payCommission(
        int $deliveryId,
        int $branchId,
        int $paymentMethodId,
        float $amount,
        string $note = '',
        string $requestKey = '',
        string $requestHash = ''
    ): array {
        $this->validateIdempotencyRequest($requestKey, $requestHash);
        if ($deliveryId <= 0 || $branchId <= 0 || $paymentMethodId <= 0 || !is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('المندوب والفرع وطريقة الدفع ومبلغ العمولة بيانات إلزامية.');
        }
        $amount = round($amount, 2);
        $actorId = $this->actorId();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $settings = $this->getSettings(true);
            $existingPayment = $this->findExistingCommissionPayment($requestKey, $requestHash);
            if ($existingPayment !== null) {
                if ($ownsTransaction) $this->db->commit();
                return $existingPayment;
            }
            $payableAccountId = (int)$settings['courier_commission_payable_account_id'];
            $cashAccountId = $this->accounts->transactionAccount($paymentMethodId, $branchId);

            $balanceStmt = $this->db->prepare("SELECT ROUND(COALESCE(SUM(d.credit - d.debit),0),2)
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                WHERE d.account_id = ? AND d.partner_type = 'DELIVERY' AND d.partner_id = ?");
            $balanceStmt->execute([$payableAccountId, $deliveryId]);
            $outstanding = round((float)$balanceStmt->fetchColumn(), 2);
            if ($amount - $outstanding > 0.009) {
                throw new RuntimeException('مبلغ الصرف أكبر من العمولة المستحقة للمندوب. المتاح: ' . number_format(max(0, $outstanding), 2, '.', ''));
            }

            $insert = $this->db->prepare("INSERT INTO delivery_commission_payments
                (payment_no, delivery_user_id, branch_id, payment_method_id, payment_date,
                 amount, status, note, created_by, request_key, request_hash)
                VALUES (NULL, ?, ?, ?, NOW(), ?, 'DRAFT', ?, ?, ?, ?)");
            $insert->execute([$deliveryId, $branchId, $paymentMethodId, $amount, trim($note), $actorId, $requestKey, $requestHash]);
            $paymentId = (int)$this->db->lastInsertId();
            $paymentNo = 'DCP-' . date('Y') . '-' . str_pad((string)$paymentId, 7, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE delivery_commission_payments SET payment_no = ? WHERE id = ?')->execute([$paymentNo, $paymentId]);

            $this->journal->reset()->create([
                'branch_id' => $branchId,
                'entry_date' => date('Y-m-d H:i:s'),
                'reference_type' => 'DELIVERY_COMMISSION_PAYMENT',
                'reference_id' => $paymentId,
                'description' => 'صرف عمولة مستحقة للمندوب ' . $deliveryId . ' — ' . $paymentNo,
                'created_by' => $actorId,
            ]);
            $this->journal->addLine([
                'account_id' => $payableAccountId,
                'partner_type' => 'DELIVERY', 'partner_id' => $deliveryId,
                'debit' => $amount, 'note' => 'تخفيض عمولة مستحقة للمندوب',
            ]);
            $this->journal->addLine([
                'account_id' => $cashAccountId,
                'credit' => $amount, 'note' => 'صرف عمولة فعليًا عبر طريقة الدفع المحددة',
            ]);
            $journalId = $this->journal->post();
            $this->db->prepare("UPDATE delivery_commission_payments
                SET journal_id = ?, status = 'POSTED', posted_at = NOW()
                WHERE id = ? AND status = 'DRAFT'")->execute([$journalId, $paymentId]);
            if ((int)$this->db->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
                throw new RuntimeException('تعذر اعتماد مستند صرف العمولة.');
            }
            if ($ownsTransaction) $this->db->commit();
            return ['success' => true, 'payment_id' => $paymentId, 'payment_no' => $paymentNo,
                'journal_id' => $journalId, 'amount' => $amount, 'outstanding_before' => $outstanding,
                'outstanding_after' => round($outstanding - $amount, 2)];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function getOpenAllocationRows(int $deliveryId, array $orderIds, bool $lock): array
    {
        $params = ['delivery_id' => $deliveryId];
        $filter = '';
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn($id) => $id > 0)));
        if ($orderIds) {
            $in = [];
            foreach ($orderIds as $i => $id) {
                $key = 'oid' . $i;
                $in[] = ':' . $key;
                $params[$key] = $id;
            }
            $filter = ' AND a.order_id IN (' . implode(',', $in) . ')';
        }
        $sql = "SELECT a.id AS allocation_id, a.collection_id, a.order_id,
                       a.customer_amount - COALESCE(SUM(CASE WHEN s.status = 'POSTED' THEN l.applied_gross_amount ELSE 0 END), 0) AS OPEN_GROSS,
                       a.commission_amount - COALESCE(SUM(CASE WHEN s.status = 'POSTED' THEN l.applied_commission_amount ELSE 0 END), 0) AS OPEN_COMMISSION,
                       o.DATE_CREATED
                FROM delivery_collection_order_allocations a
                INNER JOIN delivery_customer_collections dc ON dc.id = a.collection_id
                INNER JOIN orders o ON o.ORDER_ID = a.order_id
                LEFT JOIN delivery_settlement_collection_links l ON l.allocation_id = a.id
                LEFT JOIN delivery_company_settlements s ON s.id = l.settlement_id
                WHERE dc.delivery_user_id = :delivery_id AND dc.status = 'POSTED'" . $filter . "
                GROUP BY a.id, a.collection_id, a.order_id, a.customer_amount, a.commission_amount, o.DATE_CREATED
                HAVING ROUND(a.customer_amount - COALESCE(SUM(CASE WHEN s.status = 'POSTED' THEN l.applied_gross_amount ELSE 0 END), 0), 2) > 0
                ORDER BY o.DATE_CREATED ASC, a.order_id ASC, a.id ASC";
        if ($lock) $sql .= ' FOR UPDATE';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
