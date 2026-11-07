<?php
declare(strict_types=1);

/** قراءة الخزائن النشطة المتاحة لطريقة دفع محددة داخل فرع محدد. */
final class CashBox
{
    private PDO|TrimmedPDO $db;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
    }

    public function listForPaymentMethod(int $branchId, int $paymentMethodId): array
    {
        if ($branchId <= 0 || $paymentMethodId <= 0) {
            throw new InvalidArgumentException('الفرع وطريقة الدفع مطلوبان لعرض الخزائن.');
        }

        $stmt = $this->db->prepare("
            SELECT cb.id, cb.account_id,
                   COALESCE(NULLIF(cb.box_name, ''), NULLIF(cb.name, ''), CONCAT('خزينة #', cb.id)) AS name
            FROM cash_boxes cb
            INNER JOIN payment_method_accounts pma
                ON pma.branch_id = cb.branch_id
               AND pma.account_id = cb.account_id
               AND pma.payment_method_id = ?
               AND pma.is_active = 1
            INNER JOIN payment_methods pm ON pm.id = pma.payment_method_id AND pm.is_active = 1
            INNER JOIN accounts a ON a.id = cb.account_id
            WHERE cb.branch_id = ? AND cb.is_active = 1
              AND a.is_active = 1 AND a.allow_posting = 1
            ORDER BY cb.id
        ");
        $stmt->execute([$paymentMethodId, $branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
