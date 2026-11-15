<?php
include_once(__DIR__ . '/JournalManager.php');

/**
 * قيد تصحيح لتحصيل أمر مندوب: عكس كامل لقيد التحصيل (والعمولات التي فيه) على الأستاذ فقط.
 * لا يعيد فتح الأمر ولا يلمس النظام القديم ولا حركات المخزن. السبب إلزامي. لا يُكرَّر لنفس القيد.
 */
class CollectionCorrection {
    protected $db;
    public function __construct($db) { $this->db = $db; }

    protected function orderId(string $ref): int {
        $st = $this->db->prepare("SELECT ORDER_ID FROM orders WHERE SUB_ORDER_ID = ? OR ORDER_ID = ? LIMIT 1");
        $st->execute([$ref, ctype_digit($ref) && strlen($ref) < 12 ? (int)$ref : 0]);
        $id = $st->fetchColumn();
        if (!$id) { throw new Exception('الأمر غير موجود.'); }
        return (int)$id;
    }

    /** قيود التحصيل الخاصة بالأمر وحالة كل واحد (هل عُكس؟) مع السطور. */
    public function find(string $ref): array {
        $oid = $this->orderId($ref);
        $st = $this->db->prepare("SELECT e.id, e.entry_no, e.entry_date, e.total_debit, e.description,
                (SELECT COUNT(*) FROM journal_entries r WHERE r.reference_type = 'JOURNAL_REVERSE' AND r.reference_id = e.id AND r.status = 'POSTED') AS reversed
            FROM journal_entries e WHERE e.reference_type = 'COURIER_COLLECTION' AND e.reference_id = ? AND e.status = 'POSTED' ORDER BY e.id");
        $st->execute([$oid]);
        $out = [];
        $ln = $this->db->prepare("SELECT a.account_code, a.account_name, d.debit, d.credit, d.partner_type, d.partner_id
            FROM journal_entry_details d JOIN accounts a ON a.id = d.account_id WHERE d.journal_entry_id = ? ORDER BY d.line_no");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) { $ln->execute([$e['id']]); $e['lines'] = $ln->fetchAll(PDO::FETCH_ASSOC); $e['order_id'] = $oid; $out[] = $e; }
        return $out;
    }

    public function reverse(int $entryId, string $reason, int $userId): int {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) { throw new Exception('اكتب سبب التصحيح (5 أحرف على الأقل).'); }
        $st = $this->db->prepare("SELECT id FROM journal_entries WHERE id = ? AND reference_type = 'COURIER_COLLECTION' AND status = 'POSTED'");
        $st->execute([$entryId]);
        if (!$st->fetchColumn()) { throw new Exception('ليس قيد تحصيل مندوب.'); }
        $st = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'JOURNAL_REVERSE' AND reference_id = ? AND status = 'POSTED'");
        $st->execute([$entryId]);
        if ((int)$st->fetchColumn() > 0) { throw new Exception('هذا القيد معكوس بالفعل.'); }
        $j = new JournalManager($this->db);
        return $j->reverse($entryId, 'تصحيح تحصيل مندوب (بواسطة مستخدم ' . $userId . '): ' . $reason);
    }
}
