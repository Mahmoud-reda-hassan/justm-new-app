<?php
class DBSchema {
    private $tables; // تعريف الجداول والحقول
    private static $instance = null; // كائن وحيد للكلاس

    private function __construct() {
        // تعريف الجداول مرة واحدة
    	$this->tables = [
        'COUNTRIES' => [
	        'TABLE'  => 'countries',
	        'FIELDS' => [
	            
	        ]
	    ],
		'CITY' => [
	        'TABLE'  => 'city',
	        'FIELDS' => [
	            
	        ]
	    ],
    	'GOV' => [
	        'TABLE'  => 'gov',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ZONES' => [
	        'TABLE'  => 'zones',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'REGIONAL_AREAS' => [
	        'TABLE'  => 'regional_areas',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ADDRESSES' => [
	        'TABLE'  => 'addresses',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'TELEPHONES' => [
	        'TABLE'  => 'telephones',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'COMPANIES' => [
	        'TABLE'  => 'companies',
	        'FIELDS' => [
	            
	        ]
	    ],
		'BRANCHES' => [
	        'TABLE'  => 'branches',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'WAREHOUSE_TYPE' => [
	        'TABLE'  => 'stores_type',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'STORES' => [
	        'TABLE'  => 'stores',
	        'FIELDS' => [
	            'STO_ID'	=> ['NAME' => 'STO_ID',		'TYPE' => 'INTEGER'],
				'BRANCH_ID' => ['NAME' => 'BRANCH_ID',	'TYPE' => 'INTEGER'],
				'STO_NAME' 	=> ['NAME' => 'STO_NAME',	'TYPE' => 'STRING'],
				'STTY_ID' 	=> ['NAME' => 'STTY_ID',	'TYPE' => 'INTEGER']
	        ]
	    ],
	    'STORES_PRODUCT' => [
	        'TABLE'  => 'stores_product',
	        'FIELDS' => [
	            'STO_ID' 		=> ['NAME' => 'STO_ID',		'TYPE' => 'INTEGER'],
				'PRO_CODE' 		=> ['NAME' => 'PRO_CODE',	'TYPE' => 'STRING'],
				'STO_QTY' 		=> ['NAME' => 'STO_QTY',	'TYPE' => 'NUMERIC'],
				'LAST_UPDATE' 	=> ['NAME' => 'LAST_UPDATE','TYPE' => 'TIMESTAMP']
	        ]
	    ],
	    'WAREHOUSE_MOVEMENT' => [
	        'TABLE'  => 'inventory_movement',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'WAREHOUSE_PRODUCT_MOVEMENT' => [
	        'TABLE'  => 'inventory_product_movement',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'USERS' => [
	        'TABLE'  => 'users',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'USER_GRUOP' => [
	        'TABLE'  => 'user_gruop',
	        'FIELDS' => [
	        	
	        ]
	    ],
	    'USERS_AFFILIATE' => [
	        'TABLE'  => 'users_affiliate',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'USERS_DELIVERY' => [
	        'TABLE'  => 'users_delivery',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'CLIENT' => [
	        'TABLE'  => 'client',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'SUPPLIERS' => [
	        'TABLE'  => 'suppliers',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'SHIPPING_MOVEMENT' => [
	        'TABLE'  => 'shipping_movement',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'SHIPPING_TYPE' => [
	        'TABLE'  => 'shipping_type',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'CUSTODY_ORDERS' => [
	        'TABLE'  => 'custody_orders',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'CUSTODY_PRODUCT' => [
	        'TABLE'  => 'custody_product',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'CUSTODY_RETURN_ORDERS' => [
	        'TABLE'  => 'custody_return_orders',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'CUSTODY_RETURN_PRODUCT' => [
	        'TABLE'  => 'custody_return_product',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'PARTNERS_BALANCE' => [
	        'TABLE'  => 'partners_balance',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'PAYMENT_METHODS' => [
	        'TABLE'  => 'payment_methods',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'FINANCIAL_TRANSACTIONS' => [
	        'TABLE'  => 'financial_transactions',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'TRANSACTION_CATEGORIES' => [
	        'TABLE'  => 'transaction_categories',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'INVOICES' => [
	        'TABLE'  => 'invoices',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'PURCHASES_ORDERS' => [ 
	        'TABLE'  => 'PURCHASES_ORDERS',
	        'FIELDS' => [
	        	'ORDER_ID' 		=> ['NAME' => 'ORDER_ID', 		'TYPE' => 'INTEGER'],
				'CREATE_AT'		=> ['NAME' => 'CREATE_AT', 		'TYPE' => 'TIMESTAMP'],
				'SUPPLIER_ID'	=> ['NAME' => 'SUPPLIER_ID', 	'TYPE' => 'INTEGER'],
				'REFERENCE'		=> ['NAME' => 'REFERANC', 		'TYPE' => 'STRING'],
				'TOTAL_PRICE' 	=> ['NAME' => 'TOTAL_PRICE', 	'TYPE' => 'NUMERIC'],
				'TOTAL_PAYMENTS'=> ['NAME' => 'TOTAL_PAYMENTS', 'TYPE' => 'NUMERIC'],
				'STATUSE'		=> ['NAME' => 'STATUSE',		'TYPE' => 'STRING'],	// enum('DEFERRED', 'COMPLETED')
				'NOTE'			=> ['NAME' => 'NOTE', 			'TYPE' => 'STRING']
	        ]
	    ],
	    'PURCHASES_PRODUCTS' => [
	        'TABLE'  => 'PURCHASES_PRODUCTS',
	        'FIELDS' => [
	            'ORDER_ID'		=> ['NAME' => 'ORDER_ID', 			 'TYPE' => 'INTEGER'],
				'PRO_CODE'		=> ['NAME' => 'PRO_CODE', 			 'TYPE' => 'STRING'],
				'UNIT_PRICE'	=> ['NAME' => 'PURCHASE_UNIT_PRICE', 'TYPE' => 'NUMERIC'],
				'QTY'			=> ['NAME' => 'QTY', 				 'TYPE' => 'NUMERIC'],
				'QTY_SUPPLIED' 	=> ['NAME' => 'QTY_SUPPLIED', 		 'TYPE' => 'NUMERIC']
	        ]
	    ],
	    
	    'PURCHASES_RETURN_ORDERS' => [ 
	        'TABLE'  => 'PURCHASES_RETURN_ORDERS',
	        'FIELDS' => [
	        	'ORDER_ID' 		=> ['NAME' => 'ORDER_ID', 		'TYPE' => 'INTEGER'],
				'CREATE_AT'		=> ['NAME' => 'CREATE_AT', 		'TYPE' => 'TIMESTAMP'],
				'SUPPLIER_ID'	=> ['NAME' => 'SUPPLIER_ID', 	'TYPE' => 'INTEGER'],
				'REFERENCE'		=> ['NAME' => 'REFERANC', 		'TYPE' => 'STRING'],
				'TOTAL_PRICE' 	=> ['NAME' => 'TOTAL_PRICE', 	'TYPE' => 'NUMERIC'],
				'TOTAL_PAYMENTS'=> ['NAME' => 'TOTAL_PAYMENTS', 'TYPE' => 'NUMERIC'],
				'STATUSE'		=> ['NAME' => 'STATUSE',		'TYPE' => 'STRING'],	// enum('DEFERRED', 'COMPLETED')
				'NOTE'			=> ['NAME' => 'NOTE', 			'TYPE' => 'STRING']
	        ]
	    ],
	    'PURCHASES_RETURN_PRODUCTS' => [
	        'TABLE'  => 'PURCHASES_RETURN_PRODUCTS',
	        'FIELDS' => [
	            'ORDER_ID'		=> ['NAME' => 'ORDER_ID', 			 'TYPE' => 'INTEGER'],
				'PRO_CODE'		=> ['NAME' => 'PRO_CODE', 			 'TYPE' => 'STRING'],
				'UNIT_PRICE'	=> ['NAME' => 'PURCHASE_UNIT_PRICE', 'TYPE' => 'NUMERIC'],
				'QTY_SUPPLIED' 	=> ['NAME' => 'QTY_SUPPLIED', 		 'TYPE' => 'NUMERIC'],
				'QTY_RETURN'	=> ['NAME' => 'QTY_RETURN', 		 'TYPE' => 'NUMERIC']
	        ]
	    ],
	    
	    
	    
	    'SUPPLY_ORDERS' => [ 
	        'TABLE'  => 'SUPPLY_ORDERS',
	        'FIELDS' => [
	        	'ID' 			=> ['NAME' => 'ID', 			'TYPE' => 'INTEGER'],
				'ORDER_BY'		=> ['NAME' => 'ORDER_BY',		'TYPE' => 'INTEGER'],
				'STATUS'		=> ['NAME' => 'STATUS',			'TYPE' => 'STRING'],	// enum('Pending', 'Completed', 'Cancelled')
				'NOTES'			=> ['NAME' => 'NOTES', 			'TYPE' => 'STRING'],
				'CREATED_AT'	=> ['NAME' => 'CREATED_AT', 	'TYPE' => 'TIMESTAMP'],
				'UPDATED_AT'	=> ['NAME' => 'UPDATED_AT', 	'TYPE' => 'TIMESTAMP'],
	        ]
	    ],
	    'SUPPLY_ORDER_DETAILS' => [
	        'TABLE'  => 'SUPPLY_ORDER_DETAILS',
	        'FIELDS' => [
	            'SUPPLY_ORDER_ID'	=> ['NAME' => 'SUPPLY_ORDER_ID', 'TYPE' => 'INTEGER'],
				'PRO_CODE'			=> ['NAME' => 'PRO_CODE', 				 'TYPE' => 'STRING'],
				'QUANTITY'			=> ['NAME' => 'QUANTITY', 				 'TYPE' => 'NUMERIC'],
				'CREATED_AT'		=> ['NAME' => 'CREATED_AT', 			 'TYPE' => 'STRING'],
	        ]
	    ],
	    
	    'ORDERS' => [
	        'TABLE'  => 'orders',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDER_PRODUCT' => [
	        'TABLE'  => 'order_product',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDERS_LOG' => [
	        'TABLE'  => 'orders_log',
	        'FIELDS' => [
	        ]
	    ],
	    'ORDER_TYPE' => [
	        'TABLE'  => 'order_type',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDER_EXISTS' => [
	        'TABLE'  => 'order_exists',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDER_STATUSE' => [
	        'TABLE'  => 'order_statuse',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'ORDER_SERVICE' => [
	        'TABLE'  => 'order_service',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDER_SERVICE_TEXT' => [
	        'TABLE'  => 'order_service_text',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'ORDER_SERVICE_TYPE' => [
	        'TABLE'  => 'order_service_type',
	        'FIELDS' => [
	            
	        ]
	    ],

	    'PRODUCTS' => [
	        'TABLE'  => 'products',
	        'FIELDS' => [
	            'PRO_ID' 			=> ['NAME' => 'PRO_ID'	,		'TYPE' => 'INTEGER'],
				'CAT_ID' 			=> ['NAME' => 'CAT_ID',			'TYPE' => 'INTEGER'],
				'PRO_VISBLE'		=> ['NAME' => 'PRO_VISBLE',		'TYPE' => 'BOOLEAN' ],
				'PRO_NAME'			=> ['NAME' => 'PRO_NAME',		'TYPE' => 'STRING' ],
				'PER_BOOKING'		=> ['NAME' => 'PER_BOOKING',	'TYPE' => 'TINYINT' ],
				'PRO_DESCRIPTION'	=> ['NAME' => 'PRO_DESCRIPTION','TYPE' => 'STRING'],
				'LENGTH'			=> ['NAME' => 'LENGTH',			'TYPE' => 'NUMERIC'],
				'WIDTH'				=> ['NAME' => 'WIDTH',			'TYPE' => 'NUMERIC'],
				'HEIGHT'			=> ['NAME' => 'HEIGHT',			'TYPE' => 'NUMERIC'],
				'WEIGHT'			=> ['NAME' => 'WEIGHT',			'TYPE' => 'NUMERIC'],
				'PRO_IMG_LINK'		=> ['NAME' => 'PRO_IMG_LINK',	'TYPE' => 'STRING'],
				
	        ]
	    ],
	    'PRODUCT_CATEGORY' => [
	        'TABLE'  => 'product_category',
	        'FIELDS' => [
	        	'CAT_ID' 	=> ['NAME' => 'CAT_ID',		'TYPE' => 'INTEGER'],
	        	'CAT_NAME' 	=> ['NAME' => 'CAT_NAME',	'TYPE' => 'STRING' ],
	        	'CAT_PARENT'=> ['NAME' => 'CAT_PARENT',	'TYPE' => 'INTEGER'],
	        ]
	    ],
	    'PRODUCT_CODES' => [
	        'TABLE'  => 'product_codes',
	        'FIELDS' => [
	            'PRODUCT_CODE_ID' 	=> ['NAME' => 'PRODUCT_CODE_ID','TYPE' => 'INTEGER'],
				'PRODUCT_ID' 		=> ['NAME' => 'PRODUCT_ID',		'TYPE' => 'INTEGER'],
				'PRO_CODE' 			=> ['NAME' => 'PRO_CODE', 		'TYPE' => 'STRING'],
				'CODE_PRICE'		=> ['NAME' => 'CODE_PRICE',		'TYPE' => 'NUMERIC'],
				'CODE_COST_PRICE'	=> ['NAME' => 'CODE_COST_PRICE','TYPE' => 'NUMERIC'],
				'LOWEST_PRICE'		=> ['NAME' => 'LOWEST_PRICE',	'TYPE' => 'NUMERIC'],
				'CODE_MIN_STOCK'	=> ['NAME' => 'CODE_MIN_STOCK',	'TYPE' => 'NUMERIC'],
				'CODE_MAX_STOCK'	=> ['NAME' => 'CODE_MAX_STOCK',	'TYPE' => 'NUMERIC'],

	        ]
	    ],
	    'PRODUCT_CODE_JOIN_OPTION' => [
	        'TABLE'  => 'product_code_join_option',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'PRODUCT_OPTION' => [
	        'TABLE'  => 'product_option',
	        'FIELDS' => [
	            
	        ]
	    ],
	    'PRODUCT_OPTION_GROUP' => [
	        'TABLE'  => 'product_option_group',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'ORDERS_VIEW' => [
	        'TABLE'  => 'orders_view',
	        'FIELDS' => [
	            
	        ]
	    ],
	    
	    'PRODUCT_OPTIONS_VIEW' => [ 
			'TABLE'  => 'PRODUCT_OPTIONS_VIEW',
	        'FIELDS' => [
				'PRO_ID' 		=> ['NAME' => 'PRO_ID',			'TYPE' => 'INTEGER'],
				'PRO_CODE' 		=> ['NAME' => 'PRO_CODE',		'TYPE' => 'STRING'],
				'PURCHASE_PRICE'=> ['NAME' => 'PURCHASE_PRICE',	'TYPE' => 'NUMERIC'],
				'SELL_PRICE' 	=> ['NAME' => 'SELL_PRICE',		'TYPE' => 'NUMERIC'],
				'MIN_STOCK' 	=> ['NAME' => 'MIN_STOCK',		'TYPE' => 'NUMERIC'],
				'MAX_STOCK' 	=> ['NAME' => 'MAX_STOCK',		'TYPE' => 'NUMERIC'],
				'PRO_NAME' 		=> ['NAME' => 'PRO_NAME',		'TYPE' => 'STRING'],
				'OPT_IDS_JSON' 	=> ['NAME' => 'OPT_IDS_JSON',	'TYPE' => 'JSON'],
				'OPT_JSON' 		=> ['NAME' => 'OPT_JSON',		'TYPE' => 'JSON'],
			]
		],

	    'PURCHASES_ORDERS_VIEW' => [
	        'TABLE'  => 'purchases_orders_view',
	        'FIELDS' => [
	            	"ORDER_ID" 			=> ['NAME' => 'ORDER_ID',			'TYPE' => 'INTEGER'],
					"CREATE_AT" 		=> ['NAME' => 'CREATE_AT',			'TYPE' => 'TIMESTAMP'],
					"SUPPLIER_ID" 		=> ['NAME' => 'SUPPLIER_ID',		'TYPE' => 'INTEGER'],
					"SUP_NAME" 			=> ['NAME' => 'SUP_NAME',			'TYPE' => 'STRING'],
					"TEL_NUMBER" 		=> ['NAME' => 'TEL_NUMBER',			'TYPE' => 'TELEPHONE'],
					"SUP_EMAIL" 		=> ['NAME' => 'SUP_EMAIL',			'TYPE' => 'STRING'],
					"NOTE" 				=> ['NAME' => 'NOTE',				'TYPE' => 'STRING'],
					"QTY_ORDER_TOTAL"	=> ['NAME' => 'QTY_ORDER_TOTAL',	'TYPE' => 'NUMERIC'],
					"QTY_ORDER_WAITING" => ['NAME' => 'QTY_ORDER_WAITING', 	'TYPE' => 'NUMERIC'],
					"TOTAL_PRICE" 		=> ['NAME' => 'TOTAL_PRICE',		'TYPE' => 'NUMERIC'],
					"SUPPLIED_STATUSE" 	=> ['NAME' => 'SUPPLIED_STATUSE', 	'TYPE' => 'STRING'],
					"RETURN_STATUS"		=> ['NAME' => 'RETURN_STATUS',		'TYPE' => 'BOOLEAN'],
					"PAYMENT_STATUSE" 	=> ['NAME' => 'PAYMENT_STATUSE', 	'TYPE' => 'STRING'],
					"TOTAL_WAIT_PRICE" 	=> ['NAME' => 'TOTAL_WAIT_PRICE',	'TYPE' => 'NUMERIC'],
					"TOTAL_ORDER_PRICE" => ['NAME' => 'TOTAL_ORDER_PRICE',	'TYPE' => 'NUMERIC'],
					"TOTAL_SUP_PRICE" 	=> ['NAME' => 'TOTAL_SUP_PRICE', 	'TYPE' => 'NUMERIC'],
					"TOTAL_PAYMENTS" 	=> ['NAME' => 'TOTAL_PAYMENTS', 	'TYPE' => 'NUMERIC'],
					"PRODUCT_DETAILS" 	=> ['NAME' => 'PRODUCT_DETAILS', 	'TYPE' => 'JSON']
	        ]
	    ],
    ];
}
    // استرجاع الكائن الوحيد للكلاس
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DBSchema();
        }
        return self::$instance;
    }

    // الحصول على اسم الجدول
    public function getTableName($key) {
        return $this->tables[strtoupper($key)]['TABLE'] ?? null;
    }
    // الحصول على الحقول الخاصة بجدول معين
    public function getTableFieldsName($key) {
    	$transformedArray = [];
		foreach ($this->tables[strtoupper($key)]['FIELDS'] as $key => $field) {
		    $transformedArray[$key] = $field['NAME'];
		}
        return $transformedArray ?? [];
    }

    // الحصول على اسم حقل معين في جدول
    public function getField($tableKey, $fieldKey) {
        return $this->tables[strtoupper($tableKey)]['FIELDS'][strtoupper($fieldKey)] ?? null;
    }
    
    // الحصول على نوع حقل معين في جدول
    public function getFieldType($tableKey, $fieldKey) {
        return $this->tables[$tableKey]['FIELDS'][strtoupper($fieldKey)]['TYPE'] ?? null;
    }
    // الحصول على اسم حقل معين في جدول
    public function getFieldsType($key) {
        $transformedArray = [];
		foreach ($this->tables[strtoupper($key)]['FIELDS'] as $key => $field) {
		    $transformedArray[$key] = $field['TYPE'];
		}
        return $transformedArray ?? [];
    }
}   
?>