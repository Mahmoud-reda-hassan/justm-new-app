<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing project file: ' . $relative);
    return (string)file_get_contents($path);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$manager = $read('inc/Classes/Accounting/SupplierAccountingManager.php');
$dao = $read('inc/controller/DataAccessObject/PurchaseOrderDAO.php');
$inventory = $read('inc/controller/BackEnd/inventory_BE.php');
$finance = $read('inc/controller/BackEnd/finance_BE.php');
$balance = $read('inc/controller/BackEnd/PaymentDisbursement_BE.php');
$installer = $read('JUSTM_DATABASE_INSTALL.sql');

$assert(strpos($manager, 'PURCHASE_ORDER_RECOGNIZED') !== false, 'Purchase order GL event is missing.');
$assert(strpos($manager, 'PURCHASE_STOCK_RECEIVED') !== false, 'Purchase receipt GL reclassification is missing.');
$assert(strpos($manager, 'PURCHASE_RETURN_RECOGNIZED') !== false, 'Supplier return GL event is missing.');
$assert(strpos($manager, 'PRO.REFERANC PURCHASE_ORDER_ID') !== false, 'Supplier return does not resolve its original purchase order.');
$assert(strpos($manager, "'reference_type'=>'PURCHASE_ORDER','reference_id'=>\$purchaseOrderId") !== false, 'Supplier return liability is not linked to the original purchase order.');
$assert(strpos($manager, 'SUPPLIER_PAYMENT_POSTED') !== false, 'Supplier payment GL event is missing.');
$assert(strpos($manager, 'supplierAdvances()') !== false, 'Supplier advance account is not used.');
$assert(strpos($manager, "'partner_type'=>'SUPPLIER','partner_id'=>\$supplierId,'reference_type'=>'SUPPLIER_ADVANCE'") !== false, 'Supplier advances are not linked to their supplier partner.');
$assert(strpos($dao, 'recordPurchaseOrder') !== false && strpos($dao, 'addSupplierDebt') === false, 'New purchase orders still write legacy supplier balances.');
$assert(strpos($dao, 'addSupplierCredit') === false, 'New supplier returns still write legacy supplier balances.');
$assert(strpos($inventory, 'recordReceiptByMovementUniqueId') !== false, 'Physical purchase receipts are not linked to GL.');
$assert(strpos($inventory, 'recordReturnByMovementUniqueId') !== false, 'Physical supplier returns are not linked to GL.');
$assert(strpos($inventory, 'recordReturnByMovementUniqueId($movementUniqueId)') !== false, 'Supplier return GL posting must follow the physical stock movement.');
$assert(strpos($inventory, 'addSupplierCredit') === false, 'New supplier return path still references the legacy balance helper.');
$paymentStart = strpos($finance, 'public function PaymentOfSupplierDues');
$paymentEnd = strpos($finance, '// صرف مستحقات عميل', $paymentStart);
$paymentMethod = substr($finance, $paymentStart, $paymentEnd - $paymentStart);
$assert(strpos($paymentMethod, 'SupplierAccountingManager') !== false, 'Supplier payment does not use the GL manager.');
$assert(strpos($paymentMethod, 'Add_partners_balance') === false && strpos($paymentMethod, 'Add_transaction') === false, 'New supplier payment still writes legacy finance tables.');
$assert(strpos($balance, "'SUPPLIER_ADVANCES'") !== false, 'Supplier balance omits supplier advances.');
$assert(strpos($balance, "'legacy_balance'") !== false && strpos($balance, "'gl_balance'") !== false, 'Historical and GL supplier balances must remain separate.');
$assert(strpos($balance, '$totalBalance = round($legacyBalance + $glBalance, 2)') !== false, 'Supplier total balance must include both historical and GL balances.');
$assert(strpos($installer, 'supplier_gl_payments') !== false, 'Supplier GL payment tables are missing from installer.');
$assert(strpos($manager, "INSERT INTO purchases_payments") !== false && strpos($manager, "UPDATE purchases_orders SET TOTAL_PAYMENTS") !== false, 'Supplier GL payments must keep the legacy operational payment totals synchronized.');
$assert(strpos($manager, "supplier_gl_payment_allocations") !== false, 'Supplier payment allocations are not persisted.');
$assert(strpos($balance, "public function GetSupplierOrderList") !== false && strpos($balance, "PO.TOTAL_PAYMENTS") !== false, 'Supplier order list no longer reads synchronized operational payment totals.');
$report = $read('inc/controller/BackEnd/reports_BE.php');
$supplierStatementStart = strpos($report, "if (strtoupper((string)$partnerType) === 'SUPPLIER')");
$supplierStatementEnd = strpos($report, "if (strtoupper((string)$partnerType) === 'MARKETER')", $supplierStatementStart);
$supplierStatement = substr($report, $supplierStatementStart, $supplierStatementEnd - $supplierStatementStart);
$assert(strpos($supplierStatement, "'رصيد سابق (السجل القديم)'") !== false && strpos($supplierStatement, "'رصيد سابق (GL)'") !== false, 'Supplier statement must show historical and GL opening balances separately.');
$assert(strpos($supplierStatement, "'رصيد حالي (السجل القديم)'") !== false && strpos($supplierStatement, "'رصيد حالي (GL)'") !== false, 'Supplier statement must show historical and GL closing balances separately.');

echo "PASS: supplier purchase, receipt, return and payment paths use GL for new events.\n";
echo "PASS: historical supplier balances remain untouched and are combined in transitional balance reads.\n";
