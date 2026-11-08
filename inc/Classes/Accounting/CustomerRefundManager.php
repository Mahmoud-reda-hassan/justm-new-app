<?php
declare(strict_types=1);

require_once __DIR__ . '/AccountSettings.php';
require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/CashBox.php';
require_once __DIR__ . '/FinancialVoucherManager.php';

/**
 * صرف رصيد مردود المبيعات من الأستاذ العام فقط.
 * كل الردود الجديدة تُرحّل إلى GL؛ تُقرأ سجلات الردود القديمة فقط لمنع الصرف المكرر.
 */
final class CustomerRefundManager
{
    private PDO|TrimmedPDO $db;
    private JournalManager $journal;
    private AccountSettings $accounts;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
        $this->journal = new JournalManager($db);
        $this->accounts = new AccountSettings($db);
    }

    public function refund(
        int $orderId,
        int $paymentMethodId,
        float $amount,
        string $requestKey,
        string $requestHash
    ): array {
        if ($orderId <= 0 || $paymentMethodId <= 0 || !is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('أمر المرتجع وطريقة الدفع والمبلغ بيانات إلزامية.');
        }
        $amount = round($amount, 2);
        $requestKey = trim($requestKey);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)
            || !preg_match('/^[a-f0-9]{64}$/D', $requestHash)) {
            throw new InvalidArgumentException('مفتاح أو بصمة طلب رد المبلغ غير صحيحة.');
        }
        $actorId = (int)($_SESSION['USER_ID'] ?? 0);
        if ($actorId <= 0) throw new RuntimeException('تعذر تحديد المستخدم المنفذ للعملية.');

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $existingStmt = $this->db->prepare("SELECT id, refund_no, journal_id, amount
                FROM customer_refund_payments
                WHERE request_key = ? AND request_hash = ? AND status = 'POSTED'
                LIMIT 1 FOR UPDATE");
            $existingStmt->execute([$requestKey, $requestHash]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ($ownsTransaction) $this->db->commit();
                return [
                    'success' => true,
                    'already_posted' => true,
                    'refund_id' => (int)$existing['id'],
                    'refund_no' => $existing['refund_no'],
                    'journal_id' => (int)$existing['journal_id'],
                    'amount' => (float)$existing['amount'],
                ];
            }

            $orderStmt = $this->db->prepare("SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS,
                    o.RETURN_ENTRY_ID, t.CLIENT_ID, s.BRANCH_ID
                FROM orders o
                INNER JOIN telephones t ON t.TEL_ID = o.TEL_ID
                INNER JOIN stores s ON s.STO_ID = o.STO_ID
                WHERE o.ORDER_ID = ? LIMIT 1 FOR UPDATE");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order || empty($order['RETURN_ENTRY_ID']) || !in_array((int)$order['ORDER_STATUS'], [6, 7], true)) {
                throw new RuntimeException('لا يمكن رد مبلغ قبل استلام المرتجع مخزنيًا وترحيل مردود البيع.');
            }

            $customerAccountId = $this->accounts->customers();
            $customerCreditAccountId = $this->accounts->customerCredits();

            // توافق مع المرتجعات القديمة: أي رصيد مرتجع قديم على 1151 يخصم من أقدم المديونيات أولًا.
            // المرتجعات الجديدة تسجل الزيادة مباشرة على 1152 بعد إغلاق المديونية.
            // احتساب الردود القديمة قبل توزيع أي رصيد مرتجع قديم على المديونية.
            $legacyRefundStmt = $this->db->prepare("SELECT COALESCE(SUM(d.debit),0)
                FROM journal_entries h
                INNER JOIN financial_transactions ft ON h.reference_type = 'SALES_RETURN_PAYMENT'
                    AND ft.id = h.reference_id
                INNER JOIN invoices i ON i.INVO_ID = ft.invoice_id AND i.reference_id = ?
                INNER JOIN journal_entry_details d ON d.journal_entry_id = h.id
                    AND d.account_id = ? AND d.partner_type = 'CLIENT'
                    AND d.partner_id = ? AND d.debit > 0
                WHERE h.status = 'POSTED'");
            $legacyRefundStmt->execute([$orderId, $customerAccountId, (int)$order['CLIENT_ID']]);
            $legacyRefunded = round((float)$legacyRefundStmt->fetchColumn(), 2);

            $legacyReturnLinesStmt = $this->db->prepare(
                "SELECT d.id, d.credit, COALESCE(SUM(a.amount),0) AS allocated_amount
                 FROM journal_entry_details d
                 INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                 LEFT JOIN accounting_line_allocations a
                    ON a.credit_line_id = d.id AND a.status = 'ACTIVE'
                 WHERE d.journal_entry_id = ? AND d.account_id = ?
                   AND d.partner_type = 'CLIENT' AND d.partner_id = ? AND d.credit > 0
                 GROUP BY d.id, d.credit"
            );
            $legacyReturnLinesStmt->execute([
                (int)$order['RETURN_ENTRY_ID'],
                $customerAccountId,
                (int)$order['CLIENT_ID'],
            ]);
            $legacyReturnLines = $legacyReturnLinesStmt->fetchAll(PDO::FETCH_ASSOC);
            $legacyReturnLinesStmt->closeCursor();

            if ($legacyReturnLines) {
                $voucherManager = new FinancialVoucherManager($this->db);
                $openCustomerDebt = $voucherManager->getOpenPartnerDebitAmount('CLIENT', (int)$order['CLIENT_ID']);
                $remainingLegacyRefunded = $legacyRefunded;
                foreach ($legacyReturnLines as $returnLine) {
                    if ($openCustomerDebt <= 0.009) break;
                    $unallocated = max(0.0, round(
                        (float)$returnLine['credit'] - (float)$returnLine['allocated_amount'],
                        2
                    ));
                    $alreadyRefundedFromLine = min($unallocated, $remainingLegacyRefunded);
                    $unallocated = round($unallocated - $alreadyRefundedFromLine, 2);
                    $remainingLegacyRefunded = round($remainingLegacyRefunded - $alreadyRefundedFromLine, 2);
                    $applyToDebt = min($unallocated, $openCustomerDebt);
                    if ($applyToDebt <= 0) continue;
                    $voucherManager->allocatePartnerCreditLineToOldestDebits(
                        (int)$order['RETURN_ENTRY_ID'],
                        (int)$returnLine['id'],
                        'CLIENT',
                        (int)$order['CLIENT_ID'],
                        $applyToDebt,
                        $actorId,
                        'تسوية رصيد مرتجع تاريخي مع أقدم مديونية للعميل'
                    );
                    $openCustomerDebt = round($openCustomerDebt - $applyToDebt, 2);
                }
            }

            $returnCreditStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(d.credit - COALESCE(a.allocated_amount,0)),0)
                 FROM journal_entry_details d
                 INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                 LEFT JOIN (
                    SELECT credit_line_id, SUM(amount) AS allocated_amount
                    FROM accounting_line_allocations
                    WHERE status = 'ACTIVE'
                    GROUP BY credit_line_id
                 ) a ON a.credit_line_id = d.id
                 WHERE h.id = ? AND d.account_id = ?
                   AND d.partner_type = 'CLIENT' AND d.partner_id = ? AND d.credit > 0"
            );
            $returnCreditStmt->execute([
                (int)$order['RETURN_ENTRY_ID'],
                $customerCreditAccountId,
                (int)$order['CLIENT_ID'],
            ]);
            $customerCreditAvailable = round((float)$returnCreditStmt->fetchColumn(), 2);

            $legacyReceivableCreditStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(d.credit - COALESCE(a.allocated_amount,0)),0)
                 FROM journal_entry_details d
                 INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                 LEFT JOIN (
                    SELECT credit_line_id, SUM(amount) AS allocated_amount
                    FROM accounting_line_allocations
                    WHERE status = 'ACTIVE'
                    GROUP BY credit_line_id
                 ) a ON a.credit_line_id = d.id
                 WHERE h.id = ? AND d.account_id = ?
                   AND d.partner_type = 'CLIENT' AND d.partner_id = ? AND d.credit > 0"
            );
            $legacyReceivableCreditStmt->execute([
                (int)$order['RETURN_ENTRY_ID'],
                $customerAccountId,
                (int)$order['CLIENT_ID'],
            ]);
            $legacyReceivableCredit = round((float)$legacyReceivableCreditStmt->fetchColumn(), 2);

            $refundedStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0)
                FROM customer_refund_payments
                WHERE order_id = ? AND status = 'POSTED'");
            $refundedStmt->execute([$orderId]);
            $alreadyRefunded = round((float)$refundedStmt->fetchColumn(), 2);

            $availableFromCustomerCredit = max(0.0, round($customerCreditAvailable - $alreadyRefunded, 2));
            $availableFromLegacyReceivable = max(0.0, round($legacyReceivableCredit - $legacyRefunded, 2));
            $available = round($availableFromCustomerCredit + $availableFromLegacyReceivable, 2);
            if ($amount - $available > 0.009) {
                throw new RuntimeException('مبلغ الرد أكبر من رصيد المرتجع المتاح. المتاح: ' . number_format($available, 2, '.', ''));
            }

            if ($paymentMethodId !== 1) {
                throw new RuntimeException('رد المبالغ غير النقدي متوقف حتى اكتمال الحسابات البنكية والتسويات.');
            }
            $cashBoxes = (new CashBox($this->db))->listForPaymentMethod((int)$order['BRANCH_ID'], 1);
            if (!$cashBoxes) {
                throw new RuntimeException('لا توجد خزينة نقدية نشطة في فرع المرتجع.');
            }
            $defaultBoxes = array_values(array_filter(
                $cashBoxes,
                static fn(array $box): bool => (int)($box['is_default'] ?? 0) === 1
            ));
            if (count($defaultBoxes) === 1) {
                $selectedCashBox = $defaultBoxes[0];
            } elseif (count($cashBoxes) === 1) {
                $selectedCashBox = $cashBoxes[0];
            } else {
                throw new RuntimeException('يوجد أكثر من خزينة دون خزينة افتراضية؛ حدد الخزينة الافتراضية قبل رد المبلغ.');
            }
            $cashAccountId = (int)$selectedCashBox['account_id'];
            $cashBoxId = (int)$selectedCashBox['id'];
            $insert = $this->db->prepare("INSERT INTO customer_refund_payments
                (refund_no, order_id, customer_id, branch_id, payment_method_id, refund_date,
                 amount, status, created_by, request_key, request_hash)
                VALUES (NULL, ?, ?, ?, ?, NOW(), ?, 'DRAFT', ?, ?, ?)");
            $insert->execute([
                $orderId, (int)$order['CLIENT_ID'], (int)$order['BRANCH_ID'],
                $paymentMethodId, $amount, $actorId, $requestKey, $requestHash
            ]);
            $refundId = (int)$this->db->lastInsertId();
            $refundNo = 'CRF-' . date('Y') . '-' . str_pad((string)$refundId, 7, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE customer_refund_payments SET refund_no = ? WHERE id = ?')
                ->execute([$refundNo, $refundId]);

            $this->journal->reset()->create([
                'branch_id' => (int)$order['BRANCH_ID'],
                'entry_date' => date('Y-m-d H:i:s'),
                'reference_type' => 'CUSTOMER_RETURN_REFUND',
                'reference_id' => $refundId,
                'description' => 'رد قيمة مرتجع أمر بيع ' . $order['SUB_ORDER_ID'] . ' — ' . $refundNo,
                'created_by' => $actorId,
            ]);
            $debitFromCustomerCredit = min($amount, $availableFromCustomerCredit);
            $debitFromLegacyReceivable = round($amount - $debitFromCustomerCredit, 2);
            if ($debitFromCustomerCredit > 0) {
                $this->journal->addLine([
                    'account_id' => $customerCreditAccountId,
                    'partner_type' => 'CLIENT',
                    'partner_id' => (int)$order['CLIENT_ID'],
                    'debit' => $debitFromCustomerCredit,
                    'reference_type' => 'CUSTOMER_RETURN_REFUND',
                    'reference_id' => $refundId,
                    'note' => 'صرف الرصيد الدائن للعميل الناتج عن المرتجع',
                ]);
            }
            if ($debitFromLegacyReceivable > 0) {
                $this->journal->addLine([
                    'account_id' => $customerAccountId,
                    'partner_type' => 'CLIENT',
                    'partner_id' => (int)$order['CLIENT_ID'],
                    'debit' => $debitFromLegacyReceivable,
                    'reference_type' => 'CUSTOMER_RETURN_REFUND',
                    'reference_id' => $refundId,
                    'note' => 'صرف رصيد مرتجع تاريخي غير مخصص على حساب العملاء',
                ]);
            }
            $this->journal->addLine([
                'account_id' => $cashAccountId,
                'cash_box_id' => $cashBoxId,
                'credit' => $amount,
                'reference_type' => 'CUSTOMER_RETURN_REFUND',
                'reference_id' => $refundId,
                'note' => 'صرف مبلغ المرتجع عبر طريقة الدفع المحددة',
            ]);
            $journalId = $this->journal->post();
            $this->db->prepare("UPDATE customer_refund_payments
                SET journal_id = ?, status = 'POSTED', posted_at = NOW()
                WHERE id = ? AND status = 'DRAFT'")->execute([$journalId, $refundId]);
            if ((int)$this->db->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
                throw new RuntimeException('تعذر اعتماد مستند رد مبلغ المرتجع.');
            }

            if ($ownsTransaction) $this->db->commit();
            return [
                'success' => true,
                'refund_id' => $refundId,
                'refund_no' => $refundNo,
                'journal_id' => $journalId,
                'amount' => $amount,
                'available_before' => $available,
                'available_after' => round($available - $amount, 2),
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
