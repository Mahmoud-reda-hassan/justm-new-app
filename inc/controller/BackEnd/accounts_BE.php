<?php 
include_once(__dir__.'/../../configDateTimeFN.php');
class accounts_BE {
    private static $ordersTbl 		= 'orders';
    private static $cusOrderTbl 	= 'custody_orders';
    private static $cusProductTbl 	= 'custody_product';
    private static $oStatuseTbl 	= 'order_statuse';
    private static $orderProduct 	= 'order_product';
    private static $productCodes 	= 'product_codes';
    private static $products 		= 'products';
    private static $proCodeJoinOpt 	= 'product_code_join_option';
    private static $productOption 	= 'product_option';
    private static $productOptiGrou = 'product_option_group';
    private static $oTypeTbl 		= 'ORDER_TYPE';
    private static $usersTbl 		= 'USERS';
    public 	$rq_parm ;
	public  $delivery_id ;
	
    //private $oExistisTbl 	= 'ORDER_EXISTS';
    
    //private $addressTbl 	= 'ADDRESSES';
    //private $clientTbl 	= 'CLIENT';
    //private $telephonTbl 	= 'TELEPHONES';
    //private $cityTbl 		= 'CITY';
    //private $govTbl 		= 'GOV';
    //private $zonesTbl 	= 'ZONES';
    //private $oProTbl 		= 'ORDER_PRODUCT';
    //private $proCodeTbl 	= 'PRODUCT_CODES';
    //private $prodTbl 		= 'PRODUCTS';
    //private $invoTbl 		= 'invoices';
    //private $oservTbl 	= 'order_service';
    //private $osertexTbl 	= 'order_service_text';
    
    
    
    private $dtz ;
    private $conn;
	public function __construct 		($db ) {
        $this->conn = $db;
        $this->dtz = new configDateTime();
    }
    private function main_query ( $fi,$us,$columns='') {
    	$sqlLIMIT = "";
        $sqlSEARCH = "";
        $sqlhaving='';
    	$sqlSELECT = " SELECT *
			FROM  (SELECT  CO.ORDER_ID,
			   O.SUB_ORDER_ID,
			   Concat(UC.FORENAME, ' ', UC.LASTNAME)   AS ORDERBY,
			   Concat(UD.FORENAME, ' ', UD.LASTNAME)   AS DELIVERYBY,
			   O.DELIVERY_DATE,
			   O.SHIPPING_DATE,
			   O.DELIVERED_DATE,
			   O.ORDER_SUCCESS,
			   O.LAST_UPDATE,
			   O.DATE_CREATED,
			   H.CLIENT_NAME,
			   H.CLIENT_EMAIL,
			   O.DELIVERY_TYPE,
			   L.SHIPPING_TIME_MAX,
			   L.ZONE_NAME,
			   L.SHIPPING_LOWEST,
			   L.SHIPPING_RATE,
			   CO.SHIPPING_ADDRESS,
			   O.TELEPHONE,
			   OT.ORDER_TYPE_NAME,
			   CO.USER_ID AS DELIVERYID,
			   CO.CUST_STAT,
			   OS.STA_ID,
			   OS.STA_NAME,
			   CASE WHEN CO.CUST_STAT = 'FREEZE' THEN 'ايقاف التسليم'ELSE OS.STA_NAME END AS ORDER_STATUSE,
			   CASE WHEN CO.ORDER_STATUSE = 3 THEN 1 ELSE CO.RETURNS END              AS INVENT_STAT,
			   CO.ORDER_STATUSE AS ORDER_STATUSE_ID,
			   OS.COLOR_CLASS,
			   CO.RETURNS,
			   Count(DISTINCT CP.ORDER_ID)                                            AS PRODUCTS_COUNT,
			   Round(Sum(CP.QTY), 2)                                                  AS TOTAL_QTY,
			   Round(Sum(CP.QTY - CP.DELIVERED_QTY), 2)                               AS REJECTED_QTY,
			   Round(Sum(CP.DELIVERED_QTY), 2)                                        AS DELIVERED_QTY,
			   Round(Sum(CP.QTY * CP.PRICE), 2)                                       AS TOTAL_PRICE,
			   Round(Sum(CP.QTY * CP.DISCOUNT), 2)                                    AS TOTAL_DISCOUNT,
			   Round(Sum( if( CO.ORDER_STATUSE > 3 AND CO.RETURNS <> 1 , 0 ,  CP.QTY - CP.DELIVERED_QTY )  * (CP.PRICE - CP.DISCOUNT) ), 2) AS TOTAL_REJECTED_PRICE,
			   Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * (CP.PRICE - CP.DISCOUNT) ), 2) AS TOTAL_DELIVERED_PRICE,
			   Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * CP.DISCOUNT), 2) AS TOTAL_DELIVERED_DISCOUNT,
			   Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * ( CP.PRICE - CP.DISCOUNT )), 2) AS ACTUAL_TOTAL,
			   
			   Round(if(CO.CUST_STAT = 'FREEZE' ,0,O.SHIPPING_VAL), 2) AS SHIPPING_VAL,
			   
			   Round(Sum(if(CO.RETURNS = 0 ,CP.QTY ,CP.DELIVERED_QTY) * ( CP.PRICE - CP.DISCOUNT )) + if(CO.CUST_STAT = 'FREEZE',0,O.SHIPPING_VAL), 2) AS REQUIRED_PAYMENT,
			   
			   Round(PB.BALANCE,2) AS BP_REQUIRED_PAYMENT,
			   
			   Json_arrayagg(
			   	Json_object(
			   		'PRODUCT_CODE', 		VOP.PRO_CODE, 
			   		'PRODUCT_NAME', 		VOP.PRO_NAME, 
			   		'PRODUCT_ATTRIBUTES', 	Ifnull(VOP.OPT_JSON, ''), 
			   		'ORDER_QTY', 			Format(CP.QTY, 2), 
			   		'RETURNED_QTY',  		Format( if( CO.RETURNS = 0 , 0 , CP.QTY - CP.DELIVERED_QTY),2) ,
			   		'DELIVERED_QTY', 		Format( if( CO.RETURNS = 0 , CP.QTY , CP.DELIVERED_QTY),2), 
			   		'PRICE',  				Format(CP.PRICE, 2), 
			   		'DISCOUNT', 			Format(CP.DISCOUNT, 2), 
			   		'REQUIRED_PAYMENT', 	Format( If( CO.RETURNS = 0 ,  CP.QTY ,CP.DELIVERED_QTY) * ( CP.PRICE - CP.DISCOUNT ), 2)
			   	)
			   ) AS PRODUCTS_DATA
			 FROM   custody_orders AS CO
			   LEFT JOIN custody_product 	AS CP ON CO.ORDER_ID = CP.ORDER_ID AND CO.USER_ID = CP.USER_ID
			   LEFT JOIN ORDERS 			AS O  ON O.ORDER_ID = CO.ORDER_ID
			   LEFT JOIN order_statuse 		AS OS ON CO.ORDER_STATUSE = OS.STA_ID
			   LEFT JOIN ORDER_TYPE 		AS OT ON O.ORDER_TYPE_ID = OT.ORDER_TYPE_ID
			   LEFT JOIN USERS 				AS UD ON CO.USER_ID = UD.USER_ID
			   LEFT JOIN USERS 				AS UC ON O.ORDER_BY = UC.USER_ID
			   LEFT JOIN ADDRESSES 			AS G ON O.ADDRESS_ID = G.ADDRESS_ID
			   LEFT JOIN CLIENT 			AS H ON G.CLIENT_ID = H.CLIENT_ID
			   LEFT JOIN CITY 				AS K ON O.CITY_ID = K.CI_ID
			   LEFT JOIN ZONES 				AS L ON K.ZONE_ID = L.ZONE_ID
			   LEFT JOIN PARTNERS_BALANCE 	AS PB ON CO.ORDER_ID = PB.REFERENCE_ID AND PARTNER_TYPE = 'CLIENT' AND TRANSACTION_CATEGORY_ID = 8
			   LEFT JOIN (SELECT  CP.ORDER_ID,
			       CP.USER_ID,
			       CP.PRO_CODE,
			       PR.PRO_NAME,
			       Concat(Group_concat(Concat(E.OPTION_NAME, ' : ', D.VALUE))) AS OPT_JSON
			     FROM custody_product CP
			       LEFT JOIN product_codes 			  AS PC ON CP.PRO_CODE = PC.PRO_CODE
			       LEFT JOIN products 				  AS PR ON PC.PRODUCT_ID = PR.PRO_ID
			       LEFT JOIN product_code_join_option AS C  ON PC.PRO_CODE = C.PRODUCT_CODE
			       LEFT JOIN product_option 		  AS D  ON D.ID = C.OPTION_VALUE_ID
			       LEFT JOIN product_option_group 	  AS E  ON E.OPTION_ID = D.OPTION_ID
			     GROUP BY CP.ORDER_ID,
			       CP.USER_ID,
			       CP.PRO_CODE,
			       PR.PRO_NAME) AS VOP ON CP.ORDER_ID = VOP.ORDER_ID AND CP.PRO_CODE = VOP.PRO_CODE AND CP.USER_ID = VOP.USER_ID
			 WHERE  ( O.ORDER_STATUS = 5 AND CO.RETURNS = 0 ) OR O.ORDER_STATUS < 5 
			 GROUP BY CO.ORDER_ID,
			   O.SUB_ORDER_ID,
			   Concat(UC.FORENAME, ' ', UC.LASTNAME),
			   Concat(UD.FORENAME, ' ', UD.LASTNAME),
			   O.DELIVERY_DATE,
			   O.ORDER_SUCCESS,
			   O.LAST_UPDATE,
			   O.DATE_CREATED,
			   O.SHIPPING_DATE,
			   OT.ORDER_TYPE_NAME,
			   CO.USER_ID,
			   CO.CUST_STAT,
			   OS.STA_ID,
			   OS.STA_NAME,
			   OS.COLOR_CLASS,
			   CO.RETURNS) AS DC ";
        
        //Condition
        $sqlWHERE   = self::query_Where($this->rq_parm , $fi);
        $sqlwex     = self::query_Where($this->rq_parm , $fi,FALSE);
        
        $sqlORDERBY = self::query_orderby($this->rq_parm , $this->rq_parm['columns'] );
        $sqlLIMIT   = self::query_limit($this->rq_parm , $this->rq_parm['columns'] );

        $wh_result  = $sqlWHERE . $sqlSEARCH . $sqlORDERBY . $sqlLIMIT;
		$wh_total   = $sqlwex ;
	//	$Query_odata= $Query_total;
		
        /*return array(
        	"result" 	=> $Query_result,
        	"total"  	=> $Query_total ,
        	"odata"  	=> $Query_odata,
        	"QueryData" => $sqlSELECT 
        );*/
        return array(
        	"wh_result" 	=> $wh_result,
        	"wh_total"  	=> $wh_total ,
        	//"odata"  	=> $Query_odata,
        	"QueryData" => $sqlSELECT 
        );
    }
    private function custodyTotal  (){
    	$wh ='';
    	$userID = null;
    	if(isset($this->rq_parm['did']) && (int) $this->rq_parm['did'] > 0){
			$userID  = htmlspecialchars(strip_tags(trim($this->rq_parm['did'])));
    		$wh = 'CO.USER_ID = :uid AND ';
    	}
    	$query = "
    		SELECT 
			  SUM(REQUIRED_PAYMENT) AS total,
			  SUM(IF(ORDER_STATUSE < 3 ,REQUIRED_PAYMENT,0)) AS underDeli,
			  SUM(IF(INVENT_STAT = 0 AND ORDER_STATUSE >= 3 ,REQUIRED_PAYMENT,0))   AS invTotal,
			  SUM(IF(INVENT_STAT = 1 AND ORDER_STATUSE >= 3,REQUIRED_PAYMENT,0))   AS finTotal,
			  (SELECT sum(CASE WHEN TC.TRANSACTION_TYPE = 'CASH_IN' THEN amount ELSE 0 END) FROM financial_transactions FT LEFT JOIN transaction_categories TC ON FT.category_id = TC.ID WHERE branch_id = ".$_SESSION['BRANCH_ID'].") AS CASH_IN ,
			  (SELECT sum(CASE WHEN TC.TRANSACTION_TYPE = 'CASH_OUT' THEN amount ELSE 0 END) FROM financial_transactions FT LEFT JOIN transaction_categories TC ON FT.category_id = TC.ID WHERE branch_id = ".$_SESSION['BRANCH_ID'].") AS CASH_OUT , 
			  (SELECT sum(CASE WHEN TC.TRANSACTION_TYPE = 'CASH_IN' THEN amount WHEN TC.TRANSACTION_TYPE = 'CASH_OUT' THEN -amount ELSE 0 END) FROM financial_transactions FT LEFT JOIN transaction_categories TC ON FT.category_id = TC.ID WHERE branch_id = ".$_SESSION['BRANCH_ID'].") AS cashBalance 

			FROM  (SELECT   
			   CO.ORDER_ID,   
			   CO.USER_ID,
			   CO.CUST_STAT,
			   CO.ORDER_STATUSE,
			   CASE WHEN CO.ORDER_STATUSE = 3 THEN 1 ELSE CO.RETURNS END AS INVENT_STAT,
			   CO.ORDER_STATUSE               AS ORDER_STATUSE_ID,
			   CO.RETURNS,
			   Round(If(CO.CUST_STAT = 'FREEZE' ,0,O.SHIPPING_VAL), 2) AS SHIPPING_VAL,
			   Round(Sum(If(CO.RETURNS = 0 ,CP.QTY ,CP.DELIVERED_QTY) * ( CP.PRICE - CP.DISCOUNT )) + If(CO.CUST_STAT = 'FREEZE',0,O.SHIPPING_VAL), 2) AS REQUIRED_PAYMENT
			 FROM   custody_orders AS CO
			   LEFT JOIN custody_product AS CP ON CO.ORDER_ID = CP.ORDER_ID AND CO.USER_ID = CP.USER_ID
			   LEFT JOIN ORDERS AS O ON O.ORDER_ID = CO.ORDER_ID
			 WHERE ".$wh." (( O.ORDER_STATUS = 5 AND CO.RETURNS = 0 ) OR O.ORDER_STATUS < 5)
			 GROUP BY CO.ORDER_ID,
			   CO.USER_ID,
			   CO.CUST_STAT,
			   CO.ORDER_STATUSE,
			   CASE WHEN CO.ORDER_STATUSE = 3 THEN 1 ELSE CO.RETURNS END,
			   CO.ORDER_STATUSE,
			   CO.RETURNS) AS DC ";
    	$stmtodata 	= $this->conn->prepare($query);
	    if($userID){
	    	$stmtodata->bindValue(':uid' , $userID  , PDO::PARAM_INT);
	    }
        $stmtodata->execute();
        $returnData = array();
        while ($record = $stmtodata->fetch(PDO::FETCH_ASSOC)) {
         $returnData['total']  = $record['total'] !== NULL ? $record['total'] : 0;
         $returnData['underDeli'] = $record['underDeli'] !== NULL ? $record['underDeli'] : 0;
         $returnData['invTotal'] = $record['invTotal'] !== NULL ? $record['invTotal'] : 0;
         $returnData['finTotal'] = $record['finTotal'] !== NULL ? $record['finTotal']: 0;
         $returnData['CASH_IN'] = $record['CASH_IN'] !== NULL ? $record['CASH_IN']: 0;
         $returnData['CASH_OUT'] = $record['CASH_OUT'] !== NULL ? $record['CASH_OUT']: 0;
         $returnData['cashBalance'] = $record['cashBalance'] !== NULL ? $record['cashBalance']: 0;
         
        }
        return $returnData;
    }
    private function query_limit   (&$request, $columns ) {
    // إنشاء جملة  limit  لاستعلام SQL لمعالجة البيانات من جانب الخادم
		$limit = '';
		if ( isset($request['start']) && $request['length'] != -1 ) {
			$limit = " LIMIT ".intval($request['start']).", ".intval($request['length']." ");
		}
		return $limit;
	}
	private function query_orderby (&$request ) {
	// إنشاء جملة ORDER BY لاستعلام SQL لمعالجة البيانات من جانب الخادم
		/**
		* الترتيب
		*
		* إنشاء جملة ORDER BY لاستعلام SQL لمعالجة البيانات من جانب الخادم
		*
		* @param  array $request البيانات المرسلة إلى الخادم بواسطة DataTables
		* @return string جملة ترتيب SQL
		*/
		// 1. قائمة الأعمدة المسموح الترتيب بها (مطابقة لترتيب columns في DataTables)
		$allowedColumns = [
			0 => 'ORDER_ID',
		    1 => 'SUB_ORDER_ID',
		    2 => 'ORDER_STATUSE',
		    3 => 'CLIENT_NAME',
		    4 => 'DELIVERYBY',
		    5 => 'DELIVERED_DATE',
		    6 => 'TOTAL_AMOUNT',
		    // ... إلخ
		];
		
		$orderSql = '';
		
		// 2. التحقق من وجود بيانات الترتيب
		if (!empty($request['order']) && is_array($request['order'])) {
		
		    $orderParts = [];
		
		    // 3. دعم أكثر من عمود ترتيب (DataTables يسمح بذلك)
		    foreach ($request['order'] as $ord) {
		
		        if (!isset($ord['column'], $ord['dir'])) {
		            continue;
		        }
		
		        $colIndex = (int) $ord['column']; // تحويل صريح إلى رقم
		        $dir      = strtolower($ord['dir']) === 'desc' ? 'DESC' : 'ASC'; // قيمة آمنة
		
		        // 4. التأكد أن العمود موجود في القائمة البيضاء
		        if (array_key_exists($colIndex, $allowedColumns)) {
		            $orderParts[] = $allowedColumns[$colIndex] . ' ' . $dir;
		        }
		    }
		
		    if (!empty($orderParts)) {
		        $orderSql = ' ORDER BY ' . implode(', ', $orderParts);
		    }
		}
		/*$order = '';
		if(isset($request['order'])){
            $order = ' ORDER BY ORDER_ID  '.strtoupper($request['order']['0']['dir']);
        }*/
		return $orderSql;
	}
	private function query_Where   (&$request, $fi, $bool=TRUE ) {
		$globalSearch = array();
		$globalwhere  = array();
		$filterwhere  = array();
		$where = '';
		//====================== Global Where ==========================
		if(!empty($request["did"]) && $request["did"]){
			$request["did"] = htmlspecialchars(strip_tags(trim($request["did"])));
			$globalwhere[] = " DELIVERYID = ".$request["did"];
		}
		//====================== Filter Where ==========================
        //تستخدم الفلاتر التالية فى كل الصفحات معاده صفحة المندوب
        if (!empty($request["ptype"]) && $bool && $request["ptype"] != 3) {
        	//تصفية حسب نوع الشحن
        	if (!empty($fi["shippingType"]['id'])) {
            	$filterwhere[] = 'SHIPPING_TYPE = ' . $fi["shippingType"]['id'];
        	}
        	
	        //تصفية الاوامر حسب نوع المنتج
	        if (!empty($fi["products"]['id'])) {
	            $filterwhere[] = 'ORDER_ID IN (SELECT ORDER_ID FROM ORDER_PRODUCT AS OP LEFT JOIN PRODUCT_CODES AS PC ON OP.PRO_CODE = PC.PRO_CODE WHERE PC.PRODUCT_ID IN  ('. implode(',', $fi["products"]['id']) . ') GROUP BY ORDER_ID) ';
	        }
	        
        }
		
		//====================== Global Search ==========================
		if ( isset($request['search']) && $bool && $request['search']['value'] != '') {
            $str = $request['search']['value'];
            $globalSearch[] = 'SUB_ORDER_ID	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'ORDERBY 		LIKE "%'.$str.'%" ';
            $globalSearch[] = 'DELIVERYBY 	LIKE "%'.$str.'%" ';
   			$globalSearch[] = 'TELEPHONE    LIKE "%'.$str.'%" ';
            $globalSearch[] = 'SHIPPING_ADDRESS  	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_NAME 	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_EMAIL	LIKE "%'.$str.'%" ';
        }
        
        //====================== Columns Search ==========================
	    
	    //البحث عن امر البيع
	    if (!empty($request['columns'][1]['search']['value'])) {
	    	$filterwhere[] = 'SUB_ORDER_ID LIKE "%' . $request['columns'][1]['search']['value'].'%"';
	    }
	    
        //تصفية حالة امر البيع
	    if (!empty($request['columns'][2]['search']['value'])) {
	    	$filterwhere[] = 'STA_ID = ' . $request['columns'][2]['search']['value'];
	    }
	    
	    //البحث عن اسم العميل
	    if (!empty($request['columns'][3]['search']['value'])) {
	        $filterwhere[] = 'CLIENT_NAME LIKE "' . $request['columns'][3]['search']['value'].'%"';
	    }
	    
	    //البحث عن اسم المندوب
	    if (!empty($request['columns'][4]['search']['value'])) {
	        $filterwhere[] = 'DELIVERYBY LIKE "%' . $request['columns'][4]['search']['value'].'%"';
	    }
	    
	    //تصفية الاوامر حسب التاريخ
	    if (!empty($request['columns'][5]['search']['value'])) {
	    	list($start, $end) = explode(' - ', $request['columns'][5]['search']['value']);
	    	$filterwhere[] = 'SHIPPING_DATE BETWEEN "'.$start.' 00:00:00.000" AND "'.$end.' 23:59:59.999" ';
	    }
	    
	    //البحث عن سعر البيع
	    if (!empty($request['columns'][6]['search']['value'])) {
	    	//$filterwhere[] = 'ORDER_STATUS = ' . $request['columns'][6]['search']['value'];
	    }
		
        //=================================================================
		if(isset($_GET['cofi']) ){
			if($_GET['cofi'] === "OLUnderSettlement"){
		   		$globalwhere[] = ' INVENT_STAT = 1 AND ORDER_STATUSE_ID IN(4,3) ';
		   	}else if($_GET['cofi'] === "OLDelivered"){
		    	$globalwhere[] = 'INVENT_STAT = 0 AND ( ORDER_STATUSE_ID > 3 or CUST_STAT = "FREEZE")';
		   	}else if($_GET['cofi'] === "OLUnderDelivery"){
		    	$globalwhere[] = 'ORDER_STATUSE_ID < 3 AND CUST_STAT = "WAITING"';
		   	}
		}
		
		// Combine the filters into a single string
		$where = '';
		$having = '';
		
		if ( count( $globalwhere ) ) {
			$where = ' '.implode(' AND ', $globalwhere);
		}
		if ( count( $filterwhere ) ) {
			$where = $where === '' ?
				' '.implode(' AND ', $filterwhere):
				$where .' AND '.implode(' AND ', $filterwhere);
			
		}
		if ( count( $globalSearch ) ) {
			$where = $where === '' ?
				'('.implode(' OR ', $globalSearch).')':
				$where .' AND ('.implode(' OR ', $globalSearch).')';
		}
	
		if ( $where !== '' ) {
			$where = ' WHERE '.$where;
		}
		return $where ;
	}
    public 	function listAccount 		() {
        
	    try{
	        $oidarr   = array();
	        $records  = array();
	        $us       = unserialize($_SESSION['USER_SCRIPT']);
	        $fi       = '';//json_decode($_COOKIE['filt_'. str_replace("*","_",$this->rq_parm['url'])], true);
	        $sqlQuery = self::main_query($fi,$us);
	       
	        
	      // echo $sqlQuery['wh_result'];
	        
	        $stmt 		= $this->conn->prepare($sqlQuery['QueryData'].$sqlQuery['wh_result']); //$sqlSELECT . $sqlWHERE . $sqlSEARCH . $sqlGROUP . $sqlhaving . $sqlORDERBY);
	        $stmt->execute();
	        
	        $stmtTotal 	= $this->conn->prepare($sqlQuery['QueryData'].$sqlQuery['wh_total']); //$sqlSELECT . $sqlwex . $sqlGROUP . $sqlhaving);
	        $stmtTotal->execute();
	        
	        /*$stmtodata 	= $this->conn->prepare($sqlQuery['odata']); //$sqlSELECT . $sqlWHERE . $sqlSEARCH . $sqlGROUP . $sqlhaving . $sqlORDERBY . $sqlLIMIT);
	        $stmtodata->execute();*/
	        
	        $custodyTotal = self::custodyTotal (); 
	        
	        $result 		= $stmt;
	        $allResult 		= $stmtTotal;
	        $allRecords 	= $allResult->rowCount();
	        $displayRecords = $result->rowCount();
	        
	        while ($record = $stmtTotal->fetch(PDO::FETCH_ASSOC)) {
	            $oidarr[$record['ORDER_ID']]= $record;
	        }
	        
			if($this->rq_parm["CONTROL"] == "Orderlist"){
				$usex = $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]];
			} elseif($this->rq_parm["CONTROL"] == "OrdersCustody"){
				$usex = $us['Admin'][$this->rq_parm["CONTROL"]];
			}
	       // $usex = $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]];
	
	        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
	            /*
	            *    ------مفتاح العرض---------
	            *    يتم اغلاقه فى حالة عدم وجود صلاحيه للمستخدم
	            *    ------مفتاح التعديل--------
	            *    يتم اغلاقة فى حالة عدم وجود صلاحية للمستخدم او فى حالة طلب حذف الامر او فى حالة جاهز للشحن او فى حالة جارى الشحن 
	            *    ------مفتاح التحويل--------
	            *    يتم اغلاقة فى حالة عدم وجود اى صلاحية تحويل للمستخدم او فى حالة جارى الشحن او فى حالة طلب حذف المنتج
	            *    ------مفتاح الحذف--------
	            */
	            if($this->rq_parm["CONTROL"] == "Orderlist"){
					$usex = $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]];
				} elseif($this->rq_parm["CONTROL"] == "OrdersCustody"){
					$usex = $us['Admin'][$this->rq_parm["CONTROL"]];
				} elseif($this->rq_parm["CONTROL"] == "ListOrdersMarketer"){
					$usex = true;
				}
	            $rowsTbl = array();
	            //$rowsTbl[2] = self::listOrders_btns($this->rq_parm,$record,$usex);
				$badgcolor = " badge-light ";
				if($record['CUST_STAT']==="FREEZE"){
					$badgcolor = " badge-red50 ";
				}
	                      
	            $rowsTbl[0] = $record['ORDER_ID']; 
				$rowsTbl[1] = $record['SUB_ORDER_ID'];
				$rowsTbl[2] = $record['ORDERBY'];
				$rowsTbl[3] = $record['DELIVERYBY'];
				$rowsTbl[4] = $record['DELIVERY_DATE'];
				$rowsTbl[5] = $record['ORDER_SUCCESS'];
				$rowsTbl[6] = $record['LAST_UPDATE'];
				$rowsTbl[7] = date_format($this->dtz->setDateTime($record["DATE_CREATED"]), "Y/m/d") .' | '.date_format($this->dtz->setDateTime($record["DATE_CREATED"]), "H:i A") ;
				$rowsTbl[8] = $record['ORDER_TYPE_NAME'];
				$rowsTbl[9] = $record['DELIVERYID'];
				$rowsTbl[10] = $record['CUST_STAT'];
				$rowsTbl[11] = $record['STA_ID'];
				$rowsTbl[12] = $record['STA_NAME'];
				$rowsTbl[13] = $record['ORDER_STATUSE'];
				$rowsTbl[14] = $record['INVENT_STAT'];
				$rowsTbl[15] = $record['COLOR_CLASS'];
				$rowsTbl[16] = $record['RETURNS'];
				$rowsTbl[17] = $record['PRODUCTS_COUNT'];
				$rowsTbl[18] = $record['TOTAL_QTY'];
				$rowsTbl[19] = $record['REJECTED_QTY'];
				$rowsTbl[20] = $record['DELIVERED_QTY'];
				$rowsTbl[21] = $record['TOTAL_PRICE'];
				$rowsTbl[22] = $record['TOTAL_DISCOUNT'];
				$rowsTbl[23] = $record['TOTAL_REJECTED_PRICE'];
				$rowsTbl[24] = $record['TOTAL_DELIVERED_PRICE'];
				$rowsTbl[25] = $record['TOTAL_DELIVERED_DISCOUNT'];
				$rowsTbl[26] = $record['ACTUAL_TOTAL'];
				$rowsTbl[27] = $record['SHIPPING_VAL'];
				$rowsTbl[28] = $record['REQUIRED_PAYMENT'];
				$rowsTbl[29] = $record['PRODUCTS_DATA'];
	            $rowsTbl[30] = $record['CLIENT_NAME'];
				$rowsTbl[31] = $record['SHIPPING_ADDRESS'];
				$rowsTbl[32] = $record['TELEPHONE'];
				$rowsTbl[33] = isset($record['CLIENT_EMAIL']) ? $record['CLIENT_EMAIL'] : 'لايوجد';
				$rowsTbl[34] = $record['SHIPPING_DATE'];
				$rowsTbl[35] = $record['DELIVERED_DATE'];
	            $row['DELIVERY_TYPE'] = $record['DELIVERY_TYPE'];
	            $row['SHIPPING_TIME_MAX'] 	= $record['SHIPPING_TIME_MAX'];
	            
	            
	     /*       
	            $rowsTbl[30] = '<div class="badge '.$badgcolor.'"  style="font-size: 1em;width:100%;height:auto">
				                <div class="d-flex justify-content-between m-2">
				                	 <div class="badge"><i class="fas fa-truck-fast"></i> '.$record['DELIVERYBY'].' </div>
				                    <div class="badge '.$invstatClass.'"><i class="fa-solid fa-display"></i>  '.$record['INVENT_STAT'].'</div>
				                </div>
				                <hr class="mb-1 mt-1">
				                <div class="d-flex justify-content-between m-2">
				                     <div class="text-primary"> '.$record['SUB_ORDER_ID'].'</div>
									<div class="badge '.$record["COLOR_CLASS"].'"> <i class="fa-regular fa-clock"></i> '.$record['STA_NAME'].'</div>
				                </div>
				                <hr class="mb-1 mt-1">
				                <div class="d-flex justify-content-between m-2">
				                    <div class="badge"><i class="far fa-calendar-plus"></i> '.date_format($this->dtz->setDateTime($record["DATE_CREATED"]), "Y/m/d") .' | '.date_format($this->dtz->setDateTime($record["DATE_CREATED"]), "H:i") .'</div>
	                                <div class="badge"><i class="fa fa-truck" aria-hidden="true"></i>'. date_format(date_create($record['DELIVERY_DATE']), "Y/m/d").'</div>
	                            </div>
	                        </div>';
	
	            $rowsTbl[40] = '<div class="badge '.$badgcolor.' p-1"  style="font-size: 1em;width:100%;height:auto">
	                            <div class="d-flex justify-content-between m-2">
							        <div class="badge"><i class="fas fa-user"></i> $record[CLIENT_NAME]</div>
							        <div class="badge"><i class="fas fa-mobile-alt"></i> $record[TELEPHONE]</div>
							    </div>
							    <hr class="mb-1 mt-1">
						        <div class="d-flex justify-content-between m-2">
						            <div class="badge" ><i class="fas fa-shopping-cart"></i> المنتجات '.$record['PRODUCTS_COUNT'].' الكمية '. $record['TOTAL_QTY'].' تكلفة المنتجات '.$record['Required_payment'].'</div>
							    </div>
							    <hr class="mb-1 mt-1">
							    <div class="d-flex justify-content-between m-2">
							        <div class="badge"><i class="fas fa-truck-fast"></i> $record[ZONE_NAME]</div>
						            <div class="badge"><i class="fa fa-map-location-dot"></i><span class"text-primary"> $record[GOV_NAME] - $record[CI_NAME] </span></div>
						        </div>
							</div>';
				$rowsTbl[50] = 1;
	*/
	                
	            $records[] = $rowsTbl;
	        } 
			
	        if(!isset($this->rq_parm["draw"])){
	            $this->rq_parm["draw"] = 0 ;
	        }
			$defaultData = array(
	            "draw"                  => intval($_GET["draw"]),
	            "iTotalRecords"         => 0,
	            "iTotalDisplayRecords"  => 0,
	            "data"                  => [],
	            "cuTotal"               => $custodyTotal,
	            "odata"                 => []
	        );
	        
	        if($displayRecords){
	        	$defaultData["draw"]                  = intval($_GET["draw"]);
	            $defaultData["iTotalRecords"]         = $displayRecords;
	            $defaultData["iTotalDisplayRecords"]  = $allRecords;
	            $defaultData["data"]                  = $records;
	            //$defaultData["cuTotal"]               = ;
	            $defaultData["odata"]             	  = $oidarr;
	        }
	        $output = $defaultData;
	
	        return $output;
	        $this->conn = null;
	        exit();
	    } catch (Exception $e){
		    return $e->getMessage();
		}
    }
    
}

?>