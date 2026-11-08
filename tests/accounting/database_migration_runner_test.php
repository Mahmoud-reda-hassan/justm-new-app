<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/inc/Classes/DatabaseMigrationManager.php';

$dsn = getenv('ACCOUNTING_SCHEMA_DSN');
$user = getenv('ACCOUNTING_SCHEMA_DB_USER');
$password = getenv('ACCOUNTING_SCHEMA_DB_PASSWORD');
if (!$dsn || $user === false || $password === false) {
    throw new RuntimeException('ACCOUNTING_SCHEMA_DSN and database credentials are required.');
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$root = dirname(__DIR__, 2);
$manager = new DatabaseMigrationManager($pdo, $root);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$userPermission = (string)file_get_contents($root . '/inc/Classes/user_permission.php');
$sidebar = (string)file_get_contents($root . '/inc/template/SidebarMenu.php');
$controller = (string)file_get_contents($root . '/inc/controller/databaseMigrations.control.php');
$assert(strpos($userPermission, '$_GET["Go"] === "DatabaseMigrations"') !== false, 'Admin migration screen route is missing.');
$assert(strpos($sidebar, '?Go=DatabaseMigrations') !== false, 'Admin migration menu link is missing.');
$assert(strpos($controller, "hash_equals(\$sessionToken, \$requestToken)") !== false, 'Migration endpoint must validate its CSRF token.');
$assert(strpos($controller, "ADMIN_TYPE") !== false && strpos($controller, "testar.just-m.net") !== false,
    'Migration endpoint must restrict execution to Admin on the test host.');

$splitter = new ReflectionMethod(DatabaseMigrationManager::class, 'splitSqlStatements');
$splitter->setAccessible(true);
$parts = $splitter->invoke($manager, "-- heading\nSELECT 'a;b', \"c;d\", `e;f`; /* comment ; */ SELECT 2; # tail\n");
$assert(count($parts) === 2, 'SQL parser must preserve semicolons inside strings and ignore comments.');

$environment = $manager->getEnvironment();
$assert(stripos((string)$environment['database'], 'test') !== false, 'Migration runner integration test must use a test database.');

// Force the two first CREATE TABLE migrations through their real creation path.
$pdo->exec('DROP TABLE IF EXISTS payment_method_accounts');
$pdo->exec('DROP TABLE IF EXISTS accounting_line_allocations');
// Phase 14 tables are left intact because other installer tables may reference them.

// Seed only the test database with the three chart-of-accounts prerequisites for phase 19.
$seedAccount = $pdo->prepare(
    "INSERT INTO accounts (account_code, account_name, account_type, normal_balance, account_level, is_active, allow_posting, system_account, is_system_created, description)
     SELECT ?, ?, ?, ?, 1, 1, 1, 1, 1, 'Test prerequisite for migration runner'
     WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE account_code = ?)"
);
foreach ([
    ['2111', 'Test suppliers payable', 'LIABILITY', 'CREDIT'],
    ['1162', 'Test inventory in transit', 'ASSET', 'DEBIT'],
    ['1171', 'Test supplier advances', 'ASSET', 'DEBIT'],
] as $account) {
    $seedAccount->execute([$account[0], $account[1], $account[2], $account[3], $account[0]]);
}

$seedParentAccount = $pdo->prepare(
    "INSERT INTO accounts (account_code, account_name, account_type, normal_balance, account_level, is_active, allow_posting, system_account, is_system_created, description)
     SELECT ?, ?, ?, ?, ?, 1, 0, 1, 1, 'Test parent account for approved chart seed'
     WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE account_code = ?)"
);
foreach ([
    ['2150', 'Test other liabilities', 'LIABILITY', 'CREDIT', 3],
    ['5400', 'Test marketing and sales expenses', 'EXPENSE', 'DEBIT', 2],
] as $account) {
    $seedParentAccount->execute([$account[0], $account[1], $account[2], $account[3], $account[4], $account[0]]);
}

$pdo->exec('DROP TABLE IF EXISTS supplier_payment_allocations');
$pdo->exec('DROP TABLE IF EXISTS marketer_commission_payments');
$pdo->exec('DROP TABLE IF EXISTS customer_refund_payments');
$pdo->exec('DROP TABLE IF EXISTS financial_voucher_lines');
$pdo->exec('DROP TABLE IF EXISTS financial_vouchers');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_advance_applications');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_payment_allocations');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_payments');

// Remove Phase 22's installer-provided objects so the migration runner test exercises
// the real additive migration path on this isolated CI database.
$pdo->exec('ALTER TABLE journal_entry_details DROP FOREIGN KEY fk_journal_detail_cash_box');
$pdo->exec('ALTER TABLE journal_entry_details DROP INDEX idx_journal_detail_cash_box');
$pdo->exec('ALTER TABLE journal_entry_details DROP COLUMN cash_box_id');

$migrations = $manager->listMigrations();
$assert(count($migrations) === 12, 'The approved migration registry must contain twelve numbered phases (11-20, 22 and 24).');
$byFile = [];
foreach ($migrations as $migration) $byFile[$migration['file']] = $migration;
$assert(isset($byFile['PHASE13_SALES_RETURNS_ACCOUNT.sql']) && !$byFile['PHASE13_SALES_RETURNS_ACCOUNT.sql']['enabled'],
    'Phase 13 must remain blocked until its fixed account parent and open transaction are reviewed.');
$assert(isset($byFile['PHASE22_JOURNAL_CASHBOX_REFERENCE.sql']) && $byFile['PHASE22_JOURNAL_CASHBOX_REFERENCE.sql']['enabled'],
    'Phase 22 must be available when the branch ownership prerequisite exists and its schema objects are absent.');
$assert(isset($byFile['PHASE24_FINANCIAL_VOUCHERS.sql']) && !$byFile['PHASE24_FINANCIAL_VOUCHERS.sql']['enabled'] && $byFile['PHASE24_FINANCIAL_VOUCHERS.sql']['status'] === 'BLOCKED',
    'Phase 24 must wait until Phase 22 cash-box references are complete.');

$wrongConfirmationRejected = false;
try {
    $manager->run('PHASE11_PAYMENT_METHOD_ACCOUNT_MAPPING.sql', 'wrong-file-name.sql', 1);
} catch (RuntimeException $e) {
    $wrongConfirmationRejected = true;
}
$assert($wrongConfirmationRejected, 'Migration execution must require exact typed filename confirmation.');

foreach ($migrations as $migration) {
    // Refresh prerequisites after each migration so later phases can become enabled
    // only after their dependencies (notably Phase 22 before Phase 24) are complete.
    $current = null;
    foreach ($manager->listMigrations() as $candidate) {
        if ($candidate['file'] === $migration['file']) {
            $current = $candidate;
            break;
        }
    }
    if (!$current || !$current['enabled']) continue;

    $result = $manager->run($migration['file'], $migration['file'], 1);
    $assert(!empty($result['message']), 'Migration must return an execution result: ' . $migration['file']);

    $repeat = $manager->run($migration['file'], $migration['file'], 1);
    $assert(!empty($repeat['already_applied']), 'An applied migration must not execute twice: ' . $migration['file']);
}

$paymentAccountType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_method_accounts' AND COLUMN_NAME = 'account_id'")->fetchColumn();
$allocationDebitType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_line_allocations' AND COLUMN_NAME = 'debit_line_id'")->fetchColumn();
$assert(strtolower((string)$paymentAccountType) === 'bigint(20) unsigned', 'Phase 11 account_id must match accounts.id as BIGINT UNSIGNED.');
$assert(strtolower((string)$allocationDebitType) === 'bigint(20) unsigned', 'Phase 12 debit_line_id must match journal_entry_details.id as BIGINT UNSIGNED.');
$cashBoxColumnType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details' AND COLUMN_NAME = 'cash_box_id'")->fetchColumn();
$cashBoxIndex = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details' AND INDEX_NAME = 'idx_journal_detail_cash_box'")->fetchColumn();
$cashBoxForeignKey = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journal_entry_details' AND COLUMN_NAME = 'cash_box_id' AND REFERENCED_TABLE_NAME = 'cash_boxes' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
$marketerParents = $pdo->query("SELECT account_code, id FROM accounts WHERE account_code IN ('2150','5400')")->fetchAll(PDO::FETCH_KEY_PAIR);
$marketerAccounts = $pdo->query("SELECT account_code, parent_id FROM accounts WHERE account_code IN ('2152','5420')")->fetchAll(PDO::FETCH_KEY_PAIR);
$assert(isset($marketerParents['2150'], $marketerParents['5400']), 'Phase 17 test parent accounts were not seeded.');
$assert(($marketerAccounts['2152'] ?? 0) == $marketerParents['2150'], 'Phase 17 must create/link marketer payable 2152 under 2150.');
$assert(($marketerAccounts['5420'] ?? 0) == $marketerParents['5400'], 'Phase 17 must create/link marketer commission expense 5420 under 5400.');
$marketerMappings = $pdo->query("SELECT COUNT(DISTINCT s.setting_key) FROM account_settings s INNER JOIN accounts a ON a.id = s.account_id WHERE (s.setting_key = 'MARKETER_COMMISSION_PAYABLE' AND a.account_code = '2152') OR (s.setting_key = 'MARKETER_COMMISSION_EXPENSE' AND a.account_code = '5420')")->fetchColumn();
$assert((int)$marketerMappings === 2, 'Phase 17 must map both marketer commission accounts to GL settings.');
$assert(strtolower((string)$cashBoxColumnType) === 'bigint(20) unsigned', 'Phase 22 cash_box_id must be BIGINT UNSIGNED.');
$assert($cashBoxIndex === 1, 'Phase 22 must create the cash-box lookup index.');
$assert($cashBoxForeignKey === 1, 'Phase 22 must create the cash-box foreign key.');
$voucherHeader = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_vouchers'")->fetchColumn();
$voucherLines = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_voucher_lines'")->fetchColumn();
$voucherCashBoxFk = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_vouchers' AND COLUMN_NAME = 'cash_box_id' AND REFERENCED_TABLE_NAME = 'cash_boxes' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
$voucherLineHeaderFk = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_voucher_lines' AND COLUMN_NAME = 'voucher_id' AND REFERENCED_TABLE_NAME = 'financial_vouchers' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
$voucherLineAccountFk = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_voucher_lines' AND COLUMN_NAME = 'account_id' AND REFERENCED_TABLE_NAME = 'accounts' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
$voucherReversalEventFk = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_vouchers' AND COLUMN_NAME = 'reversal_event_id' AND REFERENCED_TABLE_NAME = 'accounting_events' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
$assert($voucherHeader === 1 && $voucherLines === 1, 'Phase 24 must create both financial voucher tables.');
$assert($voucherCashBoxFk === 1, 'Phase 24 must link vouchers to their actual cash box.');
$assert($voucherLineHeaderFk === 1, 'Phase 24 must link voucher lines to their header.');
$assert($voucherLineAccountFk === 1, 'Phase 24 must link voucher lines to GL accounts.');
$assert($voucherReversalEventFk === 1, 'Phase 24 must retain the reversal event reference.');

// The full installer may already contain Phase 22's schema without a migration-ledger row.
// The runner must recognize that state and register it without trying to add the column again.
$deletePhase22Ledger = $pdo->prepare('DELETE FROM app_schema_migrations WHERE migration_key = ?');
$deletePhase22Ledger->execute(['PHASE22_JOURNAL_CASHBOX_REFERENCE.sql']);
$schemaPresent = null;
foreach ($manager->listMigrations() as $migration) {
    if ($migration['file'] === 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql') {
        $schemaPresent = $migration;
        break;
    }
}
$assert($schemaPresent !== null && $schemaPresent['status'] === 'SCHEMA_PRESENT' && !$schemaPresent['enabled'],
    'Phase 22 must be recognized as already present when the installer created its schema.');
$recognized = $manager->run('PHASE22_JOURNAL_CASHBOX_REFERENCE.sql', 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql', 1);
$assert(!empty($recognized['already_applied']), 'Existing Phase 22 schema must be recorded without rerunning DDL.');

$deletePhase24Ledger = $pdo->prepare('DELETE FROM app_schema_migrations WHERE migration_key = ?');
$deletePhase24Ledger->execute(['PHASE24_FINANCIAL_VOUCHERS.sql']);
$voucherSchemaPresent = null;
foreach ($manager->listMigrations() as $migration) {
    if ($migration['file'] === 'PHASE24_FINANCIAL_VOUCHERS.sql') {
        $voucherSchemaPresent = $migration;
        break;
    }
}
$assert($voucherSchemaPresent !== null && $voucherSchemaPresent['status'] === 'SCHEMA_PRESENT' && !$voucherSchemaPresent['enabled'],
    'Phase 24 must recognize installer-provided voucher tables without rerunning DDL.');
$recognizedVoucherSchema = $manager->run('PHASE24_FINANCIAL_VOUCHERS.sql', 'PHASE24_FINANCIAL_VOUCHERS.sql', 1);
$assert(!empty($recognizedVoucherSchema['already_applied']), 'Existing Phase 24 schema must be recorded without rerunning DDL.');

$final = $manager->listMigrations();
foreach ($final as $migration) {
    if (!$migration['enabled']) continue;
    $assert($migration['status'] === 'APPLIED', 'Migration must be recorded as APPLIED: ' . $migration['file']);
}

echo "PASS: admin migration registry, SQL splitting, prerequisites, checksum ledger and idempotent rerun checks.\n";
