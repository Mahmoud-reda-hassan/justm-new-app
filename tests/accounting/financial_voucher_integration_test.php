<?php
declare(strict_types=1);

/**
 * اختبار تكامل مستندات القبض والصرف على مخطط مؤقت ومعزول.
 * يغطي الترحيل، تخصيص الأقدم، رصيد العميل الدائن، منع التكرار، ورد المورد والعكس.
 */
$dsn = getenv('ACCOUNTING_TEST_DSN');
$user = getenv('ACCOUNTING_TEST_DB_USER') ?: 'root';
$password = getenv('ACCOUNTING_TEST_DB_PASSWORD') ?: '';
if (!$dsn) {
    fwrite(STDERR, "FAIL: ACCOUNTING_TEST_DSN is required.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/inc/Classes/Accounting/FinancialVoucherManager.php';
require_once dirname(__DIR__, 2) . '/inc/Classes/Accounting/AccountingEvents.php';

class VoucherAccountingEventsTest extends AccountingEvents
{
    public function openCustomerCredits(int $clientId): array
    {
        return $this->getOpenCustomerCreditLines($clientId, true);
    }

    public function allocateSaleCredit(int $journalId, int $clientId, float $amount, array $creditLines): void
    {
        $this->allocateCustomerCreditToSale($journalId, $clientId, $amount, $creditLines);
    }
}

function voucherAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function voucherExpectFailure(callable $callback, string $message): Throwable
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

    $ddl = [
        "CREATE TEMPORARY TABLE branches (BR_ID INT PRIMARY KEY, BR_NAME VARCHAR(100) NOT NULL) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE accounts (
            id BIGINT UNSIGNED PRIMARY KEY,
            account_code VARCHAR(30) NOT NULL UNIQUE,
            account_name VARCHAR(150) NOT NULL,
            account_type VARCHAR(30) NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            allow_posting TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE account_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            account_id BIGINT UNSIGNED NOT NULL,
            description VARCHAR(255) NULL
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE financial_years (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            year_name VARCHAR(10) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            is_closed TINYINT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE journal_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_no VARCHAR(40) NULL,
            fiscal_year BIGINT UNSIGNED NOT NULL,
            branch_id INT NOT NULL,
            entry_date DATETIME NOT NULL,
            reference_type VARCHAR(60) NULL,
            reference_id BIGINT UNSIGNED NULL,
            description VARCHAR(500) NULL,
            total_debit DECIMAL(18,2) NOT NULL,
            total_credit DECIMAL(18,2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            posted_at DATETIME NULL,
            posted_by BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            KEY idx_journal_reference (reference_type, reference_id)
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE journal_entry_details (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_entry_id BIGINT UNSIGNED NOT NULL,
            line_no INT NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            branch_id INT NULL,
            cash_box_id BIGINT UNSIGNED NULL,
            store_id BIGINT UNSIGNED NULL,
            cost_center_id BIGINT UNSIGNED NULL,
            partner_type VARCHAR(20) NULL,
            partner_id BIGINT UNSIGNED NULL,
            reference_type VARCHAR(60) NULL,
            reference_id BIGINT UNSIGNED NULL,
            debit DECIMAL(18,2) NOT NULL DEFAULT 0,
            credit DECIMAL(18,2) NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            KEY idx_journal_detail_entry (journal_entry_id, line_no)
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE accounting_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idempotency_key VARCHAR(128) NOT NULL UNIQUE,
            event_type VARCHAR(60) NOT NULL,
            source_type VARCHAR(60) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            branch_id INT NOT NULL,
            event_date DATETIME NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            journal_entry_id BIGINT UNSIGNED NULL,
            posted_at DATETIME NULL
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE accounting_line_allocations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            debit_line_id BIGINT UNSIGNED NOT NULL,
            credit_line_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            allocation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            note VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            reversed_by BIGINT UNSIGNED NULL,
            reversed_at DATETIME NULL,
            reversal_reason VARCHAR(500) NULL,
            KEY idx_allocation_debit (debit_line_id, status),
            KEY idx_allocation_credit (credit_line_id, status)
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE client (CLIENT_ID BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE suppliers (SUP_ID BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE payment_methods (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE cash_boxes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            box_name VARCHAR(100) NOT NULL,
            name VARCHAR(100) NULL,
            is_active TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE payment_method_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_method_id INT NOT NULL,
            branch_id INT NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            UNIQUE KEY uq_payment_method_branch (payment_method_id, branch_id)
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE financial_vouchers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            voucher_no VARCHAR(40) NULL UNIQUE,
            voucher_type VARCHAR(20) NOT NULL,
            voucher_subtype VARCHAR(30) NOT NULL,
            branch_id INT NOT NULL,
            cash_box_id BIGINT UNSIGNED NOT NULL,
            payment_method_id INT NOT NULL,
            voucher_date DATETIME NOT NULL,
            partner_type VARCHAR(20) NULL,
            partner_id BIGINT UNSIGNED NULL,
            total_amount DECIMAL(18,2) NOT NULL,
            description VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL,
            journal_entry_id BIGINT UNSIGNED NULL UNIQUE,
            accounting_event_id BIGINT UNSIGNED NULL UNIQUE,
            reversal_event_id BIGINT UNSIGNED NULL UNIQUE,
            reversal_journal_entry_id BIGINT UNSIGNED NULL UNIQUE,
            idempotency_key VARCHAR(128) NOT NULL UNIQUE,
            payload_hash CHAR(64) NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            posted_at DATETIME NULL,
            reversed_by BIGINT UNSIGNED NULL,
            reversed_at DATETIME NULL,
            reversal_reason VARCHAR(500) NULL
        ) ENGINE=InnoDB",
        "CREATE TEMPORARY TABLE financial_voucher_lines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            voucher_id BIGINT UNSIGNED NOT NULL,
            line_no SMALLINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            partner_type VARCHAR(20) NULL,
            partner_id BIGINT UNSIGNED NULL,
            cost_center_id BIGINT UNSIGNED NULL,
            amount DECIMAL(18,2) NOT NULL,
            line_description VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_voucher_line (voucher_id, line_no)
        ) ENGINE=InnoDB",
    ];
    foreach ($ddl as $sql) $db->exec($sql);

    $db->exec("INSERT INTO branches (BR_ID, BR_NAME) VALUES (1, 'الفرع الرئيسي')");
    $db->exec("INSERT INTO accounts (id, account_code, account_name, account_type, is_active, allow_posting) VALUES
        (1, '1111', 'الخزينة الرئيسية', 'ASSET', 1, 1),
        (2, '1151', 'حسابات العملاء', 'ASSET', 1, 1),
        (3, '1152', 'أرصدة العملاء الدائنة', 'ASSET', 1, 1),
        (4, '2111', 'حسابات الموردين', 'LIABILITY', 1, 1),
        (5, '4130', 'إيرادات خدمات أخرى', 'REVENUE', 1, 1),
        (6, '5210', 'مصروفات تشغيلية', 'EXPENSE', 1, 1)");
    $db->exec("INSERT INTO account_settings (setting_key, account_id) VALUES
        ('CUSTOMERS', 2), ('CUSTOMER_CREDITS', 3), ('SUPPLIERS', 4)");
    $db->exec("INSERT INTO client (CLIENT_ID) VALUES (10), (11)");
    $db->exec("INSERT INTO suppliers (SUP_ID) VALUES (20)");
    $db->exec("INSERT INTO payment_methods (id, name, is_active) VALUES (1, 'نقدي', 1), (2, 'تحويل بنكي', 1)");
    $db->exec("INSERT INTO cash_boxes (id, branch_id, account_id, box_name, name, is_active) VALUES (1, 1, 1, 'الخزينة الرئيسية', 'الخزينة الرئيسية', 1)");
    $db->exec("INSERT INTO payment_method_accounts (payment_method_id, branch_id, account_id, is_active) VALUES (1, 1, 1, 1)");

    $manager = new FinancialVoucherManager($db);

    // فاتورة عميل مدينة بقيمة 300.
    $invoice = (new JournalManager($db))->create([
        'branch_id' => 1, 'entry_date' => '2026-11-01 10:00:00',
        'reference_type' => 'TEST_SALE', 'reference_id' => 1,
        'description' => 'فاتورة اختبار', 'created_by' => 7, 'status' => 'POSTED'
    ]);
    $invoice->addLine(['account_id' => 2, 'partner_type' => 'CLIENT', 'partner_id' => 10, 'debit' => 300]);
    $invoice->addLine(['account_id' => 5, 'credit' => 300]);
    $invoiceId = $invoice->post();
    $invoiceDebitLine = (int)$db->query("SELECT id FROM journal_entry_details WHERE journal_entry_id = {$invoiceId} AND account_id = 2")->fetchColumn();

    // تحصيل 500: يسدد 300 من أقدم دين، ويثبت 200 رصيدًا دائنًا للعميل في 1152.
    $receiptInput = [
        'voucher_subtype' => 'CUSTOMER_RECEIPT',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:00:00',
        'partner_id' => 10, 'amount' => 500, 'description' => 'تحصيل عميل وايداع رصيد دائن',
        'idempotency_key' => 'TEST-CUSTOMER-RECEIPT-1', 'created_by' => 7
    ];
    $receipt = $manager->post($receiptInput);
    voucherAssert($receipt['status'] === 'POSTED', 'يجب ترحيل سند تحصيل العميل.');
    $voucherId = (int)$receipt['voucher_id'];
    $receiptLines = $db->prepare('SELECT account_id, amount FROM financial_voucher_lines WHERE voucher_id = ? ORDER BY line_no');
    $receiptLines->execute([$voucherId]);
    $receiptLineRows = $receiptLines->fetchAll(PDO::FETCH_ASSOC);
    voucherAssert(count($receiptLineRows) === 2, 'يجب تقسيم تحصيل العميل إلى بندين عند وجود زيادة.');
    voucherAssert((int)$receiptLineRows[0]['account_id'] === 2 && (float)$receiptLineRows[0]['amount'] === 300.0, 'يجب تخصيص الجزء الأول لمديونية العميل 1151.');
    voucherAssert((int)$receiptLineRows[1]['account_id'] === 3 && (float)$receiptLineRows[1]['amount'] === 200.0, 'يجب تسجيل الزيادة في رصيد العميل الدائن 1152.');
    voucherAssert(abs($manager->getPartnerBalance('CLIENT', 10)) < 0.001, 'يجب إغلاق مديونية العميل بعد التحصيل.');
    voucherAssert(abs($manager->getCustomerCreditBalance(10) - 200.0) < 0.001, 'يجب بقاء رصيد العميل الدائن 200.');
    $allocationCount = (int)$db->query("SELECT COUNT(*) FROM accounting_line_allocations WHERE status = 'ACTIVE'")->fetchColumn();
    voucherAssert($allocationCount === 1, 'يجب إنشاء تخصيص واحد للمديونية المسددة.');

    // تطبيق رصيد العميل الدائن على بيع جديد، مع تخصيصه للمصدر الأصلي FIFO.
    $_SESSION = ['USER_ID' => 7];
    $creditEvents = new VoucherAccountingEventsTest($db);
    $openCustomerCredits = $creditEvents->openCustomerCredits(10);
    voucherAssert(count($openCustomerCredits) === 1 && abs((float)$openCustomerCredits[0]['credit'] - 200.0) < 0.001,
        'يجب أن يكون سند الائتمان الأصلي متاحًا قبل استخدامه في البيع.');
    $creditSale = (new JournalManager($db))->create([
        'branch_id' => 1, 'entry_date' => '2026-11-08 09:05:00',
        'reference_type' => 'TEST_CREDIT_SALE', 'reference_id' => 3001,
        'description' => 'بيع باستخدام رصيد عميل', 'created_by' => 7, 'status' => 'POSTED'
    ]);
    $creditSale->addLine(['account_id' => 2, 'partner_type' => 'CLIENT', 'partner_id' => 10, 'debit' => 100]);
    $creditSale->addLine(['account_id' => 5, 'credit' => 100]);
    $creditSale->addLine(['account_id' => 3, 'partner_type' => 'CLIENT', 'partner_id' => 10, 'debit' => 100]);
    $creditSale->addLine(['account_id' => 2, 'partner_type' => 'CLIENT', 'partner_id' => 10, 'credit' => 100]);
    $creditSaleJournalId = $creditSale->post();
    $creditEvents->allocateSaleCredit($creditSaleJournalId, 10, 100, $openCustomerCredits);
    voucherAssert(abs($manager->getCustomerCreditBalance(10) - 100.0) < 0.001,
        'استخدام 100 من الرصيد الدائن يجب أن يترك 100 متاحًا.');
    $remainingCreditLines = $creditEvents->openCustomerCredits(10);
    $remainingCredit = array_sum(array_map(
        fn($line) => (float)$line['credit'] - (float)$line['allocated_amount'],
        $remainingCreditLines
    ));
    voucherAssert(abs($remainingCredit - 100.0) < 0.001, 'يجب أن يتبقى 100 فقط غير مخصص من سند الائتمان الأصلي.');

    // عكس البيع يعكس التخصيص ويعيد الرصيد الدائن الأصلي دون احتساب سطر القيد العكسي كمصدر جديد.
    (new JournalManager($db))->reverse($creditSaleJournalId, 'عكس بيع اختبار', 99);
    voucherAssert(abs($manager->getCustomerCreditBalance(10) - 200.0) < 0.001,
        'عكس البيع يجب أن يعيد كامل الرصيد الدائن للعميل.');
    $restoredCreditLines = $creditEvents->openCustomerCredits(10);
    $restoredCredit = array_sum(array_map(
        fn($line) => (float)$line['credit'] - (float)$line['allocated_amount'],
        $restoredCreditLines
    ));
    voucherAssert(abs($restoredCredit - 200.0) < 0.001,
        'يجب ألا يُحسب سطر قيد العكس كمصدر ائتمان إضافي.');

    // إعادة الطلب بالمفتاح نفسه لا تنشئ قيدًا أو مستندًا جديدًا.
    $retry = $manager->post($receiptInput);
    voucherAssert(!empty($retry['already_posted']) && (int)$retry['voucher_id'] === $voucherId, 'إعادة نفس طلب التحصيل يجب أن تعيد المستند السابق.');
    $voucherCount = (int)$db->query('SELECT COUNT(*) FROM financial_vouchers')->fetchColumn();
    voucherAssert($voucherCount === 1, 'منع التكرار يجب ألا ينشئ مستندًا ثانيًا.');

    // عميل آخر بلا مديونية: كامل المبلغ يصبح رصيدًا دائنًا.
    $creditOnly = $manager->post([
        'voucher_subtype' => 'CUSTOMER_RECEIPT',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:10:00',
        'partner_id' => 11, 'amount' => 75, 'description' => 'رصيد عميل مقدم',
        'idempotency_key' => 'TEST-CUSTOMER-CREDIT-ONLY', 'created_by' => 7
    ]);
    voucherAssert(abs($manager->getCustomerCreditBalance(11) - 75.0) < 0.001, 'يجب تسجيل كامل المبلغ كرصيد دائن إذا لم توجد مديونية.');

    // مورد له رصيد مدين مفتوح 100 نتيجة مرتجع معتمد.
    $supplierReturn = (new JournalManager($db))->create([
        'branch_id' => 1, 'entry_date' => '2026-11-02 10:00:00',
        'reference_type' => 'TEST_SUPPLIER_RETURN', 'reference_id' => 20,
        'description' => 'مرتجع مورد اختبار', 'created_by' => 7, 'status' => 'POSTED'
    ]);
    $supplierReturn->addLine(['account_id' => 4, 'partner_type' => 'SUPPLIER', 'partner_id' => 20, 'debit' => 100]);
    $supplierReturn->addLine(['account_id' => 6, 'credit' => 100]);
    $supplierReturnId = $supplierReturn->post();

    $supplierRefund = $manager->post([
        'voucher_subtype' => 'SUPPLIER_REFUND',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:20:00',
        'partner_id' => 20, 'amount' => 40, 'description' => 'استلام رد مورد',
        'idempotency_key' => 'TEST-SUPPLIER-REFUND', 'created_by' => 7
    ]);
    voucherAssert(abs($manager->getPartnerBalance('SUPPLIER', 20) - 60.0) < 0.001, 'رد المورد يجب أن يقلل الرصيد المدين من 100 إلى 60.');
    voucherExpectFailure(fn() => $manager->post([
        'voucher_subtype' => 'SUPPLIER_REFUND',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:21:00',
        'partner_id' => 20, 'amount' => 100, 'description' => 'رد أكبر من المستحق',
        'idempotency_key' => 'TEST-SUPPLIER-REFUND-TOO-MUCH', 'created_by' => 7
    ]), 'يجب رفض رد مورد يتجاوز الرصيد المدين المفتوح.');

    // سند قبض عام وسند صرف عام يرحلان إلى حساب الإيراد/المصروف المختار.
    $generalReceipt = $manager->post([
        'voucher_subtype' => 'GENERAL_RECEIPT',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:30:00',
        'lines' => [['account_id' => 5, 'amount' => 25, 'line_description' => 'إيراد خدمة']],
        'description' => 'سند قبض عام', 'idempotency_key' => 'TEST-GENERAL-RECEIPT', 'created_by' => 7
    ]);
    $generalPayment = $manager->post([
        'voucher_subtype' => 'GENERAL_PAYMENT',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 1,
        'voucher_date' => '2026-11-08 09:40:00',
        'lines' => [['account_id' => 6, 'amount' => 10, 'line_description' => 'مصروف تشغيلي']],
        'description' => 'سند صرف عام', 'idempotency_key' => 'TEST-GENERAL-PAYMENT', 'created_by' => 7
    ]);
    voucherAssert($generalReceipt['status'] === 'POSTED' && $generalPayment['status'] === 'POSTED', 'يجب ترحيل سندي القبض والصرف العام.');

    voucherExpectFailure(fn() => $manager->post([
        'voucher_subtype' => 'GENERAL_PAYMENT',
        'branch_id' => 1, 'cash_box_id' => 1, 'payment_method_id' => 2,
        'voucher_date' => '2026-11-08 09:50:00',
        'lines' => [['account_id' => 6, 'amount' => 10]],
        'description' => 'طريقة دفع غير نقدية قبل اكتمال البنوك',
        'idempotency_key' => 'TEST-NON-CASH', 'created_by' => 7
    ]), 'يجب منع استخدام التحويل البنكي قبل اكتمال الحسابات البنكية.');

    // عكس سند العميل يعكس القيد ويعيد فتح المديونية ويزيل أثر التخصيص والرصيد الدائن.
    $reversal = $manager->reverse($voucherId, 'اختبار عكس سند العميل', 99);
    voucherAssert($reversal['status'] === 'REVERSED', 'يجب عكس سند القبض.');
    voucherAssert(abs($manager->getPartnerBalance('CLIENT', 10) - 300.0) < 0.001, 'عكس التحصيل يجب أن يعيد مديونية العميل.');
    voucherAssert(abs($manager->getCustomerCreditBalance(10)) < 0.001, 'عكس التحصيل يجب أن يلغي الرصيد الدائن المثبت.');
    $reversedAllocationCount = (int)$db->query("SELECT COUNT(*) FROM accounting_line_allocations WHERE status = 'REVERSED'")->fetchColumn();
    voucherAssert($reversedAllocationCount === 2, 'عكس البيع وسند التحصيل يجب أن يعكسا تخصيصاتهما.');
    $reversalJournal = $db->prepare('SELECT created_by FROM journal_entries WHERE id = ?');
    $reversalJournal->execute([(int)$reversal['reversal_journal_entry_id']]);
    voucherAssert((int)$reversalJournal->fetchColumn() === 99, 'يجب تسجيل المستخدم الذي نفذ العكس.');
    $retryReverse = $manager->reverse($voucherId, 'اختبار عكس سند العميل', 99);
    voucherAssert(!empty($retryReverse['already_reversed']), 'إعادة طلب العكس لا تنشئ قيد عكس آخر.');

    echo "PASS: GL financial voucher integration checks completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
