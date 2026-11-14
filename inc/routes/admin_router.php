<?php
$usc = isset($_SESSION['USER_SCRIPT']) ? unserialize($_SESSION['USER_SCRIPT']) : null;
$perm = $usc['Admin'] ?? null;
if(!$perm) return null;
return [
    // Dashboard
    "dashboard" => [
        "view" => "cp_admin",
        "title" => "لوحة التحكم",
        "js" => ["raphael.min.js","jquery.mapael.js","world_countries.min.js","Chart.min.js","dashboard2.js"]
    ],
    
    
    "Countries" => [
        "view" => "Countries",
        "title" => "إعدادات الدول",
        "js" => ["Countries.js"],
        "permission" => $perm['Countries']['View']
    ],
    "Gov" => [
        "view" => "Countries",
        "title" => "إعدادات المحافظات",
        "js" => ["Countries.js"],
        "permission" => $perm['Gov']['View']
    ],
    "City" => [
        "view" => "Countries",
        "title" => "اعدادات المدن",
        "js" => ["Countries.js"],
        "permission" => $perm['City']['View']
    ],
    "LocalShipping" => [
        "view" => "Countries",
        "title" => "إعدادات الشحن المحلى",
        "js" => ["Countries.js"],
        "permission" => $perm['Local-Shipping']['View']
    ],
    "International-Shipping" => [
        "view" => "International-Shipping",
        "title" => "اعدادات الشحن الدولى",
        "js" => ["Countries.js"],
        "permission" => $perm['International-Shipping']['View']
    ],



    "Enterprise" => [
        "view" => "Enterprise",
        "title" => "بيانات المؤسسة",
        "js" => [],
        "permission" => $perm['Enterprise']['View']
    ],
    "Administrative-Structure" => [
        "view" => "Administrative-Structure",
        "title" => "الهيكل الإدارى",
        "js" => ["Administrative-Structure.js"],
        "permission" => $perm['Administrative-Structure']['View']
    ],
    "Branches" => [
        "view" => "Branches",
        "title" => "الفروع",
        "js" => [],
        "permission" => $perm['Branches']['View']
    ],
    "Warehouse" => [
        "view" => "Warehouse",
        "title" => "المخازن",
        "js" => [],
        "permission" => $perm['Warehouse']['View']
    ],
    "Users" => [
        "view" => "Users",
        "title" => "المستخدمين",
        "js" => ["users.js"],
        "permission" => $perm['Users']['View']
    ],
    "Clients" => [
        "view" => "Clients",
        "title" => "العملاء",
        "js" => ["Clients.js"],
        "permission" => $perm['Clients']['View']
    ],
    "Suppliers" => [
        "view" => "Suppliers",
        "title" => "الموردين",
        "js" => ["Suppliers.js"],
        "permission" => $perm['Suppliers']['View']
    ],
    
    
    
    
    "Product-Categories" => [
        "view" => "Categoris",
        "title" => "اقسام المنتجات",
        "js" => ["Categoris.js"],
        "permission" => $perm['Product-Categories']['View']
    ],
    "ProductOption" => [
        "view" => "ProductOption",
        "title" => "صفات المنتجات",
        "js" => ["productOption.js"],
        "permission" => $perm['ProductOption']['View']
    ],
    "Products" => [
        "view" => "AddProduct",
        "title" => "المنتجات",
        "js" => ["products.js","summernote-bs4.min.js"],
        "css" => ["summernote-bs4.css"],
        "permission" => $perm['Products']['View']
    ],
    
    
    "NewOrder" => [
        "view" => "Neworder",
        "title" => "أمر جديد",
        "js" => ["selectProduct.js","sreach_client.js"],
        "permission" => $perm['NewOrder']['Create_Order']
    ],
    "EditOrder" => [
        "view" => "Neworder",
        "title" => "تحرير امر البيع",
        "js" => ["selectProduct.js","sreach_client.js"],
        "permission" => $perm['NewOrder']['Create_Order']
    ],
    "Orderlist0" => [
        "view" => "viewOrders",
        "title" => "طلبات العملاء",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"],
        "permission" => $perm['orderlist_ex0']['View_order_list']
    ],
    "Orderlist1" => [
        "view" => "viewOrders",
        "title" => "تأكيد طلبات العملاء",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"],
        "permission" => $perm['orderlist_ex1']['View_order_list']
    ],
    "Orderlist2" => [
        "view" => "viewOrders",
        "title" => "طلبات مؤجلة",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"],
        "permission" => $perm['orderlist_ex2']['View_order_list']
    ],
    "AddStock" => [
        "view" => "AddStockPermission",
        "title" => "إذن استلام مخزنى",
        "js" => ["AddStockPermission.js"],
        "permission" => $perm['StockIssue']['view']
    ],
    "StockIssue" => [
        "view" => "inventoryIssue",
        "title" => "إذن صرف مخزنى",
        "js" => ["inventoryIssue.js"],
        "permission" => $perm['StockIssue']['view']
    ],
    "StockTransfer" => [
        "view" => "StockTransfer",
        "title" => "إذن تحويل مخزنى",
        "js" => ["StockTransfer.js"],
        "permission" => $perm['StockTransfer']['view']
    ],
    "StockAudit" => [
        "view" => "InventoryReconciliation",
        "title" => "جرد ومطابقة المخزون",
        "js" => ["InventoryReconciliation.js"],
        "permission" => $perm['StockAudit']['view']
    ],
    "CourierReopen" => [
        "view" => "CourierReopen",
        "title" => "رد أوامر المندوب للمخزن",
        "js" => ["CourierReopen.js"],
        "permission" => in_array(trim($_SESSION['USER_GRUOP_NAME'] ?? ''), ['Administrator','Owner','المدير المالي'], true)
    ],
    "ReceivePayments" => [
        "view" => "ReceivePayments",
        "title" => "استلام المدفوعات",
        "js" => ["ReceivePayments.js"],
        "permission" => $perm['PaymentsReceipt']['view']
    ],
    "PaymentDisbursement" => [
        "view" => "PaymentDisbursement",
        "title" => "اذن صرف نقدية",
        "js" => ["PaymentDisbursement.js"],
        "permission" => $perm['PaymentDisbursement']['view']
    ],
    "CashReceiptVouchers" => [
        "view" => "CashReceiptVouchers",
        "title" => "اذن استلام نقدية",
        "js" => ["ReceivePayments.js","CashReceiptVouchers.js"],
        "permission" => $perm['CashReceiptVouchers']['view']
    ],
    "DelegatesShipmentReport" => [
        "view" => "DelegatesReport",
        "title" => "تقرير حركة عهدة المندوبين",
        "js" => [],
        "permission" => $perm['DelegatesShipmentReport']['view']
    ],
    "DelegatesInventoryReport" => [
        "view" => "PurchasesReports",
        "title" => "تقرير استلامات المرتجعات",
        "js" => [],
        "permission" => $perm['DelegatesInventoryReport']['view']
    ],
    "AddPurchaseOrder" => [
        "view" => "AddPurchaseOrder",
        "title" => "طلب توريد",
        "js" => ["AddPurchaseOrder.js"],
        "permission" => true//$perm['AddPurchaseOrder']['view']
    ]
];