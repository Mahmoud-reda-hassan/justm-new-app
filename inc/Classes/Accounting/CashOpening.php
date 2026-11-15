<?php
include_once(__DIR__ . '/JournalManager.php');
include_once(__DIR__ . '/AccountSettings.php');

/**
 * أرصدة افتتاحية للصندوق والبنك: يدخلها المستخدم من الجرد الفعلي.
 * القيد: مدين حساب الصندوق/البنك، دائن الأرصدة الافتتاحية 3300. يمكن تعديل المبلغ لاحقًا؛
 * يُسجَّل فرق (المبلغ الجديد − مجموع الافتتاحيات السابقة لنفس الحساب) بقيد جديد، ولا يُحذف شيء.
 */
class CashOpening {
    protected $db;
    public function __construct($db) { $this->db = $db; }

    /** الصناديق والبنوك مع الافتتاحي المسجّل حاليًا. */
    public function listAccounts(): array {
        $rows = [];
        $boxes = $this->db->query("SELECT 'CASHBOX' AS kind, cb.id, cb.name, cb.account_id, cb.branch_id, a.account_code
            FROM cash_boxes cb JOIN accounts a ON a.id = cb.account_id WHERE cb.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $banks = [];
        try {
            $banks = $this->db->query("SELECT 'BANK' AS kind, b.id, b.bank_name AS name, b.account_id, 0 AS branch_id, a.account_code
                FROM banks b JOIN accounts a ON a.id = b.account_id WHERE b.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { }
        $st = $this->db->prepare("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
            WHERE e.reference_type = 'OPENING_CASH' AND d.account_id = ?");
        $cur = $this->db->prepare("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED' WHERE d.account_id = ?");
        foreach (array_merge($boxes, $banks) as $r) {
            $st->execute([$r['account_id']]); $r['opening'] = round((float)$st->fetchColumn(), 2);
            $cur->execute([$r['account_id']]); $r['gl_balance'] = round((float)$cur->fetchColumn(), 2);
            $rows[] = $r;
        }
        return $rows;
    }

    /** يضبط الافتتاحي لحساب = $amount. يُرجع رقم القيد (0 لو لا فرق). */
    public function set(int $accountId, float $amount, string $date, int $branchId, int $userId): int {
        $amount = round($amount, 2);
        if ($amount < 0) { throw new Exception('الرصيد الافتتاحي لا يكون سالبًا.'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { throw new Exception('تاريخ غير صحيح.'); }
        $known = false;
        foreach ($this->listAccounts() as $r) { if ((int)$r['account_id'] === $accountId) { $known = true; $current = $r['opening']; } }
        if (!$known) { throw new Exception('الحساب ليس صندوقًا ولا بنكًا.'); }
        $diff = round($amount - $current, 2);
        if (abs($diff) < 0.005) { return 0; }
        $equity = (new AccountSettings($this->db))->openingBalance();
        $j = new JournalManager($this->db);
        $j->create([
            'branch_id' => $branchId, 'entry_date' => $date . ' 00:00:00', 'reference_type' => 'OPENING_CASH',
            'reference_id' => $accountId, 'description' => 'رصيد افتتاحي نقدية/بنك', 'created_by' => $userId
        ]);
        $abs = abs($diff);
        $j->addLine(['account_id' => $accountId] + ($diff > 0 ? ['debit' => $abs] : ['credit' => $abs]));
        $j->addLine(['account_id' => $equity] + ($diff > 0 ? ['credit' => $abs] : ['debit' => $abs]));
        return $j->post();
    }
}
