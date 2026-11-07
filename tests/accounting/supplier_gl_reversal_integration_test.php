<?php
declare(strict_types=1);

/**
 * اختبار تكاملي حقيقي لمسار دفعات ومقدمات المورد على MariaDB مؤقتة داخل GitHub Actions.
 * ينشئ مخططًا مصغرًا مطابقًا للأعمدة التي تستخدمها الخدمات، ولا يتصل بقاعدة المشروع أو الإنتاج.
 */
$dsn = getenv('ACCOUNTING_TEST_DSN');
$user = getenv('ACCOUNTING_TEST_DB_USER') ?: 'root';
$password = getenv('ACCOUNTING_TEST_DB_PASSWORD') ?: '';
if (!$dsn) {
    fwrite(STDERR, "FAIL: ACCOUNTING_TEST_DSN is required.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/inc/Classes/Accounting/SupplierAccountingManager.php';

function supplierAssertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true)
            . ' actual=' . var_export($actual, true));
    }
}

function supplierExpectFailure(callable $callback, string $message): Throwable
{
    try {
        $callback();
    } catch (Throwable $e) {
        return $e;
    }
    throw new RuntimeException($message);
}

try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $tables = [
        'accounting_events', 'supplier_gl_advance_applications', 'supplier_gl_payment_allocations',
        'supplier_gl_payments', 'purchases_payments', 'purchases_return_orders',
        'inventory_movement', 'purchases_orders', 'suppliers', 'payment_method_accounts',
        'payment_methods', 'account_settings', 'journal_entry_details', 'journal_entries',
        'financial_years', 'accounts',
    ];
    foreach ($tables as $table) {
        $db->exec('DROP TABLE IF EXISTS ' . $table);
    }

    $db->exec("CREATE TABLE accounts (
        id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        allow_posting TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO accounts (id) VALUES (2111),(1171),(1010)");
    $db->exec("CREATE TABLE account_settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        account_id BIGINT UNSIGNED NOT NULL
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO account_settings (setting_key,account_id)
        VALUES ('SUPPLIERS',2111),('SUPPLIER_ADVANCES',1171)");
    $db->exec("CREATE TABLE payment_methods (
        id INT NOT NULL PRIMARY KEY,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO payment_methods (id) VALUES (1)");
    $db->exec("CREATE TABLE payment_method_accounts (
        payment_method_id INT NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        account_id BIGINT UNSIGNED NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (payment_method_id,branch_id)
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO payment_method_accounts VALUES (1,1,1010,1)");
    $db->exec("CREATE TABLE suppliers (SUP_ID BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("INSERT INTO suppliers VALUES (7)");
    $db->exec("CREATE TABLE purchases_orders (
        ORDER_ID BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        SUPPLIER_ID BIGINT UNSIGNED NOT NULL,
        TOTAL_PRICE DECIMAL(18,2) NOT NULL,
        TOTAL_PAYMENTS DECIMAL(18,2) NOT NULL DEFAULT 0,
        STATUSE VARCHAR(30) NOT NULL DEFAULT 'DEFERRED'
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO purchases_orders (ORDER_ID,SUPPLIER_ID,TOTAL_PRICE,TOTAL_PAYMENTS)
        VALUES (1001,7,30,0),(1002,7,100,0)");
    $db->exec("CREATE TABLE purchases_return_orders (
        ORDER_ID BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        REFERANC BIGINT UNSIGNED NOT NULL,
        STATUSE VARCHAR(30) NOT NULL,
        TOTAL_PRICE DECIMAL(18,2) NOT NULL,
        TOTAL_PAYMENTS DECIMAL(18,2) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE inventory_movement (
        IM_ID BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        REFERENCE BIGINT UNSIGNED NOT NULL,
        IM_MOVE_TYPE VARCHAR(60) NOT NULL
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE purchases_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ORDER_ID BIGINT UNSIGNED NOT NULL,
        AMOUNT DECIMAL(18,2) NOT NULL,
        PAYMENT_METHOD INT NOT NULL,
        NOTE TEXT NULL,
        CREATED_BY BIGINT UNSIGNED NOT NULL,
        SUPPLIER_GL_PAYMENT_ID BIGINT UNSIGNED NULL,
        REVERSAL_JOURNAL_ENTRY_ID BIGINT UNSIGNED NULL
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE supplier_gl_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_key VARCHAR(64) NOT NULL UNIQUE,
        supplier_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        payment_method_id INT NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        journal_entry_id BIGINT UNSIGNED NOT NULL,
        note TEXT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reversal_journal_entry_id BIGINT UNSIGNED NULL
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE supplier_gl_payment_allocations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT UNSIGNED NOT NULL,
        supplier_id BIGINT UNSIGNED NOT NULL,
        purchase_order_id BIGINT UNSIGNED NULL,
        allocation_type ENUM('INVOICE','ADVANCE') NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE supplier_gl_advance_applications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        supplier_id BIGINT UNSIGNED NOT NULL,
        source_advance_allocation_id BIGINT UNSIGNED NOT NULL,
        purchase_order_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        journal_entry_id BIGINT UNSIGNED NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE financial_years (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        year_name VARCHAR(20) NOT NULL,
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        is_closed TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO financial_years (year_name,date_from,date_to)
        VALUES ('2026','2026-01-01','2026-12-31')");
    $db->exec("CREATE TABLE journal_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entry_no VARCHAR(40) NULL,
        fiscal_year BIGINT UNSIGNED NULL,
        branch_id BIGINT UNSIGNED NULL,
        entry_date DATETIME NULL,
        reference_type VARCHAR(60) NULL,
        reference_id BIGINT UNSIGNED NULL,
        description TEXT NULL,
        total_debit DECIMAL(18,2) NULL,
        total_credit DECIMAL(18,2) NULL,
        status VARCHAR(20) NOT NULL,
        posted_at DATETIME NULL,
        posted_by BIGINT UNSIGNED NULL,
        created_by BIGINT UNSIGNED NULL
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE journal_entry_details (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id BIGINT UNSIGNED NOT NULL,
        line_no INT NOT NULL,
        account_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NULL,
        store_id BIGINT UNSIGNED NULL,
        cost_center_id BIGINT UNSIGNED NULL,
        partner_type VARCHAR(30) NULL,
        partner_id BIGINT UNSIGNED NULL,
        reference_type VARCHAR(60) NULL,
        reference_id BIGINT UNSIGNED NULL,
        debit DECIMAL(18,2) NOT NULL DEFAULT 0,
        credit DECIMAL(18,2) NOT NULL DEFAULT 0,
        note TEXT NULL
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE accounting_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        idempotency_key VARCHAR(128) NOT NULL UNIQUE,
        event_type VARCHAR(60) NOT NULL,
        source_type VARCHAR(60) NOT NULL,
        source_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        event_date DATETIME NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        status ENUM('PROCESSING','POSTED') NOT NULL,
        journal_entry_id BIGINT UNSIGNED NULL UNIQUE,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        posted_at DATETIME NULL
    ) ENGINE=InnoDB");

    $manager = new SupplierAccountingManager($db);

    // صرف 30 على أمر شراء، و20 كمقدم غير مخصص.
    $payment = $manager->pay(7, 50.00, [
        ['orderId' => 1001, 'amount' => 30.00],
    ], 1, 1, 9, 'CI:SUPPLIER:PAY:001', 'اختبار تكاملي');
    supplierAssertSame(50.0, (float)$payment['total_amount'], 'Supplier payment total mismatch.');
    supplierAssertSame(20.0, (float)$payment['advance_amount'], 'Supplier advance amount mismatch.');
    supplierAssertSame(30.0, (float)$db->query('SELECT TOTAL_PAYMENTS FROM purchases_orders WHERE ORDER_ID=1001')->fetchColumn(),
        'Invoice payment must update purchase order totals.');
    supplierAssertSame(1, (int)$db->query('SELECT COUNT(*) FROM purchases_payments WHERE SUPPLIER_GL_PAYMENT_ID=' . (int)$payment['payment_id'])->fetchColumn(),
        'Operational payment row must be linked to the GL payment.');

    // يجب منع عكس أصل المقدم قبل عكس التطبيق الذي استهلكه.
    $application = $manager->applyAdvances(7, [], 'AUTO', 1, 9, 'CI:SUPPLIER:APP:001');
    supplierAssertSame(20.0, (float)$application['applied_amount'], 'Advance application amount mismatch.');
    supplierAssertSame(20.0, (float)$db->query('SELECT TOTAL_PAYMENTS FROM purchases_orders WHERE ORDER_ID=1002')->fetchColumn(),
        'Advance application must update the target purchase order.');
    supplierExpectFailure(function () use ($manager, $payment): void {
        $manager->reversePayment((int)$payment['payment_id'], 9, 'يجب رفض العكس قبل عكس التطبيق');
    }, 'Payment reversal must be blocked while its advance application is active.');

    // عكس التطبيق يعيد فتح المستحق على أمر الشراء، ثم يسمح بعكس مصدر المقدم.
    $applicationReversal = $manager->reverseAdvanceApplication((int)$application['journal_entry_id'], 9, 'اختبار عكس التطبيق');
    supplierAssertSame('REVERSED', (string)$db->query(
        'SELECT status FROM journal_entries WHERE id=' . (int)$application['journal_entry_id'])->fetchColumn(),
        'Advance application journal must be reversed.');
    supplierAssertSame(0.0, (float)$db->query('SELECT TOTAL_PAYMENTS FROM purchases_orders WHERE ORDER_ID=1002')->fetchColumn(),
        'Reversing advance application must restore the order due.');
    supplierAssertSame(1, (int)$db->query('SELECT COUNT(*) FROM supplier_gl_advance_applications WHERE journal_entry_id=' . (int)$application['journal_entry_id'])->fetchColumn(),
        'Reversal must preserve advance application history.');

    $paymentReversal = $manager->reversePayment((int)$payment['payment_id'], 9, 'اختبار عكس الدفعة');
    supplierAssertSame('REVERSED', (string)$db->query(
        'SELECT status FROM journal_entries WHERE id=' . (int)$payment['journal_entry_id'])->fetchColumn(),
        'Supplier payment journal must be reversed.');
    supplierAssertSame(0.0, (float)$db->query('SELECT TOTAL_PAYMENTS FROM purchases_orders WHERE ORDER_ID=1001')->fetchColumn(),
        'Reversing supplier payment must restore invoice due.');
    supplierAssertSame((int)$paymentReversal['reversal_journal_entry_id'], (int)$db->query(
        'SELECT reversal_journal_entry_id FROM supplier_gl_payments WHERE id=' . (int)$payment['payment_id'])->fetchColumn(),
        'GL payment must link to its reversal journal.');
    supplierAssertSame(1, (int)$db->query('SELECT COUNT(*) FROM purchases_payments WHERE supplier_gl_payment_id='
        . (int)$payment['payment_id'] . ' AND reversal_journal_entry_id=' . (int)$paymentReversal['reversal_journal_entry_id'])->fetchColumn(),
        'Operational payment row must link to its reversal journal.');

    supplierExpectFailure(function () use ($manager, $payment): void {
        $manager->reversePayment((int)$payment['payment_id'], 9, 'عكس مكرر');
    }, 'A supplier payment must not be reversed twice.');
    supplierExpectFailure(function () use ($manager, $application): void {
        $manager->reverseAdvanceApplication((int)$application['journal_entry_id'], 9, 'عكس مكرر');
    }, 'An advance application must not be reversed twice.');

    echo "PASS: supplier payment posts invoice and advance portions and synchronizes operational totals.\n";
    echo "PASS: advance application consumes the source advance and updates the target purchase order.\n";
    echo "PASS: payment reversal is blocked until active advance applications are reversed.\n";
    echo "PASS: coordinated application/payment reversals restore order balances and preserve history.\n";
    echo "PASS: duplicate supplier reversals are rejected.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
