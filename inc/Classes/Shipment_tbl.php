<?php
/*================================================
**  Consignment Database Table
**==============================================*/
class Shipment_tbl {
  //  private $shipmentTbl       = "shipment";
  //  private $orders_Tbl        = "orders";
  //  private $order_exists_Tbl  = "order_exists";
  //  private $order_product_Tbl = "order_product";
  //  private $orders_log_Tbl    = 'ORDERS_LOG';
    //private $usc 			   = unserialize($_SESSION['USER_SCRIPT']);
    private $store_id ;
    //==========tbl ORDERS_LOG============
    public $branch_id;
    public $user_action;
    public $user_group;
    //==========tbl ORDERS===============
    public $order_id;
    public $delivery_id;
    public $delivery_name;
    public $shiping_date;
    //==========public shipment tbl===========
    public $shipment_id;
    public $user_id;
    public $shipmentobj;
    //==========public variable===========
    public $orderobj;
    public function __construct($db) {
        $this->conn = $db;
    }
    //==========وظائف جزئية يعتمد عليها الوظائف العامة========
    //الاستعلامات المستخدمه فى هذا الكائن
    private function query (){
        $this->order_id     = array_keys($this->orderobj); 
        $this->delivery_id  = htmlspecialchars(strip_tags(trim($this->delivery_id)));
        $this->shiping_date = htmlspecialchars(strip_tags(date_format(date_create($this->shiping_date), "Y/m/d H:i:s")));
        $this->user_action  = htmlspecialchars(strip_tags(trim($this->user_action)));
        $this->user_group   = htmlspecialchars(strip_tags(trim($this->user_group)));
        $query = array();
       // $query['resetCustody_PRO'] = 'UPDATE custody_product AS CS LEFT JOIN order_product AS OP ON CS.PRO_CODE = OP.PRO_CODE
		//								SET CS.QTY = CS.QTY + OP.DELIVERED_QTY
		//								WHERE CS.USER_ID = :uid
 		//								 AND OP.ORDER_ID = :oid ';
 		//$query['resetOrder_PRO'] = "UPDATE order_product AS OP LEFT JOIN orders AS O ON OP.ORDER_ID = O.ORDER_ID
		//							SET  OP.DELIVERED_QTY = 0,
		//							 OP.PRO_STATUSE='waiting'
		//							WHERE OP.ORDER_ID = :oid
		//							 AND O.DELIVERY_ID = :deliveryid";
        $query['setORDER_PRO'] = 'UPDATE `order_product` SET `DELIVERED_QTY`=  :oapp , PRO_STATUSE = :pstat WHERE PRO_CODE = :procode AND ORDER_ID = :oid';
        $query['setOrderShipp'] = 'UPDATE `orders` SET EXISTS_ID = :exists , ORDER_STATUS  = :statuse , LAST_UPDATE    = now(3)
	                                  WHERE ORDER_ID    = :oid ';
	    
	    $query['setCUST_PROD'] = 'UPDATE `custody_product` SET `DELIVERED_QTY`=  :oapp , PRO_STATUSE = :pstat WHERE PRO_CODE = :procode AND ORDER_ID = :oid';
       // $query['setCUST_PROD'] = 'UPDATE `custody_product` SET `QTY`=`QTY` - :appQty WHERE PRO_CODE = :procode AND USER_ID = :deliveryid';
        $query['setCUST_STAT'] = 'UPDATE `custody_orders` SET `CUST_STAT`= :custstat WHERE ORDER_ID = :oid AND USER_ID = :deliveryid ';
		$query['countProduct'] = 'SELECT SUM(QTY) AS proCount from order_product WHERE  FIND_IN_SET (ORDER_ID,:oid)';
		$query['getOProducts'] = "SELECT OP.ORDER_ID,
										 OP.PRO_CODE,
										 PD.PRO_NAME,
										 CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
										 OP.PRICE,
										 OP.DISCOUNT,
										 OP.QTY,
										 OD.ORDER_STATUS,
										 OP.DELIVERED_QTY
										FROM  order_product AS OP
										 LEFT JOIN (SELECT  PC.PRO_CODE,
										     PR.PRO_NAME,
										     Concat(Group_concat(Concat(E.OPTION_NAME, ' : ', D.VALUE))) AS OPT_JSON
										   FROM   PRODUCT_CODES PC
										     LEFT JOIN PRODUCTS PR ON PC.PRODUCT_ID = PR.PRO_ID
										     LEFT JOIN PRODUCT_CODE_JOIN_OPTION C ON PC.PRO_CODE = C.PRODUCT_CODE
										     LEFT JOIN PRODUCT_OPTION D ON D.ID = C.OPTION_VALUE_ID
										     LEFT JOIN PRODUCT_OPTION_GROUP E ON E.OPTION_ID = D.OPTION_ID
										   GROUP BY PC.PRO_CODE,
										     PR.PRO_NAME)    AS PD ON OP.PRO_CODE = PD.PRO_CODE
										 LEFT JOIN ORDERS   AS OD ON OD.ORDER_ID = OP.ORDER_ID
										WHERE OP.ORDER_ID = :oid ";
        $query['getEmployStoreId'] = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';
        //$query['getCustodyEmoplo'] = 'SELECT CUSTODY_STORE FROM users_delivery WHERE USER_ID = :deliveryid ';
        $query['getDataOrders']    = "SELECT  A.ORDER_ID,
									  A.ORDER_BY,
									  A.DELIVERY_ID,
									  CONCAT(Z.FORENAME,' ' ,Z.LASTNAME) AS DELIVERY_NAME,
									  A.SUB_ORDER_ID,
									  A.EXISTS_ID,
									  A.ORDER_STATUS,
									  A.ORDER_SUCCESS,
									  A.LAST_UPDATE,
									  A.PRINT,
									  A.STO_ID,
									  CASE WHEN CO.CUST_STAT = 'FREEZE' THEN 1 ELSE 0 END AS FREEZE,
									  CASE WHEN Q.OS_STATUSE = 0 OR Q.MSG_COUNT > 0 THEN 1 ELSE 0 END AS OSMSGCS,
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
				                                         WHEN Max(C.OS_ID) IS NULL AND Max(B.OS_ID) IS NOT NULL THEN Max(B.OS_ID) 
				                                         ELSE 0 
				                                    END AS OS_ID,
				                                    CASE WHEN Max(C.OS_ID) IS NOT NULL THEN C.OS_STA 
				                                         WHEN Max(C.OS_ID) IS NULL AND Max(B.OS_ID) IS NOT NULL THEN B.OS_STA 
				                                         ELSE 1 
				                                    END AS OS_STATUSE,
				                                    (
				                                        Count(CASE WHEN B.OS_STA = 1 THEN D.OS_ID END)
				                                    ) AS OS_END_COUNT,
				                                    Count(E.OSTX_ID) AS MSG_COUNT
				                                FROM ORDERS A
				                                LEFT JOIN (
				                                    SELECT ORDER_ID, OS_STATUSE AS OS_STA, Max(OS_ID) AS OS_ID
				                                    FROM order_service
				                                    WHERE OS_STATUSE = 1
				                                    GROUP BY ORDER_ID
				                                ) B ON A.ORDER_ID = B.ORDER_ID
				                                LEFT JOIN (
				                                    SELECT ORDER_ID, OS_STATUSE AS OS_STA, Max(OS_ID) AS OS_ID
				                                    FROM order_service
				                                    WHERE OS_STATUSE = 0
				                                    GROUP BY ORDER_ID
				                                ) C ON A.ORDER_ID = C.ORDER_ID
				                                LEFT JOIN order_service AS D ON A.ORDER_ID = D.ORDER_ID
				                                LEFT JOIN order_service_text E ON D.OS_ID = E.OS_ID AND E.OSTX_READ = 0 AND E.USER_TYPE <> 1
				                                GROUP BY A.order_id
				                            ) AS Q ON A.ORDER_ID = Q.ORDER_ID 
				                      LEFT JOIN users AS Z ON A.DELIVERY_ID = Z.USER_ID
				                      LEFT JOIN custody_orders AS CO ON A.ORDER_ID = CO.ORDER_ID AND CO.USER_ID = A.DELIVERY_ID 
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
									ORDER BY A.DATE_CREATED";
/*		$query['checkOProductSTO'] = 'SELECT OP.ORDER_ID, OP.PRO_CODE, SP.STO_QTY, (SP.STO_QTY - SUM(OP.QTY)) AS NEWQTY
										FROM  order_product AS OP LEFT JOIN stores_product AS SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = :stoid
										GROUP BY OP.ORDER_ID,OP.PRO_CODE
										HAVING NEWQTY < 0 AND OP.ORDER_ID = :oid';*/
										
        $query["getAllDataOrders"] = 'SELECT * FROM ORDERS WHERE ORDER_ID IN (:oids)';
   		$query['getStoreProducts'] = 'SELECT STO_ID, JSON_OBJECTAGG(PRO_CODE, STO_QTY)AS STO_PRO FROM STORES_PRODUCT WHERE STO_ID = :stoid AND  FIND_IN_SET (PRO_CODE,:procode)';
/*		$query['checkOProductSTO'] = 'SELECT OP.ORDER_ID, OP.PRO_CODE, SP.STO_QTY, (SP.STO_QTY - SUM(OP.QTY)) AS NEWQTY
									  FROM  order_product AS OP LEFT JOIN stores_product AS SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = :stoid
									  GROUP BY OP.ORDER_ID,OP.PRO_CODE
									  HAVING NEWQTY < 0 AND OP.ORDER_ID = :oid';*/
									  
        $query['setDataOrders']    = 'UPDATE `orders` SET EXISTS_ID      = :exists , SHIPPING_DATE  = :shipdate , DELIVERY_ID    = :deliveryid , LAST_UPDATE    = now(3)
	                                  WHERE ORDER_ID    = :oid ';
        $query['add_Orders_log']   = "INSERT INTO `".$this->orders_log_Tbl."` (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) VALUES (:oid , :uauction, :log) ";
    //    $query['add_invetoreMove'] = "INSERT INTO `inventory_movement`(`IM_MOVE_TYPE`, `IM_USER`, `IM_BRANCH`, `STO_ID`, `IM_DETAILS`, `IM_APPROVAL_DISBURSED`, `IM_APPROVAL_RECEIVED`) VALUES (:typemove,:userid,:branchid,:stoid,:details,1,1)";
       /* $query['setStoreProductM'] = 'UPDATE STORES_PRODUCT AS SP LEFT JOIN order_product AS OP ON SP.PRO_CODE = OP.PRO_CODE
										SET  SP.STO_QTY = SP.STO_QTY - OP.QTY
									  WHERE SP.STO_ID = :stoid AND OP.ORDER_ID = :oid';
		$query['setStoreProductP'] = 'UPDATE STORES_PRODUCT AS SP LEFT JOIN order_product AS OP ON SP.PRO_CODE = OP.PRO_CODE
										SET  SP.STO_QTY = SP.STO_QTY + OP.QTY
										WHERE SP.STO_ID = :stoid AND OP.ORDER_ID = :oid';
	*/	$query['setCustodyUnCode'] = 'INSERT INTO CUSTODY  SELECT OP.PRO_CODE AS OPC, :uidv AS USER_ID, 0 AS QTY
										FROM  order_product AS OP LEFT JOIN custody AS CU ON CU.PRO_CODE = OP.PRO_CODE AND CU.USER_ID = :uid
										WHERE CU.PRO_CODE IS NULL AND OP.ORDER_ID = :oid
										GROUP BY OP.PRO_CODE';
	    $query['setCustodyEmoplo'] = 'UPDATE `users_delivery` SET CUSTODY_STORE = :custodystore WHERE  USER_ID = :deliveryid ';
        //From store = 0 |  to store = 1 | to customer = 2 | from customer = 3 | to colleague = 4
        $query['add_ShipMovement'] = 'INSERT INTO `shipping_movement`( `movement_type`, `delivery_id`, `user_id`, `shipment_orders`) VALUES (:type, :deliveryid, :userid, :shipmentdata)';
        
        
       // $query['add_invetory_out'] = 'INSERT INTO `inventory_out`(`IM_ID`) VALUES (:imid)';
        /*$query['add_inventorypro'] = 'INSERT INTO `inventory_product_movement` (`IM_ID`, IPM_TYPE_MOV,`IPM_CODE`,`IPM_STO_QTY`,`IPM_QTY`)
										 SELECT  :imid ,:type ,OP.PRO_CODE,SP.STO_QTY,Sum(OP.QTY)
										 FROM   order_product AS OP LEFT JOIN stores_product AS SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = :stoid
										 WHERE  OP.ORDER_ID = :oid
										 GROUP BY OP.ORDER_ID,OP.PRO_CODE';
     */   return $query;
    }
	//جلب بيانات مخزن الصرف
	private function get_inventory_id($branch_id){
		$query = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';
		$stStoreid = $this->conn->prepare($query);
	    $stStoreid->execute([':branchid' => $branch_id]);
	 	$result = $stStoreid->fetch(PDO::FETCH_ASSOC);
	    return $result['STO_ID'];
	        
	}
	//حذف حالات استفاء الشروط لتنفيذ اى اجراء على امر البيع
	private function filter_array_keys($array, $keys_to_keep) {
	    foreach ($array as $oid => $value) {
		    foreach ($array[$oid] as $key => $value) {
		        if (!in_array($key, $keys_to_keep)) {
		            unset($array[$oid][$key]);
		        }
		    }
	    }
	    return $array;
	}
	//التأكد من استفاء الشروط لتنفيذ الاجراء ل امر البيع الواحد
	private function check_stat_order($orderRow ){
		$x=[];
		if($orderRow){
			$x[$orderRow['ORDER_ID']] = [
			        'osubid'  => $orderRow['SUB_ORDER_ID'], 
			        'print'   => true,
			        'statuse' => true,
			        'oserv'   => true, 
			        'lastup'  => true, 
			      	'store'   => true,
			      	'freeze'  => true,
			      	'shipment'=> true
				];
			//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
			if( $orderRow['LAST_UPDATE'] != $this->orderobj[$orderRow['ORDER_ID']]['olup']){
	    		$x[$orderRow['ORDER_ID']]['lastup'] = false;
	    	}
			//لم يتم طباعة امر البيع
			if(!$orderRow['PRINT']){
	    		$x[$orderRow['ORDER_ID']]['print'] = false;
	    	}
			// يوجد طلبات لخدمة العملاء مفتوحه
			if($orderRow['OSMSGCS'] > 0){
	    		$x[$orderRow['ORDER_ID']]['oserv'] = false;
	    	}
			//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
			if($orderRow['ORDER_STATUS'] != 2){
	    		$x[$orderRow['ORDER_ID']]['statuse'] = false;
	    	}
	    	//امر البيع مجمد 
	    	$query = "SELECT CUST_STAT FROM custody_orders WHERE CUST_STAT = 'FREEZE' AND ORDER_ID = :oid AND USER_ID = :delid ";
			$stCustFZ[$orderRow['ORDER_ID']] = $this->conn->prepare($query);
			$stCustFZ[$orderRow['ORDER_ID']]->bindValue(':oid'   , $orderRow['ORDER_ID'], PDO::PARAM_INT);
			$stCustFZ[$orderRow['ORDER_ID']]->bindValue(':delid' , $this->delivery_id  , PDO::PARAM_INT);
			if($stCustFZ[$orderRow['ORDER_ID']]->execute() && $stCustFZ[$orderRow['ORDER_ID']]->rowCount()>0){
	    		$x[$orderRow['ORDER_ID']]['freeze'] = false;
	    	}
			//=========التأكد من وجود رصيد لمتجات امر البيع بالمخزن============
			$query = 'SELECT OP.ORDER_ID, OP.PRO_CODE, SP.STO_QTY, (SP.STO_QTY - SUM(OP.QTY)) AS NEWQTY
						FROM  order_product AS OP LEFT JOIN stores_product AS SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = :stoid
						GROUP BY OP.ORDER_ID,OP.PRO_CODE
						HAVING NEWQTY < 0 AND OP.ORDER_ID = :oid';
			$stcheckOProSTO[$orderRow['ORDER_ID']] = $this->conn->prepare($query);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->bindValue(':stoid', $this->store_id , PDO::PARAM_INT);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->bindValue(':oid'  , $orderRow['ORDER_ID'], PDO::PARAM_INT);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->execute();
			//===============التأكد من عدم توافر المخزون===================
			if($stcheckOProSTO[$orderRow['ORDER_ID']]->rowCount()>0){
				$x[$orderRow['ORDER_ID']]['store'] = false;
			}
		} else {
			$x[$orderRow['ORDER_ID']] = [
			        'osubid'  => $orderRow['SUB_ORDER_ID'], 
			        'print'   => false,
			        'statuse' => false,
			        'oserv'   => false, 
			        'lastup'  => false, 
			      	'store'   => false,
			      	'freeze'  => false,
			];
		}
		
		return $x;
	}
	//اضافة العهدة على المندوب
	private function insert_custody(&$orderid, &$deliveryid){
		$response = false;
		$stinsert_custody = array();
		$stinsert_custpro = array();
		$query = "SELECT CUST_STAT FROM custody_orders 
					WHERE CUST_STAT = 'FREEZE' 
						AND ORDER_ID = :oid 
						AND USER_ID = :delid ";
	
		$stCustCH[$orderid] = $this->conn->prepare($query);
		$stCustCH[$orderid]->bindValue(':oid'   , $orderid 		, PDO::PARAM_INT);
		$stCustCH[$orderid]->bindValue(':delid' , $deliveryid 	, PDO::PARAM_INT);
		$stCustCH[$orderid]->execute();
					
		$query = "SELECT CUST_STAT FROM custody_orders WHERE CUST_STAT <> 'FREEZE' AND ORDER_ID = :oid ";
		$stCustCH2[$orderid] = $this->conn->prepare($query);
		$stCustCH2[$orderid]->bindValue(':oid' , $orderid  , PDO::PARAM_INT);
		$stCustCH2[$orderid]->execute();
		
		if ($stCustCH[$orderid]->execute() && 
			$stCustCH[$orderid]->rowCount()<=0 &&
			$stCustCH2[$orderid]->execute() &&
			$stCustCH2[$orderid]->rowCount()<=0
		){
		//===========insert custody order==================
			$query = 'INSERT custody_orders (ORDER_ID,USER_ID)VALUE(:oid,:delid)';
			$stinsert_custody[$orderid] = $this->conn->prepare($query);
			$stinsert_custody[$orderid]->bindValue(':oid'   , $orderid 		, PDO::PARAM_INT);
			$stinsert_custody[$orderid]->bindValue(':delid' , $deliveryid  , PDO::PARAM_INT);
			if($stinsert_custody[$orderid]->execute()&& $stinsert_custody[$orderid]->rowCount()>0){ 
				//===========insert custody pro==================
				$query = 'INSERT INTO custody_product  
							SELECT OP.ORDER_ID,
								CO.USER_ID,
								OP.PRO_CODE,
								OP.QTY,
								OP.PRICE,
								OP.DISCOUNT,
								OP.DELIVERED_QTY,
								OP.PRO_STATUSE
							FROM  order_product AS OP
							 LEFT JOIN custody_orders AS CO ON OP.ORDER_ID = CO.ORDER_ID
							WHERE CO.ORDER_ID  = :oid
							 AND CO.USER_ID = :uid ';
				$stinsert_custpro[$orderid] = $this->conn->prepare($query);
				$stinsert_custpro[$orderid]->bindValue(':oid' , $orderid     , PDO::PARAM_INT);
				$stinsert_custpro[$orderid]->bindValue(':uid' , $deliveryid  , PDO::PARAM_INT);
				if($stinsert_custpro[$orderid]->execute()){
					$response = true;
				}
			}
		}
		return $response;
	}
	//خذف العهدة من حساب المندوب
	private function delete_custody(&$orderid, &$deliveryid){
		$response = false;
		$query = "DELETE FROM custody_orders WHERE CUST_STAT='WAITING' AND ORDER_ID = :oid AND USER_ID = :delid";
					$stCust0[$orderid] = $this->conn->prepare($query);
					$stCust0[$orderid]->bindValue(':oid'   , $orderid 		, PDO::PARAM_INT);
					$stCust0[$orderid]->bindValue(':delid' , $deliveryid, PDO::PARAM_INT);
					
					if($stCust0[$orderid]->execute() && $stCust0[$orderid]->rowCount()>0){
						//===========update custody pro==================
						$query = 'DELETE FROM custody_product WHERE  ORDER_ID = :oid AND USER_ID = :delid';
						$stCust2[$orderid] = $this->conn->prepare($query);
						$stCust2[$orderid]->bindValue(':oid'   , $orderid 		, PDO::PARAM_INT);
						$stCust2[$orderid]->bindValue(':delid' , $deliveryid, PDO::PARAM_INT);
						if($stCust2[$orderid]->execute() && $stCust2[$orderid]->rowCount()>0){
							$response = true;
						}
					}
		return $response;
	}
	//تحديث بيانات عهدة المدنوب لامر البيع الواحد
	private function update_custody(&$orderid, &$deliveryid , $custState){
		$response = false;
		if($custState ==='FREEZE'){
			$set = "CUST_STAT = :cust";
			$wh = " CUST_STAT = 'WAITING'";
		} else if($custState ==='DELIVERED'){
			$set = "CUST_STAT = :cust";
			$wh = " CUST_STAT <> 'FREEZE'";
		}else if($custState ==='REFUND'){
			$custState = true;
			$set = "RETURNS = :cust";
			$wh = "CUST_STAT IN ('DELIVERED','FREEZE')";
		}
		if(!empty($custState) && $set && $wh){
			$query = "UPDATE custody_orders SET ".$set." WHERE ".$wh." AND ORDER_ID = :oid AND USER_ID = :delid";
			$stCust0[$orderid] = $this->conn->prepare($query);
			$stCust0[$orderid]->bindValue(':cust'  , $custState  , PDO::PARAM_STR);
			$stCust0[$orderid]->bindValue(':oid'   , $orderid 	 , PDO::PARAM_INT);
			$stCust0[$orderid]->bindValue(':delid' , $deliveryid , PDO::PARAM_INT);
			if($stCust0[$orderid]->execute()){
				$response = true;
			}
			
		}
		return $response;
	}
	//صرف او اضافة المخزون
    private function exchange_Add_inventory($orderid ,$Movement_type){
    	$response = false;
    	$St_Qty_query;
    	if($Movement_type){
	    	switch (true){
	    		case ($Movement_type == "IN"):
	    			$St_Qty_query = "SP.STO_QTY + OP.QTY";
	    			$movStr = ' استرداد منتجات امر البيع من المندوب';
	    			break;
	    		case ($Movement_type == "OUT"):
	    			$St_Qty_query = "SP.STO_QTY - OP.QTY";
	    			$movStr = ' صرف منتجات امر البيع للمندوب';
	    			break;
	    		case ($Movement_type === "RETURN"):
	    			$St_Qty_query = "SP.STO_QTY + (OP.QTY - OP.DELIVERED_QTY)";
	    			$movStr = ' إستلام مرتجعات امر البيع من المندوب';
	    			break;
	    	}
	    	if($St_Qty_query){
	    		$store_id      = htmlspecialchars(strip_tags(trim($this->store_id)));
			    $branch_id     = htmlspecialchars(strip_tags(trim($this->branch_id)));
			    $user_action   = htmlspecialchars(strip_tags(trim($this->user_action)));
			    $delivery_name = htmlspecialchars(strip_tags(trim($this->delivery_name)));
		    	$query1 = 'UPDATE STORES_PRODUCT AS SP 
		    				LEFT JOIN custody_product AS OP ON SP.PRO_CODE = OP.PRO_CODE
							SET  SP.STO_QTY = ('.$St_Qty_query.')
						  WHERE SP.STO_ID   = :sid 
						    AND OP.ORDER_ID = :oid';
			
		    	$stStoPro[$orderid] = $this->conn->prepare($query1);
				$stStoPro[$orderid]->bindValue(':sid' , $store_id , PDO::PARAM_INT);
				$stStoPro[$orderid]->bindValue(':oid' , $orderid  , PDO::PARAM_INT);
				if($stStoPro[$orderid]->execute() && $stStoPro[$orderid]->rowCount()){
					
								        
					$query2 = "INSERT INTO `inventory_movement`(`IM_MOVE_TYPE`, `IM_USER`, `IM_BRANCH`, `STO_ID`, `IM_DETAILS`, `IM_APPROVAL_DISBURSED`, `IM_APPROVAL_RECEIVED`) VALUES
										(
											:typemove,
											:userid,
											:branchid,
											:stoid,
											:details,
											1,
											1
										)";
					$details = [ $movStr.$this->delivery_name , $orderid ];
					$stInvMov[$orderid] = $this->conn->prepare($query2);
					$stInvMov[$orderid]->bindValue(':typemove',$Movement_type 			 	, PDO::PARAM_STR);
					$stInvMov[$orderid]->bindValue(':userid'  ,$user_action 	, PDO::PARAM_INT);
					$stInvMov[$orderid]->bindValue(':stoid'   ,$store_id    	, PDO::PARAM_INT);
					$stInvMov[$orderid]->bindValue(':branchid',$branch_id    	, PDO::PARAM_INT);
					$stInvMov[$orderid]->bindValue(':details' ,json_encode($details) 	, PDO::PARAM_INT);
		
					if($stInvMov[$orderid]->execute()){
						$imid = (int) $this->conn->lastInsertId();
					//==========insert inventory_product_movement==========
						$query3 = 'INSERT INTO `inventory_product_movement` (`IM_ID`, IPM_TYPE_MOV,`IPM_CODE`,`IPM_STO_QTY`,`IPM_QTY`)
										 SELECT  
										 	'.$imid.' ,
										 	:type ,OP.PRO_CODE,SP.STO_QTY,Sum(OP.QTY)
										 FROM   order_product AS OP LEFT JOIN stores_product AS SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = 
										 	:stoid
										 WHERE  OP.ORDER_ID = 
										 	:oid
										 GROUP BY OP.ORDER_ID,OP.PRO_CODE';
						$stInvPMov[$orderid] = $this->conn->prepare($query3);
						//$stInvPMov[$orderid]->bindValue(':imid'    ,$imid               , PDO::PARAM_INT);
						$stInvPMov[$orderid]->bindValue(':type'    ,$Movement_type 	  , PDO::PARAM_STR);
						$stInvPMov[$orderid]->bindValue(':stoid'   ,$store_id     , PDO::PARAM_INT);
						$stInvPMov[$orderid]->bindValue(':oid'     ,$orderid 			  , PDO::PARAM_INT);
						if($stInvPMov[$orderid]->execute()){
							$response = true;
						}
					}
				}
	    	}
    	}
    	
		return $response;
    }
    //اعادة تهيئة امر البيع لما قبل التسليم
    private function reset_Delivered_Order($orderid, $deliveryid, $custStat = '', $restType = "all"){
    	$data_return = false;
    	$custStatPro = "CP.PRO_STATUSE='waiting'";
    	if($custStat == 'FREEZE'){
    		$custStatPro = "CP.PRO_STATUSE='rejected'";
    	}
    	$query1 = "UPDATE custody_product AS CP LEFT JOIN orders AS O ON CP.ORDER_ID = O.ORDER_ID
									SET  CP.DELIVERED_QTY = 0,
									 ".$custStatPro."
									WHERE CP.ORDER_ID = :oid
									 AND O.DELIVERY_ID = :deliveryid ";

 		$query2 = "UPDATE order_product AS OP LEFT JOIN orders AS O ON OP.ORDER_ID = O.ORDER_ID
									SET  OP.DELIVERED_QTY = 0,
									 OP.PRO_STATUSE='waiting'
									WHERE OP.ORDER_ID = :oid
									 AND O.DELIVERY_ID = :deliveryid";
		if($restType === "cp" || $restType === "all"){
	    	$stRE1Update[$orderid] = $this->conn->prepare($query1);
			$stRE1Update[$orderid]->bindValue(':uid', $deliveryid	, PDO::PARAM_INT);
			$stRE1Update[$orderid]->bindValue(':oid', $orderid 	, PDO::PARAM_INT);
			//$stRE1Update[$orderid]->execute();
		}
		if($restType === "op" || $restType === "all"){
			$stRE2Update[$orderid] = $this->conn->prepare($query2);
			$stRE2Update[$orderid]->bindValue(':uid', $deliveryid	, PDO::PARAM_INT);
			$stRE2Update[$orderid]->bindValue(':oid', $orderid 		, PDO::PARAM_INT);
			$stRE2Update[$orderid]->execute();
		}
		if($restType === "cp" ){
			if($stRE1Update[$orderid]->execute() && $stRE1Update[$orderid]->rowCount()){
				$data_return = true;
			}
		}else if($restType === "op"){
			if($stRE2Update[$orderid]->execute() && $stRE2Update[$orderid]->rowCount()){
				$data_return = true;
			}
		}else if($restType === "all"){
			if($stRE1Update[$orderid]->execute() && 
			   $stRE2Update[$orderid]->execute() &&
			   $stRE1Update[$orderid]->rowCount()&&
			   $stRE2Update[$orderid]->rowCount()
			){
			   	$data_return = true;
			}
		}
		return $data_return;
    }
    //اضافة منتجات امر البيع الواحد عهدة على المندوب
	private function custody_delevary($type,$orderid, $deliveryid){
		/* 
		 	$type = get
		 	$type = add
		 	$type = del
		 	$type = freeze
		 	$type = refund
		 	$type = delivered
		 	$type = returned
		 	
		*/
		$invmovType = null;
		$statuse = false;
		if($type && $orderid && $deliveryid){
			//===========insert custody order==================
			switch ($type) {
				case 'get':
					// code...
					break;
				case 'add':
					//===========insert custody order==================
					$statuse = self::insert_custody($orderid,$deliveryid);
					$invmovType = 'OUT';
					break;
				case 'del':
					//===========DELETE custody order==================
					$statuse = self::delete_custody($orderid,$deliveryid);
					$invmovType = 'IN';
					break;
				case 'refund':
					//===========update custody order statuse==================
					$statuse = self::update_custody($orderid,$deliveryid,"REFUND");
					$invmovType = 'RETURN';
					break;
				case 'freeze':
					//===========update custody order statuse==================
					$statuse = self::update_custody($orderid,$deliveryid,"FREEZE");
					if($statuse){
						$statuse = self::reset_Delivered_Order($orderid, $deliveryid, "FREEZE", "cp");
					}
					break;
				case 'delivered':
					//===========update custody order statuse==================
					$statuse = self::update_custody($orderid,$deliveryid,"DELIVERED");
					break;
			}
		}
		if($statuse && $invmovType !== null){
			$statuse = self::exchange_Add_inventory($orderid,$invmovType);
		}
		return $statuse;
	}
	
	//=============وظائف الاساسية لهذا الكائن===========
	//عدد المنتجات الموجوده فى اوامر البيع التي يتم شحنها مع المندوب الواحد
	public function countProducts(){
		$sql  = $this->query();
		$stmtCountProduct = $this->conn->prepare($sql['countProduct']);
		$stmtCountProduct->bindValue(':oid', implode(',',array_keys($this->orderobj)), PDO::PARAM_INT);
		$stmtCountProduct->execute();
		$result = $stmtCountProduct->fetch(PDO::FETCH_ASSOC);
		return array(count(array_keys($this->orderobj)), $result['proCount']);
		    
	}
    //صرف العهده للمندوب
    public function regEmployeeCustody (){
    	$sql 		  = $this->query();
		$osucceeded   = [];
		$orejected    = [];
		$ecode 		  = [];
        $ordersISNotConditions = [];
        try{
        	$startpoint = 'spSTAR1001';
        	$this->conn->beginTransaction();
        	$this->conn->exec("SAVEPOINT $startpoint");
        	
			//===============جلب بيانات مخزن الصرف ====================
			if(!$this->store_id){
				$this->store_id = $this->get_inventory_id($this->branch_id);
			}
	        //=============جلب بيانات امر البيع والتعديل عليه=================
	       	$stAllOrderData = $this->conn->prepare($sql['getDataOrders']);
		    $stAllOrderData->bindValue(':oids', implode(',',array_keys($this->orderobj)), PDO::PARAM_STR);
		    $stAllOrderData->execute();
		    $xloop = 1;
			while ($record = $stAllOrderData->fetch(PDO::FETCH_ASSOC)){
				//==========التأكد من استفاء الشروط لامر البيع
				$get_stat_order = $this->check_stat_order($record);
				$ordersISNotConditions[$record['ORDER_ID']] = $get_stat_order[$record['ORDER_ID']];
				//==========التأكد ان امر البيع يستوفى جميع الشروط 
				if( $ordersISNotConditions[$record['ORDER_ID']]['statuse'] === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['oserv']   === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['print']   === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['lastup']  === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['store']   === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['freeze']  === true
				){
					//=======انشاء نقطة رجوع لاسترجاع البيانات فى حال حدوث خطاء ========
					$PointNum     = 'sp'.$_SESSION['USER_ID'] .$record['ORDER_ID']. date("YmdHisu", time()).rand(1,9999);
					$this->conn->exec("SAVEPOINT $PointNum");
					//==================update orders======================
					$exie = 9;
					$stOUpdate[$xloop] = $this->conn->prepare($sql['setDataOrders']);
					$stOUpdate[$xloop]->bindValue(':exists'     , $exie, PDO::PARAM_INT);
					$stOUpdate[$xloop]->bindValue(':shipdate', $this->shiping_date     , PDO::PARAM_STR);
					$stOUpdate[$xloop]->bindValue(':deliveryid', $this->delivery_id    , PDO::PARAM_INT);
					$stOUpdate[$xloop]->bindValue(':oid'        , $record['ORDER_ID']  , PDO::PARAM_INT);
					if($stOUpdate[$xloop]->execute() && $stOUpdate[$xloop]->rowCount()> 0){
						//===========insert custody order==================
						$custodyStat = self::custody_delevary('add',$record['ORDER_ID'],$this->delivery_id);
						if($custodyStat){
							//============insert shipping_movement================
										/*
											0 = Delivery خروج المندوب
											1 = Retrieve
											2 = Delivered
											3 = Returning 
											4 = Returned
										*/
								$shtype=0;
								$stShMov[$xloop] = $this->conn->prepare($sql['add_ShipMovement']);
								$stShMov[$xloop]->bindValue(':type'   , $shtype  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':deliveryid'   , $this->delivery_id  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':userid'       , $this->user_action  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':shipmentdata' , $record['ORDER_ID'] , PDO::PARAM_STR);
								if($stShMov[$xloop]->execute() && $stShMov[$xloop]->rowCount()){
									//=================insert ORDERS_LOG===================
									$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group."*تم تسليم امر البيع الى المندوب ".$this->delivery_name;
									$stOLog[$xloop] = $this->conn->prepare($sql['add_Orders_log']);
									$stOLog[$xloop]->bindValue(':oid'      , $record['ORDER_ID'] , PDO::PARAM_STR);
									$stOLog[$xloop]->bindValue(':uauction' , $this->user_action  , PDO::PARAM_STR);
									$stOLog[$xloop]->bindValue(':log'      , $logtext            , PDO::PARAM_STR);
									if($stOLog[$xloop]->execute()){
										//فى حالة استفاء كل الشروط يتم حذف امر البيع من 
										unset($ordersISNotConditions[$record['ORDER_ID']]);
										$osucceeded[] = $record['ORDER_ID'];
									}else{
										$this->conn->exec("ROLLBACK TO $PointNum");
										$orejected[] = $record['ORDER_ID'];
										$ecode[] = $record['ORDER_ID']."E2";
									}
								}else{
									$this->conn->exec("ROLLBACK TO $PointNum");
									$orejected[] = $record['ORDER_ID'];
									$ecode[] = $record['ORDER_ID']."E3";
								}
						}else{
							$this->conn->exec("ROLLBACK TO $PointNum");
							$orejected[] = $record['ORDER_ID'];
							$ecode[] = $record['ORDER_ID']."E6";
						}
		            }else{
		            	$this->conn->exec("ROLLBACK TO $PointNum");
		            	$orejected[] = $record['ORDER_ID'];
		            	$ecode[] = $record['ORDER_ID']."E7";
		            }
	    		}
	    		$xloop++;
		    }
		    if(count($orejected)){
		    	$this->conn->exec("ROLLBACK TO $startpoint");
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
						'تم تسليم العهده بالكامل للمندوب',
						$ordersISNotConditions);
					}else{
						return array('warning',  
						'نجح',
						'نجح تسليم عدد ( '.count($osucceeded).' ) امر بيع وهناك عدد ( '.count( $ordersISNotConditions).' ) لم يستوفى شروط التسليم للمندوب :-<br>',
						$ordersISNotConditions);
					}
			    }else if(count($ordersISNotConditions)){
			    	return array('error',
	                'نأسف',
	                ' هناك عدد ( '. count($ordersISNotConditions) .' ) امر بيع لم يستوفى شروط التسليم للمندوب',
	                $ordersISNotConditions);
			    }
		    }
	        $this->conn = null;
		} catch (PDOException $e) {
            $this->conn->exec("ROLLBACK TO $startpoint");
            $this->conn = null;
            return array('error', $e->getCode().' - '.$e->getMessage());
        }
        exit();
    }
    //استرداد العهده من المندوب
    public function refEmployeeCustody ($refInventory=false){
    	
    	$sql 		  = $this->query();
		$osucceeded   = [];
		$orejected    = [];
		$ecode 		  = [];
        
        $ordersISNotConditions = [];
        try{
        	$startpoint = 'spSTAR1002';
        	$this->conn->beginTransaction();
        	$this->conn->exec("SAVEPOINT $startpoint");

			//===============جلب بيانات مخزن الاستلام ====================
			if(!$this->store_id){
				$this->store_id = $this->get_inventory_id($this->branch_id);
			}
	        //=============جلب بيانات امر البيع والتعديل عليه=================
	       	$stAllOrderData = $this->conn->prepare($sql['getDataOrders']);
		    $stAllOrderData->bindValue(':oids', implode(',',array_keys($this->orderobj)), PDO::PARAM_STR);
		    $stAllOrderData->execute();
		    $xloop = 1;
			while ($record = $stAllOrderData->fetch(PDO::FETCH_ASSOC)){
				$this->delivery_id = $record['DELIVERY_ID'];
				//==========التأكد ان امر البيع يستوفى جميع الشروط السابقة================
				$get_stat_order = $this->check_stat_order($record);
				$ordersISNotConditions[$record['ORDER_ID']] = $get_stat_order[$record['ORDER_ID']];
				if( $ordersISNotConditions[$record['ORDER_ID']]['lastup']  === true &&
					$ordersISNotConditions[$record['ORDER_ID']]['freeze']  === true 
					){
					//=======انشاء نقطة رجوع لاسترجاع البيانات فى حال حدوث خطاء ========
					$PointNum     = 'sp'.$_SESSION['USER_ID'] .$record['ORDER_ID']. date("YmdHisu", time()).rand(1,9999);
					$this->conn->exec("SAVEPOINT $PointNum");
					//==================update orders======================
					$exie = 8;
					$this->shiping_date = null;
					$delid = null;
					$stOUpdate[$xloop] = $this->conn->prepare($sql['setDataOrders']);
					$stOUpdate[$xloop]->bindValue(':exists'     , $exie, PDO::PARAM_INT);
					$stOUpdate[$xloop]->bindValue(':shipdate', $this->shiping_date     , PDO::PARAM_STR);
					$stOUpdate[$xloop]->bindValue(':deliveryid', $delid    , PDO::PARAM_INT);
					$stOUpdate[$xloop]->bindValue(':oid'        , $record['ORDER_ID']  , PDO::PARAM_INT);
					//=============update custody_product================
					if($stOUpdate[$xloop]->execute() && $stOUpdate[$xloop]->rowCount()> 0){
					//=================insert ORDERS_LOG===================
						$refcustmsg ='';
						$shtype=3;
						if($refInventory == "true"){
							$refcustmsg = 'دون استرداد المنتجات';
							$shtype=1;
						}
						$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group."*تم استرداد امر البيع من المندوب ".$record['DELIVERY_NAME'].' '.$refcustmsg;
						$stOLog[$xloop] = $this->conn->prepare($sql['add_Orders_log']);
						$stOLog[$xloop]->bindValue(':oid'      , $record['ORDER_ID'] , PDO::PARAM_STR);
						$stOLog[$xloop]->bindValue(':uauction' , $this->user_action  , PDO::PARAM_STR);
						$stOLog[$xloop]->bindValue(':log'      , $logtext            , PDO::PARAM_STR);
						if($stOLog[$xloop]->execute()){
						//=============insert shipping_movement================
								/*
									0 = Delivery خروج المندوب
									1 = freeze استرداد العهده دون استرداد المنتجات
									2 = Delivered الوصول للعميل
									3 = Returning استرداد العهده بالكتمل
									4 = Returned
								*/
								//$shtype=1;
								$stShMov[$xloop] = $this->conn->prepare($sql['add_ShipMovement']);
								$stShMov[$xloop]->bindValue(':type'         , $shtype  			  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':deliveryid'   , $this->delivery_id  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':userid'       , $this->user_action  , PDO::PARAM_INT);
								$stShMov[$xloop]->bindValue(':shipmentdata' , $record['ORDER_ID'] , PDO::PARAM_STR);
								if($stShMov[$xloop]->execute() && $stShMov[$xloop]->rowCount()){
									if($refInventory === 'true'){									
										//استرداد امر البيع بدون رد العهده
										$custodyStatuse = self::custody_delevary('freeze',$record['ORDER_ID'] , $this->delivery_id);
										if($custodyStatuse){
											//فى حالة استفاء كل الشروط يتم حذف امر البيع من 
											unset($ordersISNotConditions[$record['ORDER_ID']]);
											$osucceeded[] = $record['ORDER_ID'];
										}else{
											$this->conn->exec("ROLLBACK TO $PointNum");
											$orejected[] = $record['ORDER_ID'];
											$ecode[] = $record['ORDER_ID']."E-Cust_Stat";
										}
									}else{
									//استرداد العهدة بالكامل
										$custodyStatuse = $this->custody_delevary('del',$record['ORDER_ID'] , $this->delivery_id);
										if($custodyStatuse){
										//===============update STORES_PRODUCT=================
											//فى حالة استفاء كل الشروط يتم حذف امر البيع من 
											unset($ordersISNotConditions[$record['ORDER_ID']]);
											$osucceeded[] = $record['ORDER_ID'];
										}else{
											$this->conn->exec("ROLLBACK TO $PointNum");
											$orejected[] = $record['ORDER_ID'];
											$ecode[] = $record['ORDER_ID']."E-Full_Cust_Stat";
										}
									}
								}else{
									$this->conn->exec("ROLLBACK TO $PointNum");
									$orejected[] = $record['ORDER_ID'];
									$ecode[] = $record['ORDER_ID']."E2";
								}
							
						}else{
							$this->conn->exec("ROLLBACK TO $PointNum");
							$orejected[] = $record['ORDER_ID'];
							$ecode[] = $record['ORDER_ID']."E6";
						}
		            }else{
		            	$this->conn->exec("ROLLBACK TO $PointNum");
		            	$orejected[] = $record['ORDER_ID'];
		            	$ecode[] = $record['ORDER_ID']."E1";
		            }
	    		}
	    		$xloop++;
		    }
		    
		    if(count($orejected)){
		    	$this->conn->exec("ROLLBACK TO $startpoint");
		    	return array('error',
                    'خطاء',
                    'حدث خطاء اثناء تحديث بيانات اوامر البيع وقد تم الغاء الإجراء / رمز الخطاء : '.implode(" | ",$ecode),
                    []);
		    }else{
		        if(count($osucceeded)){
			    	$this->conn->commit();
			    	$msgsucc ='';
			    	if($refInventory === 'true'){
			    		$msgsucc = "نجح الاسترداد دون رد المنتجات الى المخزن ";
			    	}else{
			    		$msgsucc = "تم استرداد العهده بالكامل  ";
			    	}
				    if(!count($ordersISNotConditions)){
						return array('success',
						'نجح',
						$msgsucc,
						$ordersISNotConditions);
					}else{
						return array('warning',  
						'نجح',
						$msgsucc.' لعدد ( '.count($osucceeded).' ) امر بيع وهناك عدد ( '.count( $ordersISNotConditions).' ) لم يستوفى شروط  استرداد العهده من المندوب :-<br>',
						$ordersISNotConditions);
					}
			    }else if(count($ordersISNotConditions)){
			    	return array('error',
	                'نأسف',
	                ' هناك عدد ( '. count($ordersISNotConditions) .' ) امر بيع لم يستوفى شروط استرداد العهده لمن المندوب',
	                $ordersISNotConditions);
			    }
		    }
	        $this->conn = null;
		} catch (PDOException $e) {
            $this->conn->exec("ROLLBACK TO $startpoint");
            $this->conn = null;
            return array('error', $e->getCode().' - '.$e->getMessage());
        }
        exit();
    }
    //تسليم الشحنة للعميل
    public function ShipmentDelivery($type = 'view'){
    	$sql  = $this->query();
    	if($type == 'view' ){
    	
	    	$stOP = $this->conn->prepare($sql['getOProducts']);
	    	$stOP->bindValue(':oid', implode(',',array_keys($this->orderobj)), PDO::PARAM_STR);
		    $stOP->execute();
		    $data_return = '<div class="btn-group" style="margin:1px;"> 
								<button type="button" class="btn btn-success m-1" onclick="decreaseQtyAllPublic()">
										تسليم الكل
										<i class="fa-solid fa-check"></i>
								</button>
								<button type="button" class="btn btn-danger m-1" onclick="increaseQtyAllPublic()">
									رفض الكل
									<i class="fa-solid fa-xmark"></i>
								</button>
						</div>
		    			<table class="table table-swel table-hover table-responsive text-nowrap table-striped w-100 col-sm-12 p-0 m-0 " style="font-size: 1rem;">
							<thead>
								 <tr class="">
									<th class="col-4">المنتج</th>
									<th class="col-2">المطلوب</th>
									<th class="col-2">المرفوض</th>
									<th class="col-4"><+-></th>
								</tr>
							</thead>
								<tbody>';
		    while ($record = $stOP->fetch(PDO::FETCH_ASSOC)){
		    	$deliveredQty = ((float) $record["QTY"]) - ((float) $record["DELIVERED_QTY"]);
		    	/*if($record["ORDER_STATUS"] === 2){
		    		$deliveredQty = (float) $record["QTY"] ;
		    	}*/
		    	$attr = explode(',',$record["OPTION"]);
			    $data_return .= '<tr data-code="'.$record["PRO_CODE"].'">
									<td class="text-nowrap productname text-left">
										<div class="proname">
									        <div class=" tdrest tdproName">'.$record["PRO_NAME"].'</div>
									        <a class="tdrest text-info tdproOption p-0 m-0" style="font-size:0.79rem;margin-bottom:5px">'.implode(' | ',$attr).'</a>
									    </div>
								    </td>
								    <td><span class="default-Qty" id="max-qty-'.$record["PRO_CODE"].'">'.(float)$record["QTY"].'</span></td>
									<td class="appQty"><span class="approved-Qty" data-code="'.$record["PRO_CODE"].'" id="approved-qty-'.$record["PRO_CODE"].'">'. $deliveredQty .'</span></td>
									<td class="p-0">
										<div class="btn-group" style="margin:-1px;"> 
											<button type="button" class="btn" onclick="increaseQty('.$record["PRO_CODE"].')">
												<i class="fa-solid fa-plus text-primary"></i>
											</button>
											<button type="button" class="btn" onclick="decreaseQty('.$record["PRO_CODE"].')">
												<i class="fa-solid fa-minus text-primary"></i>
											</button>
										</div>
										<div class="btn-group" style="margin:-1px;"> 
											<button type="button" class="btn" onclick="decreaseQtyAll('.$record["PRO_CODE"].')">
												<i class="fa-solid fa-check text-success"></i>
											</button>
											<button type="button" class="btn" onclick="increaseQtyAll('.$record["PRO_CODE"].')">
												<i class="fa-solid fa-xmark text-danger"></i>
											</button>
										</div>
									</td>
								</tr>';
		    }
		    $data_return .= '</tbody>
			</table>';
		    return $data_return;
    	}else if($type == 'confirm'){
    		//shipped order
			$osucceeded   = [];
			$orejected    = [];
			$ecode 		  = [];
	        $ordersISNotConditions = [];
	        try{
	        	//=======انشاء نقطة رجوع لاسترجاع البيانات فى حال حدوث خطاء ========
				$PointNum     = 'spSTAR1003';
				$this->conn->beginTransaction();
				$this->conn->exec("SAVEPOINT $PointNum");
	   			//=============جلب بيانات امر البيع والتعديل عليه=================
	    		$stAllOrderData = $this->conn->prepare($sql['getDataOrders']);
			    $stAllOrderData->bindValue(':oids', implode(',',array_keys($this->orderobj)), PDO::PARAM_STR);
			    $stAllOrderData->execute();
			    $xloop = 1;
				while ($record = $stAllOrderData->fetch(PDO::FETCH_ASSOC)){
					//==========التأكد من استفاء الشروط لامر البيع===================
					$keys_to_keep 	= array("osubid", "lastup", "freeze");
					$get_stat 		= self::check_stat_order($record);
					$get_stat_order = self::filter_array_keys($get_stat, $keys_to_keep);
					
					$ordersISNotConditions[$record['ORDER_ID']] = $get_stat_order[$record['ORDER_ID']];
					if( $ordersISNotConditions[$record['ORDER_ID']]['lastup']  === true &&
						$ordersISNotConditions[$record['ORDER_ID']]['freeze']  === true
					){
						//========update custody order==========
						$stCOUpdate = self::update_custody($record['ORDER_ID'] , $record['DELIVERY_ID'] , 'DELIVERED');
						if($stCOUpdate){
							//=========update order_product===========
							$prosuccess 	= 0;
							$All_required 	= 0;
							$All_delivered 	= 0;
							
							foreach ($this->shipmentobj['oapp'] as $keys => $proCode) {
								$qty_required  = (float) $proCode['defqty'];//الكمية المطلوبة
								$qty_delivered = (float) $proCode['appQty'];//الكمية المسلمه
								
								$All_required  += $qty_required;
								
								if($qty_delivered >0 && $qty_delivered == $qty_required){
									$pstatus = 'complete';
								}else if($qty_delivered >0 && $qty_delivered < $qty_required){
									$pstatus = 'partial';
								}else if($qty_delivered ==0){
									$pstatus = 'rejected';
								}
								
								if($qty_delivered<=$qty_required){
									$stOPUpdate[$xloop][$keys] = $this->conn->prepare($sql['setORDER_PRO']);
									$stOPUpdate[$xloop][$keys]->bindValue(':oapp'   , $proCode['appQty'] 		, PDO::PARAM_STR);
									$stOPUpdate[$xloop][$keys]->bindValue(':pstat'  , $pstatus 					, PDO::PARAM_STR);
									$stOPUpdate[$xloop][$keys]->bindValue(':procode', $keys 					, PDO::PARAM_STR);
									$stOPUpdate[$xloop][$keys]->bindValue(':oid'    , $record['ORDER_ID'] 		, PDO::PARAM_INT);
									if($stOPUpdate[$xloop][$keys]->execute()){
										//========update custody product==========
										$stCPUpdate[$xloop][$keys] = $this->conn->prepare($sql['setCUST_PROD']);
										$stCPUpdate[$xloop][$keys]->bindValue(':oapp'    , $proCode['appQty'] 	, PDO::PARAM_STR);
										$stCPUpdate[$xloop][$keys]->bindValue(':pstat'   , $pstatus 			, PDO::PARAM_STR);
										$stCPUpdate[$xloop][$keys]->bindValue(':procode' , $keys   				, PDO::PARAM_STR);
										$stCPUpdate[$xloop][$keys]->bindValue(':oid'     , $record['ORDER_ID'] 	, PDO::PARAM_INT);
										if($stCPUpdate[$xloop][$keys]->execute()){
											$All_delivered += $qty_delivered;
											$prosuccess++;
										}
									}
									
								}
							}
							
							if($prosuccess>0){
								//==================update orders======================
								if($All_delivered > 0 && $All_required === $All_delivered){
									$textRes = "تسليم امر البيع بالكامل";
									$statuse = 3;
								}else if($All_delivered > 0 && $All_delivered < $All_required ){
									$textRes = "تسليم امر البيع بشكل جزئي";
									$statuse = 4;
								}else if($All_delivered == 0){
									$textRes = "رفض امر البيع بالكامل";
									$statuse = 5;
								}
								$exie  = 10;
								$stOUpdate[$xloop] = $this->conn->prepare($sql['setOrderShipp']);
								$stOUpdate[$xloop]->bindValue(':exists'     , $exie 				, PDO::PARAM_INT);
								$stOUpdate[$xloop]->bindValue(':statuse'    , $statuse 				, PDO::PARAM_INT);
								$stOUpdate[$xloop]->bindValue(':oid'        , $record['ORDER_ID']  	, PDO::PARAM_INT);
								if($stOUpdate[$xloop]->execute()){
									//=================insert ORDERS_LOG===================
									$stratlogtext = " تم تحديث بيانات امر البيع من ";
									if(!$stCOUpdate){
										$stratlogtext = " تم تعديل بيانات تسليم امر البيع من ";
									}
									$logtext = $stratlogtext. $this->user_group."*". $textRes ;
									$stOLog[$xloop] = $this->conn->prepare($sql['add_Orders_log']);
									$stOLog[$xloop]->bindValue(':oid'      , $record['ORDER_ID'] , PDO::PARAM_INT);
									$stOLog[$xloop]->bindValue(':uauction' , $this->delivery_id  , PDO::PARAM_INT);
									$stOLog[$xloop]->bindValue(':log'      , $logtext            , PDO::PARAM_STR);
									if($stOLog[$xloop]->execute() && $stOLog[$xloop]->rowCount()){
										//=============insert shipping_movement================
										/*
											0 = Delivery
											1 = Retrieve
											2 = Delivered تم الشحن
											3 = Returning 
											4 = Returned
										*/
										$shtype=2;
										$stShMov[$xloop] = $this->conn->prepare($sql['add_ShipMovement']);
										$stShMov[$xloop]->bindValue(':type'         , $shtype  , PDO::PARAM_INT);
										$stShMov[$xloop]->bindValue(':deliveryid'   , $record['DELIVERY_ID']  , PDO::PARAM_INT);
										$stShMov[$xloop]->bindValue(':userid'       , $this->delivery_id  , PDO::PARAM_INT);
										$stShMov[$xloop]->bindValue(':shipmentdata' , $record['ORDER_ID'] , PDO::PARAM_STR);
										if($stShMov[$xloop]->execute() && $stShMov[$xloop]->rowCount()){
											unset($ordersISNotConditions[$record['ORDER_ID']]);
											$osucceeded[] = $record['ORDER_ID'];
										}else{
											$this->conn->exec("ROLLBACK TO $PointNum");
											$orejected[] = $record['ORDER_ID'];
											$ecode[] = $record['ORDER_ID']."E-IN_ORDER_SHIPP";
										}
									}else{
										$this->conn->exec("ROLLBACK TO $PointNum");
										$orejected[] = $record['ORDER_ID'];
										$ecode[] = $record['ORDER_ID']."E-IN_ORDER_LOG";
									}
								}else{
									$this->conn->exec("ROLLBACK TO $PointNum");
									$orejected[] = $record['ORDER_ID'];
									$ecode[] = $record['ORDER_ID']."E-UP_ORDER_STAT";
								}
							}else{
								$this->conn->exec("ROLLBACK TO $PointNum");
								$orejected[] = $record['ORDER_ID'];
								$ecode[] =  'لم تقم بأى تغير على تسليم امر البيع';
							}
						}else{
							$this->conn->exec("ROLLBACK TO $PointNum");
							$orejected[] = $record['ORDER_ID'];
							$ecode[] = $record['ORDER_ID']."E-Cust_Stat";
						}
		    		}
		    		$xloop++;
			    }
				
	    		if(count($orejected)){
			    	$this->conn->exec("ROLLBACK TO $PointNum");
			    	return array('error',
	                    'خطاء',
	                    'لم يتم الحفظ - : '.implode(" | ",$ecode),
	                    []);
			    }else{
			        if(count($osucceeded)){
				    	$this->conn->commit();
					    if(!count($ordersISNotConditions)){
							return array('success',
							'نجح',
							'تم الحفظ بنجاح',
							$ordersISNotConditions);
						}else{
							return array('success',  
							'نجح',
							'تم الحفظ بنجاح',
							$ordersISNotConditions);
						}
				    }else if(count($ordersISNotConditions)){
				    	return array('error',
		                'نأسف',
		                ' امر البيع لم يستوفى الشروط',
		                $ordersISNotConditions);
				    }
			    }
			    $this->conn = null;
			} catch (PDOException $e) {
	            $this->conn->exec("ROLLBACK TO $PointNum");
	            $this->conn = null;
	            return array('error', $e->getCode().' - '.$e->getMessage());
	        }
	        exit();
    	}
    }
    //تأكد تسليم المندوب واستلام المرتجعات من العهدة المخزنيه
    public function ShipmentDelivered($order_id , $delivery_id){
    	try{
        	$startpoint = 'spSTAR1001';
        	$this->conn->beginTransaction();
        	$this->conn->exec("SAVEPOINT $startpoint");
	    	$response = self::custody_delevary("refund",$order_id, $delivery_id);
	    	if($response){
	    		$this->conn->commit();
				return array('success',
					'ناجح',
					' تم استلام المرتجع من امر البيع بنجاح',
					[]);
	    	}else{
	    		$this->conn->exec("ROLLBACK TO $startpoint");
	    		return array('error',
					'خطاء',
					'خطاء غير معروف',
					[]);
	    	}
    	} catch (PDOException $e) {
            $this->conn->exec("ROLLBACK TO $startpoint");
            $this->conn = null;
            return array('error', $e->getCode().' - '.$e->getMessage());
        }
    }
}
?>