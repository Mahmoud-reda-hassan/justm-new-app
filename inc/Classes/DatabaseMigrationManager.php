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
            'warning' => 'يتطلب استيراد شجرة الحسابات المعتمدة أولًا؛ الحسابان المستهدفان 2152 و5420.'
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
        'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql' => [
            'title' => 'ربط سطور اليومية بالخزينة الفعلية',
            'description' => 'إضافة مرجع اختياري للخزينة على سطر القيد دون تخمين الخزائن التاريخية.',
            'enabled' => true,
            'warning' => 'مرحلة بنيوية فقط؛ لا تعيّن خزائن للحركات القديمة ولا تغيّر مسارات القبض والصرف الحالية.'
        ],
        'PHASE24_FINANCIAL_VOUCHERS.sql' => [
            'title' => 'مستندات القبض والصرف العامة',
            'description' => 'إنشاء مستندات قبض وصرف مرتبطة بالخزينة والفرع وقيد الأستاذ والحدث المالي.',
            'enabled' => true,
            'warning' => 'ينشئ مستندات المصدر فقط؛ الأرصدة تظل من الأستاذ العام. يتطلب اكتمال المرحلة 22.'
        ],
    ];

    public function __construct($db, string $rootPath)
    {
        $this->db = $db;
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function getEnvironment(): array
    {
        $environmentStatement = $this->db->query('SELECT DATABASE() AS database_name, VERSION() AS database_version');
        $row = $environmentStatement->fetch(PDO::FETCH_ASSOC);
        $environmentStatement->closeCursor();
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
            $warning = $meta['warning'];

            if ($row && $row['status'] === 'APPLIED' && !hash_equals((string)$row['checksum'], $checksum)) {
                $status = 'CHANGED';
            }

            if ($file === 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql') {
                $schemaState = $this->cashBoxReferenceSchemaState();
                if ($schemaState === 'COMPLETE' && !$row) {
                    $status = 'SCHEMA_PRESENT';
                    $warning = 'العمود والفهرس والعلاقة موجودة بالفعل في قاعدة التثبيت الحالية؛ لا حاجة لإعادة تنفيذ الترحيل.';
                } elseif ($schemaState === 'PARTIAL') {
                    $status = 'SCHEMA_DRIFT';
                    $warning = 'مخطط الخزينة غير مكتمل (عمود أو فهرس أو علاقة ناقصة). أوقف التنفيذ وافحص القاعدة قبل أي تعديل.';
                } elseif ($schemaState === 'BLOCKED') {
                    $status = 'BLOCKED';
                    $warning = 'يلزم وجود علاقة cash_boxes.branch_id مع branches.BR_ID (المرحلة 21 أو ما يعادلها) قبل تنفيذ المرحلة 22.';
                } elseif ($schemaState === 'MISSING' && $row && $row['status'] === 'APPLIED') {
                    $status = 'SCHEMA_DRIFT';
                    $warning = 'المرحلة مسجلة كمطبقة لكن مراجع الخزينة غير موجودة. لا تعِد تنفيذها تلقائيًا؛ افحص القاعدة.';
                }
            }

            if ($file === 'PHASE24_FINANCIAL_VOUCHERS.sql') {
                $schemaState = $this->financialVoucherSchemaState();
                if ($schemaState === 'COMPLETE' && !$row) {
                    $status = 'SCHEMA_PRESENT';
                    $warning = 'جداول مستندات القبض والصرف موجودة بالفعل في Installer؛ لا حاجة لإعادة تنفيذ الترحيل.';
                } elseif ($schemaState === 'PARTIAL') {
                    $status = 'SCHEMA_DRIFT';
                    $warning = 'جداول مستندات القبض والصرف موجودة بصورة جزئية أو بأعمدة ناقصة؛ يلزم فحص المخطط.';
                } elseif ($schemaState === 'BLOCKED') {
                    $status = 'BLOCKED';
                    $warning = 'يتطلب الترحيل وجود الفروع والخزائن وطرق الدفع والأستاذ العام مع اكتمال مرجع الخزينة في المرحلة 22.';
                } elseif ($schemaState === 'MISSING' && $row && $row['status'] === 'APPLIED') {
                    $status = 'SCHEMA_DRIFT';
                    $warning = 'المرحلة مسجلة كمطبقة لكن جداول المستندات غير موجودة؛ افحص القاعدة قبل أي إعادة تنفيذ.';
                }
            }

            $result[] = [
                'file' => $file,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'enabled' => (bool)$meta['enabled']
                    && !in_array($status, ['CHANGED', 'MISSING', 'SCHEMA_PRESENT', 'SCHEMA_DRIFT', 'BLOCKED'], true),
                'warning' => $warning,
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
        $this->assertPrerequisites($file);
        $statements = $this->splitSqlStatements($sql);
        if (!$statements) {
            throw new RuntimeException('ملف الترحيل لا يحتوي على أوامر SQL قابلة للتنفيذ.');
        }

        $lockStatement = $this->db->query("SELECT GET_LOCK('justm_database_migrations', 10)");
        $lock = $lockStatement->fetchColumn();
        $lockStatement->closeCursor();
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

            if ($file === 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql'
                && $this->cashBoxReferenceSchemaState() === 'COMPLETE') {
                $record = $this->db->prepare(
                    'INSERT INTO ' . self::LEDGER_TABLE . ' (migration_key, checksum, status, applied_by, started_at, finished_at, error_text, statement_count)
                     VALUES (?, ?, \'APPLIED\', ?, NOW(), NOW(), NULL, 0)
                     ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = \'APPLIED\',
                        applied_by = VALUES(applied_by), finished_at = NOW(), error_text = NULL, statement_count = 0'
                );
                $record->execute([$file, $checksum, $userId]);
                return [
                    'already_applied' => true,
                    'message' => 'مراجع الخزينة موجودة بالفعل في مخطط قاعدة البيانات؛ تم تسجيل المرحلة دون إعادة تنفيذ DDL.',
                    'statement_count' => 0,
                ];
            }

            if ($file === 'PHASE24_FINANCIAL_VOUCHERS.sql'
                && $this->financialVoucherSchemaState() === 'COMPLETE') {
                $record = $this->db->prepare(
                    'INSERT INTO ' . self::LEDGER_TABLE . ' (migration_key, checksum, status, applied_by, started_at, finished_at, error_text, statement_count)
                     VALUES (?, ?, \'APPLIED\', ?, NOW(), NOW(), NULL, 0)
                     ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = \'APPLIED\',
                        applied_by = VALUES(applied_by), finished_at = NOW(), error_text = NULL, statement_count = 0'
                );
                $record->execute([$file, $checksum, $userId]);
                return [
                    'already_applied' => true,
                    'message' => 'جداول مستندات القبض والصرف موجودة بالفعل؛ تم تسجيل المرحلة دون إعادة تنفيذ DDL.',
                    'statement_count' => 0,
                ];
            }

            $stmt = $this->db->prepare(
                'INSERT INTO ' . self::LEDGER_TABLE . ' (migration_key, checksum, status, applied_by, started_at, finished_at, error_text, statement_count)
                 VALUES (?, ?, \'RUNNING\', ?, NOW(), NULL, NULL, 0)
                 ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = \'RUNNING\', applied_by = VALUES(applied_by),
                    started_at = NOW(), finished_at = NULL, error_text = NULL, statement_count = 0'
            );
            $stmt->execute([$file, $checksum, $userId]);

            try {
                $executedCount = 0;
                foreach ($statements as $statement) {
                    $this->executeStatement($statement);
                    $executedCount++;
                }
                $update = $this->db->prepare(
                    'UPDATE ' . self::LEDGER_TABLE . ' SET status = \'APPLIED\', finished_at = NOW(), error_text = NULL, statement_count = ? WHERE migration_key = ?'
                );
                $update->execute([$executedCount, $file]);
            } catch (Throwable $e) {
                $update = $this->db->prepare(
                    'UPDATE ' . self::LEDGER_TABLE . ' SET status = \'FAILED\', finished_at = NOW(), error_text = ?, statement_count = ? WHERE migration_key = ?'
                );
                $update->execute([substr($e->getMessage(), 0, 4000), $executedCount ?? 0, $file]);
                throw new RuntimeException('فشل تنفيذ الترحيل: ' . $e->getMessage(), 0, $e);
            }
            return ['already_applied' => false, 'message' => 'تم تنفيذ الترحيل بنجاح.', 'statement_count' => count($statements)];
        } finally {
            $releaseStatement = $this->db->query("SELECT RELEASE_LOCK('justm_database_migrations')");
            $releaseStatement->fetchColumn();
            $releaseStatement->closeCursor();
        }
    }

    private function executeStatement(string $sql): void
    {
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\\b/i', ltrim($sql))) {
            $statement = $this->db->query($sql);
            if ($statement) {
                $statement->fetchAll(PDO::FETCH_ASSOC);
                $statement->closeCursor();
            }
            return;
        }
        $this->db->exec($sql);
    }

    private function assertPrerequisites(string $file): void
    {
        $required = [];
        if ($file === 'PHASE11_PAYMENT_METHOD_ACCOUNT_MAPPING.sql') {
            $required = ['accounts', 'payment_methods'];
        } elseif ($file === 'PHASE12_PARTNER_LEDGER_AND_ALLOCATIONS.sql') {
            $required = ['journal_entry_details'];
        } elseif ($file === 'PHASE14_DELIVERY_COLLECTION_AND_SETTLEMENT.sql') {
            $required = ['accounts'];
        } elseif ($file === 'PHASE15_ACCOUNTING_SCHEMA_ALIGNMENT.sql') {
            $required = ['orders', 'order_product', 'financial_transactions', 'inventory_movement', 'inventory_product_movement'];
        } elseif ($file === 'PHASE17_MARKETER_COMMISSION_ACCOUNTS.sql') {
            $required = ['accounts', 'account_settings'];
        } elseif ($file === 'PHASE19_SUPPLIER_ACCOUNT_SETTINGS.sql') {
            $required = ['accounts', 'account_settings'];
            $stmt = $this->db->query("SELECT COUNT(DISTINCT account_code) FROM accounts WHERE account_code IN ('2111','1162','1171')");
            $count = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($count !== 3) {
                throw new RuntimeException('لا يمكن تنفيذ المرحلة 19: يجب وجود الحسابات 2111 و1162 و1171 أولًا.');
            }
        } elseif ($file === 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql') {
            $required = ['journal_entry_details', 'cash_boxes', 'branches'];
        } elseif ($file === 'PHASE24_FINANCIAL_VOUCHERS.sql') {
            $required = ['financial_vouchers', 'financial_voucher_lines', 'branches', 'cash_boxes', 'payment_methods', 'accounts', 'journal_entries', 'accounting_events'];
        } elseif ($file === 'PHASE20_SUPPLIER_GL_CUTOVER.sql') {
            $required = ['purchases_payments', 'purchases_orders', 'suppliers', 'journal_entries', 'account_settings'];
            $stmt = $this->db->query("SELECT COUNT(DISTINCT setting_key) FROM account_settings WHERE setting_key IN ('SUPPLIERS','INVENTORY_IN_TRANSIT','SUPPLIER_ADVANCES')");
            $count = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($count !== 3) {
                throw new RuntimeException('لا يمكن تنفيذ المرحلة 20 قبل اكتمال ربط حسابات الموردين في المرحلة 19.');
            }
        }

        foreach ($required as $table) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $exists = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($exists !== 1) {
                throw new RuntimeException('لا يمكن تنفيذ الترحيل؛ الجدول المطلوب غير موجود: ' . $table);
            }
        }

        if ($file === 'PHASE17_MARKETER_COMMISSION_ACCOUNTS.sql') {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM accounts
                 WHERE (account_code = '2150' AND account_type = 'LIABILITY' AND is_active = 1 AND allow_posting = 0)
                    OR (account_code = '5400' AND account_type = 'EXPENSE' AND is_active = 1 AND allow_posting = 0)"
            );
            $parentCount = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($parentCount !== 2) {
                throw new RuntimeException(
                    'لا يمكن تنفيذ المرحلة 17: استورد شجرة الحسابات المعتمدة أولًا، وتأكد من وجود الحسابين الرئيسيين 2150 و5400 غير القابلين للترحيل.'
                );
            }
        }

        if ($file === 'PHASE24_FINANCIAL_VOUCHERS.sql') {
            if ($this->cashBoxReferenceSchemaState() !== 'COMPLETE') {
                throw new RuntimeException('لا يمكن تنفيذ المرحلة 24 قبل اكتمال مرجع الخزينة والفرع في المرحلة 22.');
            }
        }

        if ($file === 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql') {
            $schemaState = $this->cashBoxReferenceSchemaState();
            if ($schemaState === 'BLOCKED') {
                throw new RuntimeException('لا يمكن تنفيذ المرحلة 22 قبل وجود علاقة cash_boxes.branch_id مع branches.BR_ID (المرحلة 21 أو ما يعادلها).');
            }
            if ($schemaState === 'PARTIAL') {
                throw new RuntimeException('مخطط الخزينة جزئي؛ أوقف الترحيل وافحص العمود والفهرس والعلاقة قبل التنفيذ.');
            }
        }
    }

    /**
     * COMPLETE: العمود والفهرس وFK موجودة.
     * MISSING: لا يوجد أي جزء من إضافة المرحلة 22 ويمكن تنفيذها.
     * PARTIAL: بعض العناصر موجودة وبعضها مفقود؛ التنفيذ التلقائي غير آمن.
     * BLOCKED: الجداول أو علاقة ملكية الفرع المطلوبة غير موجودة.
     */
    private function cashBoxReferenceSchemaState(): string
    {
        $tables = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)'
        );
        $tables->execute(['cash_boxes', 'journal_entry_details']);
        $tablesExist = (int)$tables->fetchColumn() === 2;
        $tables->closeCursor();
        if (!$tablesExist) {
            return 'BLOCKED';
        }

        $branchRelation = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'cash_boxes\'
               AND COLUMN_NAME = \'branch_id\' AND REFERENCED_TABLE_NAME = \'branches\'
               AND REFERENCED_COLUMN_NAME = \'BR_ID\''
        );
        $branchRelation->execute();
        $branchRelationExists = (int)$branchRelation->fetchColumn() > 0;
        $branchRelation->closeCursor();
        if (!$branchRelationExists) {
            return 'BLOCKED';
        }

        $column = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details'
               AND COLUMN_NAME = 'cash_box_id'"
        );
        $columnExists = (int)$column->fetchColumn() > 0;
        $column->closeCursor();

        $index = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details'
               AND INDEX_NAME = 'idx_journal_detail_cash_box'"
        );
        $indexExists = (int)$index->fetchColumn() > 0;
        $index->closeCursor();

        $foreignKey = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details'
               AND COLUMN_NAME = 'cash_box_id' AND REFERENCED_TABLE_NAME = 'cash_boxes'
               AND REFERENCED_COLUMN_NAME = 'id'"
        );
        $foreignKeyExists = (int)$foreignKey->fetchColumn() > 0;
        $foreignKey->closeCursor();

        $presentCount = (int)$columnExists + (int)$indexExists + (int)$foreignKeyExists;
        if ($presentCount === 3) {
            return 'COMPLETE';
        }
        return $presentCount === 0 ? 'MISSING' : 'PARTIAL';
    }

    /**
     * COMPLETE: جدولا المستندات وأعمدتهما الأساسية موجودة.
     * MISSING: الجدولان غير موجودين ويمكن إنشاءهما.
     * PARTIAL: جدول واحد أو أعمدة أساسية ناقصة.
     * BLOCKED: متطلبات الأستاذ/الخزائن/الفروع غير مكتملة.
     */
    private function financialVoucherSchemaState(): string
    {
        $dependencies = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('branches','cash_boxes','payment_methods','accounts','journal_entries','accounting_events','journal_entry_details')"
        );
        $dependenciesExist = (int)$dependencies->fetchColumn() === 7;
        $dependencies->closeCursor();
        if (!$dependenciesExist || $this->cashBoxReferenceSchemaState() !== 'COMPLETE') {
            return 'BLOCKED';
        }

        $tables = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('financial_vouchers','financial_voucher_lines')"
        );
        $tableCount = (int)$tables->fetchColumn();
        $tables->closeCursor();
        if ($tableCount === 0) {
            return 'MISSING';
        }
        if ($tableCount !== 2) {
            return 'PARTIAL';
        }

        $requiredColumns = [
            'financial_vouchers' => ['voucher_no','voucher_type','voucher_subtype','branch_id','cash_box_id','payment_method_id','voucher_date','total_amount','status','journal_entry_id','accounting_event_id','idempotency_key','payload_hash','created_by'],
            'financial_voucher_lines' => ['voucher_id','line_no','account_id','partner_type','partner_id','amount'],
        ];
        foreach ($requiredColumns as $tableName => $columns) {
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN (' . $placeholders . ')'
            );
            $stmt->execute(array_merge([$tableName], $columns));
            $found = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($found !== count($columns)) {
                return 'PARTIAL';
            }
        }

        return 'COMPLETE';
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
        $exists = (int)$stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        return $exists;
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
        $stmt->closeCursor();
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
