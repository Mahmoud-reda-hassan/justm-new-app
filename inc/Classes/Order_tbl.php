<?php
include_once(__dir__.'/../configDateTimeFN.php');
include_once(__dir__.'/../controller/BackEnd/purchases_BE.php');
include_once(__dir__.'/../controller/BackEnd/finance_BE.php');

/*================================================
**  orders Database Table
**==============================================*/
class Order_tbl {
    private $ordersTbl = 'ORDERS';
    public $order_id;
    public $sub_order_id;
    public $order_type_id;
    public $delivery_id;
    public $order_by;
    public $city_id;
    public $address_id;
    public $address;
    public $comment;
    public $tel_id;
    public $telephone;
    public $data_created;
    public $delivery_date;
    public $delivery_type;
    public $order_status;
    public $exists_id;
    public $order_success;
    public $sto_id;
    public $last_update;
    public $exchange = NULL;
    public $orderObj;
	
    private $orderProductTbl = "ORDER_PRODUCT";
    public $pro_code;
    public $qty;
    public $price;
    public $discount;
    public $stqty;
    public $pro_name;
    public $itemObj;
    private $isertProVal;
    private $union;
    private $sttype;

    private $orderlogTbl    = "ORDERS_LOG";
    public $user_action;
    public $log_text;
    public $logObj;
    public $logStr;
    
    private $consignmentTbl = "consignment";
    public $cons_id;
    public $user_id;
    public $cons_qty;
    
    public $rq_parm;
    private $storeProdTbl = "stores_product";
    
    private $start_Where  = '';
    private $start_tWhere = '';
    
    private $conn;
    private $dtz ;
    
    public 	function __construct 	( $db ) 						{
        $this->conn = $db;
        $this->dtz  = new configDateTime();
    }
    
    private function getCustody 	() 								{
    	$reval=[
    		"orders" =>[],
    		"restriction" =>[]
    		];
    	$sqlQuery = " SELECT * FROM `custody_orders` WHERE USER_ID = :deliveryid ";
       	$stmtCust = $this->conn->prepare($sqlQuery);
       	$stmtCust->bindValue(':deliveryid', $_SESSION['USER_ID'], PDO::PARAM_INT);
        $stmtCust->execute();
        $deliveryid = $_SESSION['USER_ID'];
       	while ($record = $stmtCust->fetch(PDO::FETCH_ASSOC)){
			if($record['CUST_STAT'] != 'FREEZE' && $record['USER_ID'] == $deliveryid){
				$reval['orders'][] = $record['ORDER_ID'];
			}else{
				$reval['restriction'][] = $record['ORDER_ID'];
			}
		}
        
        return([
        	implode(',',$reval['orders']),
        	implode(',',$reval['restriction'])
       	]);
    }

	private function filter 		( &$request, $fi, $bool=TRUE )	{
		$globalSearch = array();
		$columnSearch = array();
		$globalwhere  = array();
		$where = '';
		switch ($request["ptype"]) {
		    case 1: //منطقة الاداريين
		    	if(isset($request["ex"]) && $request["ex"] > 0){
		    		$globalwhere[] = ' A.EXISTS_ID = '. $request["ex"];
		    	} else if(isset($request["ex"],$fi["exists"]['id']) && $fi["exists"]['id'] > 0 && $bool){
		    		$globalwhere[] = ' A.EXISTS_ID = '. $fi["exists"]['id'];
		    	}
		        break;
		    case 3:  //منطقة مندوبين الشحن
		        $globalwhere[] = ' A.DELIVERY_ID = ' . $_SESSION['USER_ID'] .' ';
		        if($request["st"] == 2 && $request['ex'] == 9){
		        	$globalwhere[] = ' A.ORDER_STATUS = 2 ';
		        	$globalwhere[] = ' A.ORDER_ID IN('.$this->getCustody()[0].') ' ;
		        	//$globalwhere[] = ' A.ORDER_ID NOT IN('.$this->getCustody()[1].') ';
		        }else if($request["st"] == 0 && $request['ex'] == 9){
	            	$globalwhere[] = ' A.ORDER_STATUS in (3,4) ';
	            }
		        break;
		    default: //منطقة منشئى اوامر البيع
		    	$globalwhere[] = ' A.ORDER_BY = ' . $_SESSION['USER_ID'] .' ';
		}
		//تصفية حسب نوع الشحن
        if (!empty($request["ptype"]) && ($request["ptype"] == 1 || $request["ptype"] == 2 || $request["ptype"] == 4 || $request["ptype"] == 5) && $bool && !empty($fi["shippingType"]['id'])) {
            $globalwhere[] = ' L.SHIPPING_TYPE = ' . $fi["shippingType"]['id'];
        }
        //تصفية حسب خط السير 
        if (!empty($request["ptype"]) && ($request["ptype"] == 1 || $request["ptype"] == 2 || $request["ptype"] == 3 || $request["ptype"] == 4 || $request["ptype"] == 5) && $bool && !empty($fi["zones"]['id'])) {
            $globalwhere[] = ' L.ZONE_ID = ' . $fi["zones"]['id'];
        }
        //تصفية حالة امر البيع
        if (!empty($request["ptype"]) && ($request["ptype"] == 1 || $request["ptype"] == 2 || $request["ptype"] == 4 || $request["ptype"] == 5) && $bool && !empty($fi["statuse"]['id'])) {
            $globalwhere[] = ' A.ORDER_STATUS = ' . $fi["statuse"]['id'];
        }
        //تصفية الاوامر حسب نوع المنتج
        if (!empty($request["ptype"]) && ($request["ptype"] == 1 || $request["ptype"] == 2 || $request["ptype"] == 4 || $request["ptype"] == 5) && $bool && !empty($fi["products"]['id'])) {
            $globalwhere[] = ' O.PRO_ID IN  ('. implode(',', $fi["products"]['id']) . ') ';
        }
        //تصفية الاوامر حسب التاريخ
        if (!empty($request["ptype"]) && ($request["ptype"] == 1 || $request["ptype"] == 2 || $request["ptype"] == 4 || $request["ptype"] == 5) && $bool && !empty($fi["dateType"]['id']) && !empty($fi["dateRange"]['id'])) {
            if ($fi["dateType"]['id'] == 1) {
                $globalwhere[] = ' A.DATE_CREATED BETWEEN "'.$fi["dateRange"]['id'][0].' 00:00:00.000" AND "'.$fi["dateRange"]['id'][1].' 23:59:59.999" ';
            } else if ($fi["dateType"]['id'] == 2) {
                $globalwhere[] = ' A.DELIVERY_DATE BETWEEN "'.$fi["dateRange"]['id'][0].' 00:00:00.000" AND "'.$fi["dateRange"]['id'][1].' 23:59:59.999" ';
            } else if ($fi["dateType"]['id'] == 3) {
                $globalwhere[] = ' A.DELIVERY_DATE BETWEEN "'.$fi["dateRange"]['id'][0].' 00:00:00.000" AND "'.$fi["dateRange"]['id'][1].' 23:59:59.999" ';
            }
        }
        //عرض الطلبات المقدمه لخدمة العملاء
        /*تحت الانشاء*/

		if ( isset($request['search']) && $request['search']['value'] != '' && $bool) {
            $str = $request['search']['value'];
            $globalSearch[] = ' A.SUB_ORDER_ID 						LIKE "%'.$str.'%" ';
            $globalSearch[] = ' Concat(E.FORENAME," ",E.LASTNAME) 	LIKE "%'.$str.'%" ';
            $globalSearch[] = ' A.TELEPHONE    						LIKE "%'.$str.'%" ';
            $globalSearch[] = ' A.ADDRESS      						LIKE "%'.$str.'%" ';
            $globalSearch[] = ' H.CLIENT_NAME  						LIKE "%'.$str.'%" ';
            $globalSearch[] = ' H.CLIENT_EMAIL 						LIKE "%'.$str.'%" ';
        }

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalwhere ) ) {
			$where = '('.implode(' AND ', $globalwhere).')';
		}

		if ( count( $globalSearch ) ) {
			$where = $where === '' ?
				'('.implode(' OR ', $globalSearch).')':
				$where .' AND ('.implode(' OR ', $globalSearch).')';
		}

		if ( $where !== '' ) {
			$where = ' WHERE '.$where;
		}

		return $where;
	}
    
    private function change_order 	( $uid ) 						{
        $sqlQuerylastupdate = 'SELECT LAST_UPDATE FROM ORDERS WHERE ORDER_ID = ? ';
        $stlastupdate = $this->conn->prepare($sqlQuerylastupdate);
        $stlastupdate->bindValue(1, $uid, PDO::PARAM_INT);
        $stlastupdate->execute();
        $result = $stlastupdate;
        $datareturn = '';
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $datareturn = $record['LAST_UPDATE'];
        }
        return $datareturn;
        $this->conn = null;
        exit();
    }
    
	private function releaseOrderLocks(array $lockedOrders): void {
	    $releasedCount = 0;
	    
	    foreach ($lockedOrders as $orderId) {
	        try {
	            $lockName = "order_lock_{$orderId}";
	            
	            // استخدم prepared statement علشان أكثر أماناً
	            $stmt = $this->conn->prepare("SELECT RELEASE_LOCK(?) as released");
	            $stmt->execute([$lockName]);
	            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	            
	            //$this->logManager->info("🔓 تم تحرير تأقف الأمر {$orderId}");
	            $releasedCount++;
	            
	        } catch (Exception $e) {
	            //$this->logManager->error("❌ فشل تحرير تأقف الأمر {$orderId}: " . $e->getMessage());
	        }
	    }
	    
	    //$this->logManager->info("🔓 تم تحرير {$releasedCount} من أصل " . count($lockedOrders) . " أقفال");
	}
	
	private function acquireOrderLock(int $orderId, int $timeoutSeconds = 10): bool {
	    $startTime = microtime(true);
	    $attempts = 0;
	    
	    while (true) {
	        $attempts++;
	        $lockName = "order_lock_{$orderId}";
	        
	        try {
	            $stmt = $this->conn->prepare("SELECT GET_LOCK(?, 0) as locked");
	            $stmt->execute([$lockName]);
	            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	            
	            if (!empty($rows) && $rows[0]['locked'] == 1) {
	                $waitTime = round(microtime(true) - $startTime, 2);
	                //$this->logManager->info("🔒 تم تأقف الأمر {$orderId} بعد {$attempts} محاولة في {$waitTime} ثانية");
	                return true;
	            }
	        } catch (Exception $e) {
	            //$this->logManager->error("❌ خطأ في تأقف الأمر {$orderId}: " . $e->getMessage());
	        }
	        
	        if ((microtime(true) - $startTime) >= $timeoutSeconds) {
	            //$this->logManager->warning("⏰ انتهى وقت انتظار تأقف الأمر {$orderId} بعد {$attempts} محاولة");
	            return false;
	        }
	        
	        usleep(100000);
	    }
	}
    
    public 	function proStore 		( $codes=array() ,$storeid=null){
    	
        $where= array();
        $wh = '';
		if ( isset($this->rq_parm['search']) && $this->rq_parm['search']['value'] != '') {
	        $str = $this->rq_parm['search']['value'];
	        $where[] = 'PR.PRO_NAME	LIKE "'.$str.'%" ';
	    }

        if(is_array($codes) && count($codes)>0){
            $where[] = 'A.PRO_CODE IN ('.implode(",",$codes).')';
        }
        
        if($storeid && $storeid >0){
        	$where[] = 'A.STO_ID =' .$storeid;
        }
        if ( count( $where ) ) {
			$wh = ' WHERE '.implode(' AND ', $where);
		}

        $sqlQueryStore = "SELECT 
							A.STO_ID,
							C.STTY_ID,
							PR.*, 
							PO.OPT_JSON,
							A.PRO_CODE,
							A.STO_QTY   AS STORE_QTY,
							( A.STO_QTY - COALESCE(B.PRO_QTY_USER,0)) AS USER_QTY,
							( A.STO_QTY - COALESCE(B.PRO_QTY_ADMIN,0)) AS ADMIN_QTY,
           					( A.STO_QTY - COALESCE(B.PRO_QTY_PROCES,0)) AS PROCES_QTY
							FROM  STORES_PRODUCT A
							LEFT JOIN (SELECT  D.STORE_ID,
							     E.USER_STORE,
							     E.ADMIN_STORE,
							     F.PRODUCT_ID,
							     A.PRO_CODE,
							     SUM(CASE WHEN E.ADMIN_STORE = 1 AND A.QTY IS NOT NULL THEN A.QTY ELSE 0 END) AS PRO_QTY_ADMIN,
							     SUM(CASE WHEN E.USER_STORE = 1 AND A.QTY IS NOT NULL THEN A.QTY ELSE 0 END) AS PRO_QTY_USER,
                                 SUM(CASE WHEN E.EXISTS_ID = 8 AND A.QTY IS NOT NULL THEN A.QTY ELSE 0 END) AS PRO_QTY_PROCES
							   FROM   ORDER_PRODUCT A
							     LEFT JOIN ORDERS        B ON A.ORDER_ID = B.ORDER_ID 
							     LEFT JOIN CITY          C ON B.CITY_ID = C.CI_ID
							     LEFT JOIN ZONES         D ON C.ZONE_ID = D.ZONE_ID
							     LEFT JOIN ORDER_EXISTS  E ON B.EXISTS_ID = E.EXISTS_ID
							     LEFT JOIN PRODUCT_CODES F ON A.PRO_CODE = F.PRO_CODE
							   GROUP BY A.PRO_CODE,
							     D.STORE_ID) B ON A.STO_ID = B.STORE_ID AND A.PRO_CODE = B.PRO_CODE
           					LEFT JOIN product_codes PC ON A.PRO_CODE = PC.PRO_CODE
							LEFT JOIN products PR ON PR.PRO_ID = PC.PRODUCT_ID 
							LEFT JOIN STORES C ON A.STO_ID = C.STO_ID
							LEFT JOIN product_options_view PO ON PC.PRO_CODE = PO.PRO_CODE
							".$wh."
							GROUP BY
							A.STO_ID,
							C.STTY_ID,
							B.PRODUCT_ID,
							PR.PRO_NAME,
							A.PRO_CODE ";
		
        return $sqlQueryStore."ORDER BY PRO_NAME,PO.OPT_JSON ASC";
        
    }
    
    public 	function orderView 		($log = true) 								{
    	
        $orderIds = array_values(
					    array_unique(
					        array_filter(
					            array_map('intval', explode(',', $this->order_id)),
					            fn($v) => $v > 0
					        )
					    )
					);
	    
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        //==========================================================================================================================
        $sqlQuery0 = "SELECT A.ORDER_ID,
							A.SUB_ORDER_ID,
							A.ORDER_BY,
							A.ORDER_TYPE_ID,
							D.ORDER_TYPE_NAME,
							B.EXISTS_NAME,
							A.EXISTS_ID,
							A.EXCHANGE,
							A.ORDER_STATUS,
							C.STA_NAME,
							C.COLOR_CLASS,
							Concat(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
							CASE WHEN Concat(F.FORENAME,' ',F.LASTNAME) <> '' THEN Concat(F.FORENAME,' ',F.LASTNAME) ELSE '' END AS DELIVERYBY,
							H.CLIENT_ID,
							H.CLIENT_NAME,
							H.CLIENT_EMAIL,
							A.TEL_ID,
    						A.TELEPHONE,
    						A.CITY_ID,
    						A.ADDRESS_ID,
							A.ADDRESS, 
							A.COMMENT,
							A.TELEPHONE,
							A.DATE_CREATED,
							A.DELIVERY_DATE,
							A.DELIVERY_TYPE,
							L.SHIPPING_TIME_MAX,
							L.ZONE_NAME,
							L.SHIPPING_LOWEST,
    						L.SHIPPING_RATE,
    						A.STO_ID,
    						A.SHIPPING_VAL,
    						L.SHIPPING_TIME_MAX,
							A.ORDER_SUCCESS,
							A.LAST_UPDATE,
							A.PRINT,
							P.INVO_ID,
							T.OS_END_COUNT,
							CASE WHEN Q.OS_ID IS NOT NULL AND Q.OS_STATUSE IS NOT NULL AND Q.OS_ID >0 AND Q.OS_STATUSE=0 THEN 1 
                                 WHEN Q.OS_ID IS NOT NULL AND Q.OS_STATUSE IS NOT NULL AND R.MSG_COUNT IS NOT NULL AND Q.OS_ID >0 AND Q.OS_STATUSE=1 AND R.MSG_COUNT > 0 THEN 1 ELSE 0 END  AS MSGCS,
							CASE WHEN Q.OS_ID IS NOT NULL AND Q.OS_STATUSE = 0 THEN Q.OS_ID ELSE 0 END AS OS_ID,
							CASE WHEN Q.OS_STATUSE IS NOT NULL THEN Q.OS_STATUSE  ELSE 0 END AS OS_STATUSE,
							CASE WHEN P.INVO_ID IS NOT NULL THEN P.INVO_ID ELSE 0 END AS ORDER_INVO,
						    CASE WHEN R.MSG_COUNT IS NOT NULL THEN R.MSG_COUNT ELSE 0 END AS MSG_COUNT,
							mh.movement_history
					FROM ".$this->ordersTbl." A
							LEFT JOIN ORDER_EXISTS  B ON A.EXISTS_ID = B.EXISTS_ID
							LEFT JOIN ORDER_STATUSE C ON A.ORDER_STATUS = C.STA_ID
							LEFT JOIN ORDER_TYPE    D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
							LEFT JOIN USERS         E ON A.ORDER_BY = E.USER_ID
							LEFT JOIN USERS         F ON A.DELIVERY_ID = F.USER_ID
							LEFT JOIN ADDRESSES     G ON A.ADDRESS_ID = G.ADDRESS_ID
							LEFT JOIN CLIENT        H ON G.CLIENT_ID = H.CLIENT_ID
							LEFT JOIN TELEPHONES    J ON A.TEL_ID = J.TEL_ID
							LEFT JOIN CITY          K ON G.CITY_ID = K.CI_ID
							LEFT JOIN ZONES         L ON K.ZONE_ID=L.ZONE_ID
							LEFT JOIN invoices      P ON A.ORDER_ID = P.REFERENCE_ID AND P.REFERENCE_TYPE = 'SALES'
							LEFT JOIN ORDER_SERVICE Q ON A.ORDER_ID = Q.ORDER_ID AND OS_DATE_CLOSE IS NULL
							LEFT JOIN ( SELECT OS_ID, COUNT(OS_ID) AS MSG_COUNT  FROM  order_service_text WHERE  OSTX_READ = 0 AND USER_ID <> ".$_SESSION['USER_ID'] ." GROUP BY OS_ID ) R ON Q.OS_ID = R.OS_ID 
							LEFT JOIN (SELECT ORDER_ID, COUNT(OS_ID) AS OS_END_COUNT FROM ORDER_SERVICE WHERE OS_STATUSE = 1 GROUP BY ORDER_ID) T ON A.ORDER_ID = T.ORDER_ID AND T.ORDER_ID IS NOT NULL  
							LEFT JOIN (
								SELECT 
									shipment_orders AS order_id,
									JSON_ARRAYAGG(
										CONCAT(
											CASE movement_type
												WHEN 0 THEN 'بدء الشحن'
												WHEN 1 THEN 'قيد الاسترداد'
												WHEN 2 THEN 'تم الاسترداد'
												WHEN 3 THEN 'تم التسليم'
												WHEN 4 THEN 'تسليم جزئى'
												WHEN 5 THEN 'مرفوض'
												WHEN 6 THEN 'تسويه مخزنيه'
												WHEN 7 THEN 'تسويه ماليه'
												WHEN 8 THEN 'مرتجع بعد البيع'
												WHEN 9 THEN 'مرتجع جزئى بعد البيع'
												WHEN 10 THEN 'رفض العميل ارجع النتجات'
												WHEN 11 THEN 'تم تسوية المرتجع ماليا'
											END,
											' | ',
											DATE(MOVING_DATE),
											' | ',
											TIME(MOVING_DATE)
										)
										ORDER BY MOVING_DATE
									) AS movement_history
								FROM (
									SELECT *,
										   ROW_NUMBER() OVER (
											   PARTITION BY shipment_orders
											   ORDER BY MOVING_DATE DESC, shipmov_id DESC
										   ) AS rn
									FROM shipping_movement
									WHERE movement_type IN (3,4,5)

									UNION ALL

									SELECT *,
										   1 AS rn
									FROM shipping_movement
									WHERE movement_type NOT IN (3,4,5)
								) t
								WHERE rn = 1
								GROUP BY shipment_orders
							) AS mh ON A.ORDER_ID = MH.ORDER_ID
					WHERE A.ORDER_ID IN(".$placeholders.")  ";
        //==========================================================================================================================
        $sqlQuery1 = " WITH OrderProducts AS (
							SELECT DISTINCT PRO_CODE
							FROM ORDER_PRODUCT
							WHERE ORDER_ID IN(".$placeholders.")
						)
						SELECT
							OP.ORDER_ID,
							OP.PRO_CODE,
							PC.PRODUCT_CODE_ID,
							PC.PRODUCT_ID,
							PR.PRO_NAME,
							OPT.OPTION_ID,
							OPT.OPTION_TEXT,
							OP.QTY,
							OP.DELIVERED_QTY,
							OP.RETURNED_QTY,
							OP.PRICE,
							OP.DISCOUNT,
							CAT.CAT_NAME,
							PR.PRO_VISBLE,
							PR.PER_BOOKING,
							PR.PRO_DESCRIPTION,
							PR.PRO_IMG_LINK,
							PR.HEIGHT,
							PR.LENGTH,
							PR.WIDTH,
							PR.WEIGHT,
							PC.CODE_COST_PRICE,
							PC.CODE_MAX_STOCK,
							PC.CODE_MIN_STOCK,
							PC.CODE_PRICE,
							ST.USER_QTY   AS UQTY,
							ST.ADMIN_QTY  AS AQTY,
							ST.PROCES_QTY AS PQTY,
							SP.STO_QTY AS SQTY
						FROM ORDER_PRODUCT OP
						INNER JOIN ORDERS O ON O.ORDER_ID = OP.ORDER_ID
						LEFT JOIN PRODUCT_CODES PC ON PC.PRO_CODE = OP.PRO_CODE
						LEFT JOIN PRODUCTS PR ON PR.PRO_ID = PC.PRODUCT_ID
						LEFT JOIN PRODUCT_CATEGORY CAT ON CAT.CAT_ID = PR.CAT_ID
						LEFT JOIN (
							SELECT
								C.PRODUCT_CODE,
								GROUP_CONCAT(POG.OPTION_ID SEPARATOR ',') AS OPTION_ID,
								GROUP_CONCAT(
									CONCAT(POG.OPTION_NAME,' : ',PO.VALUE)
									SEPARATOR ', '
								) AS OPTION_TEXT
							FROM PRODUCT_CODE_JOIN_OPTION C
							INNER JOIN PRODUCT_OPTION PO ON PO.ID = C.OPTION_VALUE_ID
							INNER JOIN PRODUCT_OPTION_GROUP POG ON POG.OPTION_ID = PO.OPTION_ID
							INNER JOIN OrderProducts OPP ON OPP.PRO_CODE = C.PRODUCT_CODE
							GROUP BY C.PRODUCT_CODE
						) OPT ON OPT.PRODUCT_CODE = OP.PRO_CODE
						LEFT JOIN (
							SELECT
								SP.STO_ID,
								SP.PRO_CODE,
								SP.STO_QTY,
								(SP.STO_QTY-PRO_QTY_USER)   USER_QTY,
								(SP.STO_QTY-PRO_QTY_ADMIN)  ADMIN_QTY,
								(SP.STO_QTY-PRO_QTY_PROCES) PROCES_QTY
							FROM STORES_PRODUCT SP
							LEFT JOIN (
								SELECT
									Z.STORE_ID,
									OP.PRO_CODE,
									SUM(CASE WHEN OE.ADMIN_STORE=1 THEN OP.QTY ELSE 0 END) PRO_QTY_ADMIN,
									SUM(CASE WHEN OE.USER_STORE=1 THEN OP.QTY ELSE 0 END) PRO_QTY_USER,
									SUM(CASE WHEN OE.EXISTS_ID=8 THEN OP.QTY ELSE 0 END) PRO_QTY_PROCES
								FROM ORDER_PRODUCT OP
								INNER JOIN ORDERS O ON O.ORDER_ID=OP.ORDER_ID
								INNER JOIN CITY CI ON CI.CI_ID=O.CITY_ID
								INNER JOIN ZONES Z ON Z.ZONE_ID=CI.ZONE_ID
								INNER JOIN ORDER_EXISTS OE ON OE.EXISTS_ID=O.EXISTS_ID
								
								GROUP BY Z.STORE_ID, OP.PRO_CODE
							) R ON R.STORE_ID=SP.STO_ID AND R.PRO_CODE=SP.PRO_CODE
						) ST ON ST.STO_ID=O.STO_ID AND ST.PRO_CODE=OP.PRO_CODE
						LEFT JOIN STORES_PRODUCT SP ON SP.STO_ID=O.STO_ID AND SP.PRO_CODE=OP.PRO_CODE
						WHERE OP.ORDER_ID IN(".$placeholders."); ";
        //==========================================================================================================================
        $sqlQuery2 = 'SELECT A.LOG_ID,
                            A.ORDER_ID,
		                    B.SUB_ORDER_ID,
		                    CONCAT(C.FORENAME," ",C.LASTNAME) AS uACTION,
		                    A.DATE_ACTION,
		                    A.LOG_TEXT
		            FROM ORDERS_LOG A
		            LEFT JOIN `ORDERS` B ON A.ORDER_ID = B.ORDER_ID
		            LEFT JOIN `USERS` C ON A.USER_ACTION = C.USER_ID
		            WHERE A.ORDER_ID IN('.$placeholders.')  ORDER BY A.DATE_ACTION DESC LIMIT 50; ';
        //==========================================================================================================================
        $order = array();
        //$this->order_id = htmlspecialchars(strip_tags(trim($this->order_id)));
        $stmt0 = $this->conn->prepare($sqlQuery0);
        $stmt0->execute($orderIds);
        $result = $stmt0;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $row = array();
            $order[$record['ORDER_ID']] = array(
                'orderData'   =>[],
                'orderPro'    =>[]
            );
            $row['order_id'] = $record['ORDER_ID'];
            $row['orderby'] = $record['ORDER_BY'];
            $row['uorderby'] = $record['ORDERBY'];
            $row['ordertype'] = $record['ORDER_TYPE_NAME'];
            $row['sub_id'] = $record['SUB_ORDER_ID'];
            $row['uid'] = $record['CLIENT_ID'];
            $row['oty'] = $record['ORDER_TYPE_ID'];
            $row['uname'] = $record['CLIENT_NAME'];
            $row['umail'] = $record['CLIENT_EMAIL'];
            $row['telid'] = $record['TEL_ID'];
            $row['telstr'] = $record['TELEPHONE'];
            $row['addid'] = $record['ADDRESS_ID'];
            $row['cityid'] = $record['CITY_ID'];
            $row['addstr'] = $record['ADDRESS'];
			$row['ZName'] = $record['ZONE_NAME'];
            $row['cliComment'] = $record['COMMENT'];
            $row['shiplow'] = $record['SHIPPING_LOWEST'];
            $row['shipmax'] = $record['SHIPPING_RATE'];
            $row['stoid'] = $record['STO_ID'];
            $row['shipval'] = $record['SHIPPING_VAL'];
            $row['shipDays'] = $record['SHIPPING_TIME_MAX'];
            $row['shipWaiting'] = $record['DELIVERY_TYPE'];
            $row['shipDate'] = $record['DELIVERY_DATE'];
            $row['orderDate'] = date_format($this->dtz->setDateTime($record['DATE_CREATED']),"Y-m-d H:i");
            $row['EXCHANGE'] = $record['EXCHANGE'];
            $row['exists_id'] = $record['EXISTS_ID'];
            $row['exists_name'] = $record['EXISTS_NAME'];
            $row['order_status'] = $record['ORDER_STATUS'];
            $row['status_name'] = $record['STA_NAME'];
            $row['status_color']= 'bg-'.$record['COLOR_CLASS'];
            $row['print']       = $record['PRINT'];
            $row['ORDER_INVO'] = $record['ORDER_INVO'];
            $row['last_update'] = $record['LAST_UPDATE'];
            $row['MSG_COUNT'] = $record['MSG_COUNT'];
            $row['MSGCS'] = $record['MSGCS'];
            $row['OS_ID'] = $record['OS_ID'];
            $row['OS_STATUSE'] = $record['OS_STATUSE'];
            $row['movement_history'] = $record['movement_history'];
            $order[$record['ORDER_ID']]['orderData'] = $row;
            
        }
        //==========================================================================================================================
        $params = array_merge($orderIds, $orderIds);
		$stmt1 = $this->conn->prepare($sqlQuery1);
		$stmt1->execute($params);
        $result = $stmt1;
        $procodes = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $procodes[]=$record['PRO_CODE'];
            $row = array();
            $row['order_id']        = $record['ORDER_ID'];
            $row['pro_id']          = $record['PRODUCT_ID'];
            $row['PRO_CODE']        = $record['PRO_CODE'];
            $row['qty']             = $record['QTY'];
            $row['deliveredQty']    = $record['DELIVERED_QTY'];
            $row['returnedQty']     = $record['RETURNED_QTY'];
            $row['CODE_PRICE']      = $record['PRICE'];
            $row['dis']             = $record['DISCOUNT'];
            $row['CAT_NAME']        = $record['CAT_NAME'];
            $row['PRO_NAME']        = $record['PRO_NAME'];
            if($record['OPTION_ID']){
                $row['OPTION_ID']     = explode(',',$record['OPTION_ID']);
            } else {
                $row['OPTION_ID']     =array();
            }
            if($record['OPTION_TEXT']){
                $row['OPTION_TEXT']     = explode(',',$record['OPTION_TEXT']);
            } else {
                $row['OPTION_TEXT']     =array();
            }
            $row['PRODUCT_CODE_ID'] = $record['PRODUCT_CODE_ID'];
            $row['CODE_COST_PRICE'] = $record['CODE_COST_PRICE'];
            $row['CODE_MAX_STOCK']  = $record['CODE_MAX_STOCK'];
            $row['CODE_MIN_STOCK']  = $record['CODE_MIN_STOCK'];
            $row['PRO_VISBLE']      = $record['PRO_VISBLE'];
            $row['PER_BOOKING']     = $record['PER_BOOKING'];
            $row['PRO_DESCRIPTION'] = $record['PRO_DESCRIPTION'];
            $row['LENGTH']          = $record['LENGTH'];
            $row['HEIGHT']          = $record['HEIGHT'];
            $row['WIDTH']           = $record['WIDTH'];
            $row['WEIGHT']          = $record['WEIGHT'];
            $row['PRO_IMG_LINK']    = $record['PRO_IMG_LINK'];
            $row['UQTY']            = $record['UQTY'];
            $row['AQTY']            = $record['AQTY'];
            $row['SQTY']            = $record['SQTY'];
            $row['PQTY']            = $record['PQTY'];
            $order[$record['ORDER_ID']]['orderPro'][$row['PRO_CODE']]  = $row;
            
        }
        
        //==========================================================================================================================
	    if($log){
	        $stmt2 = $this->conn->prepare($sqlQuery2);
	        $stmt2->execute($orderIds);
	        $result = $stmt2;
	        //$order[$record['ORDER_ID']]['orderAction'] = array();
	        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
	            $row = array();
	            $row['order_id'] = $record['SUB_ORDER_ID'];
	            $row['user'] = $record['uACTION'];
	            
	            $row['date'] = date_format($this->dtz->setDateTime($record['DATE_ACTION']),"Y-m-d");
	            $row['time'] = date_format($this->dtz->setDateTime($record['DATE_ACTION']),"h:i A");
	            
	            //$row['date'] = date_format(date_create($record['DATE_ACTION']), "Y/m/d");
	            //$row['time'] = date_format(date_create($record['DATE_ACTION']), "h:i A");
	            $row['log'] = $record['LOG_TEXT'];
	            $order[$record['ORDER_ID']]['orderAction'][] = $row;
	        }
	    }
        return $order;
        $this->conn = null;
        exit();
    }
    
    public 	function addOrders 		() 								{
    	
        $sqlQuery1 = "INSERT INTO `".$this->ordersTbl."`(
							`SUB_ORDER_ID`, 
							`ORDER_TYPE_ID`,
							`DELIVERY_ID`,
							`ORDER_BY`,
							`COUPON_CODE`,
							`CITY_ID`,
							`ADDRESS_ID`,
							`ADDRESS`,
							`COMMENT`,
							`TEL_ID`,
							`TELEPHONE`,
							`DELIVERY_DATE`,
							`DELIVERY_TYPE`,
							`SHIPPING_VAL`,
							`ORDER_STATUS`,
							`EXISTS_ID`,
							`ORDER_SUCCESS`,
							`STO_ID`,
							`EXCHANGE`)
					    VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        //================================================================================
        $sqlQuery2 = "INSERT INTO `".$this->orderProductTbl."`(
							`ORDER_ID`,
							`PRO_CODE`,
							`QTY`,
							`PRICE`,
							`COST_PRICE`,
							`DISCOUNT`)  ";
        //================================================================================
        $sqlQuery3 = "INSERT INTO `ORDERS_LOG`(`ORDER_ID`,`USER_ACTION`,`LOG_TEXT`) VALUES (?,?,?) ";
        //================================================================================
        $i = 10;
		do {
		    $i++;
		    $code = $_SESSION['USER_ID'] . date("ymdHi") . random_int(11, 99);
		    $sqlQueryCh = "SELECT 1 FROM ORDERS WHERE SUB_ORDER_ID = ? LIMIT 1";
		    $stCh = $this->conn->prepare($sqlQueryCh);
		    $stCh->bindValue(1, $code, PDO::PARAM_STR);
		    $stCh->execute();
		} while ($stCh->fetchColumn() && $i < 100);
		
		if ($i >= 100) {
		    echo json_encode([
		        'e',
		        'لم يتم إنشاء رقم طلب فريد، حاول مرة أخرى'
		    ]); 
		    exit();
		}

        //================================================================================
        //$stoid                  = htmlspecialchars(strip_tags(trim($this->orderObj['stoid'])));
        if(!isset($this->orderObj['cliComment'])) $this->orderObj['cliComment']='';
        $this->sub_order_id = $code;
        $this->order_type_id = htmlspecialchars(strip_tags(trim($this->orderObj['oty'])));
        $this->order_by = htmlspecialchars(strip_tags(trim($this->orderObj['orderby'])));
        $this->coupon_code = htmlspecialchars(strip_tags(trim($this->orderObj['coupon_code'])));
        $this->city_id = htmlspecialchars(strip_tags(trim($this->orderObj['cityid'])));
        $this->address_id = htmlspecialchars(strip_tags(trim($this->orderObj['addid'])));
        $this->address = htmlspecialchars(strip_tags(trim($this->orderObj['addstr'])));
        $this->comment = htmlspecialchars(strip_tags(trim($this->orderObj['cliComment'])));
        $this->tel_id = htmlspecialchars(strip_tags(trim($this->orderObj['telid'])));
        $this->telephone = htmlspecialchars(strip_tags(trim($this->orderObj['telstr'])));
        $this->delivery_date = htmlspecialchars(strip_tags(date_format(date_create($this->orderObj['shipDate']), "Y/m/d H:i:s")));
        $this->delivery_type = htmlspecialchars(strip_tags(trim( $this->orderObj['shipWaiting'])));
        $this->shipping_val = htmlspecialchars(strip_tags(trim((isset($this->orderObj['exist']) && $this->orderObj['exist'] == 11) ? '0' : $this->orderObj['shipval'])));
        $this->order_status = 0;
        $this->exists_id = htmlspecialchars(strip_tags(trim(isset($this->orderObj['exist']) ? $this->orderObj['exist'] : 1)));
        $this->order_success = 0;
        $this->sto_id = htmlspecialchars(strip_tags(trim($this->orderObj['stoid'])));
        if(isset($this->orderObj['orderExchange']) && $this->orderObj['orderExchange'] > 0){
        	$this->exchange = htmlspecialchars(strip_tags(trim($this->orderObj['orderExchange'])));
        }
        //$this->last_update	    = htmlspecialchars(strip_tags(trim($this->orderObj['last_update'])));
        //================================================================================
        if (!empty($this->orderObj) && !empty($this->itemObj) && count($this->orderObj) > 0 && count(array($this->itemObj)) > 0) {
            try {
                $this->conn->beginTransaction();
                $union = ""; ///string query from item
                if ($_POST['sttye'] == 1) {
                    $sttype = "C.ADMIN_STORE";
                } else {
                    $sttype = "C.USER_STORE";
                }
                $sql1 = ' SELECT 0 AS ORDER_ID,
							 A.PRO_CODE,
							 D.QTY,
							 A.CODE_PRICE,
							 A.LOWEST_PRICE,
							 D.DISCOUNT ,
							 B.PRO_NAME ';
                $sql2 = ' ,CASE
								WHEN B.PER_BOOKING = 0 AND D.QTY > '.$sttype.' THEN CONCAT("حدث تغيرات فى الخلفية وأصبح الصنف   (",B.PRO_NAME,") مخزونة (",'.$sttype.',") ولا يمكن الحجز المسبق لهاذا الصنف")
								WHEN B.PER_BOOKING = 1 AND D.QTY > '.$sttype.' THEN CONCAT("حدث تغيرات فى الخلفية واصبح الصنف  (",B.PRO_NAME,") مخزونة (",'.$sttype.',") وسوف يتم شحنه حين توافر الكمية المطلوبة")
								WHEN D.DISCOUNT < 0 OR D.DISCOUNT > (A.CODE_PRICE - A.LOWEST_PRICE) THEN CONCAT("حدث تغيرات فى الخلفية للكود (" ,A.PRO_CODE, ") سعر البيع بعد الخصم خارج الحدود المسموح بها (",A.LOWEST_PRICE," - ",A.CODE_PRICE,")")
								ELSE "DONE"
							 END AS CODE_STAT ,
							 CASE
								WHEN B.PER_BOOKING = 0 AND D.QTY > '.$sttype.' THEN "e"
								WHEN B.PER_BOOKING = 1 AND D.STQTY <> '.$sttype.' AND D.QTY > '.$sttype.' THEN "s1"
								WHEN D.DISCOUNT < 0 OR D.DISCOUNT > (A.CODE_PRICE - A.LOWEST_PRICE) THEN "e"
								ELSE "s2"
							 END AS ERROR_TYPE ';
                $sql3 = ' FROM PRODUCT_CODES A
				           LEFT JOIN PRODUCTS B
								 ON A.PRODUCT_ID = B.PRO_ID ';
                $sql4 = ' LEFT JOIN (SELECT A.STO_ID, 
                                            C.PRODUCT_ID, 
                                            A.PRO_CODE, 
                                            A.STO_QTY,
											( A.STO_QTY - CASE WHEN B.USER_QTY IS NOT NULL THEN B.USER_QTY ELSE 0 END ) AS USER_STORE,
											( A.STO_QTY - CASE WHEN B.ADMIN_QTY IS NOT NULL THEN B.ADMIN_QTY ELSE 0 END ) AS ADMIN_STORE
										FROM  STORES_PRODUCT A
											LEFT JOIN (SELECT B.STO_ID, 
											                  D.PRODUCT_ID, 
											                  A.PRO_CODE, 
											                  A.QTY,
															  Sum(CASE WHEN C.USER_STORE = 1 THEN A.QTY ELSE 0 END) AS USER_QTY,
															  Sum(CASE WHEN C.ADMIN_STORE = 1 THEN A.QTY ELSE 0 END) AS ADMIN_QTY
													   FROM   ORDER_PRODUCT A
													   LEFT JOIN ORDERS B ON A.ORDER_ID = B.ORDER_ID
													   LEFT JOIN ORDER_EXISTS C ON B.EXISTS_ID = C.EXISTS_ID
													   LEFT JOIN PRODUCT_CODES D ON A.PRO_CODE = D.PRO_CODE
													   GROUP BY B.STO_ID, D.PRODUCT_ID, A.PRO_CODE) B ON A.PRO_CODE = B.PRO_CODE AND A.STO_ID = B.STO_ID
											LEFT JOIN PRODUCT_CODES C ON A.PRO_CODE = C.PRO_CODE
											LEFT JOIN STORES D ON A.STO_ID=D.STO_ID
										WHERE D.STTY_ID=2
										ORDER BY A.PRO_CODE , A.STO_ID) C ON A.PRO_CODE = C.PRO_CODE AND C.STO_ID = ? ';
                $sql5 = ' RIGHT JOIN ( ';
				$amount = 0;
                foreach ($this->itemObj as $key => $value) {
                    $this->pro_code = htmlspecialchars(strip_tags(trim($value['PRO_CODE'])));
                    $this->qty = htmlspecialchars(strip_tags(trim($value['qty'])));
                    $this->stqty = htmlspecialchars(strip_tags(trim($value['STQTY'])));
                    $this->price = htmlspecialchars(strip_tags(trim($value['CODE_PRICE'])));
                    $this->discount = htmlspecialchars(strip_tags(trim($value['dis'])));
                    $this->pro_name = htmlspecialchars(strip_tags(trim($value['PRO_NAME'])));
                    if ($union == "") {
                        $union .= 'SELECT "'.$this->pro_code.'" AS PRO_CODE,"'.$this->qty.'" AS QTY,"'.$this->stqty.'" AS STQTY, "'.$this->price.'" AS PRICE,"'.$this->discount.'" AS DISCOUNT ,"'.$this->pro_name.'" AS PRO_NAME ';
                    } else {
                        $union .= 'UNION SELECT "'.$this->pro_code.'" AS PRO_CODE,"'.$this->qty.'" AS QTY, "'.$this->stqty.'" AS STQTY,"'.$this->price.'" AS PRICE,"'.$this->discount.'" AS DISCOUNT ,"'.$this->pro_name.'" AS PRO_NAME ';
                    }
                }
                $sql5 .= $union .") D ON A.PRO_CODE = D.PRO_CODE ";
                $sqlchco = " SELECT * FROM (" .$sql1 . $sql2 . $sql3 . $sql4 . $sql5 . ") N ";
                // ==================check qty and discount======================
                $stchco = $this->conn->prepare($sqlchco);
                $stchco->bindValue(1, $this->sto_id, PDO::PARAM_INT);
                if ($stchco->execute()) {
                    $msg1 = '<ul class="text-left">';
                    $err1 = 0;
                    $errCode = array();
                    $err2 = 0;
                    while ($record = $stchco->fetch(PDO::FETCH_ASSOC)) {
                        // احتساب قيمة المنتجات من سعر قاعدة البيانات، وليس من سعر مرسل من المتصفح.
                        $amount += ((float)$record['CODE_PRICE'] - (float)$record['DISCOUNT']) * (float)$record['QTY'];
                        if ($record['ERROR_TYPE'] == "e") {
                            $err1++;
                            $cla = "text-danger";
                            array_push($errCode, $record["PRO_CODE"]);
                            $msg1 .= '<li class="text-danger">'.$record["CODE_STAT"].'</li>';
                        } else if ($record['ERROR_TYPE'] == "s1") {
                            $err2++;
                            $msg1 .= '<li class="">'.$record["CODE_STAT"].'</li>';
                        }
                    }
                    $msg1 .= "</ul>";
                    // مصروف الشحن يخص الطلب بالكامل ولا يضاف لكل صنف.
                    $amount += (float)$this->shipping_val;
                } else {
                    $this->conn->rollback();
                    echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
                }
                if ($err1 === 0) {
                    //======================Save Order========================================
                    $stmt1 = $this->conn->prepare($sqlQuery1);
                    $stmt1->bindValue(1, $this->sub_order_id, PDO::PARAM_STR);
                    $stmt1->bindValue(2, $this->order_type_id, PDO::PARAM_INT);
                    $stmt1->bindValue(3, $this->order_by, PDO::PARAM_INT);
                    $stmt1->bindValue(4, $this->coupon_code, PDO::PARAM_INT);
                    $stmt1->bindValue(5, $this->city_id, PDO::PARAM_INT);
                    $stmt1->bindValue(6, $this->address_id, PDO::PARAM_INT);
                    $stmt1->bindValue(7, $this->address, PDO::PARAM_STR);
                    $stmt1->bindValue(8, $this->comment, PDO::PARAM_STR);
                    $stmt1->bindValue(9, $this->tel_id, PDO::PARAM_INT);
                    $stmt1->bindValue(10,$this->telephone, PDO::PARAM_STR);
                    $stmt1->bindValue(11,$this->delivery_date, PDO::PARAM_STR);
                    $stmt1->bindValue(12,$this->delivery_type, PDO::PARAM_INT);
                    $stmt1->bindValue(13,$this->shipping_val, PDO::PARAM_STR);
                    $stmt1->bindValue(14,$this->order_status, PDO::PARAM_INT);
                    $stmt1->bindValue(15,$this->exists_id, PDO::PARAM_INT);
                    $stmt1->bindValue(16,$this->order_success, PDO::PARAM_INT);
                    $stmt1->bindValue(17,$this->sto_id, PDO::PARAM_INT);
                    $stmt1->bindValue(18,$this->exchange, PDO::PARAM_INT);
                    
                    if ($stmt1->execute()) {
                        //=======================Save Items=====================================
                        $orderid = $this->conn->lastInsertId(); // orderid last Inserted
                        $sql1 = ' SELECT '.$orderid.' AS ORDER_ID,
							 A.PRO_CODE,
							 D.QTY,
							 A.CODE_PRICE,
							 A.CODE_COST_PRICE,
							 D.DISCOUNT ';
                        $sqlitem = $sqlQuery2 . $sql1 . $sql3 . $sql5;
                        $stmt2 = $this->conn->prepare($sqlitem);
                        if ($stmt2->execute() && $stmt2->rowCount() > 0) {
                            $this->logStr = ' قام المستخدم بإنشاء امر البيع ';
                            for ($k = 0; $k < count($this->logObj); $k++) {
                                if ($k !== 0) {
                                    $this->logStr .= htmlspecialchars(strip_tags(trim($this->logObj[$k])));
                                }
                            }
                            $stmt3 = $this->conn->prepare($sqlQuery3);
                            $stmt3->bindValue(1, $orderid, PDO::PARAM_INT);
                            $stmt3->bindValue(2, $this->order_by, PDO::PARAM_INT);
                            $stmt3->bindValue(3, $this->logStr, PDO::PARAM_STR);
                            //echo $sqlQuery3;
                            if ($stmt3->execute() && $stmt3->rowCount() > 0){
	                           	$st3 = false;
	                           	if(isset($this->orderObj['orderExchange'])){
		                            $this->logStr = "قام المستخدم بإنشاء امر بيع *تم انشاء امر استبدال منتجات";
		                            $stmt_3 = $this->conn->prepare($sqlQuery3);
		                            $stmt_3->bindValue(1, $this->exchange, PDO::PARAM_INT);
		                            $stmt_3->bindValue(2, $this->order_by, PDO::PARAM_INT);
		                            $stmt_3->bindValue(3, $this->logStr, PDO::PARAM_STR);
		                            if($stmt_3->execute()&& $stmt_3->rowCount() > 0){
		                            	$st3= true;
		                            }
	                           	}else{
	                               $st3 = true;
	                           	}
	                        	//echo $sqlQuery_3;
	                        	if ($st3) {
	                            	$stmt4 = $this->conn->prepare('SELECT CLIENT_ID FROM addresses WHERE ADDRESS_ID = ?');
	                            	$stmt4->bindValue(1, $this->address_id, PDO::PARAM_INT);
	                            	$stmt4->execute();
	                            	if($stmt4->execute() && $stmt4->rowCount() == 1){
	                            		$cli_id = $stmt4->fetch(PDO::FETCH_ASSOC);                            		
	                            		$cli_id = $cli_id['CLIENT_ID'];
	                            		$finance = new finance_BE($this->conn);
	                            		$resultFinal = $finance->addClientDebt($cli_id, $orderid, $amount);
		                                if($resultFinal['result']){
		                                	$this->conn->commit();
			                                if ($err2 === 0) {
			                                    echo json_encode(array('s', 'تم حفظ امر البيع برقم - '.$this->sub_order_id));
			                                } else {
			                                    echo json_encode(array('s', '<a>تم حفظ امر البيع برقم - '.$this->sub_order_id .'<br>ولكن هناك تغيرات حدثة فى الخلفية عند الحفظ </a><hr>'.$msg1));
			                                }
		                                }else{
		                                	$this->conn->rollback();
		                                	echo json_encode(array('e','خطاء - لم ننجح فى حفظ امر البيع'));
		                                }
	                            	}else{
	                            		$this->conn->rollback();
		                                echo json_encode(array('e','خطاء - لم ننجح فى حفظ امر البيع'));
	                            	}
	                            
	                            } else {
	                                $this->conn->rollback();
	                                echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
	                            }
                            } else {
                                $this->conn->rollback();
                                echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
                            }
                        } else {
                            $this->conn->rollback();
                            echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
                        }
                    } else {
                        $this->conn->rollback();
                        echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
                    }
                } else {

                    echo json_encode(array('e', $msg1, $errCode));
                }

            } catch (PDOException $e) {
            	if ($this->conn->inTransaction()) {
					$this->conn->rollback();
				}
                echo json_encode(['e',$e->getMessage(),[]]);
            }
        } else {
            echo json_encode(array('w', 'لايمكن حفظ امر البيع .. لابد ان يحتوى امر البيع على منتج واحد على الاقل.'));
        }
        $this->conn = null;
        exit();
    }
    
    public 	function updateOrder 	() 								{
        if(empty($this->orderObj))       throw new Exception();
        if(empty($this->itemObj))        throw new Exception(); 
        if(empty($this->orderObj))       throw new Exception();
        if(empty(($this->logObj)))       throw new Exception();
       	
       	if (empty($this->orderObj)) {
		    throw new Exception('بيانات أمر البيع غير موجودة');
		}
		
		if (empty($this->itemObj)) {
		    throw new Exception('لا يمكن حفظ أمر البيع بدون منتجات');
		}
		
		if (!is_array($this->itemObj) || count($this->itemObj) === 0) {
		    throw new Exception('قائمة المنتجات فارغة');
		}
		
		if (empty($this->logObj)) {
		    throw new Exception('لم يتم تسجيل تفاصيل التعديل على أمر البيع');
		}
        
        $sqlset ="";
        $sqljoi ="";
        if(is_string($this->orderObj['exists_id'])){
		    $this->exists_id = htmlspecialchars(strip_tags($this->orderObj['exists_id']));
		    $sqljoi = " LEFT JOIN order_exists B  ON B.EXISTS_ID = ". $this->exists_id ;
		    $sqlset = "	A.ORDER_STATUS = B.STA_ID, A.EXISTS_ID = B.EXISTS_ID, ";
		}
        $sqlQuery1 = "UPDATE `".$this->ordersTbl."` A 
                        ".$sqljoi."	
                        SET A.COUPON_CODE  = ?,
							A.CITY_ID      = ?,
							A.ADDRESS_ID   = ?,
							A.ADDRESS      = ?,
							A.COMMENT      = ?,
							A.TEL_ID       = ?,
							A.TELEPHONE    = ?,
							A.DELIVERY_DATE= ?,
							A.DELIVERY_TYPE= ?,
							A.SHIPPING_VAL = ?,
							A.STO_ID       = ?, 
							".$sqlset."	
							A.LAST_UPDATE  = now(3) 
						WHERE ORDER_ID = ? 
						AND A.LAST_UPDATE = ?";
        //================================================================================
        $sqlQuery2 = "DELETE FROM `ORDER_PRODUCT` WHERE `ORDER_ID` = ? ";
        //================================================================================
        $sqlQuery3 = "INSERT INTO ".$this->orderProductTbl." (ORDER_ID,PRO_CODE,QTY,PRICE,COST_PRICE,DISCOUNT)  ";
        //================================================================================
        $sqlQuery4 = "INSERT INTO ".$this->orderlogTbl." (ORDER_ID,USER_ACTION,LOG_TEXT) VALUES (?,?,?) ";
        //================================================================================
        $this->order_by = htmlspecialchars(strip_tags(trim($this->orderObj['orderby'])));
        $this->coupon_code = htmlspecialchars(strip_tags(trim($this->orderObj['coupon_code'])));
        $this->city_id = htmlspecialchars(strip_tags(trim($this->orderObj['cityid'])));
        $this->address_id = htmlspecialchars(strip_tags(trim($this->orderObj['addid'])));
        $this->address = htmlspecialchars(strip_tags(trim($this->orderObj['addstr'])));
        $this->comment = htmlspecialchars(strip_tags(trim($this->orderObj['cliComment'])));
        $this->tel_id = htmlspecialchars(strip_tags(trim($this->orderObj['telid'])));
        $this->telephone = htmlspecialchars(strip_tags(trim($this->orderObj['telstr'])));
        $this->delivery_date = htmlspecialchars(strip_tags(date_format(date_create($this->orderObj['shipDate']), "Y/m/d H:i:s")));
        $this->delivery_type = htmlspecialchars(strip_tags(trim($this->orderObj['shipWaiting'])));
        $this->shipping_val = htmlspecialchars(strip_tags(trim($this->orderObj['shipval'])));
        $this->sto_id = htmlspecialchars(strip_tags(trim($this->orderObj['stoid'])));
        $this->order_id = htmlspecialchars(strip_tags(trim($this->orderObj['order_id'])));
        $this->last_update = htmlspecialchars(strip_tags($this->orderObj['last_update']));
        $this->sub_order_id = htmlspecialchars(strip_tags($this->orderObj['sub_id']));
        //================================================================================
        if ($_POST['sttye'] == 1) {
            $this->sttype = "AQTY";
        } else {
            $this->sttype = "UQTY";
        }

        $codes        = array();
        $insertProArr = array();
		$amount =0;
        foreach ($this->itemObj as $key => $value) {
            $this->pro_code = htmlspecialchars(strip_tags(trim($value['PRO_CODE'])));
            $codes[] = $this->pro_code;
            $itemData = array(
                'PRO_CODE'   => $this->pro_code,
                'QTY'        => htmlspecialchars(strip_tags(trim($value['qty']))),
                'STQTY'      => isset($value['STQTY']) ? htmlspecialchars(strip_tags(trim($value['STQTY']))): 0,
                'PRICE'      => htmlspecialchars(strip_tags(trim($value['CODE_PRICE']))),
                'COST_PRICE' => htmlspecialchars(strip_tags(trim($value['CODE_COST_PRICE']))),
                'DISCOUNT'   => htmlspecialchars(strip_tags(trim($value['dis']))),
                'PRO_NAME'   => htmlspecialchars(strip_tags(trim($value['PRO_NAME'])))
            );
            $insertProArr[] = $itemData;
            $amount += (($value['CODE_PRICE'] - $value['dis']) * $value['qty']);
        }
        // الشحن قيمة واحدة على أمر البيع عند تعديل الطلب أيضًا.
        $amount += (float)$this->shipping_val;
        //================================================================================
        $sqlQuery5 = $this->proStore($codes,$this->sto_id );
        //================================================================================
        $lastUpdate = $this->change_order($this->order_id);
        
        //================================================================================
        if (!empty($this->orderObj) && !empty($this->itemObj) && count($this->orderObj) > 0 && count(array($this->itemObj)) > 0 && count(array($this->logObj))) {
            
            
	        $lockedOrders=[];
	        
		    try {
		    	// ⚡ GET_LOCK أولاً - بدون انتظار
		       	$lockAcquired = $this->acquireOrderLock($this->order_id,0);
				
				if (!$lockAcquired) throw new RuntimeException('بيانات الطلب قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
				
				$lockedOrders[] = $this->order_id;
				
				// ✅ نجح في أخذ القفل - ابدأ Transaction
	            $this->conn->beginTransaction();
	
	            //==================check qty and discount======================
	                $queryOItem = 'SELECT PRO_CODE,QTY FROM ORDER_PRODUCT WHERE ORDER_ID = ?';
	                $stmtOItem = $this->conn->prepare($queryOItem);
	                $stmtOItem->bindValue(1, $this->order_id , PDO::PARAM_INT);
	                $stmtOItem->execute();
	                $itemInTable = $stmtOItem->fetchAll(PDO::FETCH_ASSOC);
	                
	                $stCh= $this->conn->prepare($sqlQuery5);
	                if (!$stCh->execute()) throw new Exception('حدث خطاء اثناء مراجعة المخزون');
					
					$storeid = $this->orderObj['stoid'];
	                $msgerr = '<ul class="text-left">';
	                $err1 = 0;
	                $errCode = array();
	                $err2 = 0;
	                while ($record = $stCh->fetch(PDO::FETCH_ASSOC)) {
	                	if($storeid == $record['STO_ID']){
		                    if(isset($this->itemObj[$record['PRO_CODE']])){
		                       		$icode = $this->itemObj[$record['PRO_CODE']]['PRO_CODE'];
			                        $iqty  = $this->itemObj[$record['PRO_CODE']]['qty'];
			                        
			                        // استخدام array_filter للبحث عن العنصر الذي يطابق الكود والكمية
									$old_record = array_filter($itemInTable, function ($item) use ($icode, $iqty) {
										return ($item['PRO_CODE'] === $icode && $item['QTY'] === $iqty);
									});
									
									if(!$old_record){
		                            	if($record['PRO_VISBLE'] ==1){
											$oqty = $this->itemObj[$record['PRO_CODE']]['qty'];
											if ($this->sttype == 'UQTY') {
												$sqty = $record['USER_QTY'];
											} elseif ($this->sttype == 'AQTY') {
												$sqty = $record['ADMIN_QTY'];
											} else {
												$sqty = $record['PROCES_QTY'];
											}
	                                		
											$chQty = $sqty-$oqty;
											
											if($chQty <= 0){
												if($record['PER_BOOKING'] == 1){
													$err2++;
													$msgerr .='<li class="">';
													$msgerr .='حدث تغيرات من قبل النظام الكمية المطلوبة للصنف ';
													$msgerr .='( '. $this->itemObj[$record['PRO_CODE']]['PRO_NAME'] . ' )';
													$msgerr .=' اكبر من المخزون الحالى سوف نقوم بشحن منتج حين توافر الكمية';
													$msgerr .='</li>';
												}else{
													$err1++;
		                                                array_push($errCode, $record["PRO_CODE"]);
		                                                $msgerr .='<li class="text-danger">';
		                                                $msgerr .='حدث تغيرات من قبل النظام الكمية المطلوبة للصنف ';
		                                                $msgerr .='( '. $this->itemObj[$record['PRO_CODE']]['PRO_NAME'] . ' )';
		                                                $msgerr .=' غير متوفرة ولايمكن طلب المنتج حاليا';
		                                                $msgerr .='</li>';
													
												}
											}
		                            		
		                            	} else {
		                                        $err1++;
		                                        array_push($errCode, $record["PRO_CODE"]);
		                                        $msgerr .='<li class="text-danger">';
		                                        $msgerr .='حدث تغيرات من قبل النظام الصنف ';
		                                        $msgerr .='( '. $this->itemObj[$record['PRO_CODE']]['PRO_NAME'] . ' )';
		                                        $msgerr .=' غير متوفرة ولايمكن طلب المنتج حاليا';
		                                        $msgerr .='</li>';
		                                }
		                            
									}
								}
						}
					}
	                $msgerr .= "</ul>";
	                
	            //------------------------------------------------------------------------------
	                if($err1 > 0){
	                	$this->conn->rollback();
	                    throw new Exception('لم يتم حفظ امر البيع للأسباب التاليه :'.$msgerr);
	                }
	            //======================Update Order========================================
	              	$stmt1 = $this->conn->prepare($sqlQuery1);
	                $stmt1->bindValue(1, $this->coupon_code, PDO::PARAM_INT);
	                $stmt1->bindValue(2, $this->city_id, PDO::PARAM_INT);
	                $stmt1->bindValue(3, $this->address_id, PDO::PARAM_INT);
	                $stmt1->bindValue(4, $this->address, PDO::PARAM_STR);
	                $stmt1->bindValue(5, $this->comment, PDO::PARAM_STR);
	                $stmt1->bindValue(6, $this->tel_id, PDO::PARAM_INT);
	                $stmt1->bindValue(7, $this->telephone, PDO::PARAM_STR);
	                $stmt1->bindValue(8, $this->delivery_date, PDO::PARAM_STR);
	                $stmt1->bindValue(9, $this->delivery_type, PDO::PARAM_INT);
	                $stmt1->bindValue(10, $this->shipping_val, PDO::PARAM_STR);
	                $stmt1->bindValue(11, $this->sto_id, PDO::PARAM_INT);
	                $stmt1->bindValue(12, $this->order_id, PDO::PARAM_STR);
	                $stmt1->bindValue(13, $this->last_update, PDO::PARAM_STR);
	                if (!$stmt1->execute() || $stmt1->rowCount()==0 || count($this->logObj) <= 1) {
	                	$this->conn->rollback();
	                    throw new Exception('حدث تغيرات فى الخلفية على امر البيع .. قم بإغلاق امر البيع وفتح التعديل مرة اخرى لمعاينة التغيرات');
	                }
	           
	                //=======================Delete Old Items=====================================
	                if ($err1 === 0) {
	                            $stmt2 = $this->conn->prepare($sqlQuery2);
	                            $stmt2->bindValue(1, $this->order_id, PDO::PARAM_INT);
	                            if ($stmt2->execute() && $stmt2->rowCount() > 0) {
	                                //==========================Insert New Item===========================
	                                
	                                $sqlQuery3 .=' VALUES '. implode(',', array_map(function ($item) {
	                                		
	                                        return '(' . $this->order_id . ',"' . $item['PRO_CODE'] . '","' . $item['QTY'] . '","' . $item['PRICE'] . '","'. $item['COST_PRICE'].'","' . $item['DISCOUNT'] . '")';
	                                }, $insertProArr));
	                                $stmt3 = $this->conn->prepare($sqlQuery3);
	                                if ($stmt3->execute() && $stmt3->rowCount() > 0) {
	                                    //======================Insert Order Log =========================
	                                   
	                                   $utype = $_POST['ptype'] == 1 ? $_SESSION["USER_GRUOP_NAME"]:
	                                   			($_POST['ptype'] == 2 && $this->orderObj['orderby'] == $_SESSION['USER_ID'] ? 'منشئ امر البيع' :
	                                   			($_POST['ptype'] == 3 ? "مندوب الشحن" : 'مجهول'));
	                                   // $_SESSION["USER_GRUOP_NAME"]
	                                    $this->logStr = 'تم تحديث بيانات امر البيع من '.$utype.'';
	                                    
	                                    for ($f = 0; $f < count($this->logObj); $f++) {
	                                        if ($f !== 0) {
	                                            $this->logStr .= htmlspecialchars(strip_tags(trim($this->logObj[$f])));
	                                        }
	                                    }
	                                    $stmt4 = $this->conn->prepare($sqlQuery4);
	                                    $stmt4->bindValue(1, $this->order_id, PDO::PARAM_INT);
	                                    $stmt4->bindValue(2, $_SESSION['USER_ID'], PDO::PARAM_INT);
	                                    $stmt4->bindValue(3, $this->logStr, PDO::PARAM_STR);
	                                    if ($stmt4->execute() && $stmt4->rowCount() > 0) {
	                                        //===================Success Update Order====================
	         					         	$finance = new finance_BE($this->conn);
			                            	$resultFinal = $finance->restClientdebt($this->order_id);
				                            if($resultFinal['result']){
			                                	$this->conn->commit();
			                                    if ($err2 === 0) {
			                                    	echo json_encode(array('s', 'تم تحديث بيانات امر البيع  - '.$this->sub_order_id));
			                                    } else {
			                                        echo json_encode(array('s', '<a> تم تحديث بيانات امر البيع - '.$this->sub_order_id .'<br>ولكن هناك تغيرات حدثة فى الخلفية عند الحفظ </a><hr>'.$msgerr));
			                                    }
				                            }else{
				                                $this->conn->rollback();
		                                        echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
				                            }
	                                    } else {
	                                        $this->conn->rollback();
	                                        echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة'));
	                                    }
	                                } else {
	                                    $this->conn->rollback();
	                                    echo json_encode(array('e', 'لم يتم حفظ امر البيع لاسباب غير معروفة - ERR:SQ.UP.OI'));
	                                }
	                            } else {
	                                $this->conn->rollback();
	                                echo json_encode(array('e', 'لم يتم حفظ امر البيع - حدث خطاء اثناء تحديث بيانات المنتجات لامر البيع'));
	                            }
	
	                } else {
	                	$this->conn->rollback();
	                	echo json_encode(array('e', $msgerr, $errCode));
	                }
	                  
	                
	        }catch (Exception $e) {
	            	if ($this->conn->inTransaction()) {
					   $this->conn->rollback();
					}
	                echo json_encode(array('e','خطاء',$e->getMessage(),[]));
	        } finally {
				// ========== 🔓 تحرير كل الأقفال بغض النظر عن النتيجة ==========
				$this->releaseOrderLocks($lockedOrders);
			}
	            
	    } else {
	    	echo json_encode(['w', 'لايمكن حفظ امر البيع .. لابد ان يحتوى امر البيع على منتج واحد على الاقل.']);
	    }
         
        
    }
    
    public function deleteOrder() {
        // أوامر البيع لا تُحذف فعليًا؛ هذا الإجراء يحول الطلب إلى حالة ملغى
        // باستخدام الحالة الصحيحة EXISTS_ID = 5 (إلغاء أمر البيع) مع الاحتفاظ بالسجل.
        if (!isset($this->order_id, $this->last_update)) {
            throw new RuntimeException('تفاصيل الخطأ: lup | oid is not set');
        }

        $lockedOrders = [];
        try {
            if (!$this->acquireOrderLock($this->order_id, 0)) {
                throw new RuntimeException('بيانات الطلب قيد المعالجة حاليًا، يرجى المحاولة مرة أخرى');
            }
            $lockedOrders[] = $this->order_id;
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                'SELECT ORDER_ID, LAST_UPDATE, ORDER_STATUS, ORDER_SUCCESS, PRINT, EXISTS_ID
                 FROM ORDERS WHERE ORDER_ID = ? FOR UPDATE'
            );
            $stmt->execute([$this->order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('أمر البيع غير موجود');
            }
            if ((string)$order['LAST_UPDATE'] !== (string)$this->last_update) {
                throw new RuntimeException('تم تحديث بيانات أمر البيع؛ حدّث القائمة ثم أعد المحاولة');
            }
            if ((int)$order['ORDER_STATUS'] >= 2) {
                throw new RuntimeException('لا يمكن إلغاء الطلب من هذه المرحلة؛ يجب تسوية الشحنة والمخزون أولًا');
            }
            if ((int)$order['EXISTS_ID'] === 5) {
                throw new RuntimeException('أمر البيع ملغى بالفعل');
            }

            // لا يمكن إلغاء طلب عليه تحصيل/دفعة مقدمة قبل تنفيذ مسار رد المقدم وتسويته.
            // لا نفترض حساب رد الأموال؛ لذلك نوقف الإلغاء بدل ترك مبلغ محصل بلا تسوية.
            $financialCheck = $this->conn->prepare(
                "SELECT
                    EXISTS(SELECT 1 FROM invoices i WHERE i.REFERENCE_TYPE = 'SALES'
                           AND i.REFERENCE_ID = o.ORDER_ID AND i.AMOUNT_PAID > 0) AS HAS_PAID_INVOICE,
                    EXISTS(SELECT 1 FROM invoices i JOIN financial_transactions ft ON ft.invoice_id = i.INVO_ID
                           WHERE i.REFERENCE_TYPE = 'SALES' AND i.REFERENCE_ID = o.ORDER_ID
                             AND ft.status = 'COMPLETED' AND ft.amount > 0) AS HAS_COMPLETED_PAYMENT,
                    o.SALE_ENTRY_ID IS NOT NULL AS HAS_SALE_ENTRY
                 FROM ORDERS o WHERE o.ORDER_ID = ?"
            );
            $financialCheck->execute([$this->order_id]);
            $financialState = $financialCheck->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($financialState['HAS_PAID_INVOICE']) || !empty($financialState['HAS_COMPLETED_PAYMENT']) || !empty($financialState['HAS_SALE_ENTRY'])) {
                throw new RuntimeException('لا يمكن إلغاء الطلب قبل تسوية أي مبلغ محصل أو قيد محاسبي. استخدم مسار رد المقدم/عكس القيد بعد تجهيزه.');
            }

            // الحالة 5 هي الحالة المعرفة في order_exists باسم «إلغاء أمر البيع».
            // لا حذف من ORDERS ولا حذف لأرصدة العميل؛ تُحفظ كل الآثار السابقة للمراجعة والتسوية.
            $update = $this->conn->prepare(
                'UPDATE ORDERS
                 SET EXISTS_ID = 5, ORDER_STATUS = 0, LAST_UPDATE = NOW(3)
                 WHERE ORDER_ID = ? AND LAST_UPDATE = ? AND ORDER_STATUS < 2'
            );
            $update->execute([$this->order_id, $this->last_update]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('تعذر تغيير حالة أمر البيع إلى ملغى؛ ربما تم تحديثه من مستخدم آخر');
            }

            $log = $this->conn->prepare(
                'INSERT INTO ORDERS_LOG (ORDER_ID, USER_ACTION, LOG_TEXT) VALUES (?, ?, ?)'
            );
            $log->execute([
                $this->order_id,
                (int)($_SESSION['USER_ID'] ?? 0),
                'تم إلغاء أمر البيع بتغيير حالته مع الاحتفاظ بسجله وبياناته المالية والمخزنية'
            ]);

            $this->conn->commit();
            echo json_encode(['success', 'تم الإلغاء', 'تم تغيير حالة أمر البيع إلى ملغى مع الاحتفاظ بالسجل']);
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            echo json_encode(['error', 'تعذر إلغاء أمر البيع', $e->getMessage(), []]);
        } finally {
            $this->releaseOrderLocks($lockedOrders);
        }
    }

    public 	function unDeleteOrder 	() 								{
        if(!isset($this->order_id,$this->last_update)){
            echo json_encode(array('error',
            ' حدث خطاء',
            'تفاصيل الخطاء : lup | oid is not set'));
            exit;
        }
        $sqlQuery0 = 'UPDATE `ORDERS` SET ORDER_SUCCESS = 0  WHERE ORDER_SUCCESS = -1 AND LAST_UPDATE = :lup AND `ORDER_ID` = :oid ';
        $sqlQuery1 = "INSERT INTO `ORDERS_LOG`(`ORDER_ID`,`USER_ACTION`,`LOG_TEXT`) VALUES (?,?,?) ";

        $sqlQuery  = $sqlQuery1;
        $this->logStr  = 'تم تحديث بيانات امر البيع من '.$_SESSION["USER_GRUOP_NAME"].'';
        $this->logStr .= '* تم رفض طلب حذف امر البيع من طرف الادارة';
        $lockedOrders =[];
        
        try {
        	
        	// ⚡ GET_LOCK أولاً - بدون انتظار
	       	$lockAcquired = $this->acquireOrderLock($this->order_id,0);
			
			if (!$lockAcquired) throw new RuntimeException('بيانات الطلب قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
						
			$lockedOrders[] = $this->order_id;
			
			// ✅ نجح في أخذ القفل - ابدأ beginTransaction
            $this->conn->beginTransaction();
            $stmt0 = $this->conn->prepare($sqlQuery0);
            $stmt0->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
            $stmt0->bindValue(':lup', $this->last_update, PDO::PARAM_STR);
            if($stmt0->execute()){
                $stmt1 = $this->conn->prepare($sqlQuery1);
                $stmt1->bindValue(1, $this->order_id, PDO::PARAM_INT);
                $stmt1->bindValue(2, $_SESSION['USER_ID'], PDO::PARAM_INT);
                $stmt1->bindValue(3, $this->logStr, PDO::PARAM_STR);
                if ($stmt1->execute() && $stmt1->rowCount() > 0) {
                    $this->conn->commit();
                    echo json_encode(['success','ناجح','تم الغاء طلب الحذف  بنجاح']);
                } else {
                    $this->conn->rollback();
                    echo json_encode(['error','خطاء','حدث خطاء غير معروف']);
                }
            } else {
                $this->conn->rollback();
                echo json_encode(['error','خطاء','حدث خطاء غير معروف']);
            }
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
				$this->conn->rollback();
			}
            echo json_encode(array('error','حدث خطاء', 'تفاصيل الخطاء :' .$e->getCode().' - '.$e->getMessage(),[]));
        } finally {
        	// ========== 🔓 تحرير كل الأقفال بغض النظر عن النتيجة ==========
			$this->releaseOrderLocks($lockedOrders);
			
        }
    }
    
    public 	function filterOrder 	() 								{
        $arr = array();
        $option = '<option value="" selected>اختار المنشئ</option>';
        $sqlQuery0 = 'SELECT ORDER_TYPE_ID, ORDER_TYPE_NAME FROM ORDER_TYPE WHERE ORDER_TYPE_ID <> 3';
        $sqlQuery1 = 'SELECT SHIPPING_T_ID, SHIPPING_T_NAME FROM SHIPPING_TYPE ';
        $sqlQuery2 = 'SELECT * FROM ORDER_EXISTS ';
        $sqlQuery3 = 'SELECT STA_ID, STA_NAME FROM ORDER_STATUSE ';
        $sqlQuery4 = 'SELECT CAT_ID, CAT_NAME FROM PRODUCT_CATEGORY WHERE CAT_PARENT<>0';
        $sqlQuery5 = 'SELECT PRO_ID, CAT_ID, PRO_NAME FROM PRODUCTS';
        $sqlQuery6 = 'SELECT * FROM `order_service_type` ORDER BY OST_ID DESC';
        $sqlQuery7 = 'SELECT * FROM `zones`';
        
        $stmt0 = $this->conn->prepare($sqlQuery0);
        $stmt0->execute();
        $result = $stmt0;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option value="'.$record['ORDER_TYPE_ID'].'">'.$record['ORDER_TYPE_NAME'].'</option>';
        }
        $arr['orderType'] = $option;

        $option = '';
        $option = '<option class"text-danger" value="" selected>اختار الشحن</option>';
        $stmt1 = $this->conn->prepare($sqlQuery1);
        $stmt1->execute();
        $result = $stmt1;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option value="'.$record['SHIPPING_T_ID'].'">'.$record['SHIPPING_T_NAME'].'</option>';
        }
        $arr['shippType'] = $option;

        $option = '';
        $option = '<option value="" selected>الموقف الادارى</option>';
        $stmt2 = $this->conn->prepare($sqlQuery2);
        $stmt2->execute();
        $result = $stmt2;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option value="'.$record['EXISTS_ID'].'">'.$record['EXISTS_NAME'].'</option>';
        }
        $arr['exists'] = $option;
        
        $option = '';
        $option = '<option value="" selected>حالة امر البيع</option>';
        $stmt3 = $this->conn->prepare($sqlQuery3);
        $stmt3->execute();
        $result = $stmt3;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option value="'.$record['STA_ID'].'">'.$record['STA_NAME'].'</option>';
        }
        $arr['statuse'] = $option;

        $option = '';
        $option = '<option value="" selected>فئة المنتجات</option>';
        $stmt4 = $this->conn->prepare($sqlQuery4);
        $stmt4->execute();
        $result = $stmt4;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option value="'.$record['CAT_ID'].'">'.$record['CAT_NAME'].'</option>';
        }
        $arr['proCat'] = $option;

        $option = '';
        $stmt5 = $this->conn->prepare($sqlQuery5);
        $stmt5->execute();
        $result = $stmt5;
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $option .= '<option class="catid" data-id="'.$record['CAT_ID'].'" value="'.$record['PRO_ID'].'">'.$record['PRO_NAME'].'</option>';
        }
        $arr['products'] = $option;
        
		
		$stmt6 = $this->conn->prepare($sqlQuery6);
        $stmt6->execute();
        $result = $stmt6;
	//	$option = '<div class="form-group text-nowrap m-2 text-left">';
		$rowsArr = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
			$rowsArr[$record['OST_ID']] = $record['OST_TYPE'];
        }
        $arr['oservice'] = $rowsArr;
        
        
        $stmt7 = $this->conn->prepare($sqlQuery7);
        $stmt7->execute();
        $result = $stmt7;
		$option = '<option value="" selected>اختار خط السير</option>';
		//$rowsArr = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
			$option .='<option class="catid" data-shtype='.$record['SHIPPING_TYPE'].'" value="'.$record['ZONE_ID'].'">'.$record['ZONE_NAME'].'</option>';
        }
        $arr['zones'] = $option;
        return $arr;
        $this->conn = null;
        exit();
    }
    
    public 	function radioOrderExists() 							{
        $sqlQuery = 'SELECT * FROM ORDER_EXISTS ';
        
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->execute();
        $result = $stmt;
        $radiohtm = '<div class="form-group text-nowrap text-left">';
        $exArray = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $radiohtm .= '<div class="custom-control custom-radio">
                            <input id="exists'.$record['EXISTS_ID'].'"class="custom-control-input" type="radio" value="'.$record['EXISTS_ID'].'" name="existsOrder">
                            <label for="exists'.$record['EXISTS_ID'].'" class="custom-control-label">تحويل الى '.$record['EXISTS_NAME'].'</label>
                          </div>';
            $row = array();
            $row[]=$record['EXISTS_ID'];
            $row[]=$record['STA_ID'];
            $row[]=$record['EXISTS_NAME'];
            $row[]=$record['EXISTS_NAME_EN'];
            $exArray[] =$row;
        }
        $radiohtm .= '</div>';
        
        return array($radiohtm,$exArray);
        $this->conn = null;
        exit();
    }
    
    public 	function RedirectToEx 	() 								{
    	$osucceeded            	= [];
    	$orejected 				= [];
        $ordersISNotConditions 	= [];
        $logStr1  = 'تم تحديث بيانات امر البيع من '.$_SESSION["USER_GRUOP_NAME"].'';
        $logStr1 .= htmlspecialchars(strip_tags('* تحويل امر البع الى : '.$this->logStr));
       	$sql =[
    		'getEmployStoreId' => 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ',
			'getDataOrders' => "SELECT  A.ORDER_ID,
									  A.ORDER_BY,
									  A.DELIVERY_ID,
									  A.SUB_ORDER_ID,
									  A.EXISTS_ID,
									  A.ORDER_STATUS,
									  A.ORDER_SUCCESS,
									  A.LAST_UPDATE,
									  A.PRINT,
									  A.STO_ID,
									  CASE WHEN Q.OS_STATUSE = 0  THEN 1 ELSE 0 END AS OSMSGCS,
									  
									  JSON_OBJECTAGG(
									  	CAST(B.PRO_CODE AS CHAR),
									  			CONCAT(B.QTY,
									  					',',
									  					CAST(B.PRICE AS double(18,2)),
									  					',',
									  					CAST(B.DISCOUNT AS double(18,2))
									  			)
									  ) AS ORDER_PRODUCT 
									FROM  orders A
									  LEFT JOIN order_product B ON A.ORDER_ID = B.ORDER_ID
									  LEFT JOIN (
				                                SELECT 
				                                    A.ORDER_ID,
				                                    CASE WHEN Max(C.OS_ID) IS NOT NULL THEN Max(C.OS_ID) 
				                                         WHEN Max(c.OS_ID) IS NULL AND Max(B.OS_ID) IS NOT NULL THEN Max(B.OS_ID) 
				                                         ELSE 0 
				                                    END AS OS_ID,
				                                    
				                                    CASE WHEN Max(C.OS_ID) IS NOT NULL THEN C.OS_STATUSE
				                                         WHEN Max(C.OS_ID) IS NULL AND Max(B.OS_ID) IS NOT NULL THEN B.OS_STATUSE 
				                                         ELSE 1 
				                                    END AS OS_STATUSE,
				                                    
				                                    (
				                                        Count(CASE WHEN B.OS_STATUSE = 1 THEN D.OS_ID END)
				                                    ) AS OS_END_COUNT,
				                                    E.CMSG AS MSG_COUNT
				                                FROM ORDERS A
				                                LEFT JOIN order_service B ON A.ORDER_ID = B.ORDER_ID AND B.OS_STATUSE = 1
				                                LEFT JOIN order_service C ON A.ORDER_ID = C.ORDER_ID AND C.OS_STATUSE = 0
				                                LEFT JOIN order_service AS D ON A.ORDER_ID = D.ORDER_ID
				                                LEFT JOIN (
				                                	SELECT 
													OS_ID,
													COUNT(case when OSTX_READ = 0 AND USER_TYPE <> 1 THEN OSTX_ID END) AS CMSG
													FROM  order_service_text
													WHERE OS_ID = 8
													GROUP BY OS_ID
												) E ON D.OS_ID = E.OS_ID 
				                                GROUP BY A.order_id


				                            ) AS Q ON A.ORDER_ID = Q.ORDER_ID 
									WHERE FIND_IN_SET (A.ORDER_ID ,:oids)
									GROUP BY A.ORDER_ID,
									  A.ORDER_BY,
									  A.SUB_ORDER_ID,
									  A.EXISTS_ID,
									  A.ORDER_STATUS,
									  A.ORDER_SUCCESS,
									  A.LAST_UPDATE,
									  A.PRINT,
									  A.STO_ID
									ORDER BY A.DATE_CREATED",
			'getStoreProducts' => 'SELECT A.PRO_CODE,
										 A.STO_QTY - B.ADMIN_STORE AS AQTY
										FROM  stores_product A
										 LEFT JOIN (SELECT  B.STO_ID,
										     A.PRO_CODE,
										     Sum(CASE WHEN C.ADMIN_STORE = 1 THEN A.QTY ELSE 0 END)  AS ADMIN_STORE
										   FROM   order_product A
										     LEFT JOIN orders B ON A.ORDER_ID = B.ORDER_ID
										     LEFT JOIN order_exists C ON B.EXISTS_ID = C.EXISTS_ID
										   GROUP BY B.STO_ID, A.PRO_CODE) B ON A.PRO_CODE = B.PRO_CODE AND A.STO_ID = B.STO_ID
										WHERE A.STO_ID = :stoid AND A.PRO_CODE IN(SELECT PRO_CODE FROM order_product WHERE  FIND_IN_SET (ORDER_ID, :oids))',
			
			'setDataOrders'    => "UPDATE ORDERS SET EXISTS_ID    = :exis, ORDER_STATUS = :osta, LAST_UPDATE  = now(3) WHERE FIND_IN_SET(`ORDER_ID` , :oids ) ",
			
			'setDataOrders1'   => "UPDATE ORDERS SET EXISTS_ID    = :exis, PRINT = 0, ORDER_STATUS = :osta, LAST_UPDATE  = now(3) WHERE FIND_IN_SET(`ORDER_ID` , :oids ) ",
			
			'addDataOrderLog'  => "INSERT INTO ".$this->orderlogTbl." (ORDER_ID,USER_ACTION,LOG_TEXT) VALUES  (:oid,:uauction,:log)",
			
			'getOrder_exists'  => "SELECT * FROM `order_exists` WHERE EXISTS_ID = ? ",
			
		];
		$us  = unserialize($_SESSION['USER_SCRIPT']);
		$allOrderIds = array_keys($this->orderObj);
		$lockedOrders = [];
		try{
			
			//$keylock = 'ORDER-'.$this->order_id,
			// ⚡ GET_LOCK أولاً - بدون انتظار
			// ========== 🔒 المرحلة 1: تأقف الأوامر لمنع التضارب ==========
			// تأقف كل الأوامر بترتيب ثابت لمنع Deadlock
			sort($allOrderIds);
			foreach ($allOrderIds as $orderId) {
				$lockAcquired = $this->acquireOrderLock($orderId,0);
				if (!$lockAcquired) throw new RuntimeException('بيانات الطلب قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
				$lockedOrders[] = $orderId;
			}
			
			if(empty($lockedOrders)) throw new RuntimeException('بيانات المطلوبة قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
			
			
			//$stmtlock = $this->conn->prepare("SELECT GET_LOCK(?, 0)");
			//$stmtlock->execute([$keylock]);
			//$lockResult = $stmtlock->fetchColumn();
				
			//if ($lockResult != 1) {
				// ❌ فشل في أخذ القفل
			//	throw new Exception('بيانات الطلب قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
			//}
				
			// ✅ نجح في أخذ القفل - ابدأ Transaction
   			$startpoint = 'spSTAR1002';
			$this->conn->beginTransaction();
			$this->conn->exec("SAVEPOINT $startpoint");
			$this->exists_id = htmlspecialchars(strip_tags($this->exists_id));
            $stmtEX = $this->conn->prepare($sql['getOrder_exists']);
            $stmtEX->bindValue(1, $this->exists_id,   PDO::PARAM_INT);
            $stmtEX->execute();
            $record = $stmtEX->fetch(PDO::FETCH_ASSOC);
            $this->order_status = $record['STA_ID'];
			//===============جلب بيانات مخزن السحب ====================
			if(!$this->sto_id){
				$stStoreid = $this->conn->prepare($sql['getEmployStoreId']);
				$stStoreid->bindValue(':branchid' , $_SESSION["BRANCH_ID"] , PDO::PARAM_INT);
				$stStoreid->execute();
				$result = $stStoreid->fetch(PDO::FETCH_ASSOC);
				$this->sto_id = $result['STO_ID'];
			}
			$stStoPro = $this->conn->prepare($sql['getStoreProducts']);
			$stStoPro->bindValue(':stoid', $this->sto_id , PDO::PARAM_STR);
			$stStoPro->bindValue(':oids' , implode(',',array_keys($this->orderObj)), PDO::PARAM_STR);
			$stStoPro->execute();
			$record = $stStoPro->fetchAll(PDO::FETCH_ASSOC);
			$stopro = [];
			foreach ($record as $code){
				$stopro[$code['PRO_CODE']] = $code['AQTY'] ;
			}
			//=============جلب بيانات امر البيع والتعديل عليه=================
			$stAllOrderData = $this->conn->prepare($sql['getDataOrders']);
			$stAllOrderData->bindValue(':oids', implode(',',array_keys($this->orderObj)), PDO::PARAM_STR);
			$stAllOrderData->execute();
			$xloop = 1;
			while ($record = $stAllOrderData->fetch(PDO::FETCH_ASSOC)){
				if($record['ORDER_SUCCESS'] == -1 && $this->order_status >= 2){
					throw new RuntimeException('لم يتم تنفيذ الاجراء - طلب المستخدم حذف امر البيع  من فضلك تواصل مع المستخدم لمعرفة اسباب الحذف - يمكنك حذف امر البيع او الغاء الحذف');
				}
				$ordersISNotConditions[$record['ORDER_ID']] = [
					'osubid'  => $record['SUB_ORDER_ID'], 
					'print'   => true,
					'statuse' => true,
					'oserv'   => true, 
					'lastup'  => true, 
					'store'   => true
				];
				
				$queryUpdate = 'setDataOrders';
				if($record['PRINT'] && $this->order_status < 2){
					$queryUpdate ='setDataOrders1';
				}
				//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
				if( $record['LAST_UPDATE'] > $this->orderObj[$record['ORDER_ID']]['olup']){
					$ordersISNotConditions[$record['ORDER_ID']]['lastup'] = false;
				}
				//لم يتم طباعة امر البيع
				if(!$record['PRINT'] && $this->order_status >= 2){
					$ordersISNotConditions[$record['ORDER_ID']]['print'] = false;
				}
				// يوجد طلبات لخدمة العملاء مفتوحه
				if($record['OSMSGCS'] > 0 && $record['ORDER_STATUS'] == 1 &&  $this->order_status > 1 ){
					$ordersISNotConditions[$record['ORDER_ID']]['oserv'] = false;
				}
				//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
				if(($record['ORDER_STATUS']  == 0 && $this->order_status > 1 ) ||
					(
						($record['ORDER_STATUS']  == 5 || $record['ORDER_STATUS']  == 3 || $record['ORDER_STATUS']  == 4) &&
						$this->order_status <= 2
					)){
					$ordersISNotConditions[$record['ORDER_ID']]['statuse'] = false;
				}
				//=========التأكد من وجود رصيد لمتجات امر البيع بالمخزن============
				$oData = $this->orderObj[$record['ORDER_ID']];
		        if ($oData['OExists'] >= 0) {
		        	$exi = 'orderlist_ex' . $oData['OExists'];
		        } else {
		        	$exi = 'orderlist_os' . abs($oData['OExists']);
		        }
		        $permission = $us['Admin'][$exi];
				$sto = [];
				$oPro = json_decode($record['ORDER_PRODUCT'],true);
				foreach ($oPro as $code=>$qty) {
					$qty = explode(',',$qty)[0];
					$sto[$code]  = $stopro[$code];
					$sto[$code] -= (double)$qty;
					if($sto[$code] < 0  && $this->order_status == 1 && $permission['Nostore']!='true'){
						$ordersISNotConditions[$record['ORDER_ID']]['store'] = false;
			        }
				}
				
				//==========التأكد ان امر البيع يستوفى جميع الشروط السابقة================
				if( $ordersISNotConditions[$record['ORDER_ID']]['statuse'] === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['oserv']   === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['print']   === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['lastup']  === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['store']   === true 
				){
					
					//=======انشاء نقطة رجوع لاسترجاع البيانات فى حال حدوث خطاء ========
					$PointNum     = 'sp'.$_SESSION['USER_ID'] .$record['ORDER_ID']. date("YmdHisu", time()).rand(1,9999);
					$this->conn->exec("SAVEPOINT $PointNum");
					//==================update orders======================
					$stmtUP[$record['ORDER_ID']] = $this->conn->prepare($sql[$queryUpdate]);
					$stmtUP[$record['ORDER_ID']]->bindValue(':exis', $this->exists_id    , PDO::PARAM_INT);
					$stmtUP[$record['ORDER_ID']]->bindValue(':osta', $this->order_status , PDO::PARAM_INT);
					$stmtUP[$record['ORDER_ID']]->bindValue(':oids', $record['ORDER_ID'] , PDO::PARAM_STR);
					if($stmtUP[$record['ORDER_ID']]->execute()){
						$stmtLog[$record['ORDER_ID']] = $this->conn->prepare($sql['addDataOrderLog']);
						$stmtLog[$record['ORDER_ID']]->bindValue(':oid', $record['ORDER_ID'] , PDO::PARAM_INT);
						$stmtLog[$record['ORDER_ID']]->bindValue(':uauction',  $_SESSION['USER_ID'] , PDO::PARAM_INT);
						$stmtLog[$record['ORDER_ID']]->bindValue(':log',$logStr1 , PDO::PARAM_STR);
						if($stmtLog[$record['ORDER_ID']]->execute()){
							foreach ($oPro as $code=>$qty) {
								$qty = explode(',',$qty)[0];
								$stopro[$code] -= (double)$qty;
							}
							$finance = new finance_BE($this->conn);
			            	$resultFinal = $finance->restClientdebt($record['ORDER_ID']);
				            if($resultFinal['statuse']){
								$osucceeded[] = $record['ORDER_ID'];
								unset($ordersISNotConditions[$record['ORDER_ID']]);
				            }else{
				            	$orejected[] = $record['ORDER_ID'];
								$ecode[] = $record['ORDER_ID']."E P B";
				            }
						}else{
							$orejected[] = $record['ORDER_ID'];
							$ecode[] = $record['ORDER_ID']."E1";
						}
					}else{
						$orejected[] = $record['ORDER_ID'];
						$ecode[] = $record['ORDER_ID']."E1";
					}
				}
			}
			if(count($orejected)){
				if ($this->conn->inTransaction()) {
					$this->conn->exec("ROLLBACK TO $startpoint");
				}
				return array('error',
				'خطاء',
				'حدث خطاء اثناء تحديث بيانات اوامر البيع وقد تم الغاء الإجراء / رمز الخطاء : '.implode(" | ",$ecode),
				[]);
			}else{
				if(count($osucceeded)){
					$this->conn->commit();
					if(!count($ordersISNotConditions)){
						return array('success',
						'نجح',
						'تم التحويل بنجاح',
						$ordersISNotConditions);
					}else{
						return array('warning',  
						'نجح',
						'نجح تحويل عدد ( '.count($osucceeded).' ) امر بيع وهناك عدد ( '.count( $ordersISNotConditions).' ) لم يستوفى شروط التحويل :-<br>',
						$ordersISNotConditions);
					}
				}else if(count($ordersISNotConditions)){
					$this->conn->exec("ROLLBACK TO $startpoint");
					return array('error',
					'نأسف',
					' هناك عدد ( '. count($ordersISNotConditions) .' ) امر بيع لم يستوفى شروط التحويل ',
					$ordersISNotConditions);
				}
			}
			//$this->conn = null;
		} catch (Exception $e) {
		 	if ($this->conn->inTransaction()) {
				$this->conn->exec("ROLLBACK TO $startpoint");
			}
			return array('error','خطاء!', $e->getCode().' - '.$e->getMessage(),[]);
		} finally {
			// ========== 🔓 تحرير كل الأقفال بغض النظر عن النتيجة ==========
			$this->releaseOrderLocks($lockedOrders);
		}

	}
	
	public  function getInventory (){ 
		$draw = isset($this->rq_parm['draw']) ? intval($this->rq_parm['draw']) : 0;
		$stoid = isset($this->rq_parm['stoid']) && $this->rq_parm['stoid']>0 ? $this->rq_parm['stoid']:0;
		
		$stmt = $this->conn->prepare($this->proStore(array() , $stoid));
        $stmt->execute();
        
        // إرجاع البيانات إلى DataTables
		$response = array( 
		    "draw" => $draw,
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
        
	}
	
	public  function resetOrder($orderId, $lastUp){
	    $userId    = $_SESSION['USER_ID'];
	    $userGroup = $_SESSION['USER_GRUOP_NAME'];
	    $lockedOrders =[];
	    // التحقق من البيانات
	    if (empty($userId)   || !is_numeric($userId)  ||
	        empty($userGroup)|| !is_string($userGroup)||
	        empty($orderId)  || !is_numeric($orderId) ||
	        empty($lastUp)   || !is_string($lastUp)
	    ) {
	        throw new Exception("المدخلات غير مكتمله");
	    }
		
	    try {
				// ⚡ GET_LOCK أولاً - بدون انتظار
	       	$lockAcquired = $this->acquireOrderLock($orderId,0);
			
			if (!$lockAcquired) throw new RuntimeException('بيانات الطلب قيد المعالجة حالياً، يرجى المحاولة مرة أخرى');
			
			$lockedOrders[] = $orderId;
			
			
	        // بدء Transaction
	        $this->conn->beginTransaction();
			$stmt = $this->conn->prepare("SELECT LAST_UPDATE FROM ORDERS WHERE ORDER_ID = ? AND LAST_UPDATE <> ? ");
	        if (!$stmt->execute([$orderId, $lastUp])) throw new Exception("خطاء اثناء التحقق من عدم تحديث بيانات امر البيع فى الخلفيه");
	        if ($stmt->rowCount() > 0) throw new Exception("قام احد المستخدمين بتحديث بيانات امر البيع فى الخلفية من فضلك قم بتحديث قائمة الاوامر واعد المحاولة");
	        
	        
			$stmtx1 = $this->conn->prepare("SELECT LAST_UPDATE FROM ORDERS WHERE ORDER_ID = ? AND ORDER_SUCCESS =-1 ");
	        if (!$stmtx1->execute([$orderId])) throw new Exception("خطاء اثناء التحقق من عدم وجود طلب حذف لامر البيع");
	        if ($stmtx1->rowCount() > 0) throw new Exception("خطاء قام المستخم بتقديم طلب حذف امر البيع  من فضل قم بإلغاء امر البيع اولا ثم اعد المحاوله");
	        
	        $stmtx2 = $this->conn->prepare("SELECT LAST_UPDATE FROM ORDERS WHERE ORDER_ID = ? AND ORDER_STATUS <> 5 ");
	        if (!$stmtx2->execute([$orderId])) throw new Exception("خطاء اثناء التحقق من حالة امر البيع");
	        if ($stmtx2->rowCount() > 0) throw new Exception("خطاء حالة امر البيع لا تسمح بتنفيذ هذا الاجراء يمكنك اعادة تفعيل امر البيع من الحاله مرفوض");
	        
	        
	        /*
	        |------------------------------------------
	        | تحديث منتجات الطلب
	        |------------------------------------------
	        */
	        $stmt1 = $this->conn->prepare("
	            UPDATE order_product
	            SET
	                PRO_STATUSE = 'waiting',
	                DELIVERED_QTY = 0,
	                RETURNED_QTY = 0
	            WHERE ORDER_ID = ? 
	        ");
	
	        if (!$stmt1->execute([$orderId])) throw new Exception("خطاء اثنات تحديث بيانات منتجات امر البيع");
	
	        /*
	        |------------------------------------------
	        | تحديث الطلب
	        |------------------------------------------
	        */
	        $stmt2 = $this->conn->prepare("
	            UPDATE orders
	            SET
	                EXCHANGE = NULL,
	                DELIVERY_ID= NULL,
	                ORDER_SUCCESS = 0,
	                PRINT = 0,
	                EXISTS_ID = 1,
	                ORDER_STATUS = 0,
	                DELIVERED_DATE = NULL,
	                SHIPPING_DATE = NULL
	            WHERE ORDER_ID = ? AND ORDER_SUCCESS <>-1 AND ORDER_STATUS = 5
	        ");
	
	        if (!$stmt2->execute([$orderId])) throw new Exception("خطاء اثناء تحديث بيانات امر البيع");
	
	        // التأكد إن الطلب موجود
	        if ($stmt2->rowCount() == 0) throw new Exception("خطاء اثناء تحديث بيانات امر البيع");
	
	        /*
	        |------------------------------------------
	        | إضافة Log
	        |------------------------------------------
	        */
	        $logStr = "تم تحديث امر البيع من قبل ".$userGroup."* المستخدم قام بإعادة تأهيل امر البيع لإعادة الشحن مرة اخرى بناء على طلب المنشئ";
		                   
	        $stmtLog = $this->conn->prepare("
	            INSERT INTO orders_log (
	                ORDER_ID,
					USER_ACTION,
					DATE_ACTION,
					LOG_TEXT
	            ) VALUES (
	                ?,
	                ?,
	                NOW(),
	                ?
	            )
	        ");
	
	        if (!$stmtLog->execute([$orderId, $userId, $logStr])) throw new Exception("خطاء اثناء حفظ سجلات امر البيع");
	        
	
	        // حفظ التعديلات
	        $this->conn->commit();
	
	        return array('success','نجح','تم الحفظ بنجاح');
	
	    } catch (PDOException $e) {
	    	throw new Exception($e->getMessage());
	    }catch (Exception $e) {
	       	if ($this->conn->inTransaction()) {
			   $this->conn->rollback();
			}
	        return array('error','خطاء',$e->getMessage(),[]);
	    } finally {
			// ========== 🔓 تحرير كل الأقفال بغض النظر عن النتيجة ==========
			$this->releaseOrderLocks($lockedOrders);
			$this->conn = null;
		}
	}

}
?>