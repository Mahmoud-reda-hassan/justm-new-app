<?php
declare(strict_types=1);

require_once __DIR__ . '/AccountSettings.php';
require_once __DIR__ . '/JournalManager.php';

/**
 * صرف رصيد مردود المبيعات من الأستاذ العام فقط.
 * لا يعتمد على invoices أو financial_transactions أو partners_balance.
 */
final class CustomerRefundManager
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
            $returnStmt = $this->db->prepare("SELECT COALESCE(SUM(d.credit),0)
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                WHERE h.id = ? AND d.account_id = ? AND d.partner_type = 'CLIENT'
                  AND d.partner_id = ? AND d.credit > 0");
            $returnStmt->execute([(int)$order['RETURN_ENTRY_ID'], $customerAccountId, (int)$order['CLIENT_ID']]);
            $returnCredit = round((float)$returnStmt->fetchColumn(), 2);

            $refundedStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0)
                FROM customer_refund_payments
                WHERE order_id = ? AND status = 'POSTED'");
            $refundedStmt->execute([$orderId]);
            $alreadyRefunded = round((float)$refundedStmt->fetchColumn(), 2);
            $available = max(0.0, round($returnCredit - $alreadyRefunded, 2));
            if ($amount - $available > 0.009) {
                throw new RuntimeException('مبلغ الرد أكبر من رصيد المرتجع المتاح. المتاح: ' . number_format($available, 2, '.', ''));
            }

            $cashAccountId = $this->accounts->transactionAccount($paymentMethodId, (int)$order['BRANCH_ID']);
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
            $this->journal->addLine([
                'account_id' => $customerAccountId,
                'partner_type' => 'CLIENT',
                'partner_id' => (int)$order['CLIENT_ID'],
                'debit' => $amount,
                'reference_type' => 'CUSTOMER_RETURN_REFUND',
                'reference_id' => $refundId,
                'note' => 'تخفيض رصيد مرتجع مستحق للعميل',
            ]);
            $this->journal->addLine([
                'account_id' => $cashAccountId,
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
