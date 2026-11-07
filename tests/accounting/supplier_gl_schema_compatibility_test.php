<?php
declare(strict_types=1);

$dsn = getenv('ACCOUNTING_SCHEMA_DSN') ?: '';
$user = getenv('ACCOUNTING_SCHEMA_DB_USER') ?: 'root';
$password = getenv('ACCOUNTING_SCHEMA_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "ACCOUNTING_SCHEMA_DSN is required.\n");
    exit(2);
}

$required = [
    'accounts' => ['id','is_active','allow_posting'],
    'account_settings' => ['setting_key','account_id'],
    'payment_methods' => ['id','is_active'],
    'payment_method_accounts' => ['payment_method_id','branch_id','account_id','is_active'],
    'suppliers' => ['SUP_ID'],
    'purchases_orders' => ['ORDER_ID','SUPPLIER_ID','TOTAL_PRICE','TOTAL_PAYMENTS','STATUSE'],
    'purchases_payments' => ['ORDER_ID','AMOUNT','PAYMENT_METHOD','NOTE','CREATED_BY','SUPPLIER_GL_PAYMENT_ID','REVERSAL_JOURNAL_ENTRY_ID'],
    'supplier_gl_payments' => ['id','request_key','supplier_id','branch_id','payment_method_id','amount','journal_entry_id','note','created_by','created_at','reversal_journal_entry_id'],
    'supplier_gl_payment_allocations' => ['id','payment_id','supplier_id','purchase_order_id','allocation_type','amount','created_by','created_at'],
    'supplier_gl_advance_applications' => ['id','supplier_id','source_advance_allocation_id','purchase_order_id','amount','journal_entry_id','created_by','created_at'],
    'financial_years' => ['id','year_name','date_from','date_to','is_closed'],
    'journal_entries' => ['id','entry_no','fiscal_year','branch_id','entry_date','reference_type','reference_id','description','total_debit','total_credit','status','posted_at','posted_by','created_by'],
    'journal_entry_details' => ['id','journal_entry_id','line_no','account_id','branch_id','cash_box_id','store_id','cost_center_id','partner_type','partner_id','reference_type','reference_id','debit','credit','note'],
    'accounting_events' => ['id','idempotency_key','event_type','source_type','source_id','branch_id','event_date','payload_hash','status','journal_entry_id','created_by','created_at','posted_at'],
    'purchases_return_orders' => ['ORDER_ID','REFERANC','STATUSE','TOTAL_PRICE','TOTAL_PAYMENTS'],
    'inventory_movement' => ['IM_ID','REFERENCE','IM_MOVE_TYPE'],
];

try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $failures = [];
    foreach ($required as $table => $columns) {
        $stmt->execute([$schema, $table]);
        $actual = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        if (!$actual) {
            $failures[] = $table . ' (table missing)';
            continue;
        }
        $missing = array_values(array_filter($columns, static fn($column) => !isset($actual[$column])));
        if ($missing) $failures[] = $table . ' missing: ' . implode(', ', $missing);
    }
    if ($failures) {
        throw new RuntimeException("Supplier GL production-schema compatibility failed:\n - " . implode("\n - ", $failures));
    }
    echo "PASS: supplier GL integration SQL dependencies match the imported full database installer schema.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
