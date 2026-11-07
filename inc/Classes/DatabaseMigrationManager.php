<?php
/**
 * إدارة ترحيلات قاعدة البيانات المعتمدة داخل التطبيق.
 * لا يقبل أسماء ملفات أو مسارات من المستخدم؛ القائمة ثابتة داخل الكود.
 */
class DatabaseMigrationManager
{
    private $db;
    private $rootPath;
    private const LEDGER_TABLE = 'app_schema_migrations';

    private const MIGRATIONS = [
        'PHASE11_PAYMENT_METHOD_ACCOUNT_MAPPING.sql' => [
            'title' => 'ربط طرق الدفع بحسابات الأستاذ',
            'description' => 'إنشاء جدول ربط طريقة الدفع بحساب الأستاذ لكل فرع.',
            'enabled' => true,
            'warning' => 'بعد التنفيذ يجب ضبط حساب كل طريقة دفع/فرع من شاشة إعدادات الحسابات.'
        ],
        'PHASE12_PARTNER_LEDGER_AND_ALLOCATIONS.sql' => [
            'title' => 'تخصيصات دفتر أستاذ الأطراف',
            'description' => 'إنشاء جدول تخصيصات سطور المدين والدائن.',
            'enabled' => true,
            'warning' => 'إنشاء جدول جديد فقط؛ لا ينقل أرصدة partners_balance القديمة.'
        ],
        'PHASE13_SALES_RETURNS_ACCOUNT.sql' => [
            'title' => 'حساب مردودات المبيعات',
            'description' => 'مرحلة تحتاج مراجعة محاسبية قبل التنفيذ.',
            'enabled' => false,
            'warning' => 'التنفيذ الآلي موقوف: الملف يحتوي معاملة مفتوحة ويستخدم parent_id ثابتًا (16). يجب مراجعة الحساب والنتيجة أولًا.'
        ],
        'PHASE14_DELIVERY_COLLECTION_AND_SETTLEMENT.sql' => [
            'title' => 'تحصيلات العملاء وتسوية عهدة المندوب',
            'description' => 'إنشاء جداول التحصيل والتسوية والعمولات.',
            'enabled' => true,
            'warning' => 'بعد التنفيذ يلزم ضبط حسابات العهدة والعمولات من إعدادات النظام.'
        ],
        'PHASE15_ACCOUNTING_SCHEMA_ALIGNMENT.sql' => [
            'title' => 'مواءمة مخطط الأستاذ العام والمخزون',
            'description' => 'إضافة أعمدة ربط القيود والتكلفة ومعرف حركة المخزون.',
            'enabled' => true,
            'warning' => 'تغييرات بنيوية غير هدامة. خذ نسخة احتياطية قبل التنفيذ.'
        ],
        'PHASE16_SUPPLIER_PAYMENT_MIXED_ALLOCATION.sql' => [
            'title' => 'تخصيص دفعات الموردين',
            'description' => 'إنشاء جدول توزيع الدفعات بين الفواتير والمقدم.',
            'enabled' => true,
            'warning' => 'إنشاء جدول جديد دون حذف بيانات قديمة.'
        ],
        'PHASE17_MARKETER_COMMISSION_ACCOUNTS.sql' => [
            'title' => 'حسابات عمولات المسوقين',
            'description' => 'إنشاء وربط حسابات مستحقات ومصروف عمولات المسوقين.',
            'enabled' => true,
            'warning' => 'يراجع الحسابان 2152 و5230 قبل استخدام دورة صرف العمولات.'
        ],
        'PHASE18_CUSTOMER_RETURN_REFUNDS.sql' => [
            'title' => 'مستندات رد مبالغ مرتجعات العملاء',
            'description' => 'إنشاء جدول مستندات رد المبالغ من الأستاذ العام.',
            'enabled' => true,
            'warning' => 'إنشاء جدول جديد؛ لا يرحّل حركات قديمة.'
        ],
        'PHASE19_SUPPLIER_ACCOUNT_SETTINGS.sql' => [
            'title' => 'إعدادات حسابات الموردين',
            'description' => 'ربط حسابات الموردين والمخزون بالطريق والمقدمات.',
            'enabled' => true,
            'warning' => 'يتطلب وجود الحسابات 2111 و1162 و1171 في دليل الحسابات.'
        ],
        'PHASE20_SUPPLIER_GL_CUTOVER.sql' => [
            'title' => 'تحويل دورة الموردين إلى الأستاذ العام',
            'description' => 'إنشاء سجلات دفعات المورد وتخصيص المقدم وحقول العكس.',
            'enabled' => true,
            'warning' => 'نفّذ بعد التأكد من إعدادات المرحلة 19. لا ينقل الأرصدة التاريخية من partners_balance.'
        ],
    ];

    public function __construct($db, string $rootPath)
    {
        $this->db = $db;
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function getEnvironment(): array
    {
        $row = $this->db->query('SELECT DATABASE() AS database_name, VERSION() AS database_version')->fetch(PDO::FETCH_ASSOC);
        return [
            'database' => (string)($row['database_name'] ?? ''),
            'version' => (string)($row['database_version'] ?? ''),
            'ledger_exists' => $this->ledgerExists(),
        ];
    }

    public function listMigrations(): array
    {
        $applied = [];
        if ($this->ledgerExists()) {
            $stmt = $this->db->query('SELECT migration_key, checksum, status, applied_by, started_at, finished_at, error_text, statement_count FROM ' . self::LEDGER_TABLE);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $applied[$row['migration_key']] = $row;
            }
        }

        $result = [];
        foreach (self::MIGRATIONS as $file => $meta) {
            $path = $this->migrationPath($file);
            if (!is_file($path) || !is_readable($path)) {
                $result[] = ['file' => $file, 'title' => $meta['title'], 'description' => $meta['description'],
                    'enabled' => false, 'warning' => 'ملف الترحيل غير موجود أو غير قابل للقراءة.',
                    'status' => 'MISSING', 'checksum' => null, 'applied_at' => null, 'error' => null];
                continue;
            }
            $checksum = hash_file('sha256', $path);
            $row = $applied[$file] ?? null;
            $status = !$row ? 'PENDING' : (string)$row['status'];
            if ($row && $row['status'] === 'APPLIED' && !hash_equals((string)$row['checksum'], $checksum)) {
                $status = 'CHANGED';
            }
            $result[] = [
                'file' => $file,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'enabled' => (bool)$meta['enabled'] && $status !== 'CHANGED' && $status !== 'MISSING',
                'warning' => $meta['warning'],
                'status' => $status,
                'checksum' => $checksum,
                'applied_at' => $row['finished_at'] ?? null,
                'error' => $row['error_text'] ?? null,
                'statement_count' => isset($row['statement_count']) ? (int)$row['statement_count'] : null,
            ];
        }
        return $result;
    }

    public function preview(string $file): array
    {
        $meta = $this->getMigration($file);
        $path = $this->migrationPath($file);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('ملف الترحيل غير متاح.');
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('تعذر قراءة ملف الترحيل.');
        }
        return [
            'file' => $file,
            'title' => $meta['title'],
            'enabled' => (bool)$meta['enabled'],
            'warning' => $meta['warning'],
            'checksum' => hash('sha256', $sql),
            'statement_count' => count($this->splitSqlStatements($sql)),
            'sql' => $sql,
        ];
    }

    public function run(string $file, string $confirmation, int $userId): array
    {
        $meta = $this->getMigration($file);
        if (!$meta['enabled']) {
            throw new RuntimeException($meta['warning']);
        }
        if ($confirmation !== $file) {
            throw new RuntimeException('نص التأكيد لا يطابق اسم ملف الترحيل.');
        }
        if ($userId <= 0) {
            throw new RuntimeException('تعذر تحديد المستخدم المنفذ.');
        }

        $path = $this->migrationPath($file);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('ملف الترحيل غير متاح.');
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('تعذر قراءة ملف الترحيل.');
        }
        $checksum = hash('sha256', $sql);
        $statements = $this->splitSqlStatements($sql);
        if (!$statements) {
            throw new RuntimeException('ملف الترحيل لا يحتوي على أوامر SQL قابلة للتنفيذ.');
        }

        $lock = $this->db->query("SELECT GET_LOCK('justm_database_migrations', 10)")->fetchColumn();
        if ((string)$lock !== '1') {
            throw new RuntimeException('هناك عملية ترحيل أخرى قيد التنفيذ. حاول بعد قليل.');
        }

        try {
            $this->ensureLedger();
            $existing = $this->getLedgerRow($file);
            if ($existing && $existing['status'] === 'APPLIED') {
                if (!hash_equals((string)$existing['checksum'], $checksum)) {
                    throw new RuntimeException('تم تغيير ملف ترحيل سبق تنفيذه. لن يتم تشغيله مرة أخرى.');
                }
                return ['already_applied' => true, 'message' => 'هذا الترحيل منفذ بالفعل بنفس نسخة الملف.'];
            }
            if ($existing && $existing['status'] === 'APPLIED' && !hash_equals((string)$existing['checksum'], $checksum)) {
                throw new RuntimeException('بصمة ملف الترحيل لا تطابق النسخة المسجلة.');
            }

            $stmt = $this->db->prepare(
                'INSERT INTO ' . self::LEDGER_TABLE . ' (migration_key, checksum, status, applied_by, started_at, finished_at, error_text, statement_count)
                 VALUES (?, ?, \'RUNNING\', ?, NOW(), NULL, NULL, 0)
                 ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = \'RUNNING\', applied_by = VALUES(applied_by),
                    started_at = NOW(), finished_at = NULL, error_text = NULL, statement_count = 0'
            );
            $stmt->execute([$file, $checksum, $userId]);

            try {
                foreach ($statements as $statement) {
                    $this->db->exec($statement);
                }
                $update = $this->db->prepare(
                    'UPDATE ' . self::LEDGER_TABLE . ' SET status = \'APPLIED\', finished_at = NOW(), error_text = NULL, statement_count = ? WHERE migration_key = ?'
                );
                $update->execute([count($statements), $file]);
            } catch (Throwable $e) {
                $update = $this->db->prepare(
                    'UPDATE ' . self::LEDGER_TABLE . ' SET status = \'FAILED\', finished_at = NOW(), error_text = ?, statement_count = ? WHERE migration_key = ?'
                );
                $update->execute([substr($e->getMessage(), 0, 4000), count($statements), $file]);
                throw new RuntimeException('فشل تنفيذ الترحيل: ' . $e->getMessage(), 0, $e);
            }
            return ['already_applied' => false, 'message' => 'تم تنفيذ الترحيل بنجاح.', 'statement_count' => count($statements)];
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('justm_database_migrations')");
        }
    }

    private function getMigration(string $file): array
    {
        if (!isset(self::MIGRATIONS[$file])) {
            throw new InvalidArgumentException('ملف الترحيل المطلوب غير موجود ضمن القائمة المعتمدة.');
        }
        return self::MIGRATIONS[$file];
    }

    private function migrationPath(string $file): string
    {
        $this->getMigration($file);
        $base = realpath($this->rootPath . '/inc/Databases/migrations');
        if ($base === false) {
            throw new RuntimeException('مجلد ملفات الترحيل غير موجود.');
        }
        $path = realpath($base . DIRECTORY_SEPARATOR . $file);
        if ($path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
            return $base . DIRECTORY_SEPARATOR . '__missing__';
        }
        return $path;
    }

    private function ledgerExists(): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([self::LEDGER_TABLE]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function ensureLedger(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::LEDGER_TABLE . ' (
                migration_key VARCHAR(190) NOT NULL,
                checksum CHAR(64) NOT NULL,
                status ENUM(\'RUNNING\',\'APPLIED\',\'FAILED\') NOT NULL,
                applied_by BIGINT UNSIGNED NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                error_text TEXT NULL,
                statement_count INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (migration_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function getLedgerRow(string $file): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . self::LEDGER_TABLE . ' WHERE migration_key = ? LIMIT 1');
        $stmt->execute([$file]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * يقسم ملفات SQL المعتمدة إلى أوامر، مع احترام النصوص والاقتباسات والتعليقات.
     * ملفات DELIMITER / الإجراءات المخزنة غير مدعومة عمدًا.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                    $buffer .= ' ';
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                $buffer .= ' ';
                continue;
            }
            if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($char === '#') {
                $lineComment = true;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') $statements[] = $statement;
        if ($quote !== null || $blockComment) {
            throw new RuntimeException('ملف SQL يحتوي على اقتباس أو تعليق غير مكتمل.');
        }
        return $statements;
    }
}
