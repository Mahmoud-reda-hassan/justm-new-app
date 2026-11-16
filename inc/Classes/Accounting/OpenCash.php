<?php
include_once(__DIR__ . '/JournalManager.php');
include_once(__DIR__ . '/AccountSettings.php');
include_once(__DIR__ . '/AccountManager.php');

/**
 * الصرف المفتوح (مصروف نقدي) والاستلام المفتوح (إيراد/استلام آخر).
 * الصرف:   مدين حساب مصروف مختار   / دائن النقدية.
 * الاستلام: مدين النقدية             / دائن حساب إيراد مختار.
 * أسباب الصرف/الاستلام = حسابات المصروفات/الإيرادات القابلة للترحيل (غير المرتبطة بإعدادات النظام)؛ ويمكن إضافة بند جديد من الشاشة.
 */
class OpenCash {
    protected $db;
    const KINDS = [
        'PAYOUT'  => ['type' => 'EXPENSE', 'parent' => '5000', 'from' => 5901, 'to' => 5999, 'ref' => 'OPEN_PAYOUT',  'desc' => 'صرف نقدي مفتوح'],
        'RECEIPT' => ['type' => 'REVENUE', 'parent' => '4000', 'from' => 4301, 'to' => 4399, 'ref' => 'OPEN_RECEIPT', 'desc' => 'استلام نقدي مفتوح'],
    ];
    public function __construct($db) { $this->db = $db; }

    private function kind(string $k): array {
        if (!isset(self::KINDS[$k])) { throw new Exception('نوع غير صحيح.'); }
        return self::KINDS[$k];
    }

    /** الحسابات المتاحة كأسباب (بدون حسابات مرتبطة بإعدادات النظام، عدا "أخرى"). */
    public function listAccounts(string $kind): array {
        $k = $this->kind($kind);
        $st = $this->db->prepare("SELECT a.id, a.account_code, a.account_name FROM accounts a
            WHERE a.account_type = ? AND a.allow_posting = 1 AND a.is_active = 1
              AND a.id NOT IN (SELECT account_id FROM account_settings WHERE setting_key NOT IN ('OTHER_EXPENSE','OTHER_REVENUE') AND account_id IS NOT NULL)
              AND a.account_code NOT IN ('5100','5700','5710','5800')
            ORDER BY a.account_code");
        $st->execute([$k['type']]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** يضيف بندًا جديدًا (حساب) بكود تلقائي تحت المجموعة الرئيسية. */
    public function addAccount(string $kind, string $name): array {
        $k = $this->kind($kind);
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '' || mb_strlen($name) > 100) { throw new Exception('اكتب اسم البند (حتى 100 حرف).'); }
        $dup = $this->db->prepare("SELECT COUNT(*) FROM accounts WHERE account_name = ?");
        $dup->execute([$name]);
        if ((int)$dup->fetchColumn() > 0) { throw new Exception('يوجد حساب بهذا الاسم بالفعل، اخترْه من القائمة.'); }
        $p = $this->db->prepare("SELECT id FROM accounts WHERE account_code = ? AND account_type = ?");
        $p->execute([$k['parent'], $k['type']]);
        $parentId = (int)$p->fetchColumn();
        if (!$parentId) { throw new Exception('المجموعة الرئيسية غير موجودة في دليل الحسابات.'); }
        $mx = $this->db->prepare("SELECT MAX(CAST(account_code AS UNSIGNED)) FROM accounts WHERE CAST(account_code AS UNSIGNED) BETWEEN ? AND ?");
        $mx->execute([$k['from'], $k['to']]);
        $max = (int)$mx->fetchColumn();
        $code = $max ? $max + 1 : $k['from'];
        if ($code > $k['to']) { throw new Exception('تم استهلاك نطاق أكواد البنود الجديدة.'); }
        $id = (new AccountManager($this->db))->create([
            'parent_id' => $parentId, 'account_code' => (string)$code, 'account_name' => $name,
            'account_type' => $k['type'], 'allow_posting' => 1, 'description' => 'أضيف من شاشة ' . $k['desc'],
        ]);
        return ['id' => $id, 'account_code' => (string)$code, 'account_name' => $name];
    }

    /** يسجّل الحركة ويرجع رقم القيد. */
    public function post(string $kind, int $accountId, float $amount, string $note, int $branchId, int $userId): int {
        $k = $this->kind($kind);
        $amount = round($amount, 2);
        if ($amount <= 0) { throw new Exception('قم بإدخال مبلغ صحيح.'); }
        $allowed = array_column($this->listAccounts($kind), 'id');
        if (!in_array((string)$accountId, array_map('strval', $allowed), true)) { throw new Exception('اختر سببًا صحيحًا.'); }
        $cash = (new AccountSettings($this->db))->cash($branchId);
        $j = new JournalManager($this->db);
        $j->create([
            'branch_id' => $branchId, 'reference_type' => $k['ref'], 'reference_id' => null,
            'description' => $k['desc'] . ($note !== '' ? ' - ' . mb_substr($note, 0, 200) : ''), 'created_by' => $userId,
        ]);
        if ($kind === 'PAYOUT') {
            $j->addLine(['account_id' => $accountId, 'debit' => $amount, 'note' => $note]);
            $j->addLine(['account_id' => $cash, 'credit' => $amount]);
        } else {
            $j->addLine(['account_id' => $cash, 'debit' => $amount]);
            $j->addLine(['account_id' => $accountId, 'credit' => $amount, 'note' => $note]);
        }
        return $j->post();
    }
}
