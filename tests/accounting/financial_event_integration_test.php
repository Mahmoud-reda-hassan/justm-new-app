<?php
declare(strict_types=1);

/**
 * اختبارات تكامل لخدمة الأحداث المالية على قاعدة MariaDB مؤقتة داخل CI فقط.
 * لا تستخدم بيانات أو إعدادات قاعدة الإنتاج.
 */

$dsn = getenv('ACCOUNTING_TEST_DSN');
$user = getenv('ACCOUNTING_TEST_DB_USER') ?: 'root';
$password = getenv('ACCOUNTING_TEST_DB_PASSWORD') ?: '';
if (!$dsn) {
    fwrite(STDERR, "FAIL: ACCOUNTING_TEST_DSN is required.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/inc/Classes/Accounting/FinancialEventManager.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function expectException(callable $callback, string $message): Throwable
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

    // الجداول هنا مصغرة ومخصصة لاختبار FinancialEventManager فقط.
    $db->exec('DROP TABLE IF EXISTS accounting_events');
    $db->exec('DROP TABLE IF EXISTS journal_entry_details');
    $db->exec('DROP TABLE IF EXISTS journal_entries');
    $db->exec('DROP TABLE IF EXISTS financial_years');
    $db->exec('DROP TABLE IF EXISTS accounts');
    $db->exec("CREATE TABLE accounts (
        id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        allow_posting TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO accounts (id,is_active,allow_posting) VALUES (10,1,1),(20,1,1)");
    $db->exec("CREATE TABLE financial_years (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        year_name VARCHAR(20) NOT NULL,
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        is_closed TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $db->exec("INSERT INTO financial_years (year_name,date_from,date_to,is_closed)
        VALUES ('2026','2026-01-01','2026-12-31',0)");
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
        idempotency_key VARCHAR(128) NOT NULL,
        event_type VARCHAR(60) NOT NULL,
        source_type VARCHAR(60) NOT NULL,
        source_id BIGINT UNSIGNED NOT NULL,
        branch_id BIGINT UNSIGNED NOT NULL,
        event_date DATETIME NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        status ENUM('PROCESSING','POSTED') NOT NULL,
        journal_entry_id BIGINT UNSIGNED NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        posted_at DATETIME NULL,
        UNIQUE KEY uq_accounting_event_idempotency (idempotency_key),
        UNIQUE KEY uq_accounting_event_journal (journal_entry_id)
    ) ENGINE=InnoDB");

    $manager = new FinancialEventManager($db);
    $event = [
        'idempotency_key' => 'CI:SALE:1001',
        'event_type' => 'SALE_RECOGNIZED',
        'source_type' => 'ORDER',
        'source_id' => 1001,
        'branch_id' => 1,
        'event_date' => '2026-11-05 12:00:00',
        'created_by' => 7,
        'payload' => ['amount' => 125.50, 'client_id' => 50],
    ];

    $callbackCalls = 0;
    $first = $manager->post($event, function () use ($db, &$callbackCalls): int {
        $callbackCalls++;
        $db->exec("INSERT INTO journal_entries (status) VALUES ('POSTED')");
        return (int)$db->lastInsertId();
    });
    assertSameValue(false, $first['already_posted'], 'First event must be posted.');
    assertSameValue(1, $callbackCalls, 'First callback must run once.');

    $second = $manager->post($event, function () use (&$callbackCalls): int {
        $callbackCalls++;
        return 999;
    });
    assertSameValue(true, $second['already_posted'], 'Duplicate event must return the previous posting.');
    assertSameValue($first['journal_entry_id'], $second['journal_entry_id'], 'Duplicate must return the same journal ID.');
    assertSameValue(1, $callbackCalls, 'Duplicate callback must not run.');

    $changed = $event;
    $changed['payload']['amount'] = 126.00;
    expectException(function () use ($manager, $changed, &$callbackCalls): void {
        $manager->post($changed, function () use (&$callbackCalls): int {
            $callbackCalls++;
            return 1000;
        });
    }, 'Same idempotency key with changed payload must fail.');
    assertSameValue(1, $callbackCalls, 'Changed payload callback must not run.');

    $processingHash = hash('sha256', '{"amount":125.5,"client_id":50}');
    $processingInsert = $db->prepare("INSERT INTO accounting_events
        (idempotency_key,event_type,source_type,source_id,branch_id,event_date,payload_hash,status,created_by)
        VALUES ('CI:PROCESSING:1002','SALE_RECOGNIZED','ORDER',1002,1,'2026-11-05 12:00:00',?,'PROCESSING',7)");
    $processingInsert->execute([$processingHash]);
    $processing = $event;
    $processing['idempotency_key'] = 'CI:PROCESSING:1002';
    $processing['source_id'] = 1002;
    $processingError = expectException(function () use ($manager, $processing): void {
        $manager->post($processing, function (): int {
            throw new RuntimeException('PROCESSING callback must not run.');
        });
    }, 'Existing PROCESSING event must fail safely.');
    if (strpos($processingError->getMessage(), 'غير مكتمل') === false) {
        throw new RuntimeException('Existing PROCESSING event was rejected for the wrong reason.');
    }

    $failed = $event;
    $failed['idempotency_key'] = 'CI:ROLLBACK:1003';
    $failed['source_id'] = 1003;
    expectException(function () use ($manager, $failed): void {
        $manager->post($failed, function (): int {
            throw new RuntimeException('Simulated journal failure.');
        });
    }, 'Callback exception must propagate.');
    $check = $db->prepare('SELECT COUNT(*) FROM accounting_events WHERE idempotency_key = ?');
    $check->execute([$failed['idempotency_key']]);
    assertSameValue(0, (int)$check->fetchColumn(), 'Failed event must be rolled back.');

    $invalidJournal = $event;
    $invalidJournal['idempotency_key'] = 'CI:INVALID:JOURNAL:1004';
    $invalidJournal['source_id'] = 1004;
    expectException(function () use ($manager, $invalidJournal): void {
        $manager->post($invalidJournal, function (): int {
            return 987654;
        });
    }, 'Missing journal row must fail.');
    $check->execute([$invalidJournal['idempotency_key']]);
    assertSameValue(0, (int)$check->fetchColumn(), 'Event with missing journal must be rolled back.');

    echo "PASS: event posts once and duplicate returns the original journal.\n";
    echo "PASS: changed payload and PROCESSING duplicates are rejected.\n";
    echo "PASS: callback failure and invalid journal both roll back the event.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
