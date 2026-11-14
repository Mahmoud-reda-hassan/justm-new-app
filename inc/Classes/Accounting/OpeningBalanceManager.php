<?php
include_once(__dir__.'/JournalManager.php');
include_once(__dir__.'/AccountSettings.php');

/**
 * مطابقة الأستاذ بأرصدة النظام القديم (أرصدة افتتاحية).
 * لكل طرف/مخزن: الفرق = رصيد النظام القديم - رصيد الأستاذ؛ يُرحَّل قيد تسوية مقابل حساب الأرصدة الافتتاحية (3300).
 * لا يعدّل أي بيان قديم، وإعادة التشغيل لا تكرر لأن الفرق يُعاد حسابه (يصبح صفرًا بعد الترحيل).
 *
 * معنى الرصيد:
 *  CLIENT   : مدين = DEBIT - CREDIT (العميل مدين لنا)، مع استبعاد أوامر لم تُقفل بعد (تُرحَّل عند الاستلام المالي).
 *  DELIVERY / MARKETER / SUPPLIER : دائن = CREDIT - DEBIT (مستحق لهم).
 *  INVENTORY: قيمة المخزون = كمية المخزن × تكلفة الكود (CODE_COST_PRICE).
 */
class OpeningBalanceManager {
    protected $db;
    protected JournalManager $journal;
    protected AccountSettings $settings;

    public const KINDS = ['CLIENT', 'DELIVERY', 'MARKETER', 'SUPPLIER', 'INVENTORY'];

    public function __construct($db) {
        $this->db = $db;
        $this->journal = new JournalManager($db);
        $this->settings = new AccountSettings($db);
    }

    // حساب الأستاذ ونوع الرصيد لكل نوع
    protected function kindInfo(string $kind): array {
        switch ($kind) {
            case 'CLIENT':   return ['account' => $this->settings->customers(), 'asset' => true];
            case 'DELIVERY': return ['account' => $this->settings->deliveryCommissionPayable(), 'asset' => false];
            case 'MARKETER': return ['account' => $this->settings->marketerCommissionPayable(), 'asset' => false];
            case 'SUPPLIER': return ['account' => $this->settings->suppliers(), 'asset' => false];
            case 'INVENTORY':return ['account' => $this->settings->inventory(), 'asset' => true];
        }
        throw new Exception("Unsupported kind.");
    }

    // أرصدة النظام القديم [id => ['name'=>, 'legacy'=>]]
    protected function legacyBalances(string $kind): array {
        if ($kind === 'INVENTORY') {
            $rows = $this->db->query("
                SELECT s.STO_ID AS id, s.STO_NAME AS name,
                       ROUND(COALESCE(SUM(sp.STO_QTY * pc.CODE_COST_PRICE),0),2) AS legacy
                FROM stores s
                LEFT JOIN stores_product sp ON sp.STO_ID = s.STO_ID
                LEFT JOIN product_codes pc ON pc.PRO_CODE = sp.PRO_CODE
                GROUP BY s.STO_ID, s.STO_NAME")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sign = ($kind === 'CLIENT')
                ? "IF(pb.TRANSACTION_TYPE='DEBIT', pb.BALANCE, -pb.BALANCE)"
                : "IF(pb.TRANSACTION_TYPE='CREDIT', pb.BALANCE, -pb.BALANCE)";
            $skipOpen = ($kind === 'CLIENT')
                ? "AND (pb.REFERENCE_ID IS NULL OR pb.REFERENCE_ID NOT IN (SELECT ORDER_ID FROM orders WHERE ORDER_SUCCESS = 0))"
                : "";
            $st = $this->db->prepare("
                SELECT pb.PARTNER_ID AS id, ROUND(SUM($sign),2) AS legacy
                FROM partners_balance pb
                WHERE pb.PARTNER_TYPE = ? $skipOpen
                GROUP BY pb.PARTNER_ID");
            $st->execute([$kind]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['id']] = ['name' => $r['name'] ?? null, 'legacy' => (float)$r['legacy']]; }
        return $out;
    }

    // أرصدة الأستاذ الحالية [id => net] بنفس اتجاه الرصيد القديم
    protected function glBalances(string $kind): array {
        $info = $this->kindInfo($kind);
        $net = $info['asset'] ? "SUM(d.debit - d.credit)" : "SUM(d.credit - d.debit)";
        if ($kind === 'INVENTORY') {
            $st = $this->db->prepare("SELECT d.store_id AS id, ROUND($net,2) AS gl FROM journal_entry_details d
                JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status='POSTED'
                WHERE d.account_id = ? AND d.store_id IS NOT NULL GROUP BY d.store_id");
        } else {
            $st = $this->db->prepare("SELECT d.partner_id AS id, ROUND($net,2) AS gl FROM journal_entry_details d
                JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status='POSTED'
                WHERE d.account_id = ? AND d.partner_id IS NOT NULL GROUP BY d.partner_id");
        }
        $st->execute([$info['account']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int)$r['id']] = (float)$r['gl']; }
        return $out;
    }

    protected function names(string $kind, array $ids): array {
        if (!$ids) { return []; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        switch ($kind) {
            case 'CLIENT':   $sql = "SELECT CLIENT_ID AS id, CLIENT_NAME AS n FROM client WHERE CLIENT_ID IN ($ph)"; break;
            case 'SUPPLIER': $sql = "SELECT SUP_ID AS id, SUP_NAME AS n FROM suppliers WHERE SUP_ID IN ($ph)"; break;
            case 'INVENTORY':$sql = "SELECT STO_ID AS id, STO_NAME AS n FROM stores WHERE STO_ID IN ($ph)"; break;
            default:         $sql = "SELECT USER_ID AS id, CONCAT(FORENAME,' ',LASTNAME) AS n FROM users WHERE USER_ID IN ($ph)";
        }
        $st = $this->db->prepare($sql);
        $st->execute(array_values($ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int)$r['id']] = $r['n']; }
        return $out;
    }

    /** قائمة الفروق (غير الصفرية) لكل الأنواع أو لنوع واحد. */
    public function preview(?string $onlyKind = null): array {
        $rows = [];
        foreach (self::KINDS as $kind) {
            if ($onlyKind !== null && $onlyKind !== $kind) { continue; }
            $legacy = $this->legacyBalances($kind);
            $gl = $this->glBalances($kind);
            $ids = array_unique(array_merge(array_keys($legacy), array_keys($gl)));
            $names = $this->names($kind, $ids);
            foreach ($ids as $id) {
                $l = $legacy[$id]['legacy'] ?? 0.0;
                $g = $gl[$id] ?? 0.0;
                $diff = round($l - $g, 2);
                if (abs($diff) < 0.005) { continue; }
                $rows[] = ['kind' => $kind, 'id' => (int)$id, 'name' => $names[$id] ?? ('#'.$id),
                           'legacy' => $l, 'gl' => $g, 'diff' => $diff];
            }
        }
        return $rows;
    }

    /** يرحّل قيد التسوية لطرف/مخزن واحد؛ يرجع 0 إن لم يكن هناك فرق. */
    public function post(string $kind, int $id, int $branchId, int $userId): int {
        if (!in_array($kind, self::KINDS, true) || $id <= 0) { throw new Exception("Invalid opening balance request."); }
        $row = null;
        foreach ($this->preview($kind) as $r) { if ($r['id'] === $id) { $row = $r; break; } }
        if (!$row) { return 0; }

        $info = $this->kindInfo($kind);
        $amount = abs($row['diff']);
        // زيادة رصيد الأصل = مدين، زيادة رصيد الالتزام = دائن؛ والنقص عكس ذلك
        $increase = $row['diff'] > 0;
        $debitMain = $info['asset'] ? $increase : !$increase;

        $main = ['account_id' => $info['account']];
        if ($kind === 'INVENTORY') { $main['store_id'] = $id; }
        else { $main['partner_type'] = $kind; $main['partner_id'] = $id; }
        $equity = ['account_id' => $this->settings->openingBalance()];

        $this->journal->reset();
        $this->journal->create([
            'branch_id'      => $branchId,
            'entry_date'     => date('Y-m-d H:i:s'),
            'reference_type' => 'OPENING_BALANCE',
            'reference_id'   => $id,
            'description'    => 'تسوية رصيد افتتاحي من النظام القديم - '.$kind.' '.$row['name'],
            'created_by'     => $userId
        ]);
        $this->journal->addLine($main   + ($debitMain ? ['debit' => $amount] : ['credit' => $amount]));
        $this->journal->addLine($equity + ($debitMain ? ['credit' => $amount] : ['debit' => $amount]));
        return $this->journal->post();
    }
}
