<?php
	



class DBTables {
    public $purchases_orders = [ 
        'table'  => 'purchases_orders',
        'fields' => [
        	'ORDER_ID' 		=> 'ORDER_ID',
			'CREATE_AT'		=> 'CREATE_AT',
			'SUPPLIER_ID'	=> 'SUPPLIER_ID',
			'REFERENCE'		=> 'REFERANC',
			'TOTAL_PRICE' 	=> 'TOTAL_PRICE',
			'TOTAL_PAYMENTS'=> 'TOTAL_PAYMENTS',
			'STATUSE'		=> 'STATUSE',	// enum('DEFERRED', 'COMPLETED')
			'NOTE'			=> 'NOTE'
        ]
    ];
    public $purchases_products = [
        'table'  => 'purchases_products',
        'fields' => [
            'ORDER_ID'		=> 'ORDER_ID',
			'PRO_CODE'		=> 'PRO_CODE',
			'UNIT_PRICE'	=> 'PURCHASE_UNIT_PRICE',
			'QTY'			=> 'QTY',
			'QTY_SUPPLIED' 	=> 'QTY_SUPPLIED'
        ]
    ];
    
    public $purchases_orders_view = [
        'table'  => 'purchases_orders_view',
        'fields' => [
            	"ORDER_ID" => "ORDER_ID",
				"CREATE_AT" => "CREATE_AT",
				"SUPPLIER_ID" => "SUPPLIER_ID",
				"SUP_NAME" => "SUP_NAME",
				"TEL_NUMBER" => "TEL_NUMBER",
				"SUP_EMAIL" => "SUP_EMAIL",
				"NOTE" => "NOTE",
				"TOTAL_PRICE" => "TOTAL_PRICE",
				"SUPPLIED_STATUSE" => "SUPPLIED_STATUSE",
				"PAYMENT_STATUSE" => "PAYMENT_STATUSE",
				"TOTAL_WAIT_PRICE" => "TOTAL_WAIT_PRICE",
				"TOTAL_ORDER_PRICE" => "TOTAL_ORDER_PRICE",
				"TOTAL_SUP_PRICE" => "TOTAL_SUP_PRICE",
				"TOTAL_PAYMENTS" => "TOTAL_PAYMENTS",
				"PRODUCT_DETAILS" => "PRODUCT_DETAILS"
        ]
    ];
    /*public static entities = [
        'table'  => 'entities',
        'fields' => [
            
        ]
    ];*/
    
    /* public static product_join_option_group = [
        'table'  => 'product_join_option_group',
        'fields' => [
            
        ]
    ];*/

}

?>