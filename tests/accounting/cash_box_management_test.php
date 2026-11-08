<?php
declare(strict_types=1);

/**
 * اختبار تكامل لخدمة إدارة الخزائن وربط طرق الدفع.
 * يستخدم جداول TEMPORARY داخل اتصال CI ولا يغير جداول قاعدة البيانات المستوردة.
 */

$dsn = getenv('ACCOUNTING_TEST_DSN');
$user = getenv('ACCOUNTING_TEST_DB_USER') ?: 'root';
$password = getenv('ACCOUNTING_TEST_DB_PASSWORD') ?: '';
if (!$dsn) {
    fwrite(STDERR, "FAIL: ACCOUNTING_TEST_DSN is required.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/inc/Classes/Accounting/CashBox.php';

function cashBoxAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cashBoxExpectFailure(callable $callback, string $message): Throwable
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

    $db->exec("CREATE TEMPORARY TABLE branches (
        BR_ID INT NOT NULL PRIMARY KEY,
        BR_NAME VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE accounts (
        id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        account_code VARCHAR(30) NOT NULL,
        account_name VARCHAR(150) NOT NULL,
        account_type VARCHAR(30) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        allow_posting TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE payment_methods (
        id INT NOT NULL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE cash_boxes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        account_id BIGINT UNSIGNED NOT NULL,
        box_name VARCHAR(100) NOT NULL,
        name VARCHAR(150) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE payment_method_accounts (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        payment_method_id INT NOT NULL,
        branch_id INT NOT NULL,
        account_id BIGINT UNSIGNED NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_payment_method_branch (payment_method_id, branch_id)
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE journal_entries (
        id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        branch_id INT NOT NULL,
        status VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB");

    $db->exec("CREATE TEMPORARY TABLE journal_entry_details (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id BIGINT UNSIGNED NOT NULL,
        account_id BIGINT UNSIGNED NOT NULL,
        cash_box_id BIGINT UNSIGNED DEFAULT NULL,
        debit DECIMAL(15,2) NOT NULL DEFAULT 0,
        credit DECIMAL(15,2) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");

    $db->exec("INSERT INTO branches (BR_ID, BR_NAME) VALUES (1, 'الفرع الرئيسي')");
    $db->exec("INSERT INTO accounts (id, account_code, account_name, account_type, is_active, allow_posting) VALUES
        (100, '1111', 'الخزينة الرئيسية', 'ASSET', 1, 1),
        (101, '1112', 'خزينة الفرع', 'ASSET', 1, 1),
        (200, '2111', 'الموردون', 'LIABILITY', 1, 1),
        (201, '1113', 'حساب غير نشط', 'ASSET', 0, 1),
        (202, '1114', 'حساب غير قابل للترحيل', 'ASSET', 1, 0)");
    $db->exec("INSERT INTO payment_methods (id, name, is_active) VALUES
        (1, 'نقدي', 1), (2, 'تحويل بنكي', 1), (3, 'طريقة موقوفة', 0)");

    $cashBoxes = new CashBox($db);
    $branches = $cashBoxes->listBranches();
    cashBoxAssert(count($branches) === 1 && (int)$branches[0]['id'] === 1, 'يجب عرض فروع الإدارة.');

    $postingAccounts = $cashBoxes->listPostingAccounts();
    cashBoxAssert(count($postingAccounts) === 2, 'يجب عرض حسابات الأصول النشطة القابلة للترحيل فقط.');

    $boxId = $cashBoxes->save(1, 0, 'الخزينة الرئيسية', 100, true);
    cashBoxAssert($boxId > 0, 'يجب إنشاء خزينة جديدة.');
    cashBoxAssert(count($cashBoxes->listForBranch(1)) === 1, 'يجب ظهور الخزينة في الفرع.');

    cashBoxExpectFailure(
        fn() => $cashBoxes->save(1, 0, 'خزينة مكررة', 100, true),
        'يجب منع استخدام حساب أستاذ واحد لأكثر من خزينة في نفس الفرع.'
    );
    cashBoxExpectFailure(
        fn() => $cashBoxes->save(1, 0, 'الخزينة الرئيسية', 101, true),
        'يجب منع تكرار اسم الخزينة داخل الفرع.'
    );
    cashBoxExpectFailure(
        fn() => $cashBoxes->save(1, 0, 'خزينة غير صالحة', 200, true),
        'يجب رفض حساب غير مصنف كأصل.'
    );

    $cashBoxes->setPaymentMethodMapping(1, 1, $boxId);
    $methodBoxes = $cashBoxes->listForPaymentMethod(1, 1);
    cashBoxAssert(count($methodBoxes) === 1 && (int)$methodBoxes[0]['id'] === $boxId, 'يجب إتاحة الخزينة لطريقة الدفع المرتبطة بها.');
    cashBoxAssert(count($cashBoxes->listForPaymentMethod(1, 2)) === 0, 'لا يجب إظهار خزائن نقدية للتحويل البنكي.');

    $methods = $cashBoxes->listPaymentMethods(1);
    cashBoxAssert(count($methods) === 1 && (int)$methods[0]['id'] === 1, 'إدارة الخزائن يجب أن تعرض طريقة الدفع النقدي فقط.');
    cashBoxAssert((int)$methods[0]['cash_box_id'] === $boxId, 'يجب عرض الخزينة الحالية في إعدادات طريقة الدفع.');
    cashBoxExpectFailure(fn() => $cashBoxes->setPaymentMethodMapping(1, 2, $boxId), 'لا يجوز ربط التحويل البنكي بخزينة نقدية.');

    $cashBoxes->save(1, $boxId, 'الخزينة بعد تعديل الحساب', 101, true);
    $methodBoxes = $cashBoxes->listForPaymentMethod(1, 1);
    cashBoxAssert((int)$methodBoxes[0]['account_id'] === 101, 'يجب نقل ربط طريقة الدفع عند تغيير حساب خزينة غير مستخدمة.');

    $db->exec("INSERT INTO journal_entries (id, branch_id, status) VALUES (1, 1, 'POSTED')");
    $detail = $db->prepare('INSERT INTO journal_entry_details (journal_entry_id, account_id, cash_box_id, debit, credit) VALUES (1, 101, ?, 100, 0)');
    $detail->execute([$boxId]);

    cashBoxExpectFailure(
        fn() => $cashBoxes->save(1, $boxId, 'الخزينة بعد تعديل الحساب', 100, true),
        'يجب منع تغيير حساب خزينة لها حركات تاريخية.'
    );
    cashBoxExpectFailure(
        fn() => $cashBoxes->save(1, $boxId, 'الخزينة بعد تعديل الحساب', 101, false),
        'يجب منع إيقاف خزينة لها رصيد غير مسوّى.'
    );

    $db->exec("INSERT INTO journal_entry_details (journal_entry_id, account_id, cash_box_id, debit, credit) VALUES (1, 101, {$boxId}, 0, 100)");
    $cashBoxes->save(1, $boxId, 'الخزينة بعد تعديل الحساب', 101, false);
    cashBoxAssert(count($cashBoxes->listForPaymentMethod(1, 1)) === 0, 'يجب إيقاف ربط طرق الدفع عند إيقاف الخزينة.');

    $cashBoxes->save(1, $boxId, 'الخزينة بعد تعديل الحساب', 101, true);
    cashBoxAssert(count($cashBoxes->listForPaymentMethod(1, 1)) === 1, 'إعادة تفعيل الخزينة تجعلها متاحة للاختيار دون استعادة الربط الافتراضي تلقائيًا.');

    $cashBoxes->setPaymentMethodMapping(1, 1, $boxId);
    cashBoxAssert(count($cashBoxes->listForPaymentMethod(1, 1)) === 1, 'يجب السماح بإعادة الربط يدويًا بعد التفعيل.');

    $cashBoxes->setPaymentMethodMapping(1, 1, 0);
    cashBoxAssert(count($cashBoxes->listForPaymentMethod(1, 1)) === 1, 'إلغاء الربط الافتراضي لا يمنع اختيار خزينة الفرع النشطة.');

    $secondBoxId = $cashBoxes->save(1, 0, 'خزينة ثانية', 100, true);
    $cashBoxes->setPaymentMethodMapping(1, 1, $boxId);
    $allBranchBoxes = $cashBoxes->listForPaymentMethod(1, 1);
    cashBoxAssert(count($allBranchBoxes) === 2, 'يجب إتاحة أكثر من خزينة نقدية نشطة داخل الفرع نفسه.');
    cashBoxAssert((int)$allBranchBoxes[0]['id'] === $boxId && (int)$allBranchBoxes[0]['is_default'] === 1,
        'الربط الافتراضي يحدد الاختيار الأول فقط ولا يمنع اختيار الخزائن الأخرى.');

    echo "PASS: Cash-box management and payment-method mapping integration checks completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
