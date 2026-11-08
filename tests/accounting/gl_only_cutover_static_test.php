<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) throw new RuntimeException('Required source file missing: ' . $path);
    return (string)file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$runtimeFiles = [
    'inc/Classes/Order_tbl.php',
    'inc/Classes/Suppliers_tbl.php',
    'inc/Classes/Invoices_tbl.php',
    'inc/Classes/Accounting/AccountingEvents.php',
    'inc/Classes/Accounting/CustomerRefundManager.php',
    'inc/controller/BackEnd/ordersList_BE.php',
    'inc/controller/BackEnd/ordersList_BE2.php',
    'inc/controller/BackEnd/crmOrderLog_BE.php',
    'inc/controller/BackEnd/PaymentDisbursement_BE.php',
    'inc/controller/BackEnd/reports_BE.php',
    'inc/controller/BackEnd/inventory_BE.php',
    'inc/controller/BackEnd/accounts_BE.php',
];
$legacyPattern = '/\\bpartners_balance\\b|\\bfinancial_transactions\\b|\\binvoices\\b/i';
foreach ($runtimeFiles as $path) {
    $source = $read($path);
    $assert(!preg_match($legacyPattern, $source), 'Runtime legacy accounting reference remains in ' . $path);
}

$finance = $read('inc/controller/BackEnd/finance_BE.php');
$legacyFinanceMethods = [
    'restClientdebt',
    'addClientDebt',
    'addClientCredit',
    'reduceClientCredit',
    'useClientCredit',
    'addSupplierDebt',
    'reduceSupplierDebt',
    'addSupplierCredit',
    'useSupplierCredit',
    'Add_invoice',
    'Update_invoice',
    'Add_transaction',
    'Add_partners_balance',
    'PaymentOfCustomerDues',
];
foreach ($legacyFinanceMethods as $method) {
    $pattern = '/function\\s+' . preg_quote($method, '/') . '\\s*\\([^)]*\\)\\s*\\{\\s*throw new LogicException/s';
    $assert((bool)preg_match($pattern, $finance), 'Legacy finance API is not hard-disabled: ' . $method);
}

$accountingEvents = $read('inc/Classes/Accounting/AccountingEvents.php');
foreach ([
    'getDirectSalePayment',
    'validateDirectSalePayment',
    'markPaymentPosted',
    'allocateDirectSalePayment',
    'directSalePayment',
    'directSalePaymentInternal',
    'getSalesReturnPayment',
    'validateSalesReturnPayment',
    'salesReturnPayment',
    'salesReturnPaymentInternal',
] as $method) {
    $pattern = '/function\\s+' . preg_quote($method, '/') . '\\s*\\([^)]*\\)\\s*\\{\\s*throw new LogicException/s';
    $assert((bool)preg_match($pattern, $accountingEvents), 'Legacy invoice-based accounting method is not hard-disabled: ' . $method);
}

$order = $read('inc/Classes/Order_tbl.php');
$assert(strpos($order, 'SALE_ENTRY_ID AS INVO_ID') !== false, 'Order view must expose the GL sale-entry reference instead of an invoice ID.');
$assert(strpos($order, 'restClientdebt(') === false, 'Order approval/editing must not recalculate partners_balance.');
$assert(strpos($order, 'HAS_GL_POSTING') !== false, 'Order edit/cancel guards must protect posted GL entries.');

$ordersList = $read('inc/controller/BackEnd/ordersList_BE.php');
$ordersList2 = $read('inc/controller/BackEnd/ordersList_BE2.php');
$assert(strpos($ordersList, 'A.SALE_ENTRY_ID AS INVO_ID') !== false, 'Primary order list must use the GL sale-entry reference.');
$assert(strpos($ordersList2, 'A.SALE_ENTRY_ID AS INVO_ID') !== false, 'Secondary order list must use the GL sale-entry reference.');

$refunds = $read('inc/Classes/Accounting/CustomerRefundManager.php');
$assert(strpos($refunds, 'getRefundedAmountByAccount') !== false, 'Refund availability must be calculated from posted GL refund documents.');
$assert(strpos($refunds, 'financial_transactions') === false && strpos($refunds, 'invoices') === false,
    'Runtime refund logic must not query legacy invoices or financial transactions.');

$migrationManager = $read('inc/Classes/DatabaseMigrationManager.php');
$migration = $read('inc/Databases/migrations/PHASE25_GL_ONLY_CUSTOMER_REFUND_BRIDGE.sql');
$assert(strpos($migrationManager, 'PHASE25_GL_ONLY_CUSTOMER_REFUND_BRIDGE.sql') !== false,
    'The one-time customer refund history bridge is not registered.');
$assert(strpos($migration, 'h.reference_type = \'SALES_RETURN_PAYMENT\'') !== false
    && strpos($migration, 'h.status = \'POSTED\'') !== false
    && strpos($migration, 'customer_refund_payments') !== false,
    'Phase 25 must import only already-posted GL refund journals into the new source document table.');

echo "PASS: active accounting paths use GL only; legacy invoice/transaction/partner-balance APIs are disabled.\n";
