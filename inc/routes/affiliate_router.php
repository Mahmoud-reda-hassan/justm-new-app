<?php
return [
    "dashboard" => [
        "view" => "cp_admin",
        "title" => "لوحة المسوق",
        "js" => ["raphael.min.js", "world_countries.min.js", "Chart.min.js", "dashboard2.js"]
    ],
    "Profile" => [
        "view" => "Profile",
        "title" => "الملف الشخصى",
        "js" => []
    ],
    "NewOrder" => [
        "view" => "Neworder",
        "title" => "أمر جديد",
        "js" => ["selectProduct.js","sreach_client.js"]
    ],
    "EditOrder" => [
        "view" => "Neworder",
        "title" => "تحرير امر البيع",
        "js" => ["selectProduct.js","sreach_client.js"]
    ],
    "ListOrdersMarketer" => [
        "view" => "viewOrders",
        "title" => "طلبات العملاء",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"]
    ],
    "MyEarnings" => [//AccountStatementMarketer
        "view" => "AccountStatementReport",
        "title" => "كشف حساب المسوقين",
        "js" => []
    ]
];

