<?php

//include_once(__dir__.'/../../../config.php');
include_once(ABSPATH.'/inc/configDateTimeFN.php');
class ordersList_BE {
	public 	$rq_parm;
    private $ordersTbl 		= 'ORDERS';
    private $oExistisTbl 	= 'ORDER_EXISTS';
    private $oStatuseTbl 	= 'ORDER_STATUSE';
    private $oTypeTbl 		= 'ORDER_TYPE';
    private $usersTbl 		= 'USERS';
    private $addressTbl 	= 'ADDRESSES';
    private $clientTbl 		= 'CLIENT';
    private $telephonTbl 	= 'TELEPHONES';
    private $cityTbl 		= 'CITY';
    private $govTbl 		= 'GOV';
    private $zonesTbl 		= 'ZONES';
    private $oProTbl 		= 'ORDER_PRODUCT';
    private $proCodeTbl 	= 'PRODUCT_CODES';
    private $prodTbl 		= 'PRODUCTS';
    private $invoTbl 		= 'invoices';
    private $oservTbl 		= 'order_service';
    private $osertexTbl 	= 'order_service_text';
    private $cusOrderTbl 	= 'custody_orders';
    
    private $dtz ;
    private $conn;
	
	public  function __construct 		($db ) {
        $this->conn = $db;
        $this->dtz = new configDateTime();
        
    }
	private function listOrders_queries($fi, $us, $columns = '', $useLimit = true) {
	    // شروط البحث
	    $sqlWHEREpv = self::listOrders_Where($this->rq_parm, $fi, false, false, false);
	    $sqlWHERE   = self::listOrders_Where($this->rq_parm, $fi);
	    $sqlwex     = self::listOrders_Where($this->rq_parm, $fi, false);
	
	    // الاستعلام الأساسي
	    $sqlSELECT = "
	        SELECT * FROM (
	            SELECT
	                A.ORDER_ID,
	                A.ORDER_BY,
	                A.ORDER_TYPE_ID,
	                A.SUB_ORDER_ID,
	                A.EXISTS_ID,
	                X.DELIVERY_ID,
	                X.CUST_STAT,
	                X.RETURNS,
	                X.REFUND_STATUS,
	                X.ORDER_STATUS,
	                A.DATE_CREATED,
	                A.DELIVERY_DATE,
	                A.SHIPPING_DATE,
	                A.DELIVERED_DATE,
	                EXS.EXCHANGE AS IS_EXCHANGE,
	                A.TELEPHONE,
	                GREATEST(
	                    COALESCE(A.LAST_UPDATE, '1900-01-02'),
	                    COALESCE(EXO.LAST_UPDATE, '1900-01-02'),
	                    COALESCE(OSE.OSTX_DATE,1900-01-02)
	                ) AS LAST_UPDATE,
	                A.SHIPPING_VAL,
	                A.STO_ID,
	                A.ORDER_SUCCESS,
	                CASE WHEN A.ORDER_SUCCESS = 0 THEN 'مفتوح' ELSE 'مغلق' END AS SUCCESS_STAT,
	                A.PRINT,
	                C.COLOR_CLASS,
	                A.EXCHANGE,
	                A.CITY_ID,
	                EXO.SUB_ORDER_ID AS EXCHANGE_SUPID,
	                CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'استرجاع المنتجات' ELSE B.EXISTS_NAME END AS EXISTS_NAME,
	                CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'ايقاف التسليم' ELSE C.STA_NAME END AS STA_NAME,
	                D.ORDER_TYPE_NAME,
	                CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
	                CONCAT(F.FORENAME,' ',F.LASTNAME) AS DELIVERYBY,
	               	A.TEL_ID,
	                A.ADDRESS,
	                G.DEFAULTADD,
	                G.ADDRESS_TITLE,
	                H.CLIENT_EMAIL,
	                H.CLIENT_NAME,
	                H.CLIENT_ID,
	                L.SHIPPING_TIME_MAX,
	                L.ZONE_NAME,
	                L.ZONE_ID,
	                L.SHIPPING_TYPE,
	                L.SHIPPING_RATE,
	                L.SHIPPING_LOWEST,
	                K.CI_NAME,
	                A.ADDRESS_ID,
	                k.CI_ID,
	                k.GOV_ID,
	                R.COUNTRY_ID,
	                R.GOV_NAME,
	                
	                OSE.OS_ID,
	                OSE.OS_STATUSE,
	              	OSE.COUNT_END_OS AS OS_END_COUNT,
	                OSE.CMSG AS MSG_COUNT,
	                CASE WHEN OSE.OS_STATUSE = 0 THEN 1 ELSE 0 END AS OSMsgCS,
	                
	                P.INVO_ID,
	                COUNT(M.ORDER_ID) AS PRODUCTS_COUNT,
	                SUM(M.QTY) AS PRODUCTS_QTY,
	                SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
	                
	                JSON_ARRAYAGG(
		                JSON_OBJECT(
		                    'PRODUCT_CODE', POV.PRO_CODE,
		                    'PRODUCT_NAME', POV.PRO_NAME,
		                    'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
		                    'ORDER_QTY', FORMAT(M.QTY,2),
		                    'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
		                    'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
		                    'PRICE', FORMAT(M.PRICE,2),
		                    'DISCOUNT', FORMAT(M.DISCOUNT,2),
		                    'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
		                )
	            	) AS PRODUCTS_DATA
	            
	            FROM (
	                SELECT 
	                    ORDER_ID,
	                    USER_ID AS DELIVERY_ID,
	                    ORDER_STATUSE AS ORDER_STATUS,
	                    0 AS REFUND_STATUS,
	                    SHIPPING_ADDRESS AS ADDRESS,
	                    CUST_STAT,
	                    RETURNS
	                FROM custody_orders
	                WHERE CUST_STAT='FREEZE'
	                ".$sqlWHEREpv[0]."
	                UNION ALL
	                SELECT 
	                    A.ORDER_ID,
	                    COALESCE(B.USER_ID, A.DELIVERY_ID) AS DELIVERY_ID,
	                    A.ORDER_STATUS,
	                    CRO.ORDER_STATUSE AS REFUND_STATUS,
	                    A.ADDRESS,
	                    COALESCE(B.CUST_STAT,'') AS CUST_STAT,
	                    CASE WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE = 3 THEN CRO.RETURNS
	                         WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE > 3 THEN B.RETURNS
	                         WHEN CRO.RETURNS IS NULL THEN COALESCE(B.RETURNS,0)
	                         ELSE 0
	                    END AS RETURNS
	                FROM ORDERS A
	                LEFT JOIN custody_orders B ON A.ORDER_ID = B.ORDER_ID AND CUST_STAT<>'FREEZE'
	                LEFT JOIN custody_return_orders CRO ON A.EXCHANGE = CRO.ORDER_ID 
	                ".$sqlWHEREpv[1]."
	            ) X
	            LEFT JOIN ORDERS 			A ON X.ORDER_ID = A.ORDER_ID
	            LEFT JOIN ORDER_EXISTS 		B ON A.EXISTS_ID = B.EXISTS_ID
	            LEFT JOIN ORDER_STATUSE 	C ON A.ORDER_STATUS = C.STA_ID
	            LEFT JOIN ORDER_TYPE 		D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
	            LEFT JOIN USERS 			E ON A.ORDER_BY = E.USER_ID
	            LEFT JOIN USERS 			F ON A.DELIVERY_ID = F.USER_ID
	            LEFT JOIN ADDRESSES 		G ON A.ADDRESS_ID = G.ADDRESS_ID
	            LEFT JOIN CLIENT 			H ON G.CLIENT_ID = H.CLIENT_ID
	            LEFT JOIN CITY 				K ON G.CITY_ID = K.CI_ID
	            LEFT JOIN GOV 				R ON K.GOV_ID = R.GOV_ID
	            LEFT JOIN ZONES 			L ON K.ZONE_ID = L.ZONE_ID
	            LEFT JOIN ORDER_PRODUCT 	M ON A.ORDER_ID = M.ORDER_ID
	            LEFT JOIN invoices 			P ON A.ORDER_ID = P.REFERENCE_ID AND REFERENCE_TYPE='SALES'
	            LEFT JOIN ORDERS 			EXO ON A.EXCHANGE = EXO.ORDER_ID
	            LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE = POV.PRO_CODE
				LEFT JOIN (
					SELECT  
					    O.ORDER_ID,
					    COALESCE(MAX(OS.OS_ID),0) AS OS_ID,
					    COALESCE(MAX(OSTX_READ_DATE),'2000-01-01 00:00:00') AS OSTX_READ_DATE,
					    COALESCE(MAX(OST.OSTX_DATE),'2000-01-01 00:00:00') AS OSTX_DATE,
					    COALESCE(MAX(OSA.OS_STATUSE),MAX(OSB.OS_STATUSE),1) AS OS_STATUSE,
					    COUNT(DISTINCT OSB.OS_ID) AS COUNT_END_OS,
					    COUNT(DISTINCT CASE WHEN OST.OSTX_READ = 0 AND OST.USER_ID <> ".$_SESSION['USER_ID']." THEN OST.OSTX_ID END) AS CMSG
					FROM orders O
					LEFT JOIN order_service OS  ON O.ORDER_ID = OS.ORDER_ID
					LEFT JOIN order_service OSA ON O.ORDER_ID = OSA.ORDER_ID AND OSA.OS_STATUSE = 0
					LEFT JOIN order_service OSB ON O.ORDER_ID = OSB.ORDER_ID AND OSB.OS_STATUSE = 1
					LEFT JOIN order_service_text OST ON OSA.OS_ID = OST.OS_ID
					GROUP BY O.ORDER_ID
				) OSE ON X.ORDER_ID = OSE.ORDER_ID
	            LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=X.ORDER_ID
	            GROUP BY X.ORDER_ID, X.DELIVERY_ID, X.ORDER_STATUS, X.ADDRESS, X.CUST_STAT, X.RETURNS
	        ) ORDERS
	    ";
	
	    $last_update = isset($this->rq_parm['last_update']) ? $this->rq_parm['last_update'] : '0000-01-01';
	
	    // إضافة LIMIT و OFFSET اختيارياً
	    $sqlLimitOffset = '';
	    if ($useLimit && isset($this->rq_parm['start']) && isset($this->rq_parm['length'])) {
	        $sqlLimitOffset = " LIMIT ".intval($this->rq_parm['start']).", ".intval($this->rq_parm['length']);
	    }
	
	    $Query_result = $sqlSELECT . $sqlWHERE . ' ORDER BY ORDER_ID ASC' . $sqlLimitOffset;
	    $Query_odata  = $sqlSELECT . $sqlwex;
	    $Query_total  = "SELECT  count(ORDER_ID) AS ordersCount FROM (
	            SELECT A.ORDER_ID,
	                A.SUB_ORDER_ID,
	                A.ORDER_TYPE_ID,
	                CO.USER_ID AS DELIVERY_ID,
	                A.ORDER_BY,
	                A.TELEPHONE,
	                A.ADDRESS,
	                A.STO_ID,
	                H.CLIENT_NAME,
	                H.CLIENT_EMAIL,
	                A.ORDER_SUCCESS,
	                A.EXISTS_ID,
	                A.ORDER_STATUS,
	                Z.SHIPPING_TYPE,
	                Z.ZONE_ID,
	                EXS.EXCHANGE AS IS_EXCHANGE,
	                COALESCE(co.RETURNS,1) AS RETURNS,
	                COALESCE(CO.CUST_STAT,'') AS CUST_STAT,
	                COALESCE(B.OS_STATUSE,1) AS OS_STATUSE,
	                COUNT(DISTINCT CASE WHEN c.OSTX_READ = 0 THEN OSTX_ID END) AS MSG_COUNT,
	                GREATEST(
	                    COALESCE(A.LAST_UPDATE,'0000-01-01'),
	                    COALESCE(C.OSTX_READ_DATE,'0000-01-01'),
	                    COALESCE(C.OSTX_DATE,'0000-01-01'),
	                    COALESCE(EXO.LAST_UPDATE,'0000-01-01')
	                ) AS LAST_UPDATE
	            FROM orders A
	            LEFT JOIN orders EXO ON A.EXCHANGE=EXO.ORDER_ID
	            LEFT JOIN order_service B ON A.ORDER_ID=B.ORDER_ID AND OS_STATUSE = 0
	            LEFT JOIN order_service_text C ON B.OS_ID=C.OS_ID
	            LEFT JOIN custody_orders CO ON CO.ORDER_ID = A.ORDER_ID
	        	LEFT JOIN USERS E ON A.ORDER_BY = E.USER_ID
	        	LEFT JOIN USERS F ON A.DELIVERY_ID = F.USER_ID
	        	LEFT JOIN ADDRESSES G ON A.ADDRESS_ID = G.ADDRESS_ID
	        	LEFT JOIN CLIENT H ON G.CLIENT_ID = H.CLIENT_ID
	        	LEFT JOIN city CI ON G.CITY_ID = CI.CI_ID
	        	LEFT JOIN ZONES Z ON CI.ZONE_ID = Z.ZONE_ID
	        	LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=A.ORDER_ID
	        	GROUP BY A.ORDER_ID
	        ) AS OTBL" . $sqlWHERE;
	    $Query_lastup = "
	        SELECT DISTINCT ORDER_ID FROM (
	            SELECT A.ORDER_ID,
	                GREATEST(
	                    COALESCE(A.LAST_UPDATE,'0000-01-01'),
	                    COALESCE(C.OSTX_READ_DATE,'0000-01-01'),
	                    COALESCE(C.OSTX_DATE,'0000-01-01'),
	                    COALESCE(EXO.LAST_UPDATE,'0000-01-01')
	                ) AS LAST_UPDATE
	            FROM orders A
	            LEFT JOIN orders EXO ON A.EXCHANGE=EXO.ORDER_ID
	            LEFT JOIN order_service B ON A.ORDER_ID=B.ORDER_ID
	            LEFT JOIN order_service_text C ON B.OS_ID=C.OS_ID
	        ) AS OTBL
	        WHERE LAST_UPDATE >= '".$last_update."'
	    ";
	
	    return [
	        "result" => $Query_result,
	        "total"  => $Query_total,
	        "odata"  => $Query_odata,
	        "lupdate" => $Query_lastup,
	        "QueryData" => $sqlSELECT
	    ];
	}
	private function custodyTotal () {
    	$userDeliveryID ='';
    	$wh = '';
    	if(isset($this->rq_parm['CONTROL']) &&  $this->rq_parm['CONTROL'] === 'OrdersCustody'){
	    	if(isset($this->rq_parm['did']) && $this->rq_parm['did']>0){
	    		$userDeliveryID = $this->rq_parm['did'];
	    		$wh = ' WHERE CU.USER_ID = :delid ';
	    	}
    	}else if(isset($this->rq_parm['CONTROL']) &&  $this->rq_parm['CONTROL'] === 'ListOrdersDelivery'){
    		$userDeliveryID = $_SESSION['USER_ID'];
	    	$wh = ' WHERE CU.USER_ID = :delid AND CU.RETURNS =0';
    	}
    	$sqlQuery 	= ' SELECT 
						  Count(CU.ORDER_ID) AS CUT,
						  Sum(CASE WHEN CU.ORDER_STATUSE = 2 AND CU.CUST_STAT <> "FREEZE" THEN 1 ELSE 0 END)  AS CU2,
						  Sum(CASE WHEN (CU.ORDER_STATUSE IN (4,5) AND CU.RETURNS = 0) OR (CU.CUST_STAT = "FREEZE" AND CU.RETURNS = 0) THEN 1 ELSE 0 END)   AS CU4_5,
						  Sum(CP.RETURNPRO) AS REFQTY
						FROM   custody_orders AS CU
						  LEFT JOIN orders AS O ON O.ORDER_ID = CU.ORDER_ID
						  LEFT JOIN (
						  	SELECT  CP.ORDER_ID, Sum(CP.QTY - CP.DELIVERED_QTY) AS RETURNPRO
						    FROM   custody_product AS CP
							 LEFT JOIN custody_orders AS CO ON CO.ORDER_ID = CP.ORDER_ID
						    WHERE PRO_STATUSE IN( "partial","rejected" ) AND CO.RETURNS = 0
						    GROUP BY ORDER_ID
						  ) AS CP ON CP.ORDER_ID = CU.ORDER_ID ';
	//	print_r( ['1234',$wh,$this->rq_parm]);
		$stCustTotal = $this->conn->prepare($sqlQuery.$wh); //$sqlSELECT . $sqlWHERE . $sqlSEARCH . $sqlGROUP . $sqlhaving . $sqlORDERBY);
        if($userDeliveryID!==''){
	        $userDeliveryID  = htmlspecialchars(strip_tags(trim($userDeliveryID)));
	        $stCustTotal->bindValue(':delid' , $userDeliveryID  , PDO::PARAM_INT);
        }
        $stCustTotal->execute();
        $returnData = array();
        while ($record = $stCustTotal->fetch(PDO::FETCH_ASSOC)) {
         $returnData['cuTotal']  = $record['CUT'] !== NULL ? $record['CUT'] : 0;
         $returnData['cuAshipp'] = $record['CU2'] !== NULL ? $record['CU2'] : 0;
         $returnData['cuBshipp'] = $record['CU4_5'] !== NULL ? $record['CU4_5'] : 0;
         $returnData['cuRefQty'] = $record['REFQTY'] !== NULL ? $record['REFQTY']: 0;
        }
        return $returnData;
    }
	private function listOrders_limit(&$request){
	    $limit = '';
	
	    if (isset($request['start']) && isset($request['length'])) {
	
	        $start  = (int) $request['start'];
	        $length = (int) $request['length'];
	
	        // لو -1 يبقى يرجع كل البيانات (DataTables convention)
	        if ($length > -1) {
	            $limit = " LIMIT {$start}, {$length}";
	        }
	    }
	
	    return $limit;
	}
	private function listOrders_orderby (&$request) {
	// إنشاء جملة ORDER BY لاستعلام SQL لمعالجة البيانات من جانب الخادم
		/**
		* الترتيب
		*
		* إنشاء جملة ORDER BY لاستعلام SQL لمعالجة البيانات من جانب الخادم
		*
		* @param  array $request البيانات المرسلة إلى الخادم بواسطة DataTables
		* @return string جملة ترتيب SQL
		*/
		$order = '';
		if(isset($request['order'])){
            $order = ' ORDER BY ORDER_ID  '.strtoupper($request['order'][0]['dir']);
        }
		return $order;
	}
	private function listOrders_Where 	(&$request, $fi, $bool=TRUE, $sherch = TRUE ,$str = true) {
		$wq1 = array();
		$wq2 = array();
		$globalSearch = array();
		$globalwhere  = array();
		$globalhaving = array();
		$filterwhere  = array();
		$where = '';
		if(isset($request["last_update"])){
			$globalwhere[] = "LAST_UPDATE > '".$request["last_update"]."'";
			$wq1[] = "LAST_UPDATE > '".$request["last_update"]."'";
			$wq2[] = "(A.LAST_UPDATE > '".$request['last_update']."' OR B.LAST_UPDATE > '".$request["last_update"]."')";
		}
		
		//====================== Global Where ==========================
		switch ($request["ptype"]) {
		    case 1: //منطقة الاداريين
		    //print_r($request);
		    	$globalwhere[] = 'STO_ID = (SELECT STO_ID FROM stores WHERE STTY_ID = 2 AND BRANCH_ID ='. $_SESSION['BRANCH_ID'].')';
		    	if($request["CONTROL"] == "Orderlist"){
		    		if(isset($request["ex"]) && $request["ex"] > 0){
		    			$globalwhere[] = 'ORDER_SUCCESS <=0';
		    			$globalwhere[] = 'EXISTS_ID = '. $request["ex"] ;
		    			$globalhaving[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
		    			$wq2[] = 'A.ORDER_SUCCESS <=0 AND A.EXISTS_ID = '. $request["ex"] ;
		    		}
		    		
		    		
		    	}else if($request['CONTROL'] == "OrdersCustody"){

		    		if(isset($request['did']) && $request['did']>0){
			    		
			    		$globalwhere[] = 'DELIVERY_ID = '.$request['did'];
			    		$WQ1 = 'USER_ID = '.$request['did'];
			    		$WQ2 = 'A.DELIVERY_ID = '.$request['did'];
		    		}
		    		
		    		if(isset($request['cofi'])){
		    			if($request['cofi'] === "OLUnderSettlement"){
		    				$globalwhere[] = '(CUST_STAT IN("FREEZE","DELIVERED") AND ORDER_STATUS <> 3 AND RETURNS = 0)';
		    			}else if($request['cofi'] === "OLDelivered"){
		    				$globalwhere[] = '(CUST_STAT ="DELIVERED" AND ORDER_STATUS = 3 AND ORDER_SUCCESS = 0)';
		    			}else if($request['cofi'] === "OLUnderDelivery"){
		    				$globalwhere[] = 'CUST_STAT ="WAITING" ';
		    			}else{
		    				$globalwhere[] = 'ORDER_SUCCESS = 0 AND CUST_STAT IN("FREEZE","DELIVERED","WAITING")';
		    			}
		    			
		    		}else if(empty($request['cofi'])){
		    			$globalwhere[] = 'ORDER_SUCCESS = 0 AND CUST_STAT IN("FREEZE","DELIVERED","WAITING")';
		    		}
		    		
		    		//print_r($globalwhere);
		    	}else if($request['CONTROL'] == "DirectSale"){
		    		$globalwhere[] = "EXISTS_ID = 11";
		    	
		    		
		    	}else if($request['CONTROL'] == "SupportAfterSales"){
		    		$globalwhere[] = "((ORDER_STATUS = 3 AND ORDER_SUCCESS = 1) OR (ORDER_STATUS = 4 AND ORDER_SUCCESS = 1) OR (ORDER_STATUS = 5 AND RETURNS = 1))";
		    	
		    		
		    	}else if($request['CONTROL'] == "SupportOrders"){
		    		$globalwhere[] = "(OS_STATUSE = 0)";
					
		    	}else if($request['CONTROL'] == "TrackShipmentConfirmation"){
		    		$globalwhere[] = "(EXISTS_ID = 12)";
				
		    	}else if($request['CONTROL'] == "SalesReturn"){
		    		$globalwhere[] = "(ORDER_STATUS IN(3,4))";
		    	}
		        break;
		    case 3: //منطقة مندوبين الشحن
		        //$globalwhere[] = 'DELIVERY_ID = ' . $_SESSION['USER_ID'] ;
			    switch ($request["CONTROL"]) {
			    	case "ListOrdersDelivery":
			       	 	$globalwhere[] = 'DELIVERY_ID = ' . $_SESSION['USER_ID'] . ' AND CUST_STAT = "WAITING"';
			    	break;
			    	case "ListOrdersDelivered":
			       	 	$globalwhere[] = 'DELIVERY_ID = ' . $_SESSION['USER_ID'] . ' AND CUST_STAT IN("FREEZE","DELIVERED")';
			    	break;
			    	case "ShippingConfirmation":
			    		if(isset($_POST['cofi'])  && $_POST['cofi'] === "waitingResponse"){
			    			$globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 6 AND ORDER_ID IN(SELECT ORDER_ID FROM crm_order_logs WHERE employee_type = "delivery" AND ACTION_TYPE IN("SMS","WHATSAPP") ) AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
			    		}else if(isset($_POST['cofi'])  && $_POST['cofi'] === "finished"){
			    			$globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 12 AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
			    		}else{
			    			$globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 6 AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
			    		}
			    	break;
		       	}
		        break;
		    case 2:
		    	switch ($request["CONTROL"]) {
			    	case "ListOrdersMarketer":
			       	 	$globalwhere[]  = 'ORDER_BY = ' . $_SESSION['USER_ID'] .' AND ORDER_TYPE_ID = 2 AND ORDER_SUCCESS >= 0 ' ;
		   				$globalwhere[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
		   				$wq2[]  = 'A.ORDER_BY = ' . $_SESSION['USER_ID'] .' AND A.ORDER_TYPE_ID = 2 AND A.ORDER_SUCCESS >= 0 ' ;
		   				
			    	break;
			    	case "ClientOrderlist":
			       	 	$globalwhere[] = 'CUST_STAT <> "FREEZE"';
			    	break;
		       	}
		        break;
		    default: //منطقة منشئى اوامر البيع
		    	$globalwhere[]  = 'ORDER_BY = ' . $_SESSION['USER_ID'] .' AND ORDER_TYPE_ID = '.$request["ptype"].' AND ORDER_SUCCESS >= 0 ' ;
		   		$globalhaving[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
		   		$wq[]  = 'A.ORDER_BY = ' . $_SESSION['USER_ID'] .' AND A.ORDER_TYPE_ID = '.$request["ptype"].' AND A.ORDER_SUCCESS >= 0 ' ;
	
		    /*	if(!empty($request["ptype"]) && $request["ptype"] != 1){
		            $sqlhaving .= ' HAVING ORDER_SUCCESS >= 0' ;
				}
				if(	isset($request["ptype"]) 	&& 
					isset($request['os']) 	&& 
					$request["ptype"] == 1 	&& 
					$request['os'] 	== 2
				){
		                if($sqlhaving === ''){
		                    $sqlhaving  = ' HAVING (CASE WHEN Q.OS_STATUSE = 0 OR Q.MSG_COUNT > 0 THEN 1 ELSE 0 END) >0 ';
		                }else{
		                    $sqlhaving .= ' AND (CASE WHEN Q.OS_STATUSE = 0 OR Q.MSG_COUNT > 0 THEN 1 ELSE 0 END) > 0 ';
		                }
		        }*/
		}
		
		//====================== Filter Where ==========================
        //تستخدم الفلاتر التالية فى كل الصفحات معاده صفحة المندوب
        if (!empty($request["ptype"]) && $request["ptype"] != 3) {
        	//تصفية حسب الحالة الادارية
        	if(isset($request["ex"],$fi["exists"]['id']) && $request["ex"] == 0 && $fi["exists"]['id'] > 0){
		    //	$filterwhere[] = 'EXISTS_ID = '. $fi["exists"]['id'];
		    }
		    //تصفية حسب نوع الامر
        	if (!empty($fi["orderType"]['id'])) {
            	$filterwhere[] = 'ORDER_TYPE_ID = ' . $fi["orderType"]['id'];
        	}
        	//تصفية حسب نوع الشحن
        	if (!empty($fi["shippingType"]['id'])) {
            	$filterwhere[] = 'SHIPPING_TYPE = ' . $fi["shippingType"]['id'];
        	}
        	//تصفية حسب نوع السير
        	if (!empty($fi["zones"]['id'])) {
            	$filterwhere[] = 'ZONE_ID = ' . $fi["zones"]['id'];
        	}
        	//تصفية حالة امر البيع
	        if (!empty($fi["statuse"]['id'])) {
	        //    $filterwhere[] = 'ORDER_STATUS = ' . $fi["statuse"]['id'];
	        }
	        //تصفية الاوامر حسب نوع المنتج
	        if (!empty($fi["products"]['id'])) {
	            $filterwhere[] = 'ORDER_ID IN (SELECT ORDER_ID FROM ORDER_PRODUCT AS OP LEFT JOIN PRODUCT_CODES AS PC ON OP.PRO_CODE = PC.PRO_CODE WHERE PC.PRODUCT_ID IN  ('. implode(',', $fi["products"]['id']) . ') GROUP BY ORDER_ID) ';
	        }
	        
	       // print_r($request);
			//البحث عن امر البيع
			if(!empty($request['columns'][0]['search']['value'])){
				$globalSearch[] = 'SUB_ORDER_ID	LIKE "%'.$request['columns'][0]['search']['value'].'%" ';
			};
			//البحث عن حالة امر البيع
			if(!empty($request['columns'][1]['search']['value'])){
				$globalSearch[] = 'STA_NAME	LIKE "%'.$request['columns'][1]['search']['value'].'%" ';
			};
			// بحث بالاسم او رقم الهاتف
			if(!empty($request['columns'][2]['search']['value'])){
				$globalSearch[] = 'CLIENT_NAME	LIKE "%'.$request['columns'][2]['search']['value'].'%" ';
				$globalSearch[] = 'TELEPHONE	LIKE "%'.$request['columns'][2]['search']['value'].'%" ';
			};
	        
	       //تصفية الاوامر حسب التاريخ
	        if (!empty($request["dateFilter"]['column']) && 
	        	!empty($request["dateFilter"]['start']) && 
	        	!empty($request["dateFilter"]['end'])) {
				$column = trim($request["dateFilter"]['column']);
				$start  = trim($request["dateFilter"]['start']).' 00:00:00.000';
				$end    = trim($request["dateFilter"]['end']).' 23:59:59.999';  
				$filterwhere[] = $column . ' BETWEEN "' . $start . '" AND "' . $end . '" ';
	        }
        }
        //يستخدم الفلاتر التاليه فى كل الصفحات
        if (!empty($request["ptype"])) {
        	//تصفية حسب خط السير 
        	if (!empty($fi["zones"]['id'])) {
	            $filterwhere[] = 'ZONE_ID = ' . $fi["zones"]['id'];
	        }
        }

        //عرض الطلبات المقدمه لخدمة العملاء
        /*تحت الانشاء*/
		//====================== Global Search ==========================
		if ( isset($request['search']) && $bool && $sherch&& $request['search']['value'] != '') {
            $str = $request['search']['value'];
            $globalSearch[] = 'SUB_ORDER_ID	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'ORDERBY 		LIKE "%'.$str.'%" ';
            $globalSearch[] = 'DELIVERYBY 	LIKE "%'.$str.'%" ';
   			$globalSearch[] = 'TELEPHONE    LIKE "%'.$str.'%" ';
            $globalSearch[] = 'ADDRESS  	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_NAME 	LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_EMAIL	LIKE "%'.$str.'%" ';
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
		
		if( count($globalhaving) ){
			$having = '('.implode(' AND ', $globalhaving).')';
			
		}
		
		if ( count( $globalSearch ) ) {
			$having = $having === '' ?
				'('.implode(' OR ', $globalSearch).')':
				$having .' AND ('.implode(' OR ', $globalSearch).')';
		}
		
		if ( $where !== '' ) {
			$where = ' WHERE '.$where;
		}
		if($having !==''){
			$having = ' AND '.$having;
		}
		if(count($wq2)){
			$wq2 = ' WHERE ('.implode(' AND ', $wq2).')';
		}else{
			$wq2 = '';
		}
		if(count($wq1)){
			$wq1 = ' AND ('.implode(' AND ', $wq1).')';
		}else{
			$wq1='';
		}
		
		//echo $where .$having;
		if($str){
			return $where .$having;
		}else{
			return array($wq1,$wq2);
		}
	}

	private function btnView 			( &$orderData ,$use = true){
		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-primary border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnOview',
			'icon'      => 'fa-solid fa-eye',
			'name'      => 'عرض',
			'msgnotification' => ""
		];
    }
	private function btnEdit 			( &$orderData ,$use = true){
        return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-info border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnOedit',
			'icon'      => 'fa-solid fa-edit',
			'name'      => 'تعديل',
			'msgnotification' => ""
		];
    }
	private function btnDelete 			( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-danger border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnOdel',
			'icon'      => 'fa-solid fa-trash',
			'name'      => 'حذف',
			'msgnotification' => ""
		];
    }
	private function btnService 		( &$orderData ,$use = true){
		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-warning border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnOservice',
			'icon'      => 'fa-solid fa-headset',
			'name'      => 'الدعم',
			'msgnotification' => ($orderData['OS_ID']>0 && $orderData['OS_STATUSE']==0 && $orderData['MSG_COUNT']>0) ? 
			$orderData['MSG_COUNT'] : 
				(($orderData['OS_ID']>0 && $orderData['OS_STATUSE']==0 && $orderData['MSG_COUNT'] ==0) ? '<i class="fa-regular fa-envelope"></i>' : "")
		];
        /*return '<button id="btnOservice" data-id="'.$oid.'" class="btn btn-app-tools btn-xs btnOservice ovbtn w-100 h-100 '.$oclass.'">
		         	<span class="badge bg-danger">'.$msgnotification.'</span>
		            <i class="fa-solid fa-headset"></i>
		            <a style="font-size:14px; font-weight: bold;">الدعم</a>
		         </button>';*/
    }
	private function btnReturn 			( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-info border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnReturn',
			'icon'      => 'fa-solid fa-exchange',
			'name'      => 'إسترداد',
			'msgnotification' => ""
		];
    }
	private function returnProduct 			( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-info border" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'returnProduct',
			'icon'      => 'fa-solid fa-exchange',
			'name'      => 'استرجاع',
			'msgnotification' => ""
		];
    }
	private function btnTransformation 	( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? (($orderData["ORDER_STATUS"] == 1 && $orderData["PRINT"]) ? "bg-primary" : "text-warning border") : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnOservice',
			'icon'      => 'fa-solid fa-exchange',
			'name'      => 'تحويل',
			'msgnotification' => ""
		];
    }
	private function btnPrint 			( &$orderData ,$use = true){
		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-success border btnSelectOLiest" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnSelectOLiest',
			'icon'      => $orderData['PRINT'] == true ? "fa-solid fa-file-circle-check" : "fa-solid fa-print",
			'name'      => 'طباعة',
			'msgnotification' => ""
		];
    }
	private function btnShipment 		( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-primary border  btnSelectOShipp" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnSelectOShipp',
			'icon'      => 'fa-solid fa-truck-fast',
			'name'      => 'شحن',
			'msgnotification' => ""
		];
    }
	private function btnHandshake 		( &$orderData ,$use = true){
		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-success border btnDelevered" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnDelevered',
			'icon'      => 'fa-solid fa-handshake',
			'name'      => 'تسليم',
			'msgnotification' => ""
		];
	}
	private function btnReceivingReturns( &$orderData ,$use = true){
		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-danger border btnReceivingReturns" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnReceivingReturns',
			'icon'      => 'fa-solid fa-indent',
			'name'      => 'استلام',
			'msgnotification' => ""
		];
	}
	private function btnNewOrderExchange( &$orderData ,$use = true){
   		return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-danger border btnNewOrderExchange" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnNewOrderExchange',
			'icon'      => 'fa-solid fa-copy',
			'name'      => 'استبدال',
			'msgnotification' => ""
		];
	}
	private function btnExchangeOrder 	( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-success border btnExchangeOrder" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnExchangeOrder',
			'icon'      => 'fa-solid fa-plus',
			'name'      => '',
			'msgnotification' => ""
		];
	}
	private function btnReactivation 	( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-success border btnReactivation" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnReactivation',
			'icon'      => 'fa-solid fa-recycle',
			'name'      => 'اعادة الشحن',
			'msgnotification' => ""
		];
        /*return '<button id="btnReactivation" type="button" data-id="'.$oid.'" class="btn btn-app-tools btn-xs '.$oclass.' ovbtn w-100 h-100 f-5" '.$available.'>
        			<i class="fa-solid fa-recycle"></i>
        			<a style="font-size:14px; font-weight: bold;">اعادة الشحن</a>
        		</button>';*/
	}
	private function btnRefund 			( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-danger border btnRefund" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnRefund',
			'icon'      => 'fa-solid fa-person-walking-arrow-loop-left',
			'name'      => 'ارجاع',
			'msgnotification' => ""
		];
    }
	private function btnShipConfirmation( &$orderData ,$use = true){
    	return [
			'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
			'oclass'    => $use == true ? "text-danger border btnShipConfirmation" : "text-secondary border",
			'available' => $use == true ? "" : "disabled",
			'elid'      => 'btnShipConfirmation',
			'icon'      => 'fa-solid fa-phone-alt',
			'name'      => 'اتصال',
			'msgnotification' => ""
		];
    }

	private function orderBTN_TR 		( &$request ,&$orderData ,&$User_permissions){
    	$btnTR = "";
   		switch($request["ptype"]){
        	case 1:
        		// btn View_order
		        $btnTR = self::btnView ($orderData, true);
        		break;
        	case 2:
        		$btnTR = self::btnView ($orderData, ($_SESSION['USER_ID'] == $orderData['ORDER_BY']));
        		break;
        	case 3:
        		$btnTR = self::btnView ($orderData ,((($orderData["EXISTS_ID"] !== 6 || $orderData["EXISTS_ID"] !== 12) && $_SESSION['USER_ID'] == $orderData['DELIVERY_ID']) || $orderData["EXISTS_ID"] == 6 || $orderData["EXISTS_ID"] == 12));
        		break;
        }
		return $btnTR;
    }
	private function orderBTN_TL 		( &$request ,&$orderData ,&$User_permissions){
    	$btnTL ="";
    	switch($request["ptype"]){
    		case 1:
    			if($request['CONTROL'] === "Orderlist" && $request["ex"] != 8){
					$btnTL = self::btnEdit($orderData, ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  <  2 && $orderData["PRINT"] == 0 && $request["ex"]  !== 0));
				}else if($request['CONTROL'] === "Orderlist" && $request["ex"] == 8){
					$btnTL = self::btnPrint ($orderData,true);
				}else if($request['CONTROL'] === "OrdersCustody" ){
					$btnTL = self::btnReceivingReturns($orderData, 
						(
							(	
								($orderData["CUST_STAT"] === "FREEZE"    && $orderData["RETURNS"] == 0 ) ||
								($orderData["CUST_STAT"] === "DELIVERED" && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  >  3 && $orderData["RETURNS"] == 0) || 
								($orderData["CUST_STAT"] === "DELIVERED" && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  == 3 && $orderData["RETURNS"] == 0) 
							) 
							
						)
					);
				}else if($request['CONTROL'] === "SupportOrders" || $request['CONTROL'] === "SupportAfterSales"){
	    			$btnTL = self::btnEdit($orderData,($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  < 2 && $orderData["PRINT"] == 0 ));
				}else if($request['CONTROL'] === "DirectSale"){
	    			$btnTL = self::btnHandshake($orderData,($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] == 0 && $orderData["OExists"] = 11));
				}else if($request['CONTROL'] === "SalesReturn"){
	    			$btnTL = self::returnProduct($orderData,($orderData["ORDER_SUCCESS"] == 1 && ($orderData["ORDER_STATUS"] == 4 || $orderData["ORDER_STATUS"] == 3)));
				}
				
    			break;
    		case 2: // المنسقين
    			$btnTL = self::btnEdit($orderData, ( $_SESSION['USER_ID'] == $orderData['ORDER_BY'] && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  <   2 && $orderData["PRINT"] == 0));
    			break;
    		case 3:
    			if($request['CONTROL'] === "ShippingConfirmation"){
					$btnTL = self::btnEdit($orderData, ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  <  2));
    			}else if($request['CONTROL'] === "SupportOrders" || $request['CONTROL'] === "SupportAfterSales"){
	    			$btnTL = self::btnEdit($orderData,($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"]  < 2 ));
				}
				break;
    	}
    	return $btnTL;
    }
	private function orderBTN_BR 		( &$request ,&$orderData ,&$User_permissions){
		$btnBR = "";
		//$ex = $request['ex'] ;
		$pt = $request["ptype"];
		
    	switch($pt){
			case 1:
				switch(true){
					case (
						 $request['CONTROL'] === "Orderlist" && 
						 $orderData["ORDER_STATUS"] <3 && 
						 !$orderData["CUST_STAT"]):
							if(!$orderData["ORDER_SUCCESS"] && $request["ex"] != 0 &&
								isset($User_permissions["transformation"]) && 
								$User_permissions["transformation"]
							){
								$btnBR = $this->btnTransformation($orderData);
							}else{
								$btnBR = $this->btnTransformation($orderData ,false);
							}
						break;
						// btn return
					case ($orderData["ORDER_SUCCESS"] == 0 &&
						 $orderData["ORDER_STATUS"] >=2 &&
						 $orderData["CUST_STAT"]  &&
			             $orderData["RETURNS"] == 0 &&
						 $request['CONTROL'] === "Orderlist" ):
							if($request['CONTROL'] === "Orderlist"  &&
								$orderData["ORDER_STATUS"] >=2 &&
								isset($request["ex"])  && 
								$request["ex"] != 0
			                ){
			                	$btnBR = self::btnReturn($orderData);
			                }else{
			                	$btnBR = self::btnReturn($orderData ,false);
			                }
						break;
					case ($request['CONTROL'] === "OrdersCustody"):
							if($orderData["ORDER_STATUS"] == 2 && 
			                	$orderData["ORDER_SUCCESS"] == 0 &&
			                	$orderData["CUST_STAT"] === 'WAITING' &&
			                	$orderData["RETURNS"] == 0
			                ){
								$btnBR = self::btnReturn($orderData);
							}else{
								$btnBR = self::btnReturn($orderData ,false);
							}
						break;
					case ($request['CONTROL'] === "SupportOrders" || $request['CONTROL'] === "SupportAfterSales"):
							if($orderData["ORDER_SUCCESS"] == 0){
								$btnBR = $this->btnTransformation($orderData);
							}else{
								$btnBR = $this->btnTransformation($orderData,false);
							}
						break;
				}
				break; 
			case 2:
				if ( ($orderData["ORDER_STATUS"]  <= 1 && !$orderData["PRINT"]) || 
		         	 ($orderData["ORDER_STATUS"]  == 5) 
		             //$orderData["OS_ID"] >0)
		        ){
		        	$btnBR = self::btnService ($orderData);
		        } else if($orderData["ORDER_SUCCESS"]  == 1 && ($orderData["ORDER_STATUS"]  == 3 || $orderData["ORDER_STATUS"]  == 4) && !$orderData["IS_EXCHANGE"]){
		        	$btnBR = self::btnRefund ($orderData ,true);
		        } else {
		        	$btnBR = self::btnService ($orderData ,false);
		        }
		        /*if(($orderData["EXISTS_ID"] == 5 || 
				   $orderData["ORDER_STATUS"] == 5 ) && $orderData["RETURNS"] == 1 ){
		           $btnBR = self::btnReactivation ($orderData);
				}*/
				break;
			case 3:
				if($request['CONTROL'] === "ShippingConfirmation" && $orderData['ORDER_STATUS'] <2){
					if($orderData["ORDER_SUCCESS"] == -1 ){
						$btnBR = self::btnDelete ($orderData);
					}else{
						$btnBR = self::btnDelete ($orderData ,false);
					}
				}
				break;
		}
		return $btnBR;
    }
	private function orderBTN_BL		( &$request ,&$orderData ,&$User_permissions){
		$btnBL = "";
		//btn Delete_order && print && Shipment
		switch($request['ptype']){
			case 1:
				switch(true){
					//---------
					case ($request['CONTROL'] === "Orderlist" && 
						$orderData["ORDER_STATUS"]  ==  0 && 
						$request['ex'] > 0):
						 	
						if($orderData["ORDER_SUCCESS"] == -1 && 
						  isset($User_permissions["Delete_order"]) && 
						  $User_permissions["Delete_order"]
						){
							$btnBL = self::btnDelete ($orderData);
						}else{
							$btnBL = self::btnDelete ($orderData ,false);
						}
						break;
					//---------
					case ($request['CONTROL'] === "Orderlist" && 
						 $orderData["ORDER_STATUS"]  ==  1  && 
						 $request['ex'] > 0):
						 	
						if($orderData["ORDER_SUCCESS"] == -1 ){
							if(isset($User_permissions["Delete_order"]) && $User_permissions["Delete_order"]){
								$btnBL = self::btnDelete ($orderData);
							}else{
								$btnBL = self::btnDelete ($orderData ,false);
							}
						}else{
							if(isset($User_permissions["Invoce_Print"]) && $User_permissions["Invoce_Print"] ){
								$btnBL = self::btnPrint ($orderData);
							}else{
								$btnBL = self::btnPrint ($orderData ,false);
							}
						}
						break;
					case ($request['CONTROL'] === "SupportOrders" || $request['CONTROL'] === "SupportAfterSales"):
		    			if($orderData["ORDER_SUCCESS"] == 0 && 
		                	$orderData["ORDER_STATUS"] == 5 && $orderData["CUST_STAT"] == ""){
		    				$btnBL = self::btnReactivation($orderData,true);
		    			}else{
		    				$btnBL = self::btnReactivation($orderData,false);
		    				
		    			}
		    			break;
					case ($request['CONTROL'] === "Orderlist" && $orderData["ORDER_STATUS"]  ==  2  && $request['ex'] > 0 && $request['ex'] != 9):
						if(isset($User_permissions["Shipment"]) && $User_permissions["Shipment"] ){
							$btnBL = self::btnShipment ($orderData);
						}else{
							$btnBL = self::btnShipment ($orderData ,false);
						}
						break;

					case ($request['CONTROL'] === "OrdersCustody" || ($request['CONTROL'] === "Orderlist" && $request['ex'] = 9)):
						
						if(
							$orderData["ORDER_SUCCESS"] < 1 && 
							$orderData["RETURNS"] == 0 &&
							isset($User_permissions["EditDelevered"]) && 
							$User_permissions["EditDelevered"]
						){
							$btnBL = self::btnHandshake ($orderData);
						}else{
							$btnBL = self::btnHandshake ($orderData ,false);
						}
						break;

				}
				break;
				
			case 2:
				if( $orderData["ORDER_STATUS"] < 2 	&&
					$orderData["ORDER_SUCCESS"] < 1 &&
					!$orderData["CUST_STAT"] 		&&
					!$orderData['PRINT']
				){
					$btnBL = self::btnDelete ($orderData);
				}else{
					$btnBL = self::btnDelete ($orderData ,false);
				}
				if( $_SESSION['USER_ID'] == $orderData['ORDER_BY'] && 
    				$orderData["ORDER_SUCCESS"] == 1 && !$orderData['IS_EXCHANGE'] &&
                	($orderData["ORDER_STATUS"] == 3 || $orderData["ORDER_STATUS"] == 4) 
                ){
    				$btnBL = self::btnNewOrderExchange($orderData);
    			}else if($orderData["ORDER_SUCCESS"] == 1){
    				$btnBL = self::btnNewOrderExchange($orderData,false);
    			}
				break;

			case 3:
				if($orderData['EXISTS_ID'] !== 6 && $orderData['EXISTS_ID'] !== 12){
					if($orderData["ORDER_SUCCESS"] == 0  && 
						$orderData["RETURNS"] == 0 && 
						($orderData['CUST_STAT'] == 'WAITING' || $orderData['CUST_STAT'] == 'DELIVERED')){
						$btnBL = self::btnHandshake ($orderData,true);
					}else{
						$btnBL = self::btnHandshake ($orderData ,false);
					}
				}else{
					if($request['CONTROL'] === "ShippingConfirmation" && $orderData['ORDER_STATUS'] <1){
						$btnBL = self::btnShipConfirmation ($orderData,true);
					}else{
						$btnBL = self::btnShipConfirmation ($orderData,false);
					}
				}
				break;

        }
       
        return $btnBL;
    }
	private function listOrders_btns 	( &$request ,&$orderData ,&$User_permissions){
    	$us  = unserialize($_SESSION['USER_SCRIPT']);
    	$usex = $User_permissions;
    	$shipdatetext = "";
        $msgnotification ="";
        //$countMsg  = "";
        $adminsupport = "";
        $return_BTNs  = "";
        $clasRowSpan  = "";
        
        if($orderData['MSG_COUNT']>0){
            $msgnotification = $orderData['MSG_COUNT'];
        }else if($orderData['OS_ID']>0 && $orderData['OS_STATUSE']==0 && $orderData['MSG_COUNT'] ==0){
            $msgnotification = '<i class="fa-regular fa-envelope"></i>';
        }
        
		$orderby = $orderData["ORDER_TYPE_NAME"].' : '.$orderData['ORDERBY'];
    	if($this->rq_parm["ptype"] ==1){ 
            $adminsupport =' <td class="bg-white " colspan="2" rowspan="2">
		                            <a id="AdminSupport" data-id="'.$orderData["ORDER_ID"].'" class="btn btn-app-tools btn-light badge-ya text-5 bg-gradient mt-2" style="margin-left:-5px;margin-right:-5px;width:50px;">
		                                <span class="badge bg-danger">'.$msgnotification.'</span>
		                                <i class="fas fa-headset"></i>الدعم
		                            </a>
		                    	</td>';
    	}
        if(isset($orderData["DELIVERY_ID"])){
            $orderby = 'المندوب : '.$orderData['DELIVERYBY'];
        }
        $BtnTR = self::orderBTN_TR($request,$orderData,$User_permissions);
        $BtnTL = self::orderBTN_TL($request,$orderData,$User_permissions);
        $BtnBR = self::orderBTN_BR($request,$orderData,$User_permissions);
        $BtnBL = self::orderBTN_BL($request,$orderData,$User_permissions);
        /*if( ($BtnBR.$BtnBL) == "" && ($BtnTL != "" || $BtnTR != "") ||
        	($BtnTR.$BtnTL) == "" && ($BtnBL != "" || $BtnBR != "") 
        ){
        	$clasRowSpan = 'rowspan="2"';
        }
        $return_BTNs = '<table class="table OLCBtn table-borderless" ><tbody class="badge card-body mt-2 ml-2 mt-r tableAllBtn" style="font-size:1.5rem;">
                    <tr class="p-1">
	                    '.$adminsupport.'
	                    <td class="p-1 badge-light btnTR" '.$clasRowSpan.'>
		                    '.$BtnTR.'
		                </td>';
        if($BtnTL != ""){
	        $return_BTNs .= '<td class="p-1 badge-light btnTL " '.$clasRowSpan.'>
	        					'.$BtnTL.'
	        				</td>';
        }
		$return_BTNs .= '</tr>
                     <tr class="p-1">';
        if($BtnBR != ""){
	    	$return_BTNs .= '<td class="p-1 badge-light btnBR " '.$clasRowSpan.'>
	    						'.$BtnBR.'
	    					</td>';
        }
        if($BtnBL != ""){
			 $return_BTNs .= '<td class="p-1 badge-light btnBL " '.$clasRowSpan.'>
			 					'.$BtnBL.'
			 				</td>';
		}
	         $return_BTNs .= '</tr>
	                     </tbody>
	                     </table>';*/
        return array([$BtnTR,$BtnTL,$BtnBR,$BtnBL],$orderby,$shipdatetext);
    }
	
	public function listOrders_dt_server_side(){
        
        $sqlLIMIT = "";
        $sqlSEARCH = "";
        $us  = unserialize($_SESSION['USER_SCRIPT']);
        $fi = !empty($this->rq_parm['url']) ? json_decode($_COOKIE['filt_'. str_replace("*","_",$this->rq_parm['url'])], true):'';
        
       	$sqlWHEREpv = self::listOrders_Where($this->rq_parm, $fi, false, false, false);
	    $sqlWHERE   = self::listOrders_Where($this->rq_parm, $fi);
	    $sqlwex     = self::listOrders_Where($this->rq_parm, $fi, false);
		$sqlORDERBY = isset($this->rq_parm['columns']) ? self::listOrders_orderby($this->rq_parm , $this->rq_parm['columns'] ) : '';
        $sqlLIMIT   = isset($this->rq_parm['columns']) ? self::listOrders_limit($this->rq_parm , $this->rq_parm['columns'] ) : '';
		$exlup = isset($_POST)  ? "COALESCE(EXO.LAST_UPDATE, '1900-01-02')," :"" ;	        
          
        $sqlSELECT = "
	            SELECT
	                A.ORDER_ID,
	                A.ORDER_BY,
	                A.ORDER_TYPE_ID,
	                A.SUB_ORDER_ID,
	                A.EXISTS_ID,
	                X.DELIVERY_ID,
	                X.CUST_STAT,
	                X.RETURNS,
	                X.REFUND_STATUS,
	                X.ORDER_STATUS,
	                A.DATE_CREATED,
	                A.DELIVERY_DATE,
	                A.SHIPPING_DATE,
	                A.DELIVERED_DATE,
	                EXS.EXCHANGE AS IS_EXCHANGE,
	                A.TELEPHONE,
	                GREATEST(
					    COALESCE(A.LAST_UPDATE, '1900-01-01 00:00:00'),
					    COALESCE(EXO.LAST_UPDATE, '1900-01-01 00:00:00'),
					    COALESCE(OSE.OSTX_DATE, '1900-01-01 00:00:00')
					) AS LAST_UPDATE,
	                A.SHIPPING_VAL,
	                A.STO_ID,
	                A.ORDER_SUCCESS,
	                CASE WHEN A.ORDER_SUCCESS = 0 THEN 'مفتوح' ELSE 'مغلق' END AS SUCCESS_STAT,
	                A.PRINT,
	                C.COLOR_CLASS,
	                A.EXCHANGE,
	                A.CITY_ID,
	                EXO.SUB_ORDER_ID AS EXCHANGE_SUPID,
	                CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'استرجاع المنتجات' ELSE B.EXISTS_NAME END AS EXISTS_NAME,
	                CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'ايقاف التسليم' ELSE C.STA_NAME END AS STA_NAME,
	                D.ORDER_TYPE_NAME,
	                CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
	                CONCAT(F.FORENAME,' ',F.LASTNAME) AS DELIVERYBY,
	               	A.TEL_ID,
	                A.ADDRESS,
	                G.DEFAULTADD,
	                G.ADDRESS_TITLE,
	                H.CLIENT_EMAIL,
	                H.CLIENT_NAME,
	                H.CLIENT_ID,
	                L.SHIPPING_TIME_MAX,
	                L.ZONE_NAME,
	                L.ZONE_ID,
	                L.SHIPPING_TYPE,
	                L.SHIPPING_RATE,
	                L.SHIPPING_LOWEST,
	                K.CI_NAME,
	                A.ADDRESS_ID,
	                k.CI_ID,
	                k.GOV_ID,
	                R.COUNTRY_ID,
	                R.GOV_NAME,
	                OSE.OS_ID,
	                OSE.OS_STATUSE,
	              	OSE.COUNT_END_OS AS OS_END_COUNT,
	                OSE.CMSG AS MSG_COUNT,
	                CASE WHEN OSE.OS_STATUSE = 0 THEN 1 ELSE 0 END AS OSMsgCS,
	                P.INVO_ID,
	                COUNT(M.ORDER_ID) AS PRODUCTS_COUNT,
	                SUM(M.QTY) AS PRODUCTS_QTY,
	                SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
	                JSON_ARRAYAGG(
		                JSON_OBJECT(
		                    'PRODUCT_CODE', POV.PRO_CODE,
		                    'PRODUCT_NAME', POV.PRO_NAME,
		                    'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
		                    'ORDER_QTY', FORMAT(M.QTY,2),
		                    'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
		                    'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
		                    'PRICE', FORMAT(M.PRICE,2),
		                    'DISCOUNT', FORMAT(M.DISCOUNT,2),
		                    'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
		                )
	            	) AS PRODUCTS_DATA
	            FROM (
	                SELECT 
	                    ORDER_ID,
	                    USER_ID AS DELIVERY_ID,
	                    ORDER_STATUSE AS ORDER_STATUS,
	                    0 AS REFUND_STATUS,
	                    SHIPPING_ADDRESS AS ADDRESS,
	                    CUST_STAT,
	                    RETURNS
	                FROM custody_orders
	                WHERE CUST_STAT='FREEZE'
	                ".$sqlWHEREpv[0]."
	                UNION ALL
	                SELECT 
	                    A.ORDER_ID,
	                    COALESCE(B.USER_ID, A.DELIVERY_ID) AS DELIVERY_ID,
	                    A.ORDER_STATUS,
	                    CRO.ORDER_STATUSE AS REFUND_STATUS,
	                    A.ADDRESS,
	                    COALESCE(B.CUST_STAT,'') AS CUST_STAT,
	                    CASE WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE = 3 THEN CRO.RETURNS
	                         WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE > 3 THEN B.RETURNS
	                         WHEN CRO.RETURNS IS NULL THEN COALESCE(B.RETURNS,0)
	                         ELSE 0
	                    END AS RETURNS
	                FROM ORDERS A
	                LEFT JOIN custody_orders B ON A.ORDER_ID = B.ORDER_ID AND CUST_STAT<>'FREEZE'
	                LEFT JOIN custody_return_orders CRO ON A.EXCHANGE = CRO.ORDER_ID 
	                ".$sqlWHEREpv[1]."
	            ) X
	            LEFT JOIN ORDERS 			A ON X.ORDER_ID = A.ORDER_ID
	            LEFT JOIN ORDER_EXISTS 		B ON A.EXISTS_ID = B.EXISTS_ID
	            LEFT JOIN ORDER_STATUSE 	C ON A.ORDER_STATUS = C.STA_ID
	            LEFT JOIN ORDER_TYPE 		D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
	            LEFT JOIN USERS 			E ON A.ORDER_BY = E.USER_ID
	            LEFT JOIN USERS 			F ON A.DELIVERY_ID = F.USER_ID
	            LEFT JOIN ADDRESSES 		G ON A.ADDRESS_ID = G.ADDRESS_ID
	            LEFT JOIN CLIENT 			H ON G.CLIENT_ID = H.CLIENT_ID
	            LEFT JOIN CITY 				K ON G.CITY_ID = K.CI_ID
	            LEFT JOIN GOV 				R ON K.GOV_ID = R.GOV_ID
	            LEFT JOIN ZONES 			L ON K.ZONE_ID = L.ZONE_ID
	            LEFT JOIN ORDER_PRODUCT 	M ON A.ORDER_ID = M.ORDER_ID
	            LEFT JOIN invoices 			P ON A.ORDER_ID = P.REFERENCE_ID AND REFERENCE_TYPE='SALES'
	            LEFT JOIN ORDERS 			EXO ON A.EXCHANGE = EXO.ORDER_ID
	            LEFT JOIN order_service 	OSB ON A.ORDER_ID=OSB.ORDER_ID AND OSB.OS_STATUSE=1
	            LEFT JOIN order_service 	OSC ON A.ORDER_ID=OSC.ORDER_ID AND OSC.OS_STATUSE=0
	            LEFT JOIN order_service 	OSD ON A.ORDER_ID=OSD.ORDER_ID
	            LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE = POV.PRO_CODE
				LEFT JOIN (
					SELECT  
					    O.ORDER_ID,
					    COALESCE(MAX(OS.OS_ID),0) AS OS_ID,
					    COALESCE(MAX(OST.OSTX_READ_DATE),'2000-01-01 00:00:00') AS OSTX_READ_DATE,
					    COALESCE(MAX(OST.OSTX_DATE),'2000-01-01 00:00:00') AS OSTX_DATE,
					    COALESCE(MAX(OSA.OS_STATUSE),MAX(OSB.OS_STATUSE),1) AS OS_STATUSE,
					    COUNT(DISTINCT OSB.OS_ID) AS COUNT_END_OS,
					    COUNT(DISTINCT CASE WHEN OST.OSTX_READ = 0 AND OST.USER_ID <> ".$_SESSION['USER_ID']." THEN OST.OSTX_ID END) AS CMSG
					FROM orders O
					LEFT JOIN order_service OS  ON O.ORDER_ID = OS.ORDER_ID
					LEFT JOIN order_service OSA ON O.ORDER_ID = OSA.ORDER_ID AND OSA.OS_STATUSE = 0
					LEFT JOIN order_service OSB ON O.ORDER_ID = OSB.ORDER_ID AND OSB.OS_STATUSE = 1
					LEFT JOIN order_service_text OST ON OSA.OS_ID = OST.OS_ID
					GROUP BY O.ORDER_ID
				) OSE ON X.ORDER_ID = OSE.ORDER_ID 
	            LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=X.ORDER_ID
	            GROUP BY X.ORDER_ID, X.DELIVERY_ID, X.ORDER_STATUS, X.ADDRESS, X.CUST_STAT, X.RETURNS";
        //echo $sqlSELECT;
		$Query_result = "SELECT * FROM (".$sqlSELECT.") ORDERS " . $sqlWHERE . $sqlORDERBY . $sqlLIMIT;
	    $Query_odata  = "SELECT * FROM (".$sqlSELECT.") ORDERS " . $sqlwex;
	    $Query_total  = "SELECT count(ORDER_ID) AS ordersCount FROM (".$sqlSELECT.") AS ORDERS " . $sqlWHERE;
        
        $stmtTotal = $this->conn->prepare($Query_total);
       
        $stmtTotal->execute();
        //echo $Query_result;
        $stmt = $this->conn->prepare($Query_result);
        $stmt->execute();
        
        
        $total  = $stmtTotal->fetchAll(PDO::FETCH_ASSOC);
        $result = $stmt;
                
        $allResult = $stmtTotal;
        $allRecords = $total[0]['ordersCount'];
        $displayRecords = $result->rowCount();
        
        $records = array();

        $usex = isset($this->rq_parm["ex"] ,$us['Admin']['orderlist_ex'.$this->rq_parm["ex"]]) ? $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]] : false;
		$custodyTotal = self::custodyTotal();
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
             $records[] = [
	            'oid' => $record['ORDER_ID'],
	            'shipUid' => $record['DELIVERY_ID'],
	            'shipUName' => $record['DELIVERYBY'],
	            'subOid' => $record['SUB_ORDER_ID'],
	            'OTName' => $record['ORDER_TYPE_NAME'], 
	            'OStat' => $record['ORDER_STATUS'],
	            'OSuccess' => $record['ORDER_SUCCESS'],
	            'OExists' => $record['EXISTS_ID'],
	            'OPrint' => $record['PRINT'],
	            'custstat' => $record['CUST_STAT'],
	            'returns' => $record['RETURNS'],
	            'olup' => $record['LAST_UPDATE'],
	            'OSid' => $record['OS_ID'],
	            'OSStatu' => $record['OS_STATUSE'],
	            'OSMsgc' => $record['MSG_COUNT'],
	            'OSMsgCS' => $record['OSMsgCS'],
	            'OSMsgEnd' => $record['OS_END_COUNT'],
	            'stoid' => $record['STO_ID'],
	            'OInvo' => $record['INVO_ID'],
	            'ZName' => $record['ZONE_NAME'],
	            'SHIP_VAL' => $record['SHIPPING_VAL'],
	            
	            'ADDRESS' => $record['ADDRESS'],
	            'DEFAULTADD' => $record['DEFAULTADD'],
	            'ADDRESS_TITLE' => $record['ADDRESS_TITLE'],
	            'EXCHANGE' => $record['EXCHANGE'],
	            'uid' => $record['CLIENT_ID'],
	            'PRO_QTY' => $record['PRODUCTS_QTY'],
	            'PRODUCTS_COUNT' => $record['PRODUCTS_COUNT'],
	            'EXISTS_NAME' => $record['EXISTS_NAME'],
	            'ORDER_TYPE_ID' => $record['ORDER_TYPE_ID'],
	            'COLOR_CLASS' => $record["COLOR_CLASS"],
	            'STA_NAME' => $record['STA_NAME'],
	            'SUCCESS_STAT' => $record['SUCCESS_STAT'],
	            'CLIENT_NAME' => $record['CLIENT_NAME'],
	            'CLIENT_EMAIL' => $record['CLIENT_EMAIL'],
	            'EXCHANGE_SUPID' => $record['EXCHANGE_SUPID'],
	            'DATE_CREATED' => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
	            'DELIVERY_DATE' => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
	            'SHIPPING_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["SHIPPING_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	            'DELIVERED_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	            'TELEPHONE' => $record['TELEPHONE'],
	            'TEL_ID' => $record['TEL_ID'],
	            'CI_ID' => $record['CITY_ID'],
	            'GOV_ID' => $record['GOV_ID'],
	            'COUNTRY_ID' => $record['COUNTRY_ID'],
	            'ADDRESS_ID' => $record['ADDRESS_ID'],
	            'SHIPPING_LOWEST' => $record['SHIPPING_LOWEST'],
	            //'PRODUCT_IDS' => $record['PRODUCT_IDS'],
	            'SHIPPING_RATE' => $record['SHIPPING_RATE'],
	            'SHIPPING_TIME_MAX' => $record['SHIPPING_TIME_MAX'],
	            'SHIPPING_TYPE' => $record['SHIPPING_TYPE'],
	            'PRICE' => $record['PRICE'],
	            'Zid' => $record['ZONE_ID'],
	            'GOV_NAME' => $record["GOV_NAME"],
	            'CI_NAME' => $record["CI_NAME"],
	            'ORDERBY'       => $record['ORDERBY'],
	            'ORDER_BY_ID'   => $record['ORDER_BY'],
	            'BUTTONS' => self::listOrders_btns($this->rq_parm, $record, $usex),
	            'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']

	        ];
        } 

        if(!isset($this->rq_parm["draw"])){
            $this->rq_parm["draw"] = 0 ;
        }
		$defaultData = array(
            "draw"                  => $this->rq_parm["draw"] ? $this->rq_parm["draw"] : 0,
            "iTotalRecords"         => 0,
            "iTotalDisplayRecords"  => 0,
            "data"                  => [],
            "cuTotal"               => $custodyTotal,
            "odata"                 => []
        );
        if($displayRecords){
        	$defaultData["draw"]                  = $this->rq_parm["draw"];
            $defaultData["iTotalRecords"]         = $displayRecords;
            $defaultData["iTotalDisplayRecords"]  = $allRecords;
            $defaultData["data"]                  = $records;
            $defaultData["cuTotal"]               = $custodyTotal;
            //$defaultData["odata"]             	  = $oidarr;
        }

        return $defaultData;
        $this->conn = null;
        exit();
    }
	public function listOrders($dtjson = false) { 
	    $oidarr   = [];
	    $records  = [];
	    $us       = unserialize($_SESSION['USER_SCRIPT']);
	    $fi       = isset($this->rq_parm['url']) && !empty($this->rq_parm['url']) ? json_decode($_COOKIE['filt_'. str_replace("*","_",$this->rq_parm['url'])], true) : null;
	    $sqlQuery = self::listOrders_queries($fi, $us);
	    $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
	
	    // الصلاحيات
	    if($this->rq_parm["CONTROL"] == "Orderlist"){
	        $usex = $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]];
	    } elseif($this->rq_parm["CONTROL"] == "OrdersCustody"){
	        $usex = $us['Admin'][$this->rq_parm["CONTROL"]];
	    }
	
	    $limit = isset($this->rq_parm["limit"]) ? (int)$this->rq_parm["limit"] : 1000;
	    $offset = isset($this->rq_parm["offset"]) ? (int)$this->rq_parm["offset"] : 0;
	    $checkLupdate = isset($this->rq_parm["limit"]);
	
	    // ===== LUPDATE =====
	    if(!$checkLupdate && !$dtjson){
	        $stmtLupdate = $this->conn->prepare($sqlQuery['lupdate']);
	        $stmtLupdate->execute();
	        $resultLupdate = $stmtLupdate->fetchAll(PDO::FETCH_ASSOC);
	        $resultLupdate = array_column($resultLupdate,'ORDER_ID');
	    }
	
	    // ===== TOTAL =====
	    $stmtTotal = $this->conn->prepare($sqlQuery['total']);
	    //$stmtTotal->execute();
	    //$resultTotal = $stmtTotal->fetchAll(PDO::FETCH_ASSOC);
	    //$allRecords = $resultTotal[0]['ordersCount'];
	    	
	    // ===== RESULT =====
	    $sqlQuery['result'] .= " LIMIT :offset, :limit ";
	    $stmt = $this->conn->prepare($sqlQuery['result']);
	    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
	    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
	    $stmt->execute();
	    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	    $displayRecords = count($result);
	    $allRecords = 	$displayRecords;
	    $custodyTotal = self::custodyTotal();
	
	    // ===== باقي الدالة كما هي =====
	    foreach ($result as $record) {
	        if (!$checkLupdate && !$dtjson && is_array($resultLupdate)) {
	            if (($key = array_search($record['ORDER_ID'], $resultLupdate)) !== false) {
	                unset($resultLupdate[$key]);
	            }
	            $resultLupdate = array_values($resultLupdate);
	        }

	        $records[] = [
	            'oid' => $record['ORDER_ID'],
	            'shipUid' => $record['DELIVERY_ID'],
	            'shipUName' => $record['DELIVERYBY'],
	            'subOid' => $record['SUB_ORDER_ID'],
	            'OTName' => $record['ORDER_TYPE_NAME'], 
	            'OStat' => $record['ORDER_STATUS'],
	            'OSuccess' => $record['ORDER_SUCCESS'],
	            'OExists' => $record['EXISTS_ID'],
	            'OPrint' => $record['PRINT'],
	            'custstat' => $record['CUST_STAT'],
	            'returns' => $record['RETURNS'],
	            'olup' => $record['LAST_UPDATE'],
	            'OSid' => $record['OS_ID'],
	            'OSStatu' => $record['OS_STATUSE'],
	            'OSMsgc' => $record['MSG_COUNT'],
	            'OSMsgCS' => $record['OSMsgCS'],
	            'OSMsgEnd' => $record['OS_END_COUNT'],
	            'stoid' => $record['STO_ID'],
	            'OInvo' => $record['INVO_ID'],
	            'ZName' => $record['ZONE_NAME'],
	            'SHIP_VAL' => $record['SHIPPING_VAL'],
	            
	            'ADDRESS' => $record['ADDRESS'],
	            'DEFAULTADD' => $record['DEFAULTADD'],
	            'ADDRESS_TITLE' => $record['ADDRESS_TITLE'],
	            'EXCHANGE' => $record['EXCHANGE'],
	            'uid' => $record['CLIENT_ID'],
	            'PRO_QTY' => $record['PRODUCTS_QTY'],
	            'PRODUCTS_COUNT' => $record['PRODUCTS_COUNT'],
	            'EXISTS_NAME' => $record['EXISTS_NAME'],
	            'ORDER_TYPE_ID' => $record['ORDER_TYPE_ID'],
	            'COLOR_CLASS' => $record["COLOR_CLASS"],
	            'STA_NAME' => $record['STA_NAME'],
	            'SUCCESS_STAT' => $record['SUCCESS_STAT'],
	            'CLIENT_NAME' => $record['CLIENT_NAME'],
	            'CLIENT_EMAIL' => $record['CLIENT_EMAIL'],
	            'EXCHANGE_SUPID' => $record['EXCHANGE_SUPID'],
	            'DATE_CREATED' => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
	            'DELIVERY_DATE' => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
	            'SHIPPING_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["SHIPPING_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	            'DELIVERED_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	            'TELEPHONE' => $record['TELEPHONE'],
	            'TEL_ID' => $record['TEL_ID'],
	            'CI_ID' => $record['CI_ID'],
	            'GOV_ID'          => $record['GOV_ID'], 
	            'COUNTRY_ID'          => $record['COUNTRY_ID'], 
	            'ADDRESS_ID' => $record['ADDRESS_ID'],
	            'SHIPPING_LOWEST' => $record['SHIPPING_LOWEST'],
	            //'PRODUCT_IDS' => $record['PRODUCT_IDS'],
	            'SHIPPING_RATE' => $record['SHIPPING_RATE'],
	            'SHIPPING_TIME_MAX' => $record['SHIPPING_TIME_MAX'],
	            'SHIPPING_TYPE' => $record['SHIPPING_TYPE'],
	            'PRICE' => $record['PRICE'],
	            'Zid' => $record['ZONE_ID'],
	            'GOV_NAME' => $record["GOV_NAME"],
	            'CI_NAME' => $record["CI_NAME"],
	            'ORDERBY'       => $record['ORDERBY'],
	            'ORDER_BY_ID'   => $record['ORDER_BY'],
	            'BUTTONS' => self::listOrders_btns($this->rq_parm, $record, $usex),
	            'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']
	            
	        ];
	    }
	
	    $nextOffset = ($offset < $allRecords) ? $offset + $limit : null;
	    return !$dtjson ? [
	        "totalRecords" => $allRecords,
	        "currentBatchSize" => $displayRecords,
	        "data" => $records,
	        "cuTotal" => $custodyTotal,
	        "dataDelete" => !$checkLupdate ? $resultLupdate : [],
	        "hasMore" => null,
	    ] : [
	        "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : (isset($_GET['draw']) ? intval($_GET['draw']) : 0),
	        "iTotalRecords" => $allRecords,
	        "iTotalDisplayRecords" => $displayRecords,
	        "data" => $records,
	        "cuTotal" => $custodyTotal,
	    ];
	}
	public function listOrders_NewOrder() { 
	    $records  = [];
	
	    // جلب clientId
	    $clid = isset($_GET['clientId']) && !empty($_GET['clientId']) ? $_GET['clientId'] : -1;
	
	    // 1️⃣ استعلام COUNT لإجمالي السجلات (بدون LIMIT وبدون GROUP BY)
	    $stmtTotal = $this->conn->prepare("
	        SELECT COUNT(A.ORDER_ID) AS TOTAL
	        FROM ORDERS A
	        LEFT JOIN TELEPHONES T ON A.TEL_ID = T.TEL_ID
	        WHERE T.CLIENT_ID = ?
	    ");
	    $stmtTotal->execute([$clid]);
	    $total = (int)$stmtTotal->fetchColumn();
	
	    // 2️⃣ حساب LIMIT حسب DataTables
	    $start  = isset($_GET['start']) ? intval($_GET['start']) : 0;
	    $length = isset($_GET['length']) ? intval($_GET['length']) : 0; // default 5
	    $limit  = isset($_GET['start']) && isset($_GET['length'])  ? " LIMIT $start, $length " : "";
	
	    // 3️⃣ جلب بيانات الصفحة الحالية
	    $stmtData = $this->conn->prepare("
	        SELECT 
	            A.ORDER_ID,
	            A.ORDER_STATUS , 
	            A.SUB_ORDER_ID, 
	            A.ADDRESS, 
	            A.TELEPHONE, 
	            A.DATE_CREATED, 
	            A.DELIVERY_DATE, 
	            A.SHIPPING_DATE, 
	            A.DELIVERED_DATE, 
	            A.SHIPPING_VAL, 
	            k.CI_ID,
	            R.GOV_ID,
	            R.COUNTRY_ID,
	            
	            C.STA_NAME, 
	            C.COLOR_CLASS,
	            
	            D.ORDER_TYPE_NAME,
	            
	            CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
	            H.CLIENT_NAME, H.CLIENT_EMAIL,
	            L.SHIPPING_TIME_MAX,
	            SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
	            K.CI_NAME, R.GOV_NAME,
	            JSON_ARRAYAGG(
	                JSON_OBJECT(
	                    'PRODUCT_CODE', POV.PRO_CODE,
	                    'PRODUCT_NAME', POV.PRO_NAME,
	                    'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
	                    'ORDER_QTY', FORMAT(M.QTY,2),
	                    'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
	                    'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
	                    'PRICE', FORMAT(M.PRICE,2),
	                    'DISCOUNT', FORMAT(M.DISCOUNT,2),
	                    'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
	                )
	            ) AS PRODUCTS_DATA
	        FROM ORDERS A
	        LEFT JOIN ORDER_STATUSE C ON A.ORDER_STATUS = C.STA_ID
	        LEFT JOIN ORDER_TYPE D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
	        LEFT JOIN USERS E ON A.ORDER_BY = E.USER_ID
	        LEFT JOIN ADDRESSES G ON A.ADDRESS_ID = G.ADDRESS_ID
	        LEFT JOIN CLIENT H ON G.CLIENT_ID = H.CLIENT_ID
	        LEFT JOIN CITY K ON G.CITY_ID = K.CI_ID
	        LEFT JOIN GOV R ON K.GOV_ID = R.GOV_ID
	        LEFT JOIN ZONES L ON K.ZONE_ID = L.ZONE_ID
	        LEFT JOIN ORDER_PRODUCT M ON A.ORDER_ID = M.ORDER_ID
	        LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE=POV.PRO_CODE
	        LEFT JOIN TELEPHONES T ON A.TEL_ID = T.TEL_ID
	        WHERE T.CLIENT_ID = ?
	        GROUP BY A.ORDER_ID
	        ORDER BY A.ORDER_STATUS, A.DATE_CREATED DESC
	        $limit
	    ");
	    $stmtData->execute([$clid]);
	    $resultData = $stmtData->fetchAll(PDO::FETCH_ASSOC);
	
	    // 4️⃣ تجهيز البيانات للـ DataTables
	    foreach ($resultData as $record) {
	        $records[] = [
	            'oid'              => $record['ORDER_ID'],
	            'subOid'           => $record['SUB_ORDER_ID'],
	            'ADDRESS'          => $record['ADDRESS'],
	            'TELEPHONE'        => $record['TELEPHONE'],
	            'DATE_CREATED'     => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
	            'DELIVERY_DATE'    => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
	            'SHIPPING_DATE'    => $record['ORDER_STATUS'] > 1 ? ( date("Y/m/d", strtotime($record["SHIPPING_DATE"])) !='1970-01-01' ?  date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	            'DELIVERED_DATE'   => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) !='1970-01-01' ?  date("Y/m/d", strtotime($record["DELIVERED_DATE"])) : 'غير متاح') : 'غير متاح',
	            'SHIP_VAL'         => $record['SHIPPING_VAL'],
	            'STA_NAME'         => $record['STA_NAME'],
	            'ORDER_STATUS'     => $record['ORDER_STATUS'],
	            'ORDER_TYPE_NAME'  => $record['ORDER_TYPE_NAME'], 
	            'ORDERBY'          => $record['ORDERBY'], 
	            'CLIENT_NAME'      => $record['CLIENT_NAME'], 
	            'CLIENT_EMAIL'     => $record['CLIENT_EMAIL'], 
	            'SHIPPING_TIME_MAX'=> $record['SHIPPING_TIME_MAX'], 
	            'PRICE'            => $record['PRICE'], 
	            'CI_ID'            => $record['CI_ID'], 
	            'GOV_ID'           => $record['GOV_ID'], 
	            'COUNTRY_ID'       => $record['COUNTRY_ID'], 
	            'CI_NAME'          => $record['CI_NAME'], 
	            'GOV_NAME'         => $record['GOV_NAME'],
	            'COLOR_CLASS'      => $record['COLOR_CLASS'],
	            'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']
	        ];
	    }
	
	    // 5️⃣ إرجاع JSON متوافق مع DataTables 2.x
	    return [
	        "draw" => intval($_POST['draw'] ?? $_GET['draw'] ?? 0),
	        "recordsTotal" => $total,         // العدد الكلي: 6
	        "recordsFiltered" => $total,      // بعد أي فلترة (هنا نفس العدد)
	        "data" => $records                 // البيانات الحالية (5 فقط في الصفحة الأولى)
	    ];
	}
}
?>