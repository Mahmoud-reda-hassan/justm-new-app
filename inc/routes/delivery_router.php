<?php
return [
    "dashboard" => [
        "view" => "cp_admin",
        "title" => "لوحة المندوب",
        "js" => []
    ],
    "Profile" => [
        "view" => "Profile",
        "title" => "الملف الشخصى",
        "js" => []
    ],
    "ShippingConfirmation" => [
        "view" => "viewOrders",
        "title" => "تأكيد الشحن والتوصيل",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"]
    ],
    "ListOrdersDelivery" => [
        "view" => "viewOrders",
        "title" => "تسليم الطلبات للعميل",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"]
    ],
    "ListOrdersDelivered" => [
        "view" => "viewOrders",
        "title" => "سجل حركات التسليم",
        "js" => ["filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js"]
    ],
    "EditOrder" => [
        "view" => "Neworder",
        "title" => "تحرير امر البيع",
        "js" => ["selectProduct.js","sreach_client.js"]
    ],
    "DelegatesShipmentReport" => [
        "view" => "DelegatesReport",
        "title" => "تقرير حركة عهدة المندوبين",
        "js" => []
    ]
];


/*
switch ($_GET["Go"]) {
            				case "Profile":
            					$this->Pagelink = "Profile";
        						$this->title= "الملف الشخصى";
	        					break;
	        				
	        				case 'EditOrder':
	            		    	$this->Pagelink = "Neworder";
	            				$this->title= "تحرير امر البيع";
	            				array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	            				break;
            			} 
*/
