<?php
/**
 * تقارير الأستاذ العام (قراءة فقط): ميزان المراجعة، كشف حساب، كشف طرف، دفتر اليومية.
 * كل القيود المحسوبة: status = POSTED. القيد العكسي قيد مستقل فيُحسب بنفس القاعدة.
 */
class GlReports {
    /** الأطراف: نوع الطرف → حساب دفتر الأطراف + اتجاه الرصيد الطبيعي */
    const PARTNERS = ['CLIENT' => '1200', 'SUPPLIER' => '2100', 'DELIVERY' => '2210', 'MARKETER' => '2220', 'BROKER' => '2230'];

    protected $db;
    public function __construct($db) { $this->db = $db; }

    protected static function dateOrNull($d): ?string {
        $d = trim((string)$d);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    /** ميزان المراجعة: رصيد أول المدة، حركة المدة، رصيد آخر المدة لكل حساب. */
    public function trialBalance(?string $from, ?string $to, int $branchId = 0): array {
        $from = self::dateOrNull($from); $to = self::dateOrNull($to);
        $sql = "SELECT a.id AS account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                    COALESCE(SUM(CASE WHEN :f1 IS NOT NULL AND DATE(e.entry_date) < :f2 THEN d.debit - d.credit ELSE 0 END),0) AS opening,
                    COALESCE(SUM(CASE WHEN (:f3 IS NULL OR DATE(e.entry_date) >= :f4) AND (:t1 IS NULL OR DATE(e.entry_date) <= :t2) THEN d.debit ELSE 0 END),0) AS period_debit,
                    COALESCE(SUM(CASE WHEN (:f5 IS NULL OR DATE(e.entry_date) >= :f6) AND (:t3 IS NULL OR DATE(e.entry_date) <= :t4) THEN d.credit ELSE 0 END),0) AS period_credit
                FROM accounts a
                LEFT JOIN journal_entry_details d ON d.account_id = a.id
                LEFT JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
                    AND (:b1 = 0 OR e.branch_id = :b2)
                GROUP BY a.id, a.account_code, a.account_name, a.account_type, a.normal_balance
                ORDER BY a.account_code";
        $st = $this->db->prepare($sql);
        $st->execute([':f1' => $from, ':f2' => $from, ':f3' => $from, ':f4' => $from, ':f5' => $from, ':f6' => $from,
                      ':t1' => $to, ':t2' => $to, ':t3' => $to, ':t4' => $to, ':b1' => $branchId, ':b2' => $branchId]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $open = (float)$r['opening']; $dr = (float)$r['period_debit']; $cr = (float)$r['period_credit'];
            $close = round($open + $dr - $cr, 2);
            if (abs($open) < 0.005 && $dr < 0.005 && $cr < 0.005) { continue; }
            $rows[] = [
                'account_id' => (int)$r['account_id'], 'account_code' => $r['account_code'], 'account_name' => $r['account_name'],
                'account_type' => $r['account_type'],
                'opening_debit'  => $open > 0 ? round($open, 2) : 0, 'opening_credit' => $open < 0 ? round(-$open, 2) : 0,
                'period_debit' => round($dr, 2), 'period_credit' => round($cr, 2),
                'closing_debit'  => $close > 0 ? $close : 0, 'closing_credit' => $close < 0 ? -$close : 0,
            ];
        }
        return $rows;
    }

    /** كشف حساب: رصيد افتتاحي ثم السطور برصيد جارٍ (مدين − دائن). */
    public function accountStatement(int $accountId, ?string $from, ?string $to, int $branchId = 0): array {
        $from = self::dateOrNull($from); $to = self::dateOrNull($to);
        $opening = 0.0;
        if ($from) {
            $st = $this->db->prepare("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
                JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
                WHERE d.account_id = ? AND DATE(e.entry_date) < ? AND (? = 0 OR e.branch_id = ?)");
            $st->execute([$accountId, $from, $branchId, $branchId]);
            $opening = (float)$st->fetchColumn();
        }
        $st = $this->db->prepare("SELECT e.id AS entry_id, e.entry_no, e.entry_date, e.reference_type, e.reference_id, e.description,
                d.debit, d.credit, d.partner_type, d.partner_id, d.note
            FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
            WHERE d.account_id = ? AND (? IS NULL OR DATE(e.entry_date) >= ?) AND (? IS NULL OR DATE(e.entry_date) <= ?)
              AND (? = 0 OR e.branch_id = ?)
            ORDER BY e.entry_date, e.id, d.line_no");
        $st->execute([$accountId, $from, $from, $to, $to, $branchId, $branchId]);
        $run = $opening; $lines = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $run = round($run + (float)$r['debit'] - (float)$r['credit'], 2);
            $r['running'] = $run; $lines[] = $r;
        }
        return ['opening' => round($opening, 2), 'closing' => round($run, 2), 'lines' => $lines];
    }

    /**
     * كشف طرف (عميل/مورد/مندوب/مسوق): سطور الأستاذ الموسومة بالطرف، رصيد جارٍ بالاتجاه الطبيعي للطرف،
     * مع الرصيد القديم (partners_balance) للمقارنة.
     * العميل: مدين − دائن (موجب = عليه). غيره: دائن − مدين (موجب = له).
     */
    public function partnerStatement(string $type, int $partnerId, ?string $from, ?string $to): array {
        if (!isset(self::PARTNERS[$type])) { throw new Exception('نوع الطرف غير معروف.'); }
        $from = self::dateOrNull($from); $to = self::dateOrNull($to);
        $sign = $type === 'CLIENT' ? 1 : -1; // الرصيد = sign * (مدين − دائن)
        $opening = 0.0;
        if ($from) {
            $st = $this->db->prepare("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
                JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
                WHERE d.partner_type = ? AND d.partner_id = ? AND DATE(e.entry_date) < ?");
            $st->execute([$type, $partnerId, $from]);
            $opening = $sign * (float)$st->fetchColumn();
        }
        $st = $this->db->prepare("SELECT e.id AS entry_id, e.entry_no, e.entry_date, e.reference_type, e.reference_id, e.description,
                a.account_code, a.account_name, d.debit, d.credit
            FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
            JOIN accounts a ON a.id = d.account_id
            WHERE d.partner_type = ? AND d.partner_id = ?
              AND (? IS NULL OR DATE(e.entry_date) >= ?) AND (? IS NULL OR DATE(e.entry_date) <= ?)
            ORDER BY e.entry_date, e.id, d.line_no");
        $st->execute([$type, $partnerId, $from, $from, $to, $to]);
        $run = $opening; $lines = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $run = round($run + $sign * ((float)$r['debit'] - (float)$r['credit']), 2);
            $r['running'] = $run; $lines[] = $r;
        }
        // الرصيد الكلي (كل الفترة) في الأستاذ وفي النظام القديم، بنفس الاتجاه
        $st = $this->db->prepare("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
            WHERE d.partner_type = ? AND d.partner_id = ?");
        $st->execute([$type, $partnerId]);
        $glTotal = round($sign * (float)$st->fetchColumn(), 2);
        // نفس معادلة تسوية الأرصدة بالضبط (العميل: تُستبعد قيود الأوامر التي لم تُقفل ماليًا بعد)
        include_once(__DIR__ . '/OpeningBalanceManager.php');
        // الوسيط جديد: لا يوجد له رصيد في النظام القديم للمقارنة
        $legacy = $type === 'BROKER' ? $glTotal : round((new OpeningBalanceManager($this->db))->legacyBalanceOf($type, $partnerId), 2); // نفس اتجاه رصيد الكشف
        return ['opening' => round($opening, 2), 'closing' => round($run, 2), 'lines' => $lines,
                'gl_total' => $glTotal, 'legacy_total' => $legacy, 'diff' => round($glTotal - $legacy, 2)];
    }

    /** بحث عن طرف بالاسم. */
    public function searchPartners(string $type, string $q): array {
        $q = '%' . trim($q) . '%';
        switch ($type) {
            case 'CLIENT':   $sql = "SELECT CLIENT_ID AS id, CLIENT_NAME AS name FROM client WHERE CLIENT_NAME LIKE ? ORDER BY CLIENT_NAME LIMIT 30"; break;
            case 'SUPPLIER': $sql = "SELECT SUP_ID AS id, SUP_NAME AS name FROM suppliers WHERE SUP_NAME LIKE ? ORDER BY SUP_NAME LIMIT 30"; break;
            case 'DELIVERY':
            case 'MARKETER':
            case 'BROKER': $sql = "SELECT USER_ID AS id, CONCAT(FORENAME,' ',LASTNAME) AS name FROM users WHERE CONCAT(FORENAME,' ',LASTNAME) LIKE ? ORDER BY FORENAME LIMIT 30"; break;
            default: throw new Exception('نوع الطرف غير معروف.');
        }
        $st = $this->db->prepare($sql); $st->execute([$q]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** دفتر اليومية: رؤوس القيود بفلاتر. */
    public function journalBook(?string $from, ?string $to, int $branchId = 0, string $refType = '', int $limit = 2000): array {
        $from = self::dateOrNull($from); $to = self::dateOrNull($to);
        $limit = max(1, min(5000, $limit));
        $st = $this->db->prepare("SELECT e.id, e.entry_no, e.entry_date, e.branch_id, e.reference_type, e.reference_id, e.description,
                e.total_debit, e.total_credit, e.status,
                (SELECT COUNT(*) FROM journal_entry_details x WHERE x.journal_entry_id = e.id) AS lines_count
            FROM journal_entries e
            WHERE (? IS NULL OR DATE(e.entry_date) >= ?) AND (? IS NULL OR DATE(e.entry_date) <= ?)
              AND (? = 0 OR e.branch_id = ?) AND (? = '' OR e.reference_type = ?)
            ORDER BY e.entry_date DESC, e.id DESC LIMIT " . $limit);
        $st->execute([$from, $from, $to, $to, $branchId, $branchId, $refType, $refType]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** سطور قيد واحد. */
    public function entryLines(int $entryId): array {
        $st = $this->db->prepare("SELECT d.line_no, a.account_code, a.account_name, d.debit, d.credit, d.partner_type, d.partner_id, d.note
            FROM journal_entry_details d JOIN accounts a ON a.id = d.account_id
            WHERE d.journal_entry_id = ? ORDER BY d.line_no");
        $st->execute([$entryId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function referenceTypes(): array {
        return $this->db->query("SELECT DISTINCT reference_type FROM journal_entries WHERE reference_type IS NOT NULL ORDER BY reference_type")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** مقارنة قيمة المخزون في الأستاذ (1300) بقيمة المخزون الحالية بتكلفة الصنف الحالية. */
    public function inventoryValuation(): array {
        $st = $this->db->query("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d
            JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
            JOIN accounts a ON a.id = d.account_id WHERE a.account_code = '1300'");
        $gl = round((float)$st->fetchColumn(), 2);
        $cur = round((float)$this->db->query("SELECT COALESCE(SUM(sp.STO_QTY * pc.CODE_COST_PRICE),0)
            FROM stores_product sp JOIN product_codes pc ON pc.PRO_CODE = sp.PRO_CODE")->fetchColumn(), 2);
        return ['gl_inventory' => $gl, 'current_cost_value' => $cur, 'diff' => round($gl - $cur, 2)];
    }
}
