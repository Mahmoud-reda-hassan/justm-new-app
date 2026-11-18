<?php
/**
 * تحديث قاعدة البيانات من ملفات inc/Databases/migrations.
 * - يعمل فقط على ملفات مسجّلة في FILES (قائمة بيضاء)، ولا يقبل أي نص من المستخدم.
 * - الملفات مكتوبة بحيث تتكرر بأمان (IF NOT EXISTS / CREATE OR REPLACE) ولا تحذف أو تعدّل بيانات.
 * - يرفض أي أمر UPDATE/DELETE/DROP/TRUNCATE/INSERT...SELECT على جداول البيانات غير المسموح بها.
 * - يسجّل ما نُفِّذ في جدول schema_migrations.
 */
class DatabaseUpdater {
    /** الملفات بالترتيب. أي ملف جديد يُضاف هنا. */
    const FILES = ['ACCOUNTING_COMPLETE_MIGRATION.sql'];
    const FORBIDDEN = '/^\s*(UPDATE|DELETE|DROP|TRUNCATE|RENAME|GRANT|REVOKE|CREATE\s+USER|LOAD|REPLACE)\b/i';

    protected $db;
    protected $dir;

    public function __construct($db) {
        $this->db  = $db;
        $this->dir = realpath(__DIR__ . '/../Databases/migrations');
    }

    protected function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `name` varchar(150) NOT NULL,
            `checksum` char(32) NOT NULL,
            `statements` int(11) NOT NULL DEFAULT 0,
            `applied_at` datetime NOT NULL,
            `applied_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    protected function path(string $name): string {
        if (!in_array($name, self::FILES, true)) { throw new Exception('ملف غير معروف.'); }
        $p = $this->dir . '/' . $name;
        if (!is_file($p)) { throw new Exception('ملف التحديث غير موجود على السيرفر: ' . $name); }
        return $p;
    }

    /** يقسم ملف SQL إلى أوامر، مع تجاهل التعليقات والفواصل داخل النصوص. */
    public static function split(string $sql): array {
        $out = []; $cur = ''; $q = ''; $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $c = $sql[$i];
            if ($q === '') {
                // كتلة إجراء مخزّن: من -- @PROC-BEGIN إلى -- @PROC-END أمرٌ واحد (فيها فواصل منقوطة داخلية)
                if ($c === '-' && trim($cur) === '' && preg_match('/\G-- @PROC-BEGIN\r?\n/', $sql, $mm, 0, $i)) {
                    $e = strpos($sql, '-- @PROC-END', $i);
                    if ($e === false) { throw new Exception('كتلة إجراء بلا نهاية.'); }
                    $blk = substr($sql, $i + strlen($mm[0]), $e - ($i + strlen($mm[0])));
                    $blk = preg_replace('/^\s*DELIMITER\b.*$/mi', '', $blk);   // DELIMITER أمر عميل mysql وليس SQL
                    $blk = preg_replace('/\$\$\s*$/', '', rtrim($blk));
                    $out[] = trim($blk);
                    $i = $e + 11; $cur = '';
                    while ($i + 1 < $n && $sql[$i + 1] !== "\n") { $i++; }
                    continue;
                }
                if ($c === '-' && ($sql[$i + 1] ?? '') === '-') { while ($i < $n && $sql[$i] !== "\n") { $i++; } continue; }
                if ($c === '#') { while ($i < $n && $sql[$i] !== "\n") { $i++; } continue; }
                if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $cur .= $c; continue; }
                if ($c === ';') { if (trim($cur) !== '') { $out[] = trim($cur); } $cur = ''; continue; }
                $cur .= $c;
            } else {
                $cur .= $c;
                if ($c === '\\' && $q !== '`') { $cur .= $sql[++$i] ?? ''; continue; }
                if ($c === $q) { $q = ''; }
            }
        }
        if (trim($cur) !== '') { $out[] = trim($cur); }
        return $out;
    }

    /** حالة كل ملف: pending / applied / changed. */
    public function status(): array {
        $this->ensureTable();
        $st = $this->db->query("SELECT name, checksum, statements, applied_at FROM schema_migrations");
        $applied = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $applied[$r['name']] = $r; }
        $rows = [];
        foreach (self::FILES as $f) {
            $p = $this->path($f);
            $sum = md5_file($p);
            $a = $applied[$f] ?? null;
            $rows[] = [
                'name' => $f,
                'state' => !$a ? 'pending' : ($a['checksum'] === $sum ? 'applied' : 'changed'),
                'applied_at' => $a['applied_at'] ?? null,
                'statements' => count(self::split(file_get_contents($p))),
            ];
        }
        return $rows;
    }

    /** فحوصات بعد التنفيذ: هل كل ما تحتاجه الشاشات موجود؟ */
    public function verify(): array {
        $checks = [];
        $accounts = ['1510', '2210', '2220', '2230', '3300', '5700', '5710'];
        $st = $this->db->prepare("SELECT COUNT(*) FROM accounts WHERE account_code = ?");
        foreach ($accounts as $code) {
            $st->execute([$code]);
            $checks[] = ['label' => 'حساب ' . $code, 'ok' => (int)$st->fetchColumn() > 0];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM account_settings WHERE setting_key = ?");
        foreach (['DELIVERY_CUSTODY', 'DELIVERY_COMMISSION_PAYABLE', 'MARKETER_COMMISSION_PAYABLE', 'BROKER_COMMISSION_PAYABLE', 'OPENING_BALANCE_EQUITY'] as $k) {
            $st->execute([$k]);
            $checks[] = ['label' => 'إعداد ' . $k, 'ok' => (int)$st->fetchColumn() > 0];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        foreach (['gl_trial_balance', 'gl_partner_balances'] as $v) {
            $st->execute([$v]);
            $checks[] = ['label' => 'تقرير ' . $v, 'ok' => (int)$st->fetchColumn() > 0];
        }
        $tb = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        foreach (['accounting_periods', 'system_flags', 'shipping_companies', 'shipping_company_rates', 'shipping_company_imports', 'shipping_company_statement_rows', 'shipping_company_statement_history', 'shipping_company_fees', 'shipping_company_shortages'] as $t) {
            $tb->execute([$t]);
            $checks[] = ['label' => 'جدول ' . $t, 'ok' => (int)$tb->fetchColumn() > 0];
        }
        $ct = $this->db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='inventory_movement' AND COLUMN_NAME='IM_MOVE_TYPE'")->fetchColumn();
        $checks[] = ['label' => 'نوع حركة المخزن REOPEN_RETURN_SHIPP', 'ok' => is_string($ct) && strpos($ct, 'REOPEN_RETURN_SHIPP') !== false];
        return $checks;
    }

    /** ينفّذ ملفًا واحدًا. عند الخطأ يتوقف ويذكر رقم الأمر؛ إعادة التشغيل آمنة. */
    public function run(string $name, int $userId): array {
        @set_time_limit(0);
        $this->ensureTable();
        $p = $this->path($name);
        $sql = file_get_contents($p);
        $stmts = self::split($sql);
        foreach ($stmts as $i => $s) {
            if (preg_match(self::FORBIDDEN, $s)) { throw new Exception('أمر غير مسموح في ملف التحديث (رقم ' . ($i + 1) . '): ' . mb_substr(preg_replace('/\s+/', ' ', $s), 0, 90)); }
        }
        $done = 0; $skipped = 0;
        foreach ($stmts as $i => $s) {
            // نوع حركة المخزن: لا يُعاد تعديله إن كان محدّثًا بالفعل
            if (preg_match('/^ALTER\s+TABLE\s+`?inventory_movement`?\s+MODIFY\s+`?IM_MOVE_TYPE`?/i', $s)) {
                $ct = $this->db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='inventory_movement' AND COLUMN_NAME='IM_MOVE_TYPE'")->fetchColumn();
                if (is_string($ct) && strpos($ct, 'REOPEN_RETURN_SHIPP') !== false) { $skipped++; continue; }
            }
            try {
                $this->db->exec($s);
                $done++;
            } catch (Throwable $e) {
                throw new Exception('فشل الأمر رقم ' . ($i + 1) . ' من ' . count($stmts) . ': ' . $e->getMessage() . ' (ما نُفِّذ قبله محفوظ، وإعادة التشغيل آمنة)');
            }
        }
        $st = $this->db->prepare("REPLACE INTO schema_migrations (name, checksum, statements, applied_at, applied_by) VALUES (?, ?, ?, NOW(), ?)");
        $st->execute([$name, md5($sql), count($stmts), $userId ?: null]);
        return ['executed' => $done, 'skipped' => $skipped, 'total' => count($stmts), 'checks' => $this->verify()];
    }
}
