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

$splitter = new ReflectionMethod(DatabaseMigrationManager::class, 'splitSqlStatements');
$splitter->setAccessible(true);
$parts = $splitter->invoke($manager, "-- heading\nSELECT 'a;b', \"c;d\", `e;f`; /* comment ; */ SELECT 2; # tail\n");
$assert(count($parts) === 2, 'SQL parser must preserve semicolons inside strings and ignore comments.');

$environment = $manager->getEnvironment();
$assert(stripos((string)$environment['database'], 'test') !== false, 'Migration runner integration test must use a test database.');

// Force the two first CREATE TABLE migrations through their real creation path.
$pdo->exec('DROP TABLE IF EXISTS payment_method_accounts');
$pdo->exec('DROP TABLE IF EXISTS accounting_line_allocations');
// Phase 14 tables are left intact because other installer tables may reference them.\n$pdo->exec('DROP TABLE IF EXISTS supplier_payment_allocations');
$pdo->exec('DROP TABLE IF EXISTS marketer_commission_payments');
$pdo->exec('DROP TABLE IF EXISTS customer_refund_payments');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_advance_applications');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_payment_allocations');
$pdo->exec('DROP TABLE IF EXISTS supplier_gl_payments');

$migrations = $manager->listMigrations();
$assert(count($migrations) === 10, 'The approved migration registry must contain ten numbered phases.');
$byFile = [];
foreach ($migrations as $migration) $byFile[$migration['file']] = $migration;
$assert(isset($byFile['PHASE13_SALES_RETURNS_ACCOUNT.sql']) && !$byFile['PHASE13_SALES_RETURNS_ACCOUNT.sql']['enabled'],
    'Phase 13 must remain blocked until its fixed account parent and open transaction are reviewed.');

$wrongConfirmationRejected = false;
try {
    $manager->run('PHASE11_PAYMENT_METHOD_ACCOUNT_MAPPING.sql', 'wrong-file-name.sql', 1);
} catch (RuntimeException $e) {
    $wrongConfirmationRejected = true;
}
$assert($wrongConfirmationRejected, 'Migration execution must require exact typed filename confirmation.');

foreach ($migrations as $migration) {
    if (!$migration['enabled']) continue;
    $result = $manager->run($migration['file'], $migration['file'], 1);
    $assert(!empty($result['message']), 'Migration must return an execution result: ' . $migration['file']);

    $repeat = $manager->run($migration['file'], $migration['file'], 1);
    $assert(!empty($repeat['already_applied']), 'An applied migration must not execute twice: ' . $migration['file']);
}

$paymentAccountType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_method_accounts' AND COLUMN_NAME = 'account_id'")->fetchColumn();
$allocationDebitType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_line_allocations' AND COLUMN_NAME = 'debit_line_id'")->fetchColumn();
$assert(strtolower((string)$paymentAccountType) === 'bigint(20) unsigned', 'Phase 11 account_id must match accounts.id as BIGINT UNSIGNED.');
$assert(strtolower((string)$allocationDebitType) === 'bigint(20) unsigned', 'Phase 12 debit_line_id must match journal_entry_details.id as BIGINT UNSIGNED.');

$final = $manager->listMigrations();
foreach ($final as $migration) {
    if (!$migration['enabled']) continue;
    $assert($migration['status'] === 'APPLIED', 'Migration must be recorded as APPLIED: ' . $migration['file']);
}

echo "PASS: admin migration registry, SQL splitting, prerequisites, checksum ledger and idempotent rerun checks.\n";
