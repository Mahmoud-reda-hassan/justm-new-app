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
$journal = $read('inc/Classes/Accounting/JournalManager.php');
$inventoryManager = $read('inc/Classes/InventoryManager.php');
$inventoryProcedure = $read('inc/Databases/procedure_inventory.sql');

$assert(strpos($manager, 'PURCHASE_ORDER_RECOGNIZED') !== false, 'Purchase order GL event is missing.');
$assert(strpos($journal, "['SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE_APPLICATION']") !== false, 'Generic journal reversal must block supplier payments and advance applications until coordinated operational reversal exists.');
$assert(strpos($journal, 'أرصدة أوامر الشراء وسجلات التخصيص') !== false, 'Supplier reversal guard must explain the coordinated reversal requirement.');
$assert(strpos($manager, 'PURCHASE_STOCK_RECEIVED') !== false, 'Purchase receipt GL reclassification is missing.');
$assert(strpos($manager, 'PURCHASE_RETURN_RECOGNIZED') !== false, 'Supplier return GL event is missing.');
$assert(strpos($manager, 'PRO.REFERANC PURCHASE_ORDER_ID') !== false, 'Supplier return does not resolve its original purchase order.');
$assert(strpos($manager, "'reference_type'=>'PURCHASE_ORDER','reference_id'=>\$purchaseOrderId") !== false, 'Supplier return liability is not linked to the original purchase order.');
$assert(strpos($manager, 'SUPPLIER_PAYMENT_POSTED') !== false, 'Supplier payment GL event is missing.');
$assert(strpos($manager, 'supplierAdvances()') !== false, 'Supplier advance account is not used.');
$assert(strpos($manager, "'partner_type'=>'SUPPLIER','partner_id'=>\$supplierId,'reference_type'=>'SUPPLIER_ADVANCE'") !== false, 'Supplier advances are not linked to their supplier partner.');
$assert(strpos($dao, 'recordPurchaseOrder') !== false && strpos($dao, 'addSupplierDebt') === false, 'New purchase orders still write legacy supplier balances.');
$assert(strpos($dao, 'if ((float)$total_payments > 0)') !== false && strpos($dao, 'لا يمكن تسجيل دفعة أولية مع أمر الشراء حاليًا') !== false, 'New purchase orders must reject initial payments until their GL payment path is implemented.');
$assert(strpos($dao, 'addSupplierCredit') === false, 'New supplier returns still write legacy supplier balances.');

$assert(strpos($inventory, 'WHERE ORDER_ID = ? AND SUPPLIER_ID = ?') !== false, 'Supplier return must verify the source purchase order belongs to the selected supplier.');
$assert(strpos($inventory, 'WHERE STO_ID = ? AND BRANCH_ID = ? FOR UPDATE') !== false, 'Supplier return must use a store assigned to the active branch.');
$assert(strpos($inventory, 'FROM purchases_products') !== false && strpos($inventory, 'QTY_SUPPLIED') !== false, 'Supplier return quantities must be based on quantities actually received.');
$assert(strpos($inventory, "LOWER(IM.IM_MOVE_TYPE) = 'refundpurchises'") !== false && strpos($inventory, 'SUM(IPM.IPM_QTY)') !== false, 'Previously returned quantities must include only completed physical return movements.');
$assert(strpos($inventory, 'كمية مرتجع المنتج') !== false && strpos($inventory, 'المتبقية للمرتجع') !== false, 'Supplier return must reject quantities exceeding the remaining received quantity.');
$assert(strpos($inventoryManager, 'WHERE STO_ID = ? AND PRO_CODE IN ($stockPlaceholders)') !== false && strpos($inventoryManager, 'FOR UPDATE') !== false && strpos($inventoryManager, 'لا يكفي لتنفيذ المرتجع') !== false, 'Supplier return must reject insufficient stock while holding the global inventory lock.');
$assert(strpos($inventory, "'QTY_RETURN' => \$qty") !== false && strpos($inventory, "'PURCHASE_UNIT_PRICE' => \$unitPrice") !== false, 'Supplier return rows must persist server-verified return quantities and purchase prices.');

$assert(strpos($inventory, 'recordReceiptByMovementUniqueId') !== false, 'Physical purchase receipts are not linked to GL.');
$assert(strpos($inventory, 'recordReturnByMovementUniqueId') !== false, 'Physical supplier returns are not linked to GL.');
$assert(strpos($inventory, 'recordReturnByMovementUniqueId($movementUniqueId)') !== false, 'Supplier return GL posting must follow the physical stock movement.');
$assert(strpos($inventory, 'addSupplierCredit') === false, 'New supplier return path still references the legacy balance helper.');
$assert(strpos($dao, "'affectedRows'] ?? 0) !== 1") !== false, 'Partial supplier receipt must stop when the guarded purchase quantity update affects no row.');
$assert(strpos($dao, '? (float)$row[\'QTY_SUPPLIED\']') !== false, 'Partial supplier receipt must preserve decimal quantities.');
$assert(strpos($inventory, 'WHERE STO_ID = ? AND BRANCH_ID = ? FOR UPDATE') !== false, 'Supplier return must verify the selected store belongs to the active branch.');
$assert(strpos($inventoryManager, '$colQty       = $type === \'refundpurchises\' ? \'op.QTY_RETURN\' : \'op.QTY_SUPPLIED\'') !== false, 'Purchase receipt and return movements must use their respective operational quantities.');
$assert(strpos($manager, 'PURCHASE_UNIT_PRICE*IPM.IPM_QTY') !== false, 'Supplier GL receipt and return valuation must use the database purchase unit price.');
$assert(strpos($inventoryProcedure, 'STP.STO_QTY = STP.STO_QTY + MOV.NET_QTY') !== false, 'Inventory balance procedure no longer applies the movement net quantity.');
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
$assert(strpos($manager, 'public function applyAdvances(') !== false, 'Supplier advance application workflow is missing.');
$assert(strpos($manager, "'SUPPLIER_ADVANCE_APPLIED'") !== false, 'Supplier advance application GL event is missing.');
$assert(strpos($manager, 'supplier_gl_advance_applications') !== false, 'Supplier advance source-to-invoice applications are not tracked.');
$assert(strpos($manager, "'mode'=>\$mode") !== false, 'Supplier advance application mode is not idempotently recorded.');
$assert(strpos($balance, 'GetSupplierAdvanceBalance') !== false, 'Available supplier advance balance endpoint is missing.');
$assert(strpos($balance, 'GetSupplierAdvanceBalance') !== false && strpos($balance, 'supplier_gl_advance_applications') !== false, 'Supplier available advance is not net of prior applications.');
$assert(strpos($read('inc/controller/finance.control.php'), "case 'ApplySupplierAdvances'") !== false, 'Supplier advance application endpoint is missing.');
$supplierJs = $read('dist/js/PaymentDisbursement.js');
$assert(strpos($supplierJs, 'supplierAdvanceRequestFingerprint') !== false, 'Supplier advance retries must reuse the same idempotency key for identical requests.');
$assert(strpos($supplierJs, 'supplierPaymentRequestFingerprint') !== false && strpos($supplierJs, 'paymentOrders.sort') !== false, 'Supplier payment retries must reuse the key only when the normalized payment request is unchanged.');
$supplierView = $read('inc/Views/PaymentDisbursement.php');
$assert(strpos($supplierView, "applySupplierAdvances('AUTO')") !== false
    && strpos($supplierView, "applySupplierAdvances('MANUAL')") !== false,
    'Supplier advance UI must support both automatic and manual allocation.');
$assert(strpos($installer, 'supplier_gl_advance_applications') !== false, 'Supplier advance application table is missing from installer.');

$assert(strpos($balance, "public function GetSupplierOrderList") !== false && strpos($balance, "PO.TOTAL_PAYMENTS") !== false, 'Supplier order list no longer reads synchronized operational payment totals.');
$report = $read('inc/controller/BackEnd/reports_BE.php');
$supplierStatementStart = strpos($report, 'if (strtoupper((string)$partnerType) === \'SUPPLIER\')');
$supplierStatementEnd = strpos($report, 'if (strtoupper((string)$partnerType) === \'MARKETER\')', $supplierStatementStart);
$supplierStatement = substr($report, $supplierStatementStart, $supplierStatementEnd - $supplierStatementStart);
$assert(strpos($supplierStatement, "'رصيد سابق (السجل القديم)'") !== false && strpos($supplierStatement, "'رصيد سابق (GL)'") !== false, 'Supplier statement must show historical and GL opening balances separately.');
$assert(strpos($supplierStatement, "'رصيد حالي (السجل القديم)'") !== false && strpos($supplierStatement, "'رصيد حالي (GL)'") !== false, 'Supplier statement must show historical and GL closing balances separately.');
$assert(strpos($supplierStatement, "CASE WHEN d.reference_type='SUPPLIER_ADVANCE' THEN NULL") !== false, 'Supplier advance credit line must not be displayed as a purchase order number.');

echo "PASS: supplier purchase, receipt, return and payment paths use GL for new events.\n";
echo "PASS: historical supplier balances remain untouched and are combined in transitional balance reads.\n";
