<?php
//=============insert shipping_movement================
/*
	-- 0 = start Shipping                       = SHIPPING
    -- 1 = freeze                               = FREEZE
    -- 2 = Returned From Freeze                 = DEFREEZE
    -- 3 = Delivered                            =
    -- 4 = Partial                              =
    -- 5 = Rejected                             =
    -- 6 = Returned From Rejected or Partial    =
    -- 7 = تم السداد
    -- 8 = REFUND From Sell
    -- 9 = REFUND partial from sell
    -- 10= رفض العميل تسليم المرتجعات
    -- 11= تسويه ماليه للمرتجع
	
	     SALES           → صرف منتجات أمر البيع المباشر
	 *   SHIPPING        → صرف منتجات أمر البيع للمندوب  
	 *   REFUND          → استرداد منتجات أمر البيع من المندوب
	 *   RETURN_SHIPP    → استلام المنتجات المرفوضة من العميل
	 *   RETURN_SALES    → استلام مرتجعات بعد البيع من العميل
	 * 
	 *   TRANSFER        → تحويل مخزنى داخلى
	 *   SETTLEMENT      → تسوية مخزنيه
	 * 
	 *   RECEIVING       → استلام منتجات أمر الشراء من المورد
	 *   PARTIALRECEIPT  → استلام جزئى من طلب شراء من المورد
 	 *   REFUNDPURCHISES → صرف مرتجعات أمر الشراء إلى المورد
*/ 
/*================================================
**  class shipment_BE
**==============================================*/
include_once(__dir__.'/../../../config.php');
include_once(ABSPATH.'/inc/configDateTimeFN.php');
include_once(ABSPATH.'/inc/controller/BackEnd/inventory_BE.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
include_once(ABSPATH.'/inc/Classes/LogManager.php');
include_once(ABSPATH.'/inc/Classes/InventoryManager.php');
include_once(ABSPATH.'/inc/Classes/Accounting/DeliveryAttemptCommissionManager.php');
include_once(ABSPATH.'/inc/Classes/Accounting/ShippingConfigurationManager.php');

class shipment_BE {
	private $orders_log_Tbl = 'ORDERS_LOG';
	private $store_id;
	//==========tbl ORDERS_LOG============
	public $branch_id;
	public $user_action;
	public $user_group;
	//==========tbl ORDERS===============
	public $order_id;
	public $delivery_id;
	public $delivery_name;
	public $shiping_date;
	public $last_update;
	//==========public shipment tbl===========
	public $shipment_id;
	public $user_id;
	public $shipmentobj;
	//==========public variable===========
	public $orderobj;
	public $urlControl;
	public $ShipApproved;
	//==========public EXCHANGE===========
	public $ShipExchange;
	
	private $InventoryManager;
	private $logManager;
	private $dtz;
	private $conn;

	public function __construct($db) {
		$this->conn = $db;
		$this->dtz = new configDateTime();
		$this->logManager = new LogManager(null, 'shipping');
		$this->InventoryManager = new InventoryManager($db);
	}

	//==========وظائف جزئية يعتمد عليها الوظائف العامة========
	//الاستعلامات المستخدمه فى هذا الكائن
	private function query () {
		//$this->order_id     = array_keys($this->orderobj);
		$this->delivery_id = htmlspecialchars(strip_tags(trim($this->delivery_id)));
		$this->shiping_date = htmlspecialchars(strip_tags(date_format(date_create($this->shiping_date), "Y/m/d H:i:s")));
		$this->user_action = htmlspecialchars(strip_tags(trim($this->user_action)));
		$this->user_group = htmlspecialchars(strip_tags(trim($this->user_group)));
		$query = array();
		//$query['resetCustody_PRO'] = 'UPDATE custody_product AS CS LEFT JOIN order_product AS OP ON CS.PRO_CODE = OP.PRO_CODE
		//								SET CS.QTY = CS.QTY + OP.DELIVERED_QTY
		//								WHERE CS.USER_ID = :uid
		//								 AND OP.ORDER_ID = :oid ';
		//$query['resetOrder_PRO'] = "UPDATE order_product AS OP LEFT JOIN orders AS O ON OP.ORDER_ID = O.ORDER_ID
		//							SET  OP.DELIVERED_QTY = 0,
		//							 OP.PRO_STATUSE='waiting'
		//							WHERE OP.ORDER_ID = :oid
		//							 AND O.DELIVERY_ID = :deliveryid";
		$query['setORDER_PRO'] = 'UPDATE `order_product` SET `DELIVERED_QTY`=  :oapp , PRO_STATUSE = :pstat ,LAST_UPDATE = NOW(3) WHERE PRO_CODE = :procode AND ORDER_ID = :oid';
		$query['setORDER_Returned_PRO'] = 'UPDATE `order_product` SET `RETURNED_QTY`=  :oapp, PRO_STATUSE = :pstat ,LAST_UPDATE = NOW(3) WHERE PRO_CODE = :procode AND ORDER_ID = :oid';

		$query['setOrderShipp'] = 'UPDATE `orders`
											SET EXISTS_ID = :exists ,
											ORDER_STATUS  = :statuse ,
											LAST_UPDATE = now(3) ,
											DELIVERED_DATE = NOW(3)
	                                  WHERE ORDER_ID    = :oid ';
		$query['setReturnedOrderShipp'] = 'UPDATE `orders`
											SET EXISTS_ID = :exists ,
											ORDER_STATUS  = :statuse ,
											LAST_UPDATE = now(3)
	                                  	   WHERE ORDER_ID    = :oid ';

		$query['setCUST_PROD'] = 'UPDATE `custody_product` SET `DELIVERED_QTY`=  :oapp , PRO_STATUSE = :pstat , LAST_UPDATE = now() WHERE PRO_CODE = :procode AND ORDER_ID = :oid AND USER_ID = :deliveryid';
		$query['setCUST_RETURN_PROD'] = 'UPDATE `custody_return_product` SET `RETURNED_QTY`=  :oapp , PRO_STATUSE = :pstat , LAST_UPDATE = now() WHERE PRO_CODE = :procode AND ORDER_ID = :oid AND USER_ID = :deliveryid';
		//$query['setCUST_PROD'] = 'UPDATE `custody_product` SET `QTY`=`QTY` - :appQty WHERE PRO_CODE = :procode AND USER_ID = :deliveryid';
		$query['setCUST_STAT'] = 'UPDATE `custody_orders` SET `CUST_STAT`= :custstat , LAST_UPDATE = NOW()  WHERE ORDER_ID = :oid AND USER_ID = :deliveryid ';
		$query['countProduct'] = 'SELECT COUNT(DISTINCT ORDER_ID) AS orderCount, SUM(QTY) AS proCount FROM order_product WHERE FIND_IN_SET(ORDER_ID, :oid)';
		$query['getOProducts'] = "SELECT OP.ORDER_ID,
									OP.PRO_CODE,
									OP.USER_ID,
									PD.PRO_NAME,
									CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
									OP.PRICE,
									OP.DISCOUNT,
									OP.QTY,
									OP.DELIVERED_QTY,
									OP.RETURNED_QTY,
									OD.ORDER_STATUSE,
									OP.PRO_STATUSE,
									'SELSE' AS TYPE
								FROM  custody_product AS OP
									LEFT JOIN custody_orders       OD ON OD.ORDER_ID = OP.ORDER_ID AND OD.USER_ID = OP.USER_ID
									LEFT JOIN product_options_view PD ON OP.PRO_CODE = PD.PRO_CODE
								WHERE OP.ORDER_ID = :oid AND OP.USER_ID = :deliveryid
								UNION
								SELECT OP.ORDER_ID,
									OP.PRO_CODE,
									OP.USER_ID,
									PD.PRO_NAME,
									CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
									OP.PRICE,
									OP.DISCOUNT,
									OP.QTY,
									OP.DELIVERED_QTY,
									OP.RETURNED_QTY,
									OD.ORDER_STATUSE,
									OP.PRO_STATUSE,
									'REFUND' AS TYPE
								FROM  custody_return_product AS OP
									LEFT JOIN custody_return_orders OD ON OD.ORDER_ID = OP.ORDER_ID AND OD.USER_ID = OP.USER_ID
									LEFT JOIN product_options_view  PD ON OP.PRO_CODE = PD.PRO_CODE
								WHERE OD.RETURNS = 0 AND OP.ORDER_ID = (SELECT EXCHANGE FROM  orders WHERE ORDER_ID = :oid) AND OP.USER_ID = :deliveryid";
		$query["getReturnOProducts"] = "SELECT OP.ORDER_ID,
										 OP.PRO_CODE,
										 PD.PRO_NAME,
										 CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
										 OP.PRICE,
										 OP.DISCOUNT,
										 OP.QTY,
										 OP.DELIVERED_QTY,
										 OP.RETURNED_QTY,
										 OD.ORDER_STATUSE,
										 OP.PRO_STATUSE
										FROM  custody_return_product AS OP
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
										 LEFT JOIN custody_return_orders AS OD ON OD.ORDER_ID = OP.ORDER_ID
										WHERE DELIVERED_QTY > 0 
										-- ORDER_STATUS  IN (3,4,6,7) AND OP.PRO_STATUSE IN ('complete','partial', 'refund', 'partial_refund')
										AND OP.ORDER_ID = (SELECT EXCHANGE FROM orders WHERE ORDER_ID = :oid) ";

		$query['getEmployStoreId'] = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';

		$query['getDataOrders'] = "SELECT * FROM (
								   	SELECT
								        A.ORDER_ID,
								        A.DATE_CREATED,
								        A.ORDER_BY,
								        CASE WHEN CO.CUST_STAT = 'FREEZE' THEN CO.USER_ID ELSE A.DELIVERY_ID END AS DELIVERY_ID,
								        CO.CUST_STAT,
								        CONCAT(Z.FORENAME, ' ', Z.LASTNAME) AS DELIVERY_NAME,
								        A.SUB_ORDER_ID,
								        A.EXISTS_ID,
								        A.ORDER_STATUS,
								        A.ORDER_SUCCESS,
								        A.LAST_UPDATE,
								        A.PRINT,
								        A.STO_ID,
								        A.ADDRESS,
								        A.EXCHANGE,
								        EXS.EXCHANGE_STATUSE,
								        XZ.CLIENT_ID,
								        CASE WHEN CO.CUST_STAT = 'FREEZE' THEN 1 ELSE 0 END AS FREEZE,
								        CASE WHEN Q.OS_STATUSE = 0 OR Q.MSG_COUNT > 0 THEN 1 ELSE 0 END AS OSMSGCS,
								        JSON_OBJECTAGG(CAST(B.PRO_CODE AS CHAR), CONCAT(B.QTY, ',', CAST(B.PRICE AS DOUBLE(18,2)), ',', CAST(B.DISCOUNT AS DOUBLE(18,2)))) AS ORDER_PRODUCT
								    FROM orders AS A
								    LEFT JOIN custody_orders CO ON A.ORDER_ID = CO.ORDER_ID AND USER_ID = :deliveryid
								    LEFT JOIN order_product B ON A.ORDER_ID = B.ORDER_ID
								    LEFT JOIN (
								        SELECT
								            A.ORDER_ID,
								            CASE
								                WHEN MAX(C.OS_ID) IS NOT NULL THEN MAX(C.OS_ID)
								                WHEN MAX(C.OS_ID) IS NULL AND MAX(B.OS_ID) IS NOT NULL THEN MAX(B.OS_ID)
								                ELSE 0
								            END AS OS_ID,
								            CASE
								                WHEN MAX(C.OS_ID) IS NOT NULL THEN C.OS_STA
								                WHEN MAX(C.OS_ID) IS NULL AND MAX(B.OS_ID) IS NOT NULL THEN B.OS_STA
								                ELSE 1
								            END AS OS_STATUSE,
								            COUNT(CASE WHEN B.OS_STA = 1 THEN D.OS_ID END) AS OS_END_COUNT,
								            COUNT(E.OSTX_ID) AS MSG_COUNT
								        FROM
								            ORDERS A
								        LEFT JOIN (
								            SELECT
								                ORDER_ID,
								                OS_STATUSE AS OS_STA,
								                MAX(OS_ID) AS OS_ID
								            FROM
								                order_service
								            WHERE
								                OS_STATUSE = 1
								            GROUP BY
								                ORDER_ID
								        ) B ON A.ORDER_ID = B.ORDER_ID
								        LEFT JOIN (
								            SELECT
								                ORDER_ID,
								                OS_STATUSE AS OS_STA,
								                MAX(OS_ID) AS OS_ID
								            FROM
								                order_service
								            WHERE
								                OS_STATUSE = 0
								            GROUP BY
								                ORDER_ID
								        ) C ON A.ORDER_ID = C.ORDER_ID
								        LEFT JOIN
								            order_service AS D ON A.ORDER_ID = D.ORDER_ID
								        LEFT JOIN
								            order_service_text E ON D.OS_ID = E.OS_ID AND E.OSTX_READ = 0 AND E.USER_TYPE <> 1
								        GROUP BY
								            A.ORDER_ID
								    ) AS Q ON A.ORDER_ID = Q.ORDER_ID
								    LEFT JOIN users AS Z ON CO.USER_ID = Z.USER_ID
								    LEFT JOIN telephones XZ ON XZ.TEL_ID = A.TEL_ID
								    LEFT JOIN (SELECT
								    			ORDER_ID AS EXCHANGE,
												case
												 when sum(qty) = sum(DELIVERED_QTY) then 3
												 when sum(DELIVERED_QTY) >0 and sum(qty) > sum(DELIVERED_QTY) then 4
												 when sum(DELIVERED_QTY) =0 then 5
												 else null
												end as EXCHANGE_STATUSE
												from order_product
												where ORDER_ID =403
												group by ORDER_ID
									) EXS ON A.EXCHANGE = EXS.EXCHANGE
								    GROUP BY
								        A.ORDER_ID,
								        A.DATE_CREATED,
								        A.ORDER_BY,
								        A.SUB_ORDER_ID,
								        A.EXISTS_ID,
								        A.ORDER_STATUS,
								        A.ORDER_SUCCESS,
								        A.LAST_UPDATE,
								        A.PRINT,
								        A.STO_ID,
								        CO.CUST_STAT
								) AS TBL
									WHERE FIND_IN_SET(CAST(ORDER_ID AS CHAR), :oids)
									ORDER BY DATE_CREATED DESC";
		$query['getDataOrders_old'] = "SELECT  A.ORDER_ID,
									  A.ORDER_BY,
									  CO.USER_ID AS DELIVERY_ID,
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
									FROM  custody_orders CO
									  LEFT JOIN orders AS A ON A.ORDER_ID = CO.ORDER_ID
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
				                      LEFT JOIN users AS Z ON CO.USER_ID = Z.USER_ID
				                     WHERE CO.USER_ID = :deliveryid AND FIND_IN_SET (CO.ORDER_ID ,:oids)
									GROUP BY A.ORDER_ID,
									  A.ORDER_BY,
									  A.SUB_ORDER_ID,
									  A.EXISTS_ID,
									  A.ORDER_STATUS,
									  A.ORDER_SUCCESS,
									  A.LAST_UPDATE,
									  A.PRINT,
									  A.STO_ID,
									  CO.CUST_STAT
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

		$query['setDataOrders'] = 'UPDATE `orders` SET EXISTS_ID  = :exists , SHIPPING_DATE  = :shipdate , DELIVERY_ID = :deliveryid , LAST_UPDATE = now(3), SHIPPING_DATE = now(3)
	                                  WHERE ORDER_ID    = :oid ';
		$query['add_Orders_log'] = "INSERT INTO `".$this->orders_log_Tbl."` (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) VALUES (:oid , :uauction, :log) ";
		$query['setLastUpdate'] = "UPDATE orders SET LAST_UPDATE = now(6) WHERE ORDER_ID IN (:oid)";
		/* $query['setStoreProductM'] = 'UPDATE STORES_PRODUCT AS SP LEFT JOIN order_product AS OP ON SP.PRO_CODE = OP.PRO_CODE
										SET  SP.STO_QTY = SP.STO_QTY - OP.QTY
									  WHERE SP.STO_ID = :stoid AND OP.ORDER_ID = :oid';
		$query['setStoreProductP'] = 'UPDATE STORES_PRODUCT AS SP LEFT JOIN order_product AS OP ON SP.PRO_CODE = OP.PRO_CODE
										SET  SP.STO_QTY = SP.STO_QTY + OP.QTY
										WHERE SP.STO_ID = :stoid AND OP.ORDER_ID = :oid';
	*/ $query['setCustodyUnCode'] = 'INSERT INTO CUSTODY  SELECT OP.PRO_CODE AS OPC, :uidv AS USER_ID, 0 AS QTY
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
     */ return $query;

	}
	//جلب بيانات مخزن الصرف
	public function get_inventory_id($branch_id) {
		$query = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';
		$stStoreid = $this->conn->prepare($query);
		$stStoreid->execute([':branchid' => $branch_id]);
		$result = $stStoreid->fetch(PDO::FETCH_ASSOC);
		return $result['STO_ID'];

	}
	// حالات استفاء الشروط لتنفيذ اى اجراء على امر البيع
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
	private function check_order(int $orderid, int $deliveryid, array $keys_to_keep) {
	    try {
	    	
	        if(empty($orderid) || empty($deliveryid)) {
	            throw new Exception('check_order بيانات غير مكتملة');
	        }
	
	        $sql = "SELECT 
	            A.ORDER_ID, A.SUB_ORDER_ID, A.ORDER_STATUS, A.ORDER_SUCCESS, A.LAST_UPDATE, A.PRINT, A.STO_ID, CO.CUST_STAT, 
	            CASE WHEN CO.CUST_STAT = 'FREEZE' THEN 1 ELSE 0 END AS FREEZE,
	            CASE WHEN Q.OS_STATUSE = 0 OR Q.MSG_COUNT > 0 THEN 1 ELSE 0 END AS OSMSGCS,
	            CASE WHEN EXISTS (
	                SELECT 1 
					FROM order_product OP 
					LEFT JOIN stores_product SP ON SP.PRO_CODE = OP.PRO_CODE AND SP.STO_ID = ?
					WHERE OP.ORDER_ID = A.ORDER_ID
					AND (COALESCE(SP.STO_QTY, 0) - OP.QTY) < 0
					LIMIT 1
				) THEN 0 ELSE 1 END AS STORE_VALID
	        FROM orders A 
	        LEFT JOIN custody_orders CO ON A.ORDER_ID = CO.ORDER_ID AND CO.USER_ID = ?
	        LEFT JOIN (
	            SELECT 
	                A.ORDER_ID,
	                CASE 
	                    WHEN MAX(C.OS_ID) IS NOT NULL THEN MAX(C.OS_ID)
	                    WHEN MAX(C.OS_ID) IS NULL AND MAX(B.OS_ID) IS NOT NULL THEN MAX(B.OS_ID)
	                    ELSE 0 
	                END AS OS_ID,
	                CASE 
	                    WHEN MAX(C.OS_ID) IS NOT NULL THEN C.OS_STA
	                    WHEN MAX(C.OS_ID) IS NULL AND MAX(B.OS_ID) IS NOT NULL THEN B.OS_STA
	                    ELSE 1 
	                END AS OS_STATUSE,
	                COUNT(E.OSTX_ID) AS MSG_COUNT
	            FROM ORDERS A 
	            LEFT JOIN (
	                SELECT ORDER_ID, OS_STATUSE AS OS_STA, MAX(OS_ID) AS OS_ID 
	                FROM order_service 
	                WHERE OS_STATUSE = 1 
	                GROUP BY ORDER_ID
	            ) B ON A.ORDER_ID = B.ORDER_ID 
	            LEFT JOIN (
	                SELECT ORDER_ID, OS_STATUSE AS OS_STA, MAX(OS_ID) AS OS_ID 
	                FROM order_service 
	                WHERE OS_STATUSE = 0 
	                GROUP BY ORDER_ID
	            ) C ON A.ORDER_ID = C.ORDER_ID 
	            LEFT JOIN order_service D ON A.ORDER_ID = D.ORDER_ID 
	            LEFT JOIN order_service_text E ON D.OS_ID = E.OS_ID 
	                AND E.OSTX_READ = 0 AND E.USER_TYPE <> 1
	            GROUP BY A.ORDER_ID
	        ) Q ON A.ORDER_ID = Q.ORDER_ID 
	        WHERE A.ORDER_ID = ?";
	
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute([$this->store_id, $deliveryid, $orderid]);
	        
	        if ($stmt->rowCount() != 1) {
	            throw new Exception('لا توجد بيانات للطلب: ' . $orderid);
	        }
	
	        $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
	        
	        // التصحيح النهائي للمتغير
	        $x = [];
	        if ($orderRow) {
	            $x[$orderRow['ORDER_ID']] = [
	                'osubid'   => $orderRow['SUB_ORDER_ID'],
	                'print'    => (bool)$orderRow['PRINT'],
	                'statuse'  => $orderRow['ORDER_STATUS'] == 2,
	                'oserv'    => !($orderRow['OSMSGCS'] > 0),
	                'lastup'   => $orderRow['LAST_UPDATE'] == ($this->orderobj[$orderRow['ORDER_ID']]['olup'] ?? null),
	                'store'    => (bool)$orderRow['STORE_VALID'],
	                'freeze'   => !$orderRow['FREEZE'],
	                'shipment' => true
	            ];
	        }
	
	        return self::filter_array_keys($x, $keys_to_keep);
	        
	    } catch (Exception $e) {
	        throw new Exception('فشل في التحقق من الطلب: ' . $e->getMessage());
	    }
	}	
	private function check_stat_order($orderRow, $keys_to_keep) {
			
		$x = [];
		if ($orderRow) {
			$x[$orderRow['ORDER_ID']] = [
				'osubid' => $orderRow['SUB_ORDER_ID'],
				'print' => true,
				'statuse' => true,
				'oserv' => true,
				'lastup' => true,
				'store' => true,
				'freeze' => true,
				'shipment' => true
			];

			//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
			if ($orderRow['LAST_UPDATE'] != $this->orderobj[$orderRow['ORDER_ID']]['olup']) {
				$x[$orderRow['ORDER_ID']]['lastup'] = false;
			}
			//لم يتم طباعة امر البيع
			if (!$orderRow['PRINT']) {
				$x[$orderRow['ORDER_ID']]['print'] = false;
			}
			// يوجد طلبات لخدمة العملاء مفتوحه
			if (isset($orderRow['OSMSGCS']) && $orderRow['OSMSGCS'] > 0) {
				$x[$orderRow['ORDER_ID']]['oserv'] = false;
			}
			//غير مسموح بالتحويل من الحاله الحالية الى الحالة المراد التحويل اليها
			if ($orderRow['ORDER_STATUS'] != 2) {
				$x[$orderRow['ORDER_ID']]['statuse'] = false;
			}
			//امر البيع مجمد
			//	$query = "SELECT CUST_STAT FROM custody_orders WHERE CUST_STAT = 'FREEZE' AND ORDER_ID = :oid AND USER_ID = :delid ";
			//	$stCustFZ[$orderRow['ORDER_ID']] = $this->conn->prepare($query);
			//	$stCustFZ[$orderRow['ORDER_ID']]->bindValue(':oid'   , $orderRow['ORDER_ID'], PDO::PARAM_INT);
			//	$stCustFZ[$orderRow['ORDER_ID']]->bindValue(':delid' , $this->delivery_id  	, PDO::PARAM_INT);
			//	if($stCustFZ[$orderRow['ORDER_ID']]->execute() && $stCustFZ[$orderRow['ORDER_ID']]->rowCount()>0){
			//		$rec = $stCustFZ[$orderRow['ORDER_ID']]->fetchAll(PDO::FETCH_ASSOC);

			if (isset($orderRow['FREEZE']) && $orderRow['FREEZE']) {
				$x[$orderRow['ORDER_ID']]['freeze'] = false;
			}
			//=========التأكد من وجود رصيد لمتجات امر البيع بالمخزن============
			$query = 'SELECT OP.ORDER_ID, OP.PRO_CODE, SP.STO_QTY, (SP.STO_QTY - SUM(OP.QTY)) AS NEWQTY
						FROM  order_product AS OP 
						LEFT JOIN stores_product AS SP 
							ON SP.PRO_CODE = OP.PRO_CODE 
							AND SP.STO_ID = :stoid
						GROUP BY OP.ORDER_ID,OP.PRO_CODE
						HAVING NEWQTY < 0 AND OP.ORDER_ID = :oid';
			$stcheckOProSTO[$orderRow['ORDER_ID']] = $this->conn->prepare($query);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->bindValue(':stoid', $this->store_id, PDO::PARAM_INT);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->bindValue(':oid', $orderRow['ORDER_ID'], PDO::PARAM_INT);
			$stcheckOProSTO[$orderRow['ORDER_ID']]->execute();
			//===============التأكد من عدم توافر المخزون===================
			if ($stcheckOProSTO[$orderRow['ORDER_ID']]->rowCount() > 0) {
				$x[$orderRow['ORDER_ID']]['store'] = false;
			}
		} else {
			$x[$orderRow['ORDER_ID']] = [
				'osubid' => $orderRow['SUB_ORDER_ID'],
				'print' => false,
				'statuse' => false,
				'oserv' => false,
				'lastup' => false,
				'store' => false,
				'freeze' => false,
			];
		}

		return self::filter_array_keys($x, $keys_to_keep);

	}
	//اضافة العهدة على المندوب
	private function insert_custody(&$orderid, &$deliveryid) {

		$stinsert_custody = array(); 
		$stinsert_custpro = array();
		$query = "SELECT EXCHANGE,ADDRESS,ORDER_STATUS FROM orders
					WHERE ORDER_ID = :oid ;";

		$stCustEX[$orderid] = $this->conn->prepare($query);
		$stCustEX[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		if (!$stCustEX[$orderid]->execute() || $stCustEX[$orderid]->rowCount() <= 0) {
			$this->logManager->error("غير قادر على قرأة امر الاستبدال");
			return false;
		}

		$query = "SELECT CUST_STAT FROM custody_orders
					WHERE CUST_STAT = 'FREEZE'
						AND ORDER_ID = :oid
						AND USER_ID = :delid ;";

		$stCustCH[$orderid] = $this->conn->prepare($query);
		$stCustCH[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		$stCustCH[$orderid]->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
		if (!$stCustCH[$orderid]->execute() || $stCustCH[$orderid]->rowCount() > 0) {
			$this->logManager->error("لا يمكن اضافة امر البيع لهذا المندوب حث انه تم تجميد امر البع فى عهدته مسبقا");
			return false;
		}

		$query = "SELECT CUST_STAT FROM custody_orders WHERE CUST_STAT <> 'FREEZE' AND ORDER_ID = :oid ;";
		$stCustCH2[$orderid] = $this->conn->prepare($query);
		$stCustCH2[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		if (!$stCustCH2[$orderid]->execute() || $stCustCH2[$orderid]->rowCount() > 0) {
			$this->logManager->error("امر البيع مسجل فى عهدة المندوب او مندوب اخر");
			return false;
		}

		//===========insert custody order=================
		$query = 'INSERT custody_orders (ORDER_ID,USER_ID,ORDER_STATUSE,SHIPPING_ADDRESS)
					SELECT 
					  ORDER_ID,
					  DELIVERY_ID AS USER_ID,
					  ORDER_STATUS AS ORDER_STATUSE,
					  ADDRESS AS SHIPPING_ADDRESS
					FROM orders WHERE ORDER_ID = :oid AND DELIVERY_ID = :uid;';
		$stinsert_custody[$orderid] = $this->conn->prepare($query);
		$stinsert_custody[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		$stinsert_custody[$orderid]->bindValue(':uid', $deliveryid, PDO::PARAM_INT);
		if (!$stinsert_custody[$orderid]->execute() || $stinsert_custody[$orderid]->rowCount() <= 0) {
			$this->logManager->error("خطاء فى تسجيل امر البيع على عهدة المندوب");
			return false;
		}
	
		//===========insert custody pro==================
		$query = 'INSERT INTO custody_product
							SELECT OP.ORDER_ID,
								CO.USER_ID,
								OP.PRO_CODE,
								OP.QTY,
								OP.PRICE,
								OP.DISCOUNT,
								OP.DELIVERED_QTY,
								OP.RETURNED_QTY,
								OP.PRO_STATUSE,
								NOW() AS LAST_UPDATE
							FROM  order_product AS OP
							 LEFT JOIN custody_orders AS CO ON OP.ORDER_ID = CO.ORDER_ID
							WHERE CO.ORDER_ID  = :oid
							 AND CO.USER_ID = :uid ;';

		$stinsert_custpro[$orderid] = $this->conn->prepare($query);
		$stinsert_custpro[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		$stinsert_custpro[$orderid]->bindValue(':uid', $deliveryid, PDO::PARAM_INT);
		if (!$stinsert_custpro[$orderid]->execute()) {
			$this->logManager->error("خطاء فى تسجيل منتجات امر البيع على عهدة المندوب");
			return false;
		}
		//===========insert custody return order=================
		$EXCHANGE = $stCustEX[$orderid]->fetchAll(PDO::FETCH_ASSOC);
		if ($EXCHANGE[0]['EXCHANGE'] > 0) {
			$exoid = $EXCHANGE[0]['EXCHANGE'];
			$exstatus = $EXCHANGE[0]['ORDER_STATUS'];
			$exaddress =  $EXCHANGE[0]['ADDRESS'];
			$query = 'INSERT custody_return_orders (ORDER_ID,USER_ID,ORDER_STATUSE,SHIPPING_ADDRESS)VALUE(:oid,:delid,:osta,:shipAdd);';
			$stinsert_return_custody[$orderid] = $this->conn->prepare($query);
			$stinsert_return_custody[$orderid]->bindValue(':oid', $exoid, PDO::PARAM_INT);
			$stinsert_return_custody[$orderid]->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
			$stinsert_return_custody[$orderid]->bindValue(':osta', $exstatus, PDO::PARAM_INT);
			$stinsert_return_custody[$orderid]->bindValue(':shipAdd', $exaddress, PDO::PARAM_STR);
			if (!$stinsert_return_custody[$orderid]->execute() || $stinsert_custody[$orderid]->rowCount() <= 0) {
				$this->logManager->error("خطاء فى اضافة امر الاستبدال على عهدة المندوب");
				return false;
			}
			//===========insert custody pro==================
			$query = 'INSERT INTO custody_return_product
								SELECT OP.ORDER_ID,
									CO.USER_ID,
									OP.PRO_CODE,
									OP.QTY,
									OP.PRICE,
									OP.DISCOUNT,
									OP.DELIVERED_QTY,
									OP.RETURNED_QTY,
									OP.PRO_STATUSE,
									NOW() AS LAST_UPDATE
								FROM  order_product AS OP
								 LEFT JOIN custody_return_orders AS CO ON OP.ORDER_ID = CO.ORDER_ID
								WHERE CO.ORDER_ID  = :oid
								 AND CO.USER_ID = :uid
								 AND OP.DELIVERED_QTY > 0;';

			$stinsert_return_custpro[$orderid] = $this->conn->prepare($query);
			$stinsert_return_custpro[$orderid]->bindValue(':oid', $exoid, PDO::PARAM_INT);
			$stinsert_return_custpro[$orderid]->bindValue(':uid', $deliveryid, PDO::PARAM_INT);
			if (!$stinsert_return_custpro[$orderid]->execute() || $stinsert_return_custpro[$orderid]->rowCount() <= 0) {
				$this->logManager->error("خطاء فى اضافة منتجات امر الاستبدال على عهدة المندوب");
				return false;
			}

		}
		$this->logManager->error("تم تسجيل العهدا على المندوب بنجاح");
		
		return true;
	}
	//خذف العهدة من حساب المندوب
	private function delete_custody(&$orderid, &$deliveryid) {
		$response = false;
		$query = "DELETE FROM custody_orders WHERE ORDER_ID = :oid AND USER_ID = :delid ";
		$stCust0[$orderid] = $this->conn->prepare($query);
		$stCust0[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		$stCust0[$orderid]->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
		if ($stCust0[$orderid]->execute() && $stCust0[$orderid]->rowCount()) {
			$response = true;
		}
		return $response;
	}
	//خذف العهدة المرتجعه من حساب المندوب
	private function delete_custody_return(&$orderid, &$deliveryid) {
		$query = "DELETE FROM custody_return_orders WHERE ORDER_ID = :oid AND USER_ID = :delid ";
		$stCust0[$orderid] = $this->conn->prepare($query);
		$stCust0[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
		$stCust0[$orderid]->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
		if (!$stCust0[$orderid]->execute() || $stCust0[$orderid]->rowCount() <= 0) {
			return false;
		}
		return true;
	}
	//تحديث بيانات عهدة المدنوب لامر البيع الواحد
	private function update_custody(int &$orderid, int &$deliveryid, string $custState = null, int $orderStatr = null, bool $custReturn = false) {
		if (!$custState || !$orderid || !$deliveryid || empty($custState) || empty($orderid)) {
			throw new ErrorException('update_custody خطاء غير قادر على تنفيذ هذا الاجراء (error @param)');
			return false;
		}
		$tbl = $custReturn ? "custody_return_orders" : "custody_orders";
		if ($custState === 'FREEZE' && !$custReturn) {
			$set = "CUST_STAT =  '".$custState."'";
			$wh = " CUST_STAT = 'WAITING' AND";
		} else if ($custState === 'DELIVERED' && !$custReturn) {
			$set = "CUST_STAT = '".$custState."' , ORDER_STATUSE = ".$orderStatr;
			$wh = " CUST_STAT <> 'FREEZE' AND";
		} else if ($custState === 'REFUND' && !$custReturn) {
			$set = "RETURNS = 1";
			$wh = "CUST_STAT IN ('DELIVERED','FREEZE') AND";
		} else if ($custState === 'RETURNED' && $custReturn) {
			$set = "CUST_STAT = '".$custState."' , ORDER_STATUSE = ".$orderStatr;
			$wh = "";
		} else {
			throw new ErrorException('update_custody خطاء غير قادر على تنفيذ هذا الاجراء ('.$custState.' | '. $custReturn.')');
			return false;
		}
		if (!empty($custState) && $set && $wh) {
			$query = "UPDATE $tbl SET ".$set.", LAST_UPDATE = NOW(3) WHERE ".$wh." ORDER_ID = :oid AND USER_ID = :delid";
		
			$stCust0[$orderid] = $this->conn->prepare($query);
			//$stCust0[$orderid]->bindValue(':cust', $custState, PDO::PARAM_STR);
			$stCust0[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
			$stCust0[$orderid]->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
			if (!$stCust0[$orderid]->execute() || $stCust0[$orderid]->rowCount() <= 0) {
				throw new ErrorException(' خطاء غير قادر على تنفيذ هذا الاجراء (update_custody->rowCount : '.$stCust0[$orderid]->rowCount().' )');
				return false;
			}
		}

		return true;
	}
	//اعادة تهيئة امر البيع لما قبل التسليم
	private function reset_Delivered_Order($orderid, $deliveryid, $custStat = '', $restType = "all") {
		$data_return = false;
		$custStatPro = "CP.PRO_STATUSE='waiting'";
		if ($custStat == 'FREEZE') {
			$custStatPro = "CP.PRO_STATUSE='rejected'";
		}
		$query1 = "UPDATE custody_product AS CP LEFT JOIN custody_orders AS CO ON CP.ORDER_ID = CO.ORDER_ID AND CP.USER_ID = CO.USER_ID
									SET  CP.DELIVERED_QTY = 0,
									 ".$custStatPro."
									WHERE CP.ORDER_ID  = :oid
									 AND CO.USER_ID = :deliveryid ";

		$query2 = "UPDATE order_product AS OP LEFT JOIN orders AS O ON OP.ORDER_ID = O.ORDER_ID
									SET  OP.DELIVERED_QTY = 0,
									 OP.PRO_STATUSE ='waiting',
									 O.ORDER_STATUS = 1
									WHERE OP.ORDER_ID  = :oid
									 AND O.DELIVERY_ID = :deliveryid";
		if ($restType === "cp" || $restType === "all") {
			$stRE1Update[$orderid] = $this->conn->prepare($query1);
			$stRE1Update[$orderid]->bindValue(':deliveryid', $deliveryid, PDO::PARAM_INT);
			$stRE1Update[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
			//$stRE1Update[$orderid]->execute();
		}
		if ($restType === "op" || $restType === "all") {
			$stRE2Update[$orderid] = $this->conn->prepare($query2);
			$stRE2Update[$orderid]->bindValue(':deliveryid', $deliveryid, PDO::PARAM_INT);
			$stRE2Update[$orderid]->bindValue(':oid', $orderid, PDO::PARAM_INT);
			//$stRE2Update[$orderid]->execute();
		}
		if ($restType === "cp") {
			if ($stRE1Update[$orderid]->execute() && $stRE1Update[$orderid]->rowCount()) {
				$data_return = true;
			}
		} else if ($restType === "op") {
			if ($stRE2Update[$orderid]->execute() && $stRE2Update[$orderid]->rowCount()) {
				$data_return = true;
			}
		} else if ($restType === "all") {
			if ($stRE1Update[$orderid]->execute() &&
				$stRE2Update[$orderid]->execute()

			) {
				$data_return = true;
			}
		}

		return $data_return;
	}
	//اضافة منتجات امر البيع الواحد عهدة على المندوب
	private function custody_delevary($type, $orderid, $deliveryid, $ostat = null, $address = null) {
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
		if ($type && $orderid && $deliveryid) {
			//===========insert custody order==================
			switch (strtolower($type)) {
				case 'get':
					// code...
					break;
				
				case 'add': //===========insert custody order==================
					if ($address) {
						$statuse = self::insert_custody($orderid, $deliveryid, $ostat, $address);
						$invmovType = 'shipping'; // صرف كميات امر البيع من المخزن
					}
					break;
				case 'del': //===========DELETE custody order==================
					$type = 'IN';
					$statuse1 = self::exchange_Add_inventory($orderid, $deliveryid, $type);
					if ($statuse1) {
						$statuse = self::delete_custody($orderid, $deliveryid);
					}
					break;
				case 'refund': //===========استلام مرتجعات امر البيع==================
					$statuse = self::update_custody($orderid, $deliveryid, "REFUND");
					
					$invmovType = 'refund'; // استرداد منتجات امر البيع من المندوب واضافتها بالمخزن
					break;
				case 'freeze': //=========== حالة تجميد امر البيع==================
					$statuse1 = self::update_custody($orderid, $deliveryid, "FREEZE");
					if ($statuse1) {
						$statuse = self::reset_Delivered_Order($orderid, $deliveryid, "FREEZE", "cp");
					} else {
						$statuse = $statuse1;
					}
					break;
				case 'delivered': //=========== حالة تسليم امر البيع==================
					if ($ostat >= 3 && $ostat <= 5) {
						$statuse = self::update_custody($orderid, $deliveryid, "DELIVERED", $ostat);
					}
					break;
			}
		}

		if ($statuse && $invmovType != null) {
			$statuse = self::exchange_Add_inventory($orderid, $deliveryid, $invmovType);
		}

		return $statuse;
	}
	//تأكد تسليم المندوب واستلام المرفوضات من العهدة المخزنيه
	private function ShipmentRefundFN($checklastudate = false, $actionType = 'ORDERQTY') {
		//==========التأكد من ان المصفوفة تحتوى على امر بيع واحد

		if (count(array_keys($this->orderobj)) !== 1) return array('error', 'خطاء', 'لا يمكن تنفيذ هذا الاجراء لعدة اوامر - مسموح بتنفيذه لامر واحد فقط', []);

		$query = self::query();
		$queryLU = "UPDATE orders SET LAST_UPDATE = now(6) WHERE ORDER_ID IN (:oid)";

		$this->order_id = implode(", ", array_keys($this->orderobj));
		$sr_query = 'SELECT
	    						 CO.CUST_STAT,
	    						 OS.STA_NAME,
	    						 CO.ORDER_STATUSE AS COORDER_STATUSE,
	    						 CASE WHEN CO.CUST_STAT = "FREEZE" THEN "ايقاف التسليم" ELSE OS.STA_NAME END AS OSTATUSE_NAME,
	    						 U.FORENAME,
	    						 U.LASTNAME,
	    						 O.*
	    						FROM orders AS O
	    						 LEFT JOIN custody_orders AS CO ON O.ORDER_ID = CO.ORDER_ID
	    						 LEFT JOIN users  AS U ON CO.USER_ID = U.USER_ID
	    						 LEFT JOIN order_statuse AS OS ON CO.ORDER_STATUSE = OS.STA_ID
	    						WHERE CO.ORDER_ID = :oid AND CO.USER_ID = :did';

		$this->order_id = htmlspecialchars(strip_tags(trim($this->order_id)));
		$this->delivery_id = htmlspecialchars(strip_tags(trim($this->delivery_id)));
		$stShipRef[$this->order_id] = $this->conn->prepare($sr_query);
		$stShipRef[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		$stShipRef[$this->order_id]->bindValue(':did', $this->delivery_id, PDO::PARAM_INT);
		$stShipRef[$this->order_id]->execute();
		$dataCheck = $stShipRef[$this->order_id]->fetchAll(PDO::FETCH_ASSOC);

		$filArr = array('osubid', 'lastup');
		$get_stat_order = $this->check_stat_order($dataCheck[0], ['osubid', 'lastup']);
		$ordersISNotConditions[$this->order_id] = $get_stat_order[$this->order_id];
		
		//==========التأكد ان امر البيع يستوفى جميع الشروط
		if ($checklastudate && !$ordersISNotConditions[$this->order_id]['lastup']) return array('error', 'خطاء', 'لم يستوفى امر البيع الشروط التاليه', $ordersISNotConditions);

		$custStat = $dataCheck[0]['CUST_STAT'];
		$orderStat = $dataCheck[0]['COORDER_STATUSE'];
		$orderStatName = $dataCheck[0]['OSTATUSE_NAME'];
		$userDeliveryName = $dataCheck[0]['FORENAME'] . ' ' .$dataCheck[0]['LASTNAME'];
		// تحديث عهدة المندوب
		
/*		$set = "RETURNS = 1";
		$wh = "CUST_STAT IN ('DELIVERED','FREEZE') AND";
		$query = "UPDATE custody_orders 
				  SET 
				    RETURNS = 1, 
					LAST_UPDATE = NOW(3) 
				  WHERE CUST_STAT IN ('DELIVERED','FREEZE') AND ORDER_ID = :oid AND USER_ID = :delid";
*/		
		
		$stUpCust = self::update_custody($this->order_id, $this->delivery_id, "REFUND"); // صرف او اضافة المنتجات فى المخزن
		
		$proOrderQuery = "SELECT
							 	OP.PRO_CODE,
							 	SUM(QTY) as ORDERQTY,
								SUM(CASE WHEN PRO_STATUSE IN ('rejected','partial') THEN (QTY - DELIVERED_QTY) ELSE 0 END) as REJECTED,
								SUM(RETURNED_QTY) AS REFUND
							  FROM  order_product AS OP
							  WHERE OP.ORDER_ID = :oid
							  GROUP BY OP.PRO_CODE";

		$stGetOrderPro[$this->order_id] = $this->conn->prepare($proOrderQuery);
		$stGetOrderPro[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		if ($stGetOrderPro[$this->order_id]->execute() && $stGetOrderPro[$this->order_id]->rowCount()) {
			
			//التعامل مع امر البيع الاصلى
			$stAddInve = false;
			$inventory = new inventory_BE($this->conn);
			$productArray = $stGetOrderPro[$this->order_id]->fetchAll(PDO::FETCH_ASSOC);
			
			$newArray = [];
			//echo $actionType;
			foreach ($productArray as &$row) {
				if ($row[$actionType] > 0) {
					$row['QTY'] = $row[$actionType];
					unset($row['ORDERQTY'], $row['REJECTED'], $row['REFUND']); // اختياري: تمسح الباقي
					$newArray[] = $row;
				}
			}
			unset($row);
			
			$productArray = $newArray;
			
			$stAddInve = $inventory->inventoryMovement ($productArray, $this->order_id, $this->delivery_id, 'refund');

		}
		
		if ($custStat === "FREEZE") $shtype = 2;

		//=============insert shipping_movement================
		/*
			0 = start Shipping
			1 = freeze
			2 = Returned From Freeze
			3 = Delivered
			4 = Partial
			5 = Rejected ,
			6 = Returned From Rejected or Partial
			7 = تم السداد
			8 = refund from client
			9 = partial refound from client
		*/
		$shtype = 6;
		$stShMov = $this->conn->prepare($query['add_ShipMovement']);
		$stShMov->bindValue(':type', $shtype, PDO::PARAM_INT);
		$stShMov->bindValue(':deliveryid', $this->delivery_id, PDO::PARAM_INT);
		$stShMov->bindValue(':userid', $this->user_action, PDO::PARAM_INT);
		$stShMov->bindValue(':shipmentdata', $this->order_id, PDO::PARAM_STR);

		$ShResponse = true;
		$response = true;

		// تنفيذ insert shipping_movement
		if (!$stShMov->execute() || $stShMov->rowCount() <= 0) $ShResponse = false;

		// فى حال امر البيع مجمد او مرفوض يتم حذف عهده المندوب
		if ($custStat === "FREEZE" || ($custStat === "DELIVERED" && $orderStat == 5)) $response = self::delete_custody($this->order_id, $this->delivery_id);

		// التأكد من تنفيذ كل الاجرأت السابقه بنجاح
		if (!$stUpCust || !$stAddInve || !$response || !$ShResponse) return array('error', 'خطاء', 'خطاء غير معروف', [2]);

		// اضافة الحركه فى سجل حركات المستخدمين بأمر الليع
		$sql = $this->query();
		$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group. "*تم استلام مرتجعات امر البيع من عهدة المندوب ".$userDeliveryName." - حالة امر البيع ".$orderStatName;
		$stOLog[$this->order_id] = $this->conn->prepare($sql['add_Orders_log']);
		$stOLog[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		$stOLog[$this->order_id]->bindValue(':uauction', $_SESSION['USER_ID'], PDO::PARAM_INT);
		$stOLog[$this->order_id]->bindValue(':log', $logtext, PDO::PARAM_STR);
		if (!$stOLog[$this->order_id]->execute() || $stOLog[$this->order_id]->rowCount() <= 0) return array('error', 'خطاء', 'خطاء غير معروف', [3]);

		$stSLU[$this->order_id] = $this->conn->prepare($queryLU);
		$stSLU[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		if (!$stSLU[$this->order_id]->execute() || !$stSLU[$this->order_id]->rowCount()) return array('error', 'خطاء', 'خطاء غير معروف', [1]);

		return array('success', 'ناجح', ' تم استلام المرتجع من امر البيع بنجاح', []);
	}
	// استلام مرتجعات امر الاستبدال من المندوب
	private function ShipmentRefundFromClient() {
		//==========التأكد من ان المصفوفة تحتوى على امر بيع واحد

		if (count(array_keys($this->orderobj)) !== 1) return array('error', 'خطاء', 'لا يمكن تنفيذ هذا الاجراء لعدة اوامر - مسموح بتنفيذه لامر واحد فقط', $ordersISNotConditions);

		$query = self::query();
		$queryLU = $query['setLastUpdate'];

		$this->order_id = implode(", ", array_keys($this->orderobj));
		$sr_query = 'SELECT O.*,
					 OS.STA_NAME AS OSTATUSE_NAME
					FROM  orders O
					LEFT JOIN order_statuse  OS on O.ORDER_STATUS = OS.STA_ID
					WHERE O.ORDER_ID = (select EXCHANGE FROM orders WHERE ORDER_ID = :oid)';

		$sr_query2 = 'SELECT FORENAME ,LASTNAME FROM users WHERE USER_ID = :did';

		$this->order_id = htmlspecialchars(strip_tags(trim($this->order_id)));
		$this->delivery_id = htmlspecialchars(strip_tags(trim($this->delivery_id)));

		//==========جلب بيانات امر البيع
		$stmt0 = $this->conn->prepare($sr_query2);
		$stmt0->bindValue(':did', $this->delivery_id, PDO::PARAM_INT);
		$stmt0->execute();
		$result = $stmt0->fetchAll(PDO::FETCH_ASSOC);
		$userDeliveryName = $result[0]['FORENAME'] . ' ' .$result[0]['LASTNAME'];
		//=========جلب بيانات اسم موظف الشحن
		$stmt1 = $this->conn->prepare($sr_query);
		$stmt1->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		$stmt1->execute();

		$dataCheck = $stmt1->fetchAll(PDO::FETCH_ASSOC);
		//===========التأكد ان امر البيع يستوفى جميع الشروط===============
		if ($stmt1->rowCount() <= 0) return array('error', 'لم يتم العثور على بيانات امر الاستبدال', 'خطاء', []);

		$orderStat = $dataCheck[0]['ORDER_STATUS'];
		$orderStatName = $dataCheck[0]['OSTATUSE_NAME'];
		$exchangeOrder = $dataCheck[0]['ORDER_ID'];
		//=================أضافة المنتجات فى المخزن===================
		$proOrderQuery = "SELECT
							 	OP.PRO_CODE,
							 	OP.PRO_STATUSE,
								SUM(RETURNED_QTY) AS QTY
							  FROM  order_product AS OP
							  WHERE OP.ORDER_ID = :oid
							  GROUP BY OP.PRO_CODE";
		$stmt2 = $this->conn->prepare($proOrderQuery);
		$stmt2->bindValue(':oid', $exchangeOrder, PDO::PARAM_INT);
		if (!$stmt2->execute() || $stmt2->rowCount() <= 0) return array('error', 'لم يتم العثور على بيانات منتجات امر الاستبدال', 'خطاء', []);

		$productArray = $stmt2->fetchAll(PDO::FETCH_ASSOC);

		//التعامل مع امر البيع الاستبدال
		$stAddInve = false;
		$shtype = 8;
		$inventory = new inventory_BE($this->conn);
		$newArray = [];

		foreach ($productArray as $row) {
			// لو ينطبق عليه حالة الشحن الجزئي أو التسليم
			if ($row['PRO_STATUSE'] == 'partial_refund' || $row['PRO_STATUSE'] == 'partial' || $row['PRO_STATUSE'] == 'delivered') {
				$shtype = 9;
			}

			// لو الحالة المطلوبة للاحتفاظ
			if ($row['PRO_STATUSE'] == 'partial_refund' || $row['PRO_STATUSE'] == 'refund') {
				unset($row['PRO_STATUSE']);
				$newArray[] = $row;
			}
		}

		$productArray = $newArray;

		$stAddInve = $inventory->inventoryMovement ($productArray, $this->order_id, $this->delivery_id, 'return_sales');



		//=============insert shipping_movement================
		/*
			0 = start Shipping
			1 = freeze
			2 = Returned From Freeze
			3 = Delivered
			4 = Partial
			5 = Rejected ,
			6 = Returned From Rejected or Partial
			7 = تم السداد
			8 = refund from client
			9 = partial refound from client
		*/

		$stShMov = $this->conn->prepare($query['add_ShipMovement']);
		$stShMov->bindValue(':type', $shtype, PDO::PARAM_INT);
		$stShMov->bindValue(':deliveryid', $this->delivery_id, PDO::PARAM_INT);
		$stShMov->bindValue(':userid', $this->user_action, PDO::PARAM_INT);
		$stShMov->bindValue(':shipmentdata', $this->order_id, PDO::PARAM_STR);

		$ShResponse = true;

		//===========تنفيذ insert shipping_movement==============
		if (!$stShMov->execute() || $stShMov->rowCount() <= 0) $ShResponse = false;

		// التأكد من تنفيذ كل الاجرأت السابقه بنجاح
		if (!$stAddInve || !$ShResponse) return array('error', 'خطاء', 'خطاء غير معروف', [2]);

		//========اضافة الحركه فى سجل حركات المستخدمين بأمر الليع=========
		$sql = $this->query();
		$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group. "*تم استلام مرتجعات امر البيع من عهدة المندوب ".$userDeliveryName." - حالة امر البيع ".$orderStatName;
		$stOLog[$this->order_id] = $this->conn->prepare($sql['add_Orders_log']);
		$stOLog[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		$stOLog[$this->order_id]->bindValue(':uauction', $_SESSION['USER_ID'], PDO::PARAM_INT);
		$stOLog[$this->order_id]->bindValue(':log', $logtext, PDO::PARAM_STR);
		if (!$stOLog[$this->order_id]->execute() || !$stOLog[$this->order_id]->rowCount()) return array('error', 'خطاء', 'خطاء غير معروف', [3]);

		//================تعديل تاريخ LAST_UPDATE=================
		$stSLU[$this->order_id] = $this->conn->prepare($queryLU);
		$stSLU[$this->order_id]->bindValue(':oid', $this->order_id, PDO::PARAM_INT);
		if (!$stSLU[$this->order_id]->execute() || !$stSLU[$this->order_id]->rowCount()) return array('error', 'خطاء', 'خطاء غير معروف', [1]);
		$stUpCust = self::update_custody($this->order_id, $this->delivery_id, "REFUND"); // صرف او اضافة المنتجات فى المخزنيه
		if (!$stUpCust) return array('error', 'خطاء', 'خطاء غير معروف', [1]);
		return array('success', 'ناجح', ' تم استلام المرتجع من امر البيع بنجاح', ['done']);
	}

	//=============وظائف مرتبطه بوظيفة  ShipmentDelivery===========
	 
	/**
	* معالجة وضع العرض لتسليم الشحنة
	*
	* @param array $sql مصفوفة تحتوي على استعلامات SQL اللازمة
	* @return array نتيجة المعالجة تحتوي على نوع النتيجة وبيانات المنتجات
	*/
	private function handleViewMode(array $sql): array {
		try {
			// يدعم شكل الطلب القديم (مصفوفة) وشكل الواجهة الحالي (رقم الطلب مباشرة).
			if (is_array($this->orderobj)) {
				$this->order_id = (int)array_key_first($this->orderobj);
				$orderData = $this->orderobj[$this->order_id] ?? [];
				$this->delivery_id = (int)($orderData['shipUid'] ?? $this->delivery_id ?? $this->user_action);
			} else {
				$this->order_id = (int)$this->orderobj;
				$this->delivery_id = (int)($this->delivery_id ?: $this->user_action);
			}
			if ($this->order_id <= 0 || $this->delivery_id <= 0) {
				throw new InvalidArgumentException('رقم الطلب أو المندوب غير صالح.');
			}

			// التحقق من وجود مرتجع للطلب
			if (is_array($this->orderobj) &&
				isset($this->orderobj[$this->order_id]['returns']) &&
				$this->orderobj[$this->order_id]['returns']) {

				// إذا كان هناك مرتجع، نرجع رسالة تحذير
				return [
					'type' => 'warning',
					'title' => 'نأسف',
					'message' => 'تم استلام المرتجع بالفعل ولا يمكن تنفيذ الإجراء مرة أخرى'
				];
			}

			// جلب المنتجات العادية للطلب
			$orderMetaStmt = $this->conn->prepare(
				"SELECT o.ORDER_TYPE_ID, s.BRANCH_ID
				 FROM orders o
				 LEFT JOIN stores s ON s.STO_ID = o.STO_ID
				 WHERE o.ORDER_ID = ? LIMIT 1"
			);
			$orderMetaStmt->execute([$this->order_id]);
			$orderMeta = $orderMetaStmt->fetch(PDO::FETCH_ASSOC);
			if (!$orderMeta || (int)$orderMeta['BRANCH_ID'] !== (int)$this->branch_id) {
				return [
					'type' => 'error',
					'title' => 'الطلب غير متاح',
					'message' => 'الطلب غير موجود أو لا يتبع الفرع الحالي.'
				];
			}

			$products = $this->fetchOrderProducts($sql['getOProducts'], $this->order_id);
			if (empty($products['no'])) {
				return [
					'type' => 'error',
					'title' => 'لا توجد منتجات',
					'message' => 'لم يتم العثور على منتجات هذا الطلب داخل عهدة المندوب الحالي.'
				];
			}
			$shippingEditAllowed = (int)$orderMeta['ORDER_TYPE_ID'] !== 5
				&& (new ShippingConfigurationManager($this->conn))->isShippingAmountEditAllowed();

			// جلب المنتجات المرتجعة للطلب
			//$returned = $this->fetchOrderProducts($sql['getReturnOProducts'], $this->order_id, true);

			// إرجاع نتيجة نجاح مع بيانات المنتجات
			return [
				'type' => 'success',
				'products' => $products['no'],
				'returned' => $products['ex'],
				'SHIPPING_VAL' => $products['SHIPPING_VAL'],
				'order_type_id' => (int)$orderMeta['ORDER_TYPE_ID'],
				'shipping_edit_allowed' => $shippingEditAllowed
			];

		} catch (Exception $e) {
			// معالجة أي استثناء قد يحدث وإرجاع رسالة خطأ
			return [
				'type' => 'error',
				'title' => 'خطأ',
				'message' => 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage()
			];
		}
	}
	/**
	* تحديث أوامر البيع والمرتجعات وعهدة المندوبين بسرعة فائقة مع ضمان الاتساق الكامل
	*
	* @param array $records مصفوفة تحتوي على بيانات الأوامر والمنتجات المراد تحديثها
	*    هيكل المصفوفة:
	*    [
	*        [
	*            'ORDER_ID' => 101,                    // رقم أمر البيع أو المرتجع
	*            'PRO_CODE' => 'SKU123',               // كود المنتج
	*            'DELIVERY_ID' => 12,                  // رقم المندوب
	*            'TYPE' => 'SELSE',                    // نوع السجل: SELSE (بيع) أو REFUND (مرتجع)
	*            'productData' => [
	*                'appQty' => 2,                    // الكمية المطلوبة
	*                'defqty' => 5,                    // الكمية المعتمدة
	*                'recqty' => null                  // الكمية المستلمة (للمرتجع فقط)
	*            ]
	*        ],
	*        ...
	*    ]
	*
	* قواعد recqty:
	* - إذا كان TYPE = SELSE: recqty يجب أن يكون null دائماً
	* - إذا كان TYPE = REFUND: recqty يجب أن يكون رقم صالح
	*
	* @param array &$orejected مصفوفة مرجعية لتسجيل الأوامر المرفوضة
	* @param mixed &$ecode متغير مرجعي لتسجيل كود الخطأ
	*
	* @return bool true في حالة النجاح، false في حالة الفشل
	*
	* @throws PDOException في حالة حدوث خطأ في قاعدة البيانات
	*/ 
	private function confirmShipmentDelivery(){
	    // تطبيع حمولة الواجهة الحالية (oapp) إلى بنية confirm_staging المتوقعة.
	    // الواجهة ترسل الكمية المسلمة الإجمالية لكل منتج، وليس كمية الحركة الإضافية.
	    if (!isset($this->shipmentobj['ship']) && isset($this->shipmentobj['oapp']) && is_array($this->shipmentobj['oapp'])) {
	        $orderId = (int)$this->orderobj;
	        $deliveryId = (int)($this->delivery_id ?: $this->user_action);
	        $normalizedItems = [];
	        foreach ($this->shipmentobj['oapp'] as $productCode => $productData) {
	            if (!is_array($productData) || !array_key_exists('appQty', $productData)) {
	                return ['error', 'خطأ', 'بيانات كمية المنتج غير صالحة.', []];
	            }
	            $normalizedItems[] = [
	                'ORDER_ID' => $orderId,
	                'PRO_CODE' => (string)$productCode,
	                'DELIVERY_ID' => $deliveryId,
	                'TYPE' => 'SELSE',
	                'productData' => ['appQty' => $productData['appQty'], 'recqty' => null],
	            ];
	        }
	        $this->shipmentobj['ship'] = $normalizedItems;
	    }

	    // التحقق من البيانات المدخلة
	    if (empty($this->shipmentobj['ship']) || !is_array($this->shipmentobj['ship'])) {
	        return ['error', 'خطأ', 'بيانات الشحنة غير صالحة',[]];
	    }
	    
		$shipmentType = (int)($this->shipmentobj['ptype'] ?? 0);
		if($shipmentType !== 1 && ($this->ShipApproved === TRUE || $this->ShipApproved === 'TRUE' || $this->ShipApproved == 1)){
			return ['error', 'خطأ', 'انت لا تملك صلاحية انشاء اذن استلام مخزنى',[]];
		}
	    $items = $this->shipmentobj['ship'];
        $requestKey = trim((string)($this->shipmentobj['request_key'] ?? ''));
        if ($requestKey === '') {
            // توافق مؤقت مع أي نسخة واجهة قديمة لم تُحدّث بعد.
            $requestKey = 'LEGACY:' . bin2hex(random_bytes(16));
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)) {
            return ['error', 'خطأ', 'مفتاح تأكيد الشحنة غير صالح، حدّث الصفحة وحاول مرة أخرى', []];
        }
        $sessionId = uniqid('IM_', true);
        $executedBy = $this->user_action;
	    $executedGr = $this->user_group;
	
	    $lockedOrders = [];
	    $inventoryLocked = false;
	
	    try {
	        $this->conn->beginTransaction();

            // منع إعادة تنفيذ نفس تأكيد الشحنة عند تكرار طلب HTTP.
            $shippingAmountProvided = array_key_exists('shipping_amount', $this->shipmentobj);
            $shippingAmount = null;
            if ($shippingAmountProvided) {
                $rawShippingAmount = $this->shipmentobj['shipping_amount'];
                if (!is_scalar($rawShippingAmount) || !is_numeric((string)$rawShippingAmount)) {
                    throw new InvalidArgumentException('قيمة رسوم الشحن غير صالحة.');
                }
                $shippingAmount = round((float)$rawShippingAmount, 2);
                if (!is_finite($shippingAmount) || $shippingAmount < 0 || $shippingAmount > 9999999999999.99) {
                    throw new InvalidArgumentException('قيمة رسوم الشحن يجب أن تكون رقمًا موجبًا أو صفرًا وبحد أقصى مناسب.');
                }
            }

            $requestPayload = [
                'actor_id' => (int)$executedBy,
                'branch_id' => (int)$this->branch_id,
                'ship_approved' => (string)$this->ShipApproved,
                'items' => $items,
                'shipping_amount_provided' => $shippingAmountProvided,
                'shipping_amount' => $shippingAmountProvided ? number_format($shippingAmount, 2, '.', '') : null,
            ];
            $requestJson = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            if ($requestJson === false) {
                throw new RuntimeException('تعذر تجهيز بصمة طلب تأكيد الشحنة.');
            }
            $requestHash = hash('sha256', $requestJson);

            try {
                $requestInsert = $this->conn->prepare(
                    "INSERT INTO delivery_confirmation_requests
                        (request_key, request_hash, session_id, actor_id, branch_id, status)
                     VALUES (?, ?, ?, ?, ?, 'PROCESSING')"
                );
                $requestInsert->execute([$requestKey, $requestHash, $sessionId, $executedBy, $this->branch_id]);
            } catch (PDOException $requestException) {
                if ((string)$requestException->getCode() !== '23000') {
                    throw $requestException;
                }
                $existingRequestStmt = $this->conn->prepare(
                    "SELECT request_hash, status
                     FROM delivery_confirmation_requests
                     WHERE request_key = ? LIMIT 1 FOR UPDATE"
                );
                $existingRequestStmt->execute([$requestKey]);
                $existingRequest = $existingRequestStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingRequest) {
                    throw $requestException;
                }
                if (!hash_equals((string)$existingRequest['request_hash'], $requestHash)) {
                    throw new RuntimeException('مفتاح تأكيد الشحنة مستخدم مع بيانات مختلفة؛ أعد فتح التأكيد.');
                }
                if ($existingRequest['status'] === 'COMPLETED') {
                    $this->conn->commit();
                    return ['success', 'نجح', 'تم تنفيذ تأكيد الشحنة بالفعل؛ لم تتم إعادة العملية', ''];
                }
                throw new RuntimeException('يوجد طلب تأكيد سابق غير مكتمل؛ يلزم مراجعته قبل إعادة التنفيذ.');
            }

			$orderids = array_map(function ($row) {
			    return (int)$row['ORDER_ID'];
			}, $items);
			
			// ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
		    $placeholders = implode(',', array_fill(0, count($orderids), '?'));
		    $stmtLock = $this->conn->prepare("
		        SELECT o.ORDER_ID, op.PRO_CODE
		        FROM orders o
		        JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
		        WHERE o.ORDER_ID IN ($placeholders)
		        FOR UPDATE NOWAIT
		    ");
		    $stmtLock->execute($orderids);

            // التحقق من الفرع ونوع الطلب وسياسة تعديل الشحن داخل نفس المعاملة.
            $orderMetaLock = $this->conn->prepare(
                "SELECT o.ORDER_ID, o.ORDER_TYPE_ID, o.SHIPPING_VAL, s.BRANCH_ID
                 FROM orders o
                 LEFT JOIN stores s ON s.STO_ID = o.STO_ID
                 WHERE o.ORDER_ID = ? LIMIT 1 FOR UPDATE"
            );
            $uniqueOrderIds = array_values(array_unique($orderids));
            if (count($uniqueOrderIds) !== 1) {
                throw new RuntimeException('يجب أن يحتوي تأكيد الشحنة على أمر بيع واحد فقط.');
            }
            $orderMetaLock->execute([$uniqueOrderIds[0]]);
            $lockedOrder = $orderMetaLock->fetch(PDO::FETCH_ASSOC);
            if (!$lockedOrder || (int)$lockedOrder['BRANCH_ID'] !== (int)$this->branch_id) {
                throw new RuntimeException('الطلب غير موجود أو لا يتبع الفرع الحالي.');
            }
            if ($shippingAmountProvided) {
                $shippingSetting = $this->conn->prepare(
                    "SELECT SETTING_VALUE FROM system_settings
                     WHERE SETTING_KEY = 'ALLOW_SHIPPING_AMOUNT_EDIT_DURING_CONFIRMATION'
                     LIMIT 1 FOR UPDATE"
                );
                $shippingSetting->execute();
                $settingValue = strtolower(trim((string)$shippingSetting->fetchColumn()));
                $canEditShipping = in_array($settingValue, ['1', 'true', 'yes', 'on'], true);
                if (!$canEditShipping) {
                    throw new RuntimeException('تعديل رسوم الشحن غير مسموح به من إعدادات النظام.');
                }
                if ((int)$lockedOrder['ORDER_TYPE_ID'] === 5) {
                    throw new RuntimeException('لا يمكن تعديل رسوم الشحن في البيع المباشر.');
                }
                $updateShipping = $this->conn->prepare(
                    "UPDATE orders SET SHIPPING_VAL = ?, LAST_UPDATE = NOW(3) WHERE ORDER_ID = ?"
                );
                $updateShipping->execute([$shippingAmount, (int)$lockedOrder['ORDER_ID']]);
            }

	        // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }
	        
	        $this->conn->exec("DROP TEMPORARY TABLE IF EXISTS confirm_staging");
	
	        // 3. أكمل العملية عادي
	        //TEMPORARY
	        $createTmp = "
	            CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
	                SESSION_ID    VARCHAR(64) NOT NULL,
	                ORDER_ID      INT NOT NULL,
	                PRO_CODE      VARCHAR(100) NOT NULL,
	                USER_ID       INT NOT NULL,
	                APP_QTY       DECIMAL(18,3) NOT NULL,
	                STATUSE       VARCHAR(50) NOT NULL,
	                TYPE          ENUM('SELSE','REFUND') NOT NULL,
	                CREATED_AT    DATETIME(3) DEFAULT NOW(3),
	                PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, TYPE)
	            ) ENGINE=InnoDB;
	            

	        ";
	        $this->conn->exec($createTmp);

	        // إعداد استعلام الإدخال
	        $insertSql = "
	            INSERT INTO confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE) 
	            VALUES (:session_id, :order_id, :pro_code, :user_id, :app_qty, :statuse, :type)";
	        $insStmt = $this->conn->prepare($insertSql);
	        
	        foreach ($items as $index => $row) {
	            if (empty($row['ORDER_ID']) || empty($row['PRO_CODE'])) {
	                throw new Exception("بيانات غير مكتملة في الصف " . ($index + 1));
	            }
	            $orderId = (int)$row['ORDER_ID'];
	            $proCode = trim($row['PRO_CODE']);
	            $userId  = isset($row['DELIVERY_ID']) ? (int)$row['DELIVERY_ID'] : $executedBy;
	            $appQty  = isset($row['productData']['appQty']) && is_numeric($row['productData']['appQty']) ? (float)$row['productData']['appQty'] : 0.0;
	            $recQty  = $row['productData']['recqty'] ?? null;
	            $statuse = (!empty($recQty) && is_numeric($recQty) && (float)$recQty > 0) ? 'returned' : 'pending';
	            $type = in_array($row['TYPE'] ?? '', ['SELSE', 'REFUND']) ? $row['TYPE'] : 'SELSE';
	            
	            $insStmt->execute([
	                ':session_id' => $sessionId,
	                ':order_id'   => $orderId,
	                ':pro_code'   => $proCode,
	                ':user_id'    => $userId,
	                ':app_qty'    => $appQty,
	                ':statuse'    => $statuse,
	                ':type'       => $type
	            ]);
	        }
	        /*
			|--------------------------------------------------------------------------
			| CREATE TEMP TABLE
			|--------------------------------------------------------------------------
			*
			
			$this->conn->exec("
			    CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
			        SESSION_ID VARCHAR(64) NOT NULL,
			        ORDER_ID INT NOT NULL,
			        PRO_CODE VARCHAR(100) NOT NULL,
			        USER_ID INT NOT NULL,
			        APP_QTY DECIMAL(18,3) NOT NULL,
			        STATUSE VARCHAR(50) NOT NULL,
			        TYPE ENUM('SELSE', 'REFUND', 'SHIPPING', 'FREEZE', 'DEFREEZE') NOT NULL,
			        CREATED_AT DATETIME(3) DEFAULT NOW(3),
			
			        PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, TYPE),
			        
			        INDEX idx_session (SESSION_ID),
			        INDEX idx_session_type (SESSION_ID, TYPE),
			        INDEX idx_order_product (ORDER_ID, PRO_CODE),
			        INDEX idx_order_user (ORDER_ID, USER_ID)
			
			    ) ENGINE=InnoDB
			");
			
			/*
			|--------------------------------------------------------------------------
			| BULK INSERT
			|--------------------------------------------------------------------------
			*
			
			$chunkSize = 1000;
			
			foreach (array_chunk($items, $chunkSize) as $chunk) {
			
			    $placeholders = [];
			    $params = [];
			
			    foreach ($chunk as $index => $row) {
			
			        if (!isset($row['ORDER_ID']) || !isset($row['PRO_CODE'])) {
			            throw new Exception('بيانات غير مكتملة فى الصف رقم ' . ($index + 1));
			        }
			        $orderId = (int)$row['ORDER_ID'];
			        $proCode = trim((string)$row['PRO_CODE']);
			        $userId  = isset($row['DELIVERY_ID']) ? (int)$row['DELIVERY_ID'] : $executedBy;
					$appQty  = isset($row['productData']['appQty']) && is_numeric($row['productData']['appQty']) ? (float)$row['productData']['appQty'] : 0.0;
					$recQty  = $row['productData']['recqty'] ?? null;
			        $statuse = (!empty($recQty) && is_numeric($recQty) && (float)$recQty > 0) ? 'returned' : 'pending';
			        $type = in_array($row['TYPE'] ?? '', ['SELSE', 'REFUND']) ? $row['TYPE'] : 'SELSE';
			        //if (!in_array($type,['SELSE','REFUND', 'SHIPPING', 'FREEZE', 'DEFREEZE'], true)) {
			        //    $type = 'SELSE';
			        //}
			
			        $placeholders[] = '(?,?,?,?,?,?,?)';
			
			        $params[] = $sessionId;
			        $params[] = $orderId;
			        $params[] = $proCode;
			        $params[] = $userId;
			        $params[] = $appQty;
			        $params[] = $statuse;
			        $params[] = $type;
			    }
			
			    if (!$placeholders) {
			        continue;
			    }
			
			    $sql = "INSERT INTO confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE ) VALUES " . implode(',', $placeholders);
			
			    $insStmt = $this->conn->prepare($sql);
			
			    $insStmt->execute($params);
			}
	        print_r($params);
	        */
	        
	        $insStmt->closeCursor();
	 
	        // استدعاء البروسيدجر
	        $call = $this->conn->prepare("CALL setDeliveryConfirmationOptimized(:session_id, :executed_by, :executed_gr, :branch_id, :ShipApproved)");
	        $call->execute([
	            ':session_id'   => $sessionId,
	            ':executed_by'  => $executedBy,
	            ':executed_gr'  => $executedGr,
	            ':branch_id'    => $this->branch_id,
	            ':ShipApproved' => $this->ShipApproved,
	        ]);
	        
	        $result = $call->fetchAll(PDO::FETCH_ASSOC);
	        $call->closeCursor();
	       
	        if (!empty($result) && isset($result[0]['status']) && $result[0]['status'] == 'success') {
                // ترحيل عمولة كل حركة رفض فعلية قبل اعتماد طلب التأكيد.
                $attemptCommissionManager = new DeliveryAttemptCommissionManager($this->conn);
                $attemptCommissionManager->accrueRejectedAttempts(
                    $sessionId,
                    (int)$this->branch_id,
                    (int)$executedBy
                );

                $requestComplete = $this->conn->prepare(
                    "UPDATE delivery_confirmation_requests
                     SET status = 'COMPLETED', completed_at = NOW()
                     WHERE request_key = ? AND request_hash = ? AND status = 'PROCESSING'"
                );
                $requestComplete->execute([$requestKey, $requestHash]);
                if ($requestComplete->rowCount() !== 1) {
                    throw new RuntimeException('تعذر اعتماد سجل منع تكرار تأكيد الشحنة.');
                }
	            $this->conn->commit();
	            return [
	                'success',
	                'نجح',
	                'تم تأكيد الشحنة بنجاح',
	                ''
	            ];
	        } else {
	        	
	            $errorMessage = $result[0]['message'] ?? 'فشل في تأكيد الشحنة';
	            //print_r($result);
	            throw new Exception($errorMessage);
	        }
	        
	    } catch (PDOException $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ في قاعدة البيانات', $e->getMessage()];
	    } catch (Exception $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ', $e->getMessage()];
	    } finally {
	        // 🔓 تحرير الأقفال
			$inventoryLocked = $this->releaseGlobalInventoryLock();
	        // الـ Inventory Lock بيتحرر أوتوماتيك مع انتهاء الـ Transaction
	        if ($inventoryLocked) {
	            $this->logManager->info("🔓 تم تحرير أقفال المخزون تلقائياً");
	        }
	    }
	}
	/**
	* جلب منتجات الطلب من قاعدة البيانات
	*
	* هذه الدالة تقوم بجلب قائمة المنتجات الخاصة بطلب معين
	* ويمكنها التعامل مع المنتجات العادية أو المرتجعة
	*
	* @param string $query استعلام SQL لجلب المنتجات
	* @param int|string $orderId رقم الطلب
	* @param bool $isReturned هل هذه منتجات مرتجعة؟ (افتراضي: false)
	* @return array مصفوفة تحتوي على بيانات المنتجات
	* @throws PDOException في حالة حدوث خطأ في قاعدة البيانات
	*/
	private function fetchOrderProducts(string $query, int $orderId, bool $isReturned = false): array {
		try {

			// إعداد وتنفيذ الاستعلام
			$stmt = $this->conn->prepare($query);
			$stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
			$stmt->bindValue(':deliveryid', $this->delivery_id, PDO::PARAM_INT);
			
						
			$stmt->execute();
			$stmt2 = $this->conn->prepare("SELECT SHIPPING_VAL FROM orders WHERE ORDER_ID = :oid");

			$stmt2->execute(['oid' => $orderId]);
			
			$shippingVal = $stmt2->fetchColumn();
			
			
			$products = [];
			$return = [
				'no' => [],
				'ex' => [],
				'SHIPPING_VAL' => 0
			];

			// معالجة كل منتج في النتيجة
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($row['TYPE'] == 'SELSE') {
					$return['no'][] = [
						'oid' => $row['ORDER_ID'] ?? '',
						'did' => $row['USER_ID']  ?? '0',
						'code' => $row['PRO_CODE'] ?? '',
						'name' => $row['PRO_NAME'] ?? '',
						'price' => $row['PRICE'] - $row['DISCOUNT'] ?? 0,
						'option' => isset($row['OPTION']) ? explode(',', $row['OPTION']) : [],
						'qty' => (float) ($row['QTY'] ?? 0),
						'delivered_qty' => (float) (($row['PRO_STATUSE'] ?? '') === 'WAITING'
							? ($row['QTY'] ?? 0)
							: ($row['DELIVERED_QTY'] ?? 0)),
						'returned_qty' => (float) $row['RETURNED_QTY'] ?? 0,
						'status' => $row['PRO_STATUSE'] ?? 'UNKNOWN'
					];
				}

				if ($row['TYPE'] == 'REFUND') {
					$return['ex'][] = [
						'oid' => $row['ORDER_ID'] ?? '',
						'did' => $row['USER_ID']  ?? '',
						'code' => $row['PRO_CODE'] ?? '',
						'name' => $row['PRO_NAME'] ?? '',
						'price' => $row['PRICE'] - $row['DISCOUNT'] ?? 0,
						'option' => isset($row['OPTION']) ? explode(',', $row['OPTION']) : [],
						'qty' => (float) ($row['QTY'] ?? 0),
						'delivered_qty' => (float) (($row['PRO_STATUSE'] ?? '') === 'WAITING'
							? ($row['QTY'] ?? 0)
							: ($row['DELIVERED_QTY'] ?? 0)),
						'returned_qty' => (float) $row['RETURNED_QTY'] ?? 0,
						'status' => $row['PRO_STATUSE'] ?? 'UNKNOWN'
					];
				}
				// إضافة حقل الكمية المرتجعة إذا كانت منتجات مرتجعة
				/*if ($isReturned) {
	                $entry['returned_qty'] = (float) ($row['RETURNED_QTY'] ?? 0);
	            }*/

				//$products[] = $return;
			}
			$return['SHIPPING_VAL'] = $shippingVal;
			return $return;

		} catch (PDOException $e) {
			// تسجيل الخطأ ثم إعادة رميه للتعامل معه في مستوى أعلى
			throw new PDOException("فشل في جلب بيانات المنتجات: " . $e->getMessage());
		}
	}
	/**
	* جلب بيانات الطلبات من قاعدة البيانات
	*
	* @param array $ordersIds أرقام الطلبات المراد جلبها
	* @param int $deliveryId معرف موظف الشحن
	* @param array $sql مصفوفة استعلامات SQL
	* @return PDOStatement نتيجة الاستعلام
	* @throws PDOException إذا حدث خطأ في قاعدة البيانات
	* @throws InvalidArgumentException إذا كانت البيانات غير صالحة
	*/
	private function fetchOrderData(array $ordersIds, int $deliveryId, array $sql) {
		$this->logManager->info("جلب بيانات الطلبات (fetchOrderData)");
		try {
			// التحقق من صحة البيانات
			if (!isset($sql['getDataOrders'])) throw new ErrorException('مفتاح الاستعلام getDataOrders غير موجود في مصفوفة SQL');
	
			if (!count($ordersIds)) throw new ErrorException('مصفوفة الطلبات فارغة - لا توجد طلبات للمعالجة');
			
			if (!$deliveryId || $deliveryId <= 0) throw new ErrorException('معرف موظف الشحن غير متوفر');

			$orderids = implode(',', array_keys($ordersIds));
			
			$stmt = $this->conn->prepare($sql['getDataOrders']);
			$stmt->bindValue(':oids', $orderids, PDO::PARAM_STR);
			$stmt->bindValue(':deliveryid', $deliveryId, PDO::PARAM_INT);
			$stmt->execute();

			if ($stmt->rowCount() <= 0) throw new ErrorException('لا توجد بيانات للطلبات المطلوبة: ' . $orderids);
			
			return $stmt;

		} catch (Exception $e) {
			// إعادة رمي الاستثناء مع رسالة أفضل
			throw new PDOException(
				'فشل في جلب بيانات الطلبات: ' . $e->getMessage(),
				(int)$e->getCode()
			);
		} catch (ErrorException $e) {
			// إعادة رمي الاستثناء مع رسالة أفضل
			throw new PDOException(
				'فشل في جلب بيانات الطلبات: ' . $e->getMessage(),
				(int)$e->getCode()
			);
		} catch (PDOException $e) {
			// إعادة رمي الاستثناء مع رسالة أفضل
			throw new PDOException(
				'فشل في جلب بيانات الطلبات: ' . $e->getMessage(),
				(int)$e->getCode()
			);
		}
	}
	/**
	* الحصول على نص الحالة
	*/
	private function getStatusText($status) {
		switch ($status) {
			case 3: return 'تسليم أمر البيع بالكامل';
			case 4: return 'تسليم أمر البيع بشكل جزئي';
			case 5: return 'رفض أمر البيع بالكامل';
			case 6: return 'إستلام مرتجع كلي';
			case 7: return 'إستلام مرتجع جزئي';
			default: return '';
		}
	}
	/**
	* تحديد حالة المنتج بناء على الكميات المدخلة والمطلوبة والمسلمة
	*
	* هذه الدالة تقوم بحساب حالة المنتج (كامل، جزئي، مرتجع، إلخ)
	* بناء على الكمية المدخلة والكمية المطلوبة والكمية المسلمة
	* @param float $input الكمية المدخلة (الكمية الحالية)
	* @param float $required الكمية المطلوبة (الكمية الأصلية)
	* @param float|null $delivered الكمية المسلمة (إن وجدت)
	* @return string|bool حالة المنتج أو false إذا لم تطبق أي حالة
	*/
	private function getProductStatus(float $input, float $required, ? float $delivered = null) {
		// تحديد القيمة المرجعية للمقارنة
		$referenceValue = ($delivered !== null) ? $delivered : $required;

		// إذا كانت الكمية المدخلة تساوي القيمة المرجعية وكانت أكبر من الصفر
		if ($input == $referenceValue && $input > 0) {
			return ($delivered === null) ? 'complete' : 'refund';
		}

		// إذا كانت الكمية المدخلة بين الصفر والقيمة المرجعية
		if ($input > 0 && $input < $referenceValue) {
			return ($delivered === null) ? 'partial' : 'partial_refund';
		}
		
		// إذا لم تكن هناك كمية مسلمة والكمية المدخلة صفر
		if ($delivered === null && $input == 0) {
			return 'rejected';
		}
		
		// إذا كانت هناك كمية مسلمة والكمية المدخلة صفر
		if ($delivered !== null && $input == 0) {
			if ($delivered > 0 && $delivered == $required) {
				return 'complete';
			}

			if ($delivered > 0 && $delivered < $required) {
				return 'partial';
			}

			if ($delivered == 0) {
				return 'rejected';
			}
		}

		// إذا لم تطبق أي من الشروط السابقة
		return null;
	}
	/**
	* تحديد حالة المنتج بناء على الكميات المدخلة والمطلوبة والمسلمة
	*
	* هذه الدالة تقوم بحساب حالة المنتج (كامل، جزئي، مرتجع، إلخ)
	* بناء على الكمية المدخلة والكمية المطلوبة والكمية المسلمة
	* يتم حساب حالة المنتج عن طريق استخدام المتغيراتويمكن استخراج حالة امر البيع العادى او اوامر البيع المرتجع وتستخدم كالتالى
	* امر البيع العادى
	* input = التسليم
	* required = المطلوب
	* امر البيع المرتجع
	* input = المرتجع
	* required = المطلوب
	* delivered = التسليم
	*
	* @param float $input الكمية المدخلة (الكمية الحالية)
	* @param float $required الكمية المطلوبة (الكمية الأصلية)
	* @param float|null $delivered الكمية المسلمة (إن وجدت)
	* @return string|bool حالة المنتج أو false إذا لم تطبق أي حالة
	*/
	private function getOrderStatus(float $input, float $required, ?float $delivered = null) {

		// تحديد حالة الطلب بناء على الكميات المسلمة
		$referenceValue = ($delivered !== null) ? $delivered : $required;

		// إذا كانت الكمية المدخلة تساوي القيمة المرجعية وكانت أكبر من الصفر
		if ($input == $referenceValue && $input > 0) {
			$returnArray['statuse'] = ($delivered === null) ? 3 : 6;
			$returnArray['shipmov'] = ($delivered === null) ? 3 : 8;
			$returnArray['stattxt'] = self::getStatusText($returnArray['statuse']) ;//($delivered === null) ? 'تسليم امر البيع بالكامل' : 'استلام المرتجع بالكامل';
			return $returnArray;
		}

		// إذا كانت الكمية المدخلة بين الصفر والقيمة المرجعية
		if ($input < $referenceValue && $input > 0 ) {
			$returnArray['statuse'] = ($delivered === null) ? 4 : 7;
			$returnArray['shipmov'] = ($delivered === null) ? 4 : 9;
			$returnArray['stattxt'] =  self::getStatusText($returnArray['statuse']) ;//($delivered === null) ? 'تسليم امر البيع بشكل جزئى' : 'استلام مرتجع جزئى';
			return $returnArray;
		}

		// إذا لم تكن هناك كمية مسلمة والكمية المدخلة صفر
		if ($delivered === null && $input == 0) {
			$returnArray['statuse'] = 5;
			$returnArray['shipmov'] = 5;
			$returnArray['stattxt'] =  self::getStatusText($returnArray['statuse']) ;//'رفض امر البيع بالكامل';
			return $returnArray;
		}

		// إذا كانت هناك كمية مسلمة والكمية المدخلة صفر
		if ($delivered !== null && $input == 0) {
			$stat = self::getOrderStatus($delivered, $required);
			$returnArray['statuse'] = null;
			$returnArray['stattxt'] = "رفض العميل تسليم المرتجعات";
			$returnArray['shipmov'] = 10;
			return $returnArray;
		}
		// إذا لم تطبق أي من الشروط السابقة
		return false;
	}
	/**
	* تحديث أوامر البيع والمرتجعات وعهدة المندوبين بسرعة فائقة مع ضمان الاتساق الكامل
	*
	* @param array $records مصفوفة تحتوي على بيانات الأوامر والمنتجات المراد تحديثها
	*    هيكل المصفوفة:
	*    [
	*        [
	*            'ORDER_ID' => 101,                    // رقم أمر البيع أو المرتجع
	*            'PRO_CODE' => 'SKU123',               // كود المنتج
	*            'DELIVERY_ID' => 12,                  // رقم المندوب
	*            'TYPE' => 'SELSE',                    // نوع السجل: SELSE (بيع) أو REFUND (مرتجع)
	*            'productData' => [
	*                'appQty' => 2,                    // الكمية المطلوبة
	*                'defqty' => 5,                    // الكمية المعتمدة
	*                'recqty' => null                  // الكمية المستلمة (للمرتجع فقط)
	*            ]
	*        ],
	*        ...
	*    ]
	*
	* قواعد recqty:
	* - إذا كان TYPE = SELSE: recqty يجب أن يكون null دائماً
	* - إذا كان TYPE = REFUND: recqty يجب أن يكون رقم صالح
	*
	* @param array &$orejected مصفوفة مرجعية لتسجيل الأوامر المرفوضة
	* @param mixed &$ecode متغير مرجعي لتسجيل كود الخطأ
	*
	* @return bool true في حالة النجاح، false في حالة الفشل
	*
	* @throws PDOException في حالة حدوث خطأ في قاعدة البيانات
	*/ 
	/*private function handleConfirmMode(array $records, &$orejected, &$ecode, &$ordersISNotConditions) {
		// التحقق من وجود بيانات
		if (empty($records)) {
			$this->logManager->warning("مصفوفة الطلبات فارغة");
			return false;
		}
		$lockedOrders = [];
		$ordersISNotConditions = [];
		$startTime = microtime(true);
		$orderUpdates = []; // لتخزين بيانات الأوامر المجمعة
		$returnOrStatus = false;
		try {

			// ==========  updateOrderAndCustodyProduct بداء المعامله تسليم المنتجات واستلام المرتجعات  ==========
			//$this->logManager->info("🚀 بداء المعاملة updateOrderAndCustodyProduct");

			// تجميع جميع ORDER_IDs الفريدة
			$allOrderIds = [];
			$affectedRows = []; // تتبع الصفوف المتأثرة
			$hashRefundOrder = false;
			$sel_orderid = 0;
			$sel_deliveryid = 0;
			$ref_orderid = 0;
			$ref_deliveryid = 0;
			
			$allAppQty = 0;
		//	$allSelQtyInput = 0;
		//	$allSelQtyDeliverd = 0;
			
		//	$allRefQtyInput = 0;
		//	$allRefQtyDeliverd = 0;
		//	$allRefQtyReturn = 0;

			// ========== 📊 تجميع البيانات للاستعلامات المجمعة ==========
			$selseQtyCases = $selseStatusCases = $selseWherePairs = [];
			$refundQtyCases = $refundStatusCases = $refundWherePairs = [];
			$logValues = $movValues = [];
			
			// معالجة كل سجل في المصفوفة
			foreach ($records as $index => $rec) {

				// ✅ فحص البيانات - دفعة واحدة
				$requiredKeys = ['ORDER_ID', 'PRO_CODE', 'DELIVERY_ID', 'TYPE', 'productData'];
				foreach ($requiredKeys as $k) {
				    if (!isset($rec[$k])) throw new RuntimeException("سجل رقم {$index} ناقص البيانات: {$k} غير متوفر");
				}
				
				// فحص productData مرة واحدة
				if (!isset($rec['productData'])) throw new RuntimeException("سجل رقم {$index}: productData غير متوفر");
				
				// فحص productData[appQty]
				if (!isset($rec['productData']['appQty'])) throw new RuntimeException("سجل رقم {$index}: appQty غير متوفر");
				
				// فحص productData[defqty]
				if (!isset($rec['productData']['defqty'])) throw new RuntimeException("سجل رقم {$index}: defqty غير متوفر");

				$productData = $rec['productData'];
				
				// فحص appQty و defqty معاً
				$appQtyVal = $rec['productData']['appQty'] ?? null;
				$defQtyVal = $rec['productData']['defqty'] ?? null;
				
				// تحقق رقمي سريع
				if (!is_numeric($rec['productData']['appQty']) || !is_numeric($rec['productData']['defqty'])) {
					$prodatacheck = "";
					if (!is_numeric($rec['productData']['appQty'])) $prodatacheck .= " سجل رقم {$index}: productData.appQty غير متوفر ";
					if (!is_numeric($rec['productData']['defqty'])) $prodatacheck .= " سجل رقم {$index}: productData.defqty غير متوفر ";
				    throw new RuntimeException($prodatacheck);
				}
				
				// معالجة REFUND بشكل منفصل
				$recordType = trim($rec['TYPE']);
				$delivered = null;
				 
				if ($recordType === 'REFUND') {
				    $recqtyVal = $rec['productData']['recqty'] ?? 0; 
				    if ($recqtyVal === null) throw new RuntimeException("سجل رقم {$index}: recqty غير متوفر");
				    // تنظيف سريع للقيمة
				    $recqtyVal = is_string($recqtyVal) ? trim($recqtyVal) : $recqtyVal;
				    if (!is_numeric($recqtyVal)) throw new RuntimeException("سجل رقم {$index}: recqty غير صالح");
				    $delivered = (float)$recqtyVal;
				    if ($delivered < 0) throw new RuntimeException("سجل رقم {$index}: recqty سالب");
				}
				
				// 🔒 تنظيف وتأمين البيانات (بعد اجتياز الفحوص)
				$orderId     = (int)$rec['ORDER_ID'];
				$deliveryId  = (int)$rec['DELIVERY_ID'];
				$productCode = $this->conn->quote(trim($rec['PRO_CODE']));
				$appQty      = (float)$appQtyVal;
				$required    = (float)$defQtyVal;
				
			//	$allSelQtyInput += $appQty;
			//	$allSelQtyDeliverd += $required;
				
				
				$allAppQty += $appQty;
				$allOrderIds[$orderId] = $orderId;
				// 🔒 حساب حالة المنتج
				
				$status =  $this->getProductStatus($appQty, $required, $delivered);
				if (!$status) throw new RuntimeException("سجل رقم {$index}: فشل حساب حالة المنتج {$productCode}");
				$quotedStatus = $this->conn->quote($status);
				
				// 🔄 فصل البيانات حسب النوع (بيع/مرتجع)
				if ($recordType === 'SELSE') {
					
					if ($sel_orderid === 0)    $sel_orderid    = $orderId;
				    if ($sel_deliveryid === 0) $sel_deliveryid = $deliveryId;
					// تجميع حالات التحديث للبيع
					$selseQtyCases[] 	= "WHEN ORDER_ID = {$orderId} AND PRO_CODE = {$productCode} AND USER_ID = {$deliveryId} THEN {$appQty}";
					$selseStatusCases[] = "WHEN ORDER_ID = {$orderId} AND PRO_CODE = {$productCode} AND USER_ID = {$deliveryId} THEN {$quotedStatus}";
					$selseWherePairs[]  = "({$orderId}, {$productCode}, {$deliveryId})";

					// تجميع إحصائيات الأمر
			//		if (!isset($orderUpdates[$orderId])) {
			//			$orderUpdates[$orderId] = ['type' => 'SELSE',
			//				'required' => 0,
			//				'delivery_id' => $deliveryId,
			//				'input' => 0];
			//		}
			//		$orderUpdates[$orderId]['input'] += $appQty;
			//		$orderUpdates[$orderId]['required'] += $required;
				} else if ($rec['TYPE'] === 'REFUND') {
					$hashRefundOrder = true;
					
					if ($ref_orderid === 0)    $ref_orderid    = $orderId;
				    if ($ref_deliveryid === 0) $ref_deliveryid = $deliveryId;
				    
					// تجميع حالات التحديث للمرتجع
					$refundQtyCases[] 	 = "WHEN ORDER_ID = {$orderId} AND PRO_CODE = {$productCode} AND USER_ID = {$deliveryId} THEN {$appQty}";
					$refundStatusCases[] = "WHEN ORDER_ID = {$orderId} AND PRO_CODE = {$productCode} AND USER_ID = {$deliveryId} THEN {$quotedStatus}";
					$refundWherePairs[]  = "({$orderId}, {$productCode}, {$deliveryId})";

					// تجميع إحصائيات المرتجع
			//		if (!isset($orderUpdates[$orderId])) {
			//			$orderUpdates[$orderId] = ['type' => 'REFUND',
			//				'delivery_id' => $deliveryId,
			//				'input' => 0,
			//				'required' => 0,
			//				'delivered' => 0];
			//		}
			//		$orderUpdates[$orderId]['input'] += $appQty;
			//		$orderUpdates[$orderId]['required'] += $required;
			//		$orderUpdates[$orderId]['delivered'] += $delivered; // تأكيد وجود قيمة للمرتجع
				}else{
					throw new RuntimeException("❌ نوع البيانات غير معروفة ");
				}
			}

			// 🔒 التحقق من وجود بيانات صالحة بعد التصفية
			if (empty($selseQtyCases) && empty($refundQtyCases)) throw new RuntimeException("❌ لا توجد سجلات صالحة بعد التصفية");
			
			if (empty($allOrderIds) || empty($sel_orderid) || empty($sel_deliveryid) || ($hashRefundOrder && empty($ref_orderid)) ||  ($hashRefundOrder && empty($ref_deliveryid)) ) throw new RuntimeException("❌ لا توجد أرقام أوامر صالحة");
			
		// ========== 🔒 المرحلة 1: تأقف الأوامر لمنع التضارب ==========
			// تأقف كل الأوامر بترتيب ثابت لمنع Deadlock
			sort($allOrderIds);
			foreach ($allOrderIds as $orderId) {
				$lockAcquired = $this->acquireOrderLock($orderId,2);
				if (!$lockAcquired) throw new RuntimeException("❌ تعذر تأقف الأمر {$orderId} - قد يكون قيد المعالجة من جهاز آخر");
				$lockedOrders[] = $orderId;
			}
			if(empty($lockedOrders)) throw new RuntimeException("❌  تعذر استكمال الاجراء لعدم القدرة على ايقاف العمل على اوامر البيع");
			
		// ========== التأكد من صلاحيات امر البيع ==========
			$statusCheck_sel = $this->check_order($sel_orderid, $sel_deliveryid,  ["osubid", "lastup", "freeze"]);
			$ordersISNotConditions[$sel_orderid] = $statusCheck_sel[$sel_orderid];
			if (!$statusCheck_sel[$sel_orderid]['lastup'] || !$statusCheck_sel[$sel_orderid]['freeze']) {
				$selseCheckMassage = "";
				if (!$statusCheck_sel[$sel_orderid]['lastup']) $selseCheckMassage = "❌ تعذر تنفيذ الإجراء - قام مستخدم آخر بتحديث بيانات أمر البيع";
				if (!$statusCheck_sel[$sel_orderid]['freeze']) $selseCheckMassage = "❌ لا يمكن تسليم أمر البيع - أمر البيع تم تجميده. في انتظار المندوب لرده إلى المخزن";
				$this->logManager->error($selseCheckMassage);
				throw new RuntimeException($selseCheckMassage);
			}
			unset($ordersISNotConditions[$sel_orderid]);

		// ========== 🔐 بدء المعاملة - ضمان أن كل التحديثات تنجح أو تتراجع معاً ==========
					
			$this->conn->beginTransaction();
			$this->logManager->info("بدأ معاملة تحديث " . count($records) . " سجل");

		// ========== 1. تحديث CUSTODY_PRODUCT (عهدة البيع) - استعلام واحد مجمع ==========
			
			if (empty($selseQtyCases)) throw new RuntimeException("❌ فشل تحديث custody_product -> selseQtyCases empty");
			$sql = "UPDATE custody_product 
						SET DELIVERED_QTY = CASE " . implode(' ', $selseQtyCases) . " ELSE DELIVERED_QTY END,
						PRO_STATUSE = CASE " . implode(' ', $selseStatusCases) . " ELSE PRO_STATUSE END,
						LAST_UPDATE = NOW(3) 
						WHERE (ORDER_ID, PRO_CODE, USER_ID) IN (" . implode(',', array_unique($selseWherePairs)) . ")";

			$affectedRows['custody_product'] = $this->conn->exec($sql);
			if ($affectedRows['custody_product'] === false) throw new RuntimeException("❌ فشل تحديث custody_product");
			
		// ========== 2. تحديث CUSTODY_RETURN_PRODUCT (عهدة المرتجع) - استعلام واحد مجمع ==========
			
			if($hashRefundOrder){
				if(empty($refundQtyCases)) throw new RuntimeException("❌ فشل تحديث custody_return_product -> refundQtyCases empty");
				$sql = "UPDATE custody_return_product SET RETURNED_QTY = CASE " . implode(' ', $refundQtyCases) . " ELSE RETURNED_QTY END, PRO_STATUSE = CASE " . implode(' ', $refundStatusCases) . " ELSE PRO_STATUSE END, LAST_UPDATE = NOW(3) WHERE (ORDER_ID, PRO_CODE, USER_ID) IN (" . implode(',', array_unique($refundWherePairs)) . ")";
				$affectedRows['custody_return_product'] = $this->conn->exec($sql);
				if ($affectedRows['custody_return_product'] === false) throw new RuntimeException("❌ فشل تحديث custody_return_product");
				$this->logManager->info("✅ تم تحديث {$affectedRows['custody_return_product']} صف في custody_return_product");
			}
			
			// ========== 4. تحديث ORDER_PRODUCT من جداول العهدة ==========
			if (empty($selseWherePairs)) throw new RuntimeException("❌ فشل تحديث order_product من custody_product");
			$uniquePairs = array_unique($selseWherePairs);
			
			$affectedRows['order_product_selse'] = $this->conn->exec("
				UPDATE order_product op 
				INNER JOIN custody_product cp ON op.ORDER_ID = cp.ORDER_ID AND op.PRO_CODE = cp.PRO_CODE 
					SET op.DELIVERED_QTY = cp.DELIVERED_QTY, 
						op.PRO_STATUSE = cp.PRO_STATUSE, 
						op.LAST_UPDATE = NOW(3) 
				WHERE (cp.ORDER_ID, cp.PRO_CODE, cp.USER_ID) IN (" . implode(',', $uniquePairs) . ")"
			);
			if ($affectedRows['order_product_selse'] === false) throw new RuntimeException("❌ فشل تحديث order_product من custody_product");
			$this->logManager->info("✅ تم تحديث {$affectedRows['order_product_selse']} صف في order_product للبيع");
		
			// ========== 4. تحديث ORDER_PRODUCT من جداول المرتجعات ==========
		
			if ($hashRefundOrder){
				if(empty($refundWherePairs)) throw new RuntimeException("❌ فشل تحديث order_product من custody_return_product");
				$uniquePairs = array_unique($refundWherePairs);
				$affectedRows['order_product_refund'] = $this->conn->exec(
					"UPDATE order_product op
	                 INNER JOIN custody_return_product crp ON op.ORDER_ID = crp.ORDER_ID AND op.PRO_CODE = crp.PRO_CODE
	                 	SET op.RETURNED_QTY = crp.RETURNED_QTY,
	                 		op.PRO_STATUSE = crp.PRO_STATUSE, 
	                 		op.LAST_UPDATE = NOW(3)
	                 WHERE (crp.ORDER_ID, crp.PRO_CODE, crp.USER_ID) IN (" . implode(',', $uniquePairs) . ")"
				);
				if ($affectedRows['order_product_refund'] === false) throw new RuntimeException("❌ فشل تحديث order_product من custody_return_product");
				$this->logManager->info("✅ تم تحديث {$affectedRows['order_product_refund']} صف في order_product للمرتجع");
			}
				
			// ========== 5. تحديث ORDERS (الأوامر الرئيسية)
			
			if(empty($allOrderIds)) throw new RuntimeException("❌ فشل تحديث order_product من custody_return_product");
			$affectedRows['orders_selse'] = $this->conn->exec(
				//	"UPDATE orders SET EXISTS_ID = 10, ORDER_STATUS = {$statusCode}, LAST_UPDATE = NOW(3), DELIVERED_DATE = NOW(3) WHERE ORDER_ID = {$orderId}"
				"UPDATE orders O
				JOIN (
					    SELECT 
					        ORDER_ID,
					        CASE
					            WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) > SUM(RETURNED_QTY) THEN 7
					            WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) = SUM(RETURNED_QTY) THEN 6
					            WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = 0 THEN 5
					            WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) < SUM(QTY) AND SUM(DELIVERED_QTY) > 0 THEN 4
					            WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = SUM(QTY) THEN 3
					            ELSE NULL
					        END AS ORDER_STATUS
					    FROM order_product
					    WHERE ORDER_ID IN (" . implode(',', $allOrderIds) . ")
					    GROUP BY ORDER_ID
					) OP ON O.ORDER_ID = OP.ORDER_ID
					SET EXISTS_ID = 10,
						O.ORDER_STATUS = OP.ORDER_STATUS,
					 	O.LAST_UPDATE = NOW(3), 
					 	O.DELIVERED_DATE = NOW(3)
				WHERE O.ORDER_ID IN (" . implode(',', $allOrderIds) . ");"
			);
			if ($affectedRows['orders_selse'] === false) throw new RuntimeException("❌ فشل تحديث orders للطلب {$orderId}");
			$this->logManager->info("✅ تم تحديث أمر البيع ".implode(',', $allOrderIds) );
			
			// ========== 3. تحديث الأوامر والجداول الرئيسية ==========
			$statuseArray = [];
			foreach ($orderUpdates as $orderId => $data) {
				// 🔒 حساب حالة الأمر
				$deliveredForStatus = $data['type'] === 'REFUND' ? $data['delivered'] : null;
				$orderStatus = $this->getOrderStatus($data['input'], $data['required'], $deliveredForStatus);
				
				if (!$orderStatus) {
					throw new RuntimeException("❌ فشل حساب حالة الأمر {$orderId}");
				}
				
				$statusCode = (int)$orderStatus['statuse'];
				$deliveryId = $data['delivery_id'];
				
				$statuseArray[] = $orderStatus['statuse'];
				
				if ($data['type'] === 'SELSE') {
					// 🔄 تحديث CUSTODY_ORDERS (عهدة أوامر البيع)
					$affectedRows['custody_orders'] = $this->conn->exec("UPDATE custody_orders SET CUST_STAT = 'DELIVERED', ORDER_STATUSE = {$statusCode}, LAST_UPDATE = NOW(3) WHERE ORDER_ID = {$orderId} AND USER_ID = {$deliveryId}");
					if ($affectedRows['custody_orders'] === false) throw new RuntimeException("❌ فشل تحديث custody_orders للطلب {$orderId}");
				}else if($data['type'] === 'REFUND'){
					// 🔄 تحديث CUSTODY_RETURN_ORDERS (عهدة أوامر المرتجع)
					$affectedRows['custody_return_orders'] = $this->conn->exec("UPDATE custody_return_orders SET CUST_STAT = 'RETURNED', ORDER_STATUSE = {$statusCode}, LAST_UPDATE = NOW(3) WHERE ORDER_ID = {$orderId} AND USER_ID = {$deliveryId}");
					if ($affectedRows['custody_return_orders'] === false) throw new RuntimeException("❌ فشل تحديث custody_return_orders للطلب {$orderId}");
				}else{
					throw new RuntimeException("❌ نوع البيانات غير معروف {$orderId}");
				}
				// 📝 تجهيز سجلات التدقيق
				$logText = $this->conn->quote("تم التحديث من {$this->user_group} * {$orderStatus['stattxt']}");
				$logValues[] = "({$orderId}, " . $_SESSION['USER_ID'] . ", {$logText})";
				$movValues[] = "({$statusCode}, {$deliveryId}, " . $_SESSION['USER_ID'] . ", {$orderId})";
			}

		//	$maxValue = max($statuseArray);

			$returnOrStatus = $allAppQty > 0 ? false:true;// ($maxValue >= 4 && $maxValue <= 7) ? true:false;
			
			// ========== 5. معالجة الارصده للعملاء ==========
			
			$finance = new finance_BE($this->conn);
			$this->logManager->info("معالجة ارصدة العملاء");
			foreach ($orderUpdates as $orderId => $data) {
				if ($data['type'] === 'SELSE') {
					$rest = $finance->restClientdebt($orderId);
					if (!$rest['statuse']) throw new RuntimeException("💰 {$rest['message']} : {$orderId}");
					$this->logManager->info("✅  تم معالجة دين العميل: {$orderId}");
				} else if ($data['type'] === 'REFUND') {
					$deduct = $finance->reduceClientCredit(null, $orderId);
					if (!$deduct['statuse']) {
						$this->logManager->error("💰❌  فشل خصم رصيد العميل: {$orderId}");
						throw new RuntimeException("💰 فشل خصم رصيد العميل: {$orderId}");
					}
					$this->logManager->info("✅  تم معالجة دين العميل للمرتجعات: {$orderId}");
				}
			}
			
			// ========== 6. إضافة الحركة المخزنية ==========
			
			
			
			
			// ========== 7. إضافة سجلات التدقيق والحركات ==========
			
			if (!empty($logValues)) {
				$affectedRows['order_logs'] = $this->conn->exec("INSERT INTO `{$this->orders_log_Tbl}` (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) VALUES " . implode(',', $logValues));
				if ($affectedRows['order_logs'] === false) throw new RuntimeException("❌ فشل إضافة سجلات النظام");
				$this->logManager->info("✅ تم إضافة {$affectedRows['order_logs']} سجل في order_logs");
			}

			if (!empty($movValues)) {
				$affectedRows['shipping_movement'] = $this->conn->exec("INSERT INTO `shipping_movement` (`movement_type`, `delivery_id`, `user_id`, `shipment_orders`) VALUES " . implode(',', $movValues));
				if ($affectedRows['shipping_movement'] === false) throw new RuntimeException("❌ فشل إضافة حركات الشحن");
				$this->logManager->info("✅ تم إضافة {$affectedRows['shipping_movement']} حركة في shipping_movement");
			}
			
			// ========== ✅ تأكيد المعاملة - كل التحديثات نجحت ==========
			$this->conn->commit();
			$executionTime = microtime(true) - $startTime;
			$this->logManager->info("🎉 تم تحديث " . count($orderUpdates) . " أمر في {$executionTime} ثانية");
			$respobnseMassage ="تم الحفظ بنجاح";
			$this->logManager->success("✅ تم تأكيد معاملة التحديث بنجاح");
			
			return ['success',
					'نجح',
					$respobnseMassage,
					$ordersISNotConditions,
					$returnOrStatus,
					$statuseArray];

		} catch (Exception $e) {
			// ========== 🔄 التراجع عن كل التحديثات في حالة خطأ ==========
			if ($this->conn->inTransaction()) {
				$this->conn->rollBack();
				$this->logManager->error("🔄 تم التراجع عن كل التحديثات بسبب الخطأ");
			}

			$executionTime = microtime(true) - $startTime;
			$this->logManager->error("❌ فشل في {$executionTime} ثانية: " . $e->getMessage());

			return ["error","خطاء:", $e->getMessage(),$ordersISNotConditions];
		} finally {
			// ========== 🔓 تحرير كل الأقفال بغض النظر عن النتيجة ==========
			$this->releaseOrderLocks($lockedOrders);
		}
	}*/	
	/**
	 * انشاء قفل لامر البيع
 	*/
	public function lockOrdersForUpdate(array $orderIds): void{
	    if (empty($orderIds)) {
	        throw new Exception('لا توجد أوامر للقفل');
	    }
	
	    // مهم جدًا لمنع deadlock
	    sort($orderIds, SORT_NUMERIC);
	
	    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	
	    $stmt = $this->conn->prepare("
	        SELECT ORDER_ID
	        FROM orders
	        WHERE ORDER_ID IN ($placeholders)
	        FOR UPDATE
	    ");
	
	    if (!$stmt->execute($orderIds)) {
	        throw new Exception('فشل قفل أوامر البيع');
	    }
	}
	/**
	 * 🔒 دالة جديدة: أقفل المخزون والحركات
	 */
	public function acquireInventoryLocks(array $items): bool {
	    try {
	        // 1. أقفل جدول حركات المخزون الغير معالجة
	        $lockStmt = $this->conn->prepare("
	            SELECT IPM_ID 
	            FROM inventory_product_movement 
	            WHERE IS_PROCESSED = FALSE 
	            FOR UPDATE
	        ");
	        $lockStmt->execute();
	        $lockStmt->closeCursor();
	        
	        // 2. أقفل المنتجات المتأثرة في المخازن
	        $productCodes = [];
	        foreach ($items as $row) {
	            $productCodes[] = trim($row['PRO_CODE']);
	        }
	        $productCodes = array_unique($productCodes);
	        
	        if (!empty($productCodes)) {
	            $placeholders = str_repeat('?,', count($productCodes) - 1) . '?';
	            
	            $productLockStmt = $this->conn->prepare("
	                SELECT PRO_CODE 
	                FROM stores_product 
	                WHERE PRO_CODE IN ($placeholders) 
	                FOR UPDATE
	            ");
	            
	            $productLockStmt->execute($productCodes);
	            $productLockStmt->closeCursor();
	        }
	        
	        $this->logManager->info("🔒 تم تأقف المخزون بنجاح لـ " . count($productCodes) . " منتج");
	        return true;
	        
	    } catch (Exception $e) {
	        $this->logManager->error("❌ فشل في تأقف المخزون: " . $e->getMessage());
	        return false;
	    }
	}
	
	/**
	 * 🔒 دالة جديدة: Lock عام للمخزون (بديل)
	 */
	public function acquireGlobalInventoryLock(): bool {
	    try {
	        $stmt = $this->conn->prepare("SELECT GET_LOCK('global_inventory_lock', 2) as locked");
	        $stmt->execute();
	        $result = $stmt->fetch(PDO::FETCH_ASSOC);
	        
	        if ($result && $result['locked'] == 1) {
	            return true;
	        }
	        
	        // قفل المخزون مشغول - نرمي استثناء
	        throw new Exception(
	            "⏳ **المخزون قيد الاستخدام**\n\n" .
	            "هناك عملية تحديث أخرى جارية حالياً على المخزون.\n" .
	            "يرجى الانتظار بضع ثوانٍ ثم المحاولة مرة أخرى.\n\n" .
	            "💡 **نصيحة**: حاول إعادة المحاولة بعد 5-10 ثوانٍ."
	        );
	        
	    } catch (PDOException $e) {
	        // خطأ في قاعدة البيانات
	        throw new Exception("خطأ في الاتصال بقاعدة البيانات");
	    }
	}
	
	/**
	 * الغاء اقفال اوامر البيع
 	*/
	public function releaseOrderLocks(array $lockedOrders): void {
	    $releasedCount = 0;
	    
	    foreach ($lockedOrders as $orderId) {
	        try {
	            $lockName = "order_lock_{$orderId}";
	            
	            // استخدم prepared statement علشان أكثر أماناً
	            $stmt = $this->conn->prepare("SELECT RELEASE_LOCK(?) as released");
	            $stmt->execute([$lockName]);
	            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	            
	            $this->logManager->info("🔓 تم تحرير تأقف الأمر {$orderId}");
	            $releasedCount++;
	            
	        } catch (Exception $e) {
	            $this->logManager->error("❌ فشل تحرير تأقف الأمر {$orderId}: " . $e->getMessage());
	        }
	    }
	    
	    $this->logManager->info("🔓 تم تحرير {$releasedCount} من أصل " . count($lockedOrders) . " أقفال");
	}

	/**
	 * 🔓 دالة جديدة: تحرير Lock المخزون العام
	 */
	public function releaseGlobalInventoryLock(): void {
	    try {
	        $stmt = $this->conn->prepare("SELECT RELEASE_LOCK('global_inventory_lock') as released");
	        $stmt->execute();
	        $this->logManager->info("🔓 تم تحرير تأقف المخزون العام");
	    } catch (Exception $e) {
	        $this->logManager->error("❌ فشل تحرير تأقف المخزون العام: " . $e->getMessage());
	    }
	}
	
	public function batchCheckStockWithTempTables(array $orderIds, string $maxLastUpdate, int $statuse, int $storeId, int $deliveryId) {
	    try {
	        if(!$this->acquireGlobalInventoryLock()){
	            throw new Exception('يقوم النظام بمعالجة المخزون لعمليه اخرى في الوقت الحالي، يرجى إعادة المحاولة في وقت لاحق');
	        }
	        
	        $placeholders = implode(',', array_map('intval', $orderIds));
	        
	        // 1. إنشاء جداول مؤقتة
	        $this->conn->exec("CREATE TEMPORARY TABLE tmp_batch_check (
	            seq INT AUTO_INCREMENT PRIMARY KEY,
	            ORDER_ID INT UNIQUE,
	            osubid   VARCHAR(150),
	            statuse  INT,
	            print    INT,
	            freeze   INT,
	            oserv    INT,
	            store    INT,
	            lastup   INT,
	            can_proceed INT
	        ) ENGINE=MEMORY");
	
	        $this->conn->exec("CREATE TEMPORARY TABLE tmp_batch_products (
	            seq INT,
	            ORDER_ID INT,
	            PRO_CODE VARCHAR(50),
	            QTY INT,
	            can_fulfill INT DEFAULT 0,
	            INDEX idx_seq (seq),
	            INDEX idx_product (PRO_CODE),
	            INDEX idx_seq_product (seq, PRO_CODE)
	        ) ENGINE=MEMORY");
	
	        $this->conn->exec("CREATE TEMPORARY TABLE tmp_stock_tracker (
	            PRO_CODE VARCHAR(50) PRIMARY KEY,
	            current_stock INT
	        ) ENGINE=MEMORY");
	
	        // 2. إدخال الأوامر مرتبة
	        $insertOrdersSql = "
	            INSERT INTO tmp_batch_check (ORDER_ID, osubid, print, statuse, oserv, freeze, store, lastup)
	            SELECT 
	                O.ORDER_ID,
	                O.SUB_ORDER_ID AS osubid,
	                CASE WHEN O.PRINT = 0 THEN 0 ELSE 1 END AS print,
	                CASE WHEN O.ORDER_STATUS = ? THEN 0 ELSE 1 END AS statuse,
	                CASE WHEN Q.OS_STATUSE IS NOT NULL AND Q.OS_STATUSE = 0 THEN 0 ELSE 1 END AS oserv,
	                CASE WHEN co.CUST_STAT != 'FREEZE' OR co.CUST_STAT IS NULL THEN 1 ELSE 0 END AS freeze,
	                0 AS store, 
	                CASE WHEN O.LAST_UPDATE > ? THEN 0 ELSE 1 END AS lastup
	            FROM orders AS O
	            LEFT JOIN custody_orders CO ON O.ORDER_ID = CO.ORDER_ID AND USER_ID = ?
	            LEFT JOIN (SELECT ORDER_ID, OS_STATUSE FROM order_service WHERE OS_STATUSE = 0 GROUP BY ORDER_ID) AS Q ON O.ORDER_ID = Q.ORDER_ID
	            WHERE O.ORDER_ID IN ($placeholders)
	            GROUP BY O.ORDER_ID, O.SUB_ORDER_ID, O.PRINT, O.ORDER_STATUS, O.LAST_UPDATE, CO.CUST_STAT, Q.OS_STATUSE
	            ORDER BY O.DATE_CREATED, O.ORDER_ID";
	        
	        $stmt = $this->conn->prepare($insertOrdersSql);
	        $stmt->execute([$statuse, $maxLastUpdate, $deliveryId]);
	
	        // 3. إدخال المنتجات مرتبة حسب تسلسل الأوامر
	        $this->conn->exec("
	            INSERT INTO tmp_batch_products (seq, ORDER_ID, PRO_CODE, QTY)
	            SELECT 
	                bc.seq,
	                bc.ORDER_ID,
	                op.PRO_CODE,
	                SUM(op.QTY) as QTY
	            FROM tmp_batch_check bc
	            JOIN order_product op ON bc.ORDER_ID = op.ORDER_ID
	            GROUP BY bc.seq, bc.ORDER_ID, op.PRO_CODE
	            ORDER BY bc.seq, bc.ORDER_ID, op.PRO_CODE
	        ");
	
	        // 4. إدخال المخزون الأولي
	        $insertStockSql = "
	            INSERT INTO tmp_stock_tracker (PRO_CODE, current_stock)
	            SELECT DISTINCT
	                bp.PRO_CODE,
	                COALESCE(sp.STO_QTY, 0) as initial_stock
	            FROM tmp_batch_products bp
	            LEFT JOIN stores_product sp ON bp.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = ?
	            ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)";
	        
	        $stmt = $this->conn->prepare($insertStockSql);
	        $stmt->execute([$storeId]);
	
	        // 5. حساب المخزون التراكمي باستخدام PHP (الأكثر موثوقية)
	        // جلب جميع المنتجات مرتبة حسب التسلسل والمنتج
	        $stmt = $this->conn->query("
	            SELECT bp.seq, bp.PRO_CODE, bp.QTY 
	            FROM tmp_batch_products bp
	            ORDER BY bp.seq, bp.PRO_CODE
	        ");
	        $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
	        
	        // جلب المخزون المتاح لكل منتج
	        $stmt = $this->conn->query("SELECT PRO_CODE, current_stock FROM tmp_stock_tracker");
	        $availableStock = [];
	        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	            $availableStock[$row['PRO_CODE']] = (int)$row['current_stock'];
	        }
	        
	        // حساب can_fulfill لكل منتج
	        $updates = [];
	        foreach ($allProducts as $product) {
	            $seq = (int)$product['seq'];
	            $proCode = $product['PRO_CODE'];
	            $qty = (int)$product['QTY'];
	            
	            // تحقق من وجود المنتج في المخزون
	            if (!isset($availableStock[$proCode])) {
	                $canFulfill = 0;
	            } else {
	                // تحقق إذا كان المخزون كافي
	                if ($availableStock[$proCode] >= $qty) {
	                    $canFulfill = 1;
	                    // اخصم من المخزون
	                    $availableStock[$proCode] -= $qty;
	                } else {
	                    $canFulfill = 0;
	                }
	            }
	            
	            $updates[$seq] = $canFulfill;
	        }
	        
	        // تحديث قاعدة البيانات بجميع النتائج في مرة واحدة
	        if (!empty($updates)) {
	            $caseStatements = [];
	            $params = [];
	            $index = 0;
	            
	            foreach ($updates as $seq => $canFulfill) {
	                $caseStatements[] = "WHEN ? THEN ?";
	                $params[] = $seq;
	                $params[] = $canFulfill;
	                $index++;
	            }
	            
	            $caseSql = implode(' ', $caseStatements);
	            $updateSql = "
	                UPDATE tmp_batch_products 
	                SET can_fulfill = CASE seq 
	                    $caseSql
	                    ELSE can_fulfill 
	                END
	                WHERE seq IN (" . implode(',', array_keys($updates)) . ")
	            ";
	            
	            $stmt = $this->conn->prepare($updateSql);
	            $stmt->execute($params);
	        }
	
	        // 6. تحديث حالة المخزون لكل أمر
	        $this->conn->exec("
	            UPDATE tmp_batch_check bc
	            JOIN (
	                SELECT 
	                    ORDER_ID,
	                    CASE WHEN MIN(can_fulfill) = 1 THEN 1 ELSE 0 END as all_products_ok
	                FROM tmp_batch_products
	                GROUP BY ORDER_ID
	            ) bp ON bc.ORDER_ID = bp.ORDER_ID
	            SET bc.store = bp.all_products_ok
	        ");
	
	        // 7. تحديد الأوامر التي يمكن المتابعة بها
	        $this->conn->exec("
	            UPDATE tmp_batch_check 
	            SET can_proceed = (
	                statuse = 1 AND
	                print   = 1 AND
	                freeze  = 1 AND
	                oserv   = 1 AND
	                store   = 1 AND
	                lastup  = 1
	            )
	        ");
	
	        // 8. جلب النتائج
	        $resultsSql = "
	            SELECT 
	                ORDER_ID,
	                osubid,
	                statuse,
	                print,
	                freeze,
	                oserv,
	                store,
	                lastup,
	                can_proceed
	            FROM tmp_batch_check
	            ORDER BY seq";
	        
	        $stmt = $this->conn->prepare($resultsSql);
	        $stmt->execute();
	        
	        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
	        
	        $total = count($results);
	        $success = array_sum(array_column($results, 'can_proceed'));
	        $failed = $total - $success;
	        
	        $message = $failed > 0 
	            ? "⚠️ اجمالى المطلوب تسليمه للمندوب $total أمر: $success ناجح، $failed مرفوض. راجع قائمة الاوامر المرفوضه ."
	            : "✅ تم تسليم للمندوب عدد $total أمر بنجاح!.";
	            
	        $ordersISNotConditions = [];
	        $ordersISConditions = [];
	        $ordersAllConditions = [];
	        
	        foreach ($results as $record) {
	            $conditionsArray = [
	                'osubid'    => $record['osubid'] ?? 'غير متوفر',
	                'print'     => $record['print'],
	                'statuse'   => $record['statuse'],
	                'oserv'     => $record['oserv'],
	                'lastup'    => $record['lastup'],
	                'store'     => $record['store'],
	                'freeze'    => $record['freeze'],
	                'shipment'  => 1
	            ];
	            $ordersAllConditions[$record['ORDER_ID']] = $conditionsArray;
	            if($record['can_proceed']) $ordersISConditions[$record['ORDER_ID']] = $conditionsArray;
	            if(!$record['can_proceed']) $ordersISNotConditions[$record['ORDER_ID']] = $conditionsArray;
	        }
	        
	        // 9. إرجاع النتائج
	        return [
	            'statuse' => $total == $success ? 'success' : ($success > 0 && $success < $total ? 'warning' : 'error'),
	            'osucceeded' => array_column(array_filter($results, fn($o) => $o['can_proceed'] == 1), 'ORDER_ID'),
	            'orejected' => array_column(array_filter($results, fn($o) => $o['can_proceed'] == 0), 'ORDER_ID'),
	            'failed_count' => $total,
	            'success_count' => $success,
	            'ordersISNotConditions' => $ordersISNotConditions,
	            'ordersISConditions' => $ordersISConditions,
	            'ordersAllConditions' => $ordersAllConditions,
	            'message' => $message
	        ];
	
	    } catch (Exception $e) {
	        // تنظيف الجداول المؤقتة في حالة الخطأ
	        /*$this->conn->exec("DROP TEMPORARY TABLE IF EXISTS tmp_batch_check");
	        $this->conn->exec("DROP TEMPORARY TABLE IF EXISTS tmp_batch_products");
	        $this->conn->exec("DROP TEMPORARY TABLE IF EXISTS tmp_stock_tracker");*/
	        
	        throw new Exception("خطأ في فحص حالة اوامر البيع: " . $e->getMessage());
	    } 
	}

	//=============وظائف الاساسية لهذا الكائن===========

	//عدد المنتجات الموجوده فى اوامر البيع التي يتم شحنها مع المندوب الواحد
	public function countProducts () {
		$sql = $this->query();
		$stmtCountProduct = $this->conn->prepare($sql['countProduct']);
		$stmtCountProduct->bindValue(':oid', implode(',', array_keys($this->orderobj)), PDO::PARAM_STR);
		$stmtCountProduct->execute();
		$result = $stmtCountProduct->fetch(PDO::FETCH_ASSOC);
		return array('0' => $result['orderCount'], '1' => $result['proCount']);
	}
	 /**
	 * ============================================================================
	 *  regEmployeeCustody()
	 * ============================================================================
	 * 
	 * 📌 الوصف العام:
	 * ----------------------------------------------------------------------------
	 * دالة مسئولة عن تسليم أوامر البيع إلى المندوب (عهدة)، مع ضمان:
	 * - عدم تسليم نفس الأمر أكثر من مرة
	 * - عدم صرف مخزون زائد
	 * - سلامة البيانات في حالة التزامن العالي (Concurrency)
	 * - تنفيذ جميع العمليات داخل Transaction واحدة (Atomic Operation)
	 *
	 * ----------------------------------------------------------------------------
	 * 🎯 الأهداف الرئيسية:
	 * ----------------------------------------------------------------------------
	 * 1- تسليم أوامر بيع صالحة فقط للمندوب
	 * 2- قفل الأوامر والمخزون أثناء التنفيذ
	 * 3- تسجيل العهدة (أوامر + منتجات)
	 * 4- تسجيل حركة المخزون (Audit + Accounting)
	 * 5- دعم التنفيذ المتوازي لأكثر من 200 مستخدم بدون تعارض
	 *
	 * ----------------------------------------------------------------------------
	 * 🧠 التصميم المعماري (Architecture):
	 * ----------------------------------------------------------------------------
	 * - Transactional Domain Service
	 * - كل المنطق التجاري داخل Transaction واحدة
	 * - قاعدة البيانات هي مصدر الحقيقة
	 * - Fail-Fast Strategy (NOWAIT)
	 *
	 * ----------------------------------------------------------------------------
	 * 🔒 استراتيجية القفل (Locking Strategy):
	 * ----------------------------------------------------------------------------
	 * - استخدام Row-Level Locks فقط
	 * - قفل الأوامر:
	 *      orders + order_product
	 *      باستخدام: FOR UPDATE NOWAIT
	 *
	 * - قفل المخزون:
	 *      stores_product
	 *      باستخدام: FOR UPDATE NOWAIT
	 *
	 * - في حالة وجود قفل مسبق:
	 *      يتم الفشل فوراً بدون انتظار
	 *      (No Spinlock - No Sleep - No Resource Waste)
	 *
	 * ----------------------------------------------------------------------------
	 * ⚙️ تسلسل التنفيذ (Execution Flow):
	 * ----------------------------------------------------------------------------
	 * 1) تنظيف بيانات أوامر البيع:
	 *    - تحويل القيم إلى أرقام صحيحة
	 *    - إزالة القيم الصفرية والسالبة
	 *    - إزالة التكرار
	 *    - التحقق من الحد الأقصى (Batch Size)
	 *
	 * 2) التحقق من وجود مخزن صرف للفرع
	 *
	 * 3) بدء Transaction
	 *
	 * 4) قفل الأوامر والمنتجات المرتبطة بها (FOR UPDATE NOWAIT)
	 *
	 * 5) قفل المخزون الخاص بالمنتجات المطلوبة (FOR UPDATE NOWAIT)
	 *
	 * 6) التحقق من شروط التسليم:
	 *    - حالة الطلب
	 *    - الطباعة
	 *    - الخدمات
	 *    - آخر تحديث
	 *    - التجميد
	 *    - الشحن
	 *
	 * 7) تقسيم الأوامر إلى:
	 *    - أوامر ناجحة
	 *    - أوامر غير مستوفية للشروط
	 *
	 * 8) في حالة عدم وجود أوامر ناجحة:
	 *    - Rollback
	 *    - إرجاع رسالة خطأ
	 *
	 * 9) تحديث أوامر البيع الناجحة:
	 *    - EXISTS_ID
	 *    - SHIPPING_DATE
	 *    - DELIVERY_ID
	 *    - LAST_UPDATE
	 *
	 * 10) تسجيل العهدة:
	 *     - custody_orders
	 *     - custody_product
	 *
	 * 11) معالجة أوامر الاستبدال (إن وجدت):
	 *     - custody_return_orders
	 *     - custody_return_product
	 *
	 * 12) تسجيل حركة الشحن:
	 *     - shipping_movement
	 *
	 * 13) تسجيل حركة المخزون:
	 *     - inventory_movement
	 *     - inventory_product_movement
	 *
	 * 14) تحديث المخزون الفعلي:
	 *     - CALL update_stores()
	 *
	 * 15) تسجيل سجلات الأوامر:
	 *     - ORDERS_LOG
	 *
	 * 16) Commit في حالة النجاح
	 *     Rollback في حالة أي خطأ
	 *
	 * ----------------------------------------------------------------------------
	 * 🔁 سيناريو التزامن (Concurrency Scenario):
	 * ----------------------------------------------------------------------------
	 * - في حالة تنفيذ نفس الإجراء من مستخدمين في نفس الميكرو ثانية:
	 *   ✔ مستخدم واحد فقط يحصل على القفل
	 *   ✖ الآخر يفشل فوراً (NOWAIT)
	 *   ✔ لا يوجد استهلاك موارد زائد
	 *
	 * ----------------------------------------------------------------------------
	 * 📈 الأداء (Performance):
	 * ----------------------------------------------------------------------------
	 * - Batch Limit = 100 Order
	 * - Indexed Queries
	 * - No Table Locks
	 * - No Busy Waiting
	 * - مناسب لـ 200+ مستخدم متزامن
	 *
	 * ----------------------------------------------------------------------------
	 * 🔐 مستوى الأمان:
	 * ----------------------------------------------------------------------------
	 * - Transaction كاملة
	 * - Rollback تلقائي عند أي Exception
	 * - منع التكرار والازدواجية
	 * - Audit كامل لحركات المخزون
	 *
	 * ----------------------------------------------------------------------------
	 * 🧾 القيم المرجعة:
	 * ----------------------------------------------------------------------------
	 * Success:
	 *   ['success', 'نجح', 'تم تسليم العهدة للمندوب بنجاح', تفاصيل الأوامر المرفوضة إن وجدت]
	 *
	 * Error:
	 *   ['error', 'خطأ', رسالة الخطأ, تفاصيل الأوامر غير المستوفية]
	 *
	 * ============================================================================
	 */
	public function regEmployeeCustody () {
		try {
			$lockedOrders = [];
			$oISNotConditions = [];
			$batchSize  = 250; // الحد الأقصى لأوامر البيع في المعاملة الواحدة
			
			// تنظيف مصفوفة أوامر البيع: تحويل للقيم الصحيحة، إزالة الصفر والسالب، وإزالة التكرار
			$orderIds = array_values(array_unique(array_filter(
			    array_map('intval', $this->orderobj),
			    fn($v) => $v > 0
			)));
			
			// 1️⃣ التحقق من وجود أوامر بيع
			if (empty($orderIds)) {
			    return ['error', '', 'لا يوجد بيانات لمعالجتها، برجاء اختيار أمر بيع واحد أو أكثر'];
			}
			
			// 2️⃣ التحقق من الحد الأقصى لعدد الأوامر في المعاملة
			if (count($orderIds) > $batchSize) {
			    throw new Exception('عذراً، لا يمكن تنفيذ الإجراء - الحد الأقصى لعدد الأوامر هو: ' . $batchSize);
			}
			
			// 3️⃣ جلب بيانات مخزن الصرف للفرع إذا لم يتم تحديده مسبقاً
			if (!$this->store_id) {
			    $stmt = $this->conn->prepare("
			        SELECT STO_ID 
			        FROM STORES 
			        WHERE STTY_ID = 2 AND BRANCH_ID = ? 
			        LIMIT 1
			    ");
			    $stmt->execute([(int)$this->branch_id]);
			    $row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			    if ($row && !empty($row['STO_ID'])) {
			        $this->store_id = (int)$row['STO_ID'];
			    } else {
			        throw new Exception("لم يتم العثور على مخزن للفرع المحدد");
			    }
			}
			
		//===========بداء معاملة الاتصال بقاعدة البيانات==============
			$this->conn->beginTransaction();
		
		//============= قفل الأوامر والمنتجات FOR UPDATE =========
	    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	    $stmtLock = $this->conn->prepare("
	        SELECT o.ORDER_ID, op.PRO_CODE
		        FROM orders o
		        JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
		        WHERE o.ORDER_ID IN ($placeholders)
		        FOR UPDATE NOWAIT
	    ");
	    $stmtLock->execute($orderIds);
		
	    // 🔒 2. أقفل المخزون والحركات (الجديد)
        $inventoryLocked = $this->acquireGlobalInventoryLock();
        if (!$inventoryLocked) {
	       throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
        }
	
		// ================== التحقق من الأوامر الناجحة ==================
			$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
			$stmt = $this->conn->prepare("
				SELECT 
				    O.ORDER_ID,
				    O.SUB_ORDER_ID AS osubid,
				    IF(O.ORDER_STATUS = 2, 1, 0) AS statuse,
				    O.PRINT AS print,
				    IF(Q.ORDER_ID IS NULL, 1, 0) AS oserv,
				    IF(O.LAST_UPDATE <= ?, 1, 0) AS lastup,
				    IF(CO.CUST_STAT = 'FREEZE',0,1) AS freeze,
				    IF(CO2.CUST_STAT IS NOT NULL,0,1) AS shipment
				FROM orders O
				LEFT JOIN (
				    SELECT DISTINCT ORDER_ID
				    FROM order_service
				    WHERE OS_STATUSE = 0
				) Q ON O.ORDER_ID = Q.ORDER_ID
				LEFT JOIN custody_orders CO ON O.ORDER_ID = CO.ORDER_ID AND CO.CUST_STAT = 'FREEZE' AND CO.USER_ID = ?
				LEFT JOIN custody_orders CO2 ON O.ORDER_ID = CO2.ORDER_ID AND CO2.CUST_STAT <> 'FREEZE'
				WHERE O.ORDER_ID IN ($placeholders)
					AND (O.ORDER_STATUS <> 2
				    	OR O.PRINT = 0 
				    	OR Q.ORDER_ID IS NOT NULL
						OR O.LAST_UPDATE > ?
						OR CO.ORDER_ID IS NOT NULL
				    	OR CO2.ORDER_ID IS NOT NULL )
				ORDER BY O.DATE_CREATED
			");
			$params = array_merge([$this->last_update, $this->delivery_id], $orderIds, [$this->last_update]);
			$stmt->execute($params);
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			// ================== التحليل العام ==================
			$total        = count($orderIds);
			$failedCount  = count($results);
			$successCount = $total - $failedCount;
			$statuse = $successCount === 0 ? 'error' : ($successCount < $total ? 'warning' : 'success');
			
			// ================== أوامر فاشلة / ناجحة ==================
			$failedOrderIds = array_unique(array_column($results, 'ORDER_ID'));
			$failed = array_flip($failedOrderIds);
			$successOrderIds = array_values(array_filter($orderIds, fn($id) => !isset($failed[$id])));
			
			if($failedCount == 0 && $successCount > 0){
				$message = "
				        <div style='text-align:center'>
				            <b>✅ تم تسليم جميع الأوامر للمندوب بنجاح</b><br><br>
				            📦 <b>إجمالي الأوامر:</b> {$total}
				        </div>
				      ";
			}else if($failedCount > 0 && $successCount > 0){
				$message =  "
				        <div style='text-align:center'>
				            <b>تم التسليم ولكن هناك اوامر لم تستوفى الشروط</b><br>
				            📦 <b>إجمالي عدد الأوامر المطلوب تسلمها :</b> {$total}<br>
				            ✅ <b>أوامر تم تسليمها للمندوب بنجاح:</b> {$successCount}<br>
				            ❌ <b>أوامر لا تستوفى شروط التسليم:</b> {$failedCount}<br><br>
				            <span style='color:#d33'>يرجى مراجعة قائمة الأوامر المرفوضة.</span>
				        </div>
				      ";
			}else{
				$message = "
				        <div style='text-align:center'>
				         <b>فشل تسليم اوامر البيع للمندوب</b><br><br>
				            📦 <b>إجمالي عدد الأوامر المطلوب تسلمها :</b> {$total}<br>
				            ❌ <b>أوامر لا تستوفى شروط التسليم:</b> {$failedCount}<br><br>
				        	<span style='color:#d33'>يرجى مراجعة قائمة الأوامر المرفوضة.</span>
				        </div>
				      ";
			}
		// ================== تجميع النتائج ==================
			foreach ($results as $row) {
			    $oISNotConditions[$row['ORDER_ID']] = [
			        'osubid'   => $row['osubid'] ?? 'غير متوفر',
			        'print'    => (int)$row['print'],
			        'statuse'  => (int)$row['statuse'],
			        'oserv'    => (int)$row['oserv'],
			        'lastup'   => (int)$row['lastup'],
			        //'store'    => (int)($row['store'] ?? 0),
			        'freeze'   => (int)($row['freeze'] ?? 0),
			        'shipment' => (int)($row['shipment'] ?? 0),
			    ];
			}
			
			if(empty($statuse)){
				throw new Exception('فشل تنفيذ الاجراء لاسباب غير معروفه برجاء الاتصال بالدعم الفنى');
			}
			
			if($statuse == 'error' || $successCount == 0){
				throw new Exception($message);
			}
		//===================جلب بيانات اوامر الاستبدال===========
			$placeholders = implode(',', array_fill(0, count($successOrderIds), '?'));
			$stCustEX = $this->conn->prepare("
				SELECT 
					EXCHANGE,
					ADDRESS,
					ORDER_STATUS 
				FROM orders 
				WHERE ORDER_ID IN($placeholders) AND EXCHANGE IS NOT NULL;
			");
			
			if (!$stCustEX->execute($successOrderIds)) {
				throw new Exception("غير قادر على قرأة اوامر الاستبدال");
			}
			
			$resultsEx = $stCustEX->fetchAll(PDO::FETCH_ASSOC);
			$successOrderExIds =[];
			
			if($resultsEx){
				$successOrderExIds = array_unique(array_column($resultsEx, 'EXCHANGE'));
			}
        	
		//==================update orders======================
			$placeholders = implode(',', array_fill(0, count($successOrderIds), '?'));
			$stOUpdate = $this->conn->prepare("
				UPDATE orders 
					SET EXISTS_ID = 10 , 
					SHIPPING_DATE = ? , 
					DELIVERY_ID   = ? , 
					LAST_UPDATE   = now(6) 
				WHERE ORDER_ID IN ($placeholders);
			");
			$params = array_merge([$this->shiping_date, $this->delivery_id], $successOrderIds);
			
			
			if (!$stOUpdate->execute($params) || $stOUpdate->rowCount() != count($successOrderIds)) {
				throw new Exception('حدث خطاء اثناء تحديث بيانات اوامر البيع');
			}
			
		//===========insert custody=================
			//insert custody order
			$stinsertCustody = $this->conn->prepare("
				INSERT custody_orders (ORDER_ID,USER_ID,ORDER_STATUSE,SHIPPING_ADDRESS)
					SELECT 
					  ORDER_ID,
					  DELIVERY_ID AS USER_ID,
					  ORDER_STATUS AS ORDER_STATUSE,
					  ADDRESS AS SHIPPING_ADDRESS
					FROM orders WHERE DELIVERY_ID = ? AND ORDER_ID IN($placeholders);
			");
			$params = array_merge([$this->delivery_id], $successOrderIds);
			if (!$stinsertCustody->execute($params) || $stinsertCustody->rowCount() != count($successOrderIds)) {
				throw new Exception("خطاء فى تسجيل امر البيع على عهدة المندوب");
			}

			//insert custody pro
			$stinsertCustpro = $this->conn->prepare("
				INSERT INTO custody_product
					SELECT OP.ORDER_ID,
						CO.USER_ID,
						OP.PRO_CODE,
						OP.QTY,
						OP.PRICE,
						OP.DISCOUNT,
						OP.DELIVERED_QTY,
						OP.RETURNED_QTY,
						OP.PRO_STATUSE,
						NOW(6) AS LAST_UPDATE
					FROM  order_product AS OP
					 LEFT JOIN custody_orders AS CO ON OP.ORDER_ID = CO.ORDER_ID
					WHERE CO.ORDER_ID  IN($placeholders)
					 AND CO.USER_ID = ? ;
			");
			$params = array_merge($successOrderIds,[$this->delivery_id]);
			if (!$stinsertCustpro->execute($params) || $stinsertCustpro->rowCount()==0) {
				throw new Exception("خطاء فى تسجيل منتجات امر البيع على عهدة المندوب");
			}
			
			//insert custody return order
			if (count($successOrderExIds) > 0 && 1==2) {
				$placeholdersEx = implode(',', array_fill(0, count($successOrderExIds), '?'));
				$stinsertReturnCustody = $this->conn->prepare("
					INSERT custody_return_orders (
						ORDER_ID,
						USER_ID,
						ORDER_STATUSE,
						SHIPPING_ADDRESS
						) SELECT 
						  ORDER_ID,
						  ".$this->delivery_id." AS USER_ID,
						  ORDER_STATUS AS ORDER_STATUSE,
						  ADDRESS AS SHIPPING_ADDRESS
						FROM orders 
						WHERE ORDER_STATUS IN(4,3)
							AND ORDER_ID IN($placeholdersEx);
				");
				
				$params = $successOrderExIds;
				if (!$stinsertReturnCustody->execute($params) || $stinsertReturnCustody->rowCount() == 0) {
					
					print_r($stinsertReturnCustody->errorInfo());
					die;
					echo $stinsertReturnCustody->rowCount();
					throw new Exception("خطاء فى اضافة اوامر الاستبدال على عهدة المندوب");
				}
				
				//insert custody return pro
				$stinsertReturnCustpro = $this->conn->prepare("
					INSERT INTO custody_return_product
						SELECT OP.ORDER_ID,
							CO.USER_ID,
							OP.PRO_CODE,
							OP.QTY,
							OP.PRICE,
							OP.DISCOUNT,
							OP.DELIVERED_QTY,
							OP.RETURNED_QTY,
							OP.PRO_STATUSE,
							NOW(6) AS LAST_UPDATE
						FROM  order_product AS OP
						 LEFT JOIN custody_return_orders AS CO ON OP.ORDER_ID = CO.ORDER_ID
						WHERE CO.ORDER_ID  IN($placeholdersEx)
						 AND CO.USER_ID = ?
						 AND OP.DELIVERED_QTY > 0;
				");
				$params = array_merge($successOrderExIds,[$this->delivery_id]);
				
				if (!$stinsertReturnCustpro->execute($params) || $stinsertReturnCustpro->rowCount() == 0) {
					throw new Exception("خطاء فى اضافة منتجات اوامر الاستبدال على عهدة المندوب");
				}
		
			}
			$uniqid = uniqid('IM_', true);
		//=============insert shipping_movement================
			$stShMov = $this->conn->prepare("
				INSERT INTO shipping_movement(UNIQUE_ID,movement_type, delivery_id, user_id, shipment_orders) 
				SELECT 
				    ? AS UNIQUE_ID,
				    0 AS movement_type, 
				    ? AS delivery_id, 
				    ? AS user_id, 
				    ORDER_ID AS shipment_orders
				FROM ORDERS WHERE ORDER_ID IN ($placeholders)
			");
			$params = array_merge([$uniqid,$this->delivery_id, $this->user_action], $successOrderIds);
			if (!$stShMov->execute($params) || $stShMov->rowCount() != count($successOrderIds)) {
				throw new Exception('حدث خطاء اثناء تسجيل حركة الشحن لاوامر البيع');
			}
		//===========insert inventoryMovement And update stores_product=================
			$this->InventoryManager->processInventory('SHIPPING', $successOrderIds, $this->user_action, $this->branch_id, $uniqid, $this->store_id);
			
		/*	$referencekey = $this->user_action . date('ymdHis') . random_int(100, 999);
			//تسجيل حركة المخزون 
			$stmtInvMov = $this->conn->prepare("INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED) VALUES (?,?,?,?,?,?,1,1);");
			$params=['SHIPPING', 
			$this->user_action, 
			$this->branch_id , 
			$this->store_id, 
			$referencekey, 
			implode(", ", $successOrderIds)];
				
			// التحقق من تنفيد الاستعلام
			if (!$stmtInvMov->execute($params)) {
				throw new Exception("خطأ فى تسجيل حركة المخزون.");
			}
			// جلب مفتاح اخر تسجل
			$lastInvMovementId = $this->conn->lastInsertId();
			// التحقق من وجد مفتاح التسجيل
			if (!$lastInvMovementId) {
				throw new Exception("لم يتم إنشاء ID لحركة المخزون");
			}
			
			// تسجيل منتجات حركة المخزون 
			$stmtInvProMov = $this->conn->prepare("
				INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT)
				    SELECT
				     ? AS IM_ID,
				     op.ORDER_ID,
				     'OUT' AS IPM_TYPE_MOV,
				     op.PRO_CODE AS IPM_CODE,
				     COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY,
				     op.QTY AS IPM_QTY,
				     NOW(6) AS CREATE_AT
				    FROM order_product op
				    INNER JOIN orders o ON o.ORDER_ID = op.ORDER_ID
				    LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = ?
				    WHERE op.ORDER_ID IN ($placeholders);
			");
				
			$params = array_merge([$lastInvMovementId,$this->store_id],$successOrderIds);
			if(!$stmtInvProMov->execute($params) || $stmtInvProMov->rowCount() <=0){
				throw new Exception("خطاء فى تسجيل منتجات حركة المخزون."); 
			}
		    
		    // 1️⃣ استدعاء الإجراء
			$stmtUpdateStore = $this->conn->prepare("CALL update_stores(@p_return)");
			$stmtUpdateStore->execute();
			$stmtUpdateStore->closeCursor(); // مهم جدًا
						
			// 2️⃣ قراءة قيمة OUT
			$result = $this->conn->query("SELECT @p_return AS p_return")->fetch(PDO::FETCH_ASSOC);
			
			$p_return = (int)$result['p_return'];
			if($p_return <= 0){
				throw new Exception("خطاء فى معالجة صرف المنتجات من المخزن."); 
			}*/
		//=================insert ORDERS_LOG===================
			$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group."*تم تسليم امر البيع الى المندوب ".$this->delivery_name;
			$stOLog = $this->conn->prepare("
				INSERT INTO `ORDERS_LOG` (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) 
				SELECT ORDER_ID,
				? ,
				? FROM ORDERS WHERE ORDER_ID IN ($placeholders)" 
            );
            $params = array_merge([$this->user_action,$logtext],$successOrderIds);
			if (!$stOLog->execute($params) || $stOLog->rowCount() != count($successOrderIds)) {
				throw new Exception('حدث خطاء اثناء حفظ سجلات امر البيع');
			}
			
        	$this->conn->commit();
			return ['success', 
					'نجح',
					$message,
					$oISNotConditions
			];
			
		} catch (PDOException $e) {
			$this->conn->rollBack();
			return ['error','خطأ',$this->mapPDOExceptionMessage($e),[]];
		} catch (Exception $e) {
			$this->conn->rollBack();
			return ['error','خطأ',  $e->getMessage(),$oISNotConditions];
		} finally {
			// 🔓 تحرير الأقفال
			$this->releaseGlobalInventoryLock();
			$this->logManager->finalize();
	    	$this->conn = null;
		}
	}
	/**
	 * ============================================================
	 * Ref Employee Custody Service
	 * ============================================================
	 *
	 * @function refEmployeeCustody
	 *
	 * @description
	 * ------------------------------------------------------------
	 * تقوم هذه الدالة بتنفيذ عملية استرداد أوامر البيع من عهدة
	 * المندوب (Delivery Agent) مع دعم حالتين:
	 *
	 * 1) استرداد كامل مع إعادة المنتجات إلى المخزن
	 * 2) استرداد بدون إعادة المنتجات (تجميد العهدة فقط)
	 *
	 * تم تصميم الدالة لتعمل في بيئة:
	 * - Multi Users (200+)
	 * - High Concurrency
	 * - High Data Integrity
	 *
	 * تعتمد على:
	 * - Database Transactions
	 * - Row Level Locking (FOR UPDATE)
	 * - Stored Procedures
	 * - Full Audit Logging
	 *
	 * ------------------------------------------------------------
	 *
	 * @param bool|string $refInventory
	 * ------------------------------------------------------------
	 * false  : استرداد كامل + إعادة المنتجات للمخزون
	 * "true" : استرداد بدون إعادة المخزون (Freeze Custody)
	 *
	 * ------------------------------------------------------------
	 *
	 * @required class properties
	 * ------------------------------------------------------------
	 * $this->orderobj      array   // ORDER_IDs المراد استردادها
	 * $this->branch_id    int     // الفرع
	 * $this->store_id     int     // المخزن (يحدد تلقائياً إن لم يوجد)
	 * $this->delivery_id  int     // المندوب
	 * $this->user_action  int     // المستخدم القائم بالإجراء
	 * $this->user_group   string  // نوع المستخدم (ADMIN / ...)
	 * $this->last_update  datetime// آخر وقت مزامنة
	 * $this->conn         PDO     // اتصال قاعدة البيانات
	 *
	 * ------------------------------------------------------------
	 *
	 * @return array
	 * ------------------------------------------------------------
	 * SUCCESS:
	 * [
	 *   'success',
	 *   'نجح',
	 *   HTML Message,
	 *   OrdersNotMatchedConditions
	 * ]
	 *
	 * ERROR:
	 * [
	 *   'error',
	 *   'خطأ',
	 *   Error Message,
	 *   OrdersNotMatchedConditions
	 * ]
	 *
	 * ============================================================
	 * BUSINESS RULES
	 * ============================================================
	 *
	 * - لا يمكن استرداد أمر:
	 *   * حالته أكبر من (2)
	 *   * تم تحديثه بعد last_update
	 *   * مجمد مسبقاً في عهدة المندوب
	 *
	 * - يتم تقسيم الأوامر إلى:
	 *   * أوامر صالحة للتنفيذ
	 *   * أوامر مرفوضة مع سبب الرفض
	 *
	 * ============================================================
	 * CONCURRENCY & LOCKING STRATEGY
	 * ============================================================
	 *
	 * BEGIN TRANSACTION
	 *
	 * - Lock orders + order_product
	 * - Lock stores_product المرتبط بالأوامر
	 *
	 * SELECT ... FOR UPDATE
	 *
	 * الهدف:
	 * - منع double execution
	 * - منع التلاعب بالمخزون
	 * - منع Race Conditions
	 *
	 * ============================================================
	 * EXECUTION FLOW (STEP BY STEP)
	 * ============================================================
	 *
	 * 1) BEGIN TRANSACTION
	 *
	 * 2) Validate Orders
	 *    - التأكد من وجود أوامر
	 *    - تحديد المخزن تلقائياً
	 *
	 * 3) Lock Critical Tables
	 *    - orders
	 *    - order_product
	 *    - stores_product
	 *
	 * 4) Business Validation
	 *    - فحص حالة الأمر
	 *    - فحص آخر تحديث
	 *    - فحص التجميد
	 *
	 * 5) Split Orders
	 *    - Success Orders
	 *    - Failed Orders
	 *
	 * 6) Insert Logs
	 *    - orders_log
	 *    - shipping_movement
	 *
	 * 7) Conditional Logic
	 *
	 *    IF refInventory == "true"
	 *    --------------------------------
	 *    - Update custody_orders
	 *    - SET CUST_STAT = 'FREEZE'
	 *
	 *    ELSE (Full Refund)
	 *    --------------------------------
	 *    - Insert inventory_movement
	 *    - Insert inventory_product_movement
	 *    - CALL update_stores()
	 *    - Delete custody_orders
	 *
	 * 8) Update Orders
	 *    - ORDER_STATUS = 2
	 *    - DELIVERY_ID = NULL
	 *    - SHIPPING_DATE = NULL
	 *
	 * 9) Reset Order Products
	 *    - DELIVERED_QTY = 0
	 *    - PRO_STATUSE = 'WAITING'
	 *
	 * 10) COMMIT TRANSACTION
	 *
	 * ============================================================
	 * FLOWCHART (LOGICAL VIEW)
	 * ============================================================
	 *
	 * [Start]
	 *    |
	 *    v
	 * [Validate Orders]
	 *    |
	 *    |-- invalid --> [Error]
	 *    |
	 *    v
	 * [Begin Transaction]
	 *    |
	 *    v
	 * [Lock Orders + Inventory]
	 *    |
	 *    v
	 * [Check Business Rules]
	 *    |
	 *    |-- fail --> [Rollback]
	 *    |
	 *    v
	 * [Insert Logs]
	 *    |
	 *    v
	 * { refInventory ? }
	 *    |
	 *    |-- YES --> [Freeze Custody]
	 *    |
	 *    |-- NO --> [Inventory Refund]
	 *                |
	 *                v
	 *          [update_stores()]
	 *
	 *    |
	 *    v
	 * [Update Orders]
	 *    |
	 *    v
	 * [Commit]
	 *    |
	 *    v
	 * [End]
	 *
	 * ============================================================
	 * TABLES AFFECTED
	 * ============================================================
	 *
	 * orders
	 * order_product
	 * custody_orders
	 * inventory_movement
	 * inventory_product_movement
	 * shipping_movement
	 * orders_log
	 *
	 * ============================================================
	 * ERROR HANDLING
	 * ============================================================
	 *
	 * - Any Exception:
	 *   * ROLLBACK
	 *   * Release Locks
	 *   * Return Clear Error Message
	 *
	 * ============================================================
	 * PERFORMANCE NOTES
	 * ============================================================
	 *
	 * - Single Transaction
	 * - Batch Processing
	 * - No Temp Tables
	 * - Stored Procedure for heavy logic
	 * - Safe for 200+ concurrent users
	 *
	 * ============================================================
	 * AUTHOR NOTES
	 * ============================================================
	 *
	 * - Designed for Production Environment
	 * - High Integrity & Audit Ready
	 * - Easily Extendable
	 *
	 * ============================================================
	 */
 	public function refEmployeeCustody($refInventory = false) {

		$sql = $this->query();
		$lockedOrders = [];
		$osucceeded = [];
		$orejected = [];
		$oISNotConditions = [];
		$oISConditions  = [];
		$oAllConditions = [];
		$successCount = 0;
		$failedCount = 0;
		
		try {

			$this->conn->beginTransaction();
			//=============التحقق من وجود اوامر بيع=================
			// تنظيف مصفوفة أوامر البيع: تحويل للقيم الصحيحة، إزالة الصفر والسالب، وإزالة التكرار
			$orderIds = array_values(array_unique(array_filter(
			    array_map('intval', $this->orderobj),
			    fn($v) => $v > 0
			)));
			if (empty($orderIds)) {
			    return array('error','','لايوجد بيانات لمعالجتها برجاء اختيار امر بيع واحد او اكتر');
			}
			//=============== جلب بيانات مخزن الاستلام ====================
			if (!$this->store_id) $this->store_id = $this->get_inventory_id($this->branch_id);
			
			// ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
		    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
		    $stmtLock = $this->conn->prepare("
		        SELECT o.ORDER_ID, op.PRO_CODE
		        FROM orders o
		        JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
		        WHERE o.ORDER_ID IN ($placeholders)
		        FOR UPDATE NOWAIT
		    ");
		    $stmtLock->execute($orderIds);
		
		    // ========== قفل المخزون FOR UPDATE NOWAIT ==========
		    // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }

			// ================== التحقق من الأوامر الناجحة ==================
			$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
			$stmt = $this->conn->prepare("
				SELECT 
				    O.ORDER_ID,
				    O.SUB_ORDER_ID AS osubid,
				    IF(O.ORDER_STATUS = 2, 1, 0) AS statuse,
				    IF(O.LAST_UPDATE <= ?, 1, 0) AS lastup,
				    IF(CO.CUST_STAT = 'FREEZE',0,1) AS freeze
				FROM orders O
				LEFT JOIN custody_orders CO ON O.ORDER_ID = CO.ORDER_ID AND CO.CUST_STAT = 'FREEZE' AND CO.USER_ID = ?
				WHERE O.ORDER_ID IN ($placeholders)
					AND (O.ORDER_STATUS  > 2
						OR O.LAST_UPDATE > ?
						OR CO.ORDER_ID IS NOT NULL)
				ORDER BY O.DATE_CREATED
			");
			
			$params = array_merge([$this->last_update, $this->delivery_id], $orderIds, [$this->last_update]);
			$stmt->execute($params);
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			// ================== تجميع النتائج ==================
			foreach ($results as $row) {
			    $oISConditions[$row['ORDER_ID']] = [
			        'osubid'   => $row['osubid']  ?? 'غير متوفر',
			        'statuse'  => $row['statuse'],
			        'lastup'   => $row['lastup'],
			        'freeze'   => $row['freeze']
			    ];
			}
			
			// ================== التحليل العام ==================
			$total        = count($orderIds);
			$failedCount  = count($results);
			$successCount = $total - $failedCount;
			$statuse = $successCount === 0 ? 'error' : ($successCount < $total ? 'warning' : 'success');
			
			// ================== أوامر فاشلة / ناجحة ==================
			$failedOrderIds = array_unique(array_column($results, 'ORDER_ID'));
			$failed = array_flip($failedOrderIds);
			$successOrderIds = array_values(array_filter($orderIds, fn($id) => !isset($failed[$id])));
			
			//================== التحليل العام ==================
			if($successCount > 0 && $failedCount <= 0){
				$statuse = "success";
				$message = "
					<div style='text-align:center'>
				        <b>نجح استرداد امر البيع</b>
					</div>
				";
			} else {
				$message = "
				    <div style='text-align:center'>
				        <b>فشل استرداد امر البيع</b><br>
				        📦 <b>إجمالي عدد الأوامر المطلوب استردادها :</b> {$total}<br>
				        ❌ <b>عدد الاوامر التى لا تستوفى شروط الاسترداد:</b> {$failedCount}<br><br>
				        <span style='color:#d33'>يرجى مراجعة قائمة الأوامر المرفوضة.</span>
				    </div>
				";
			}
			if($statuse == 'error') throw new Exception($message);
			if (empty($successOrderIds)) {
			   throw new Exception('لا توجد أوامر صالحة للتنفيذ');
			}
			
			//=================تحضير البيانات===================
			$shtype = 2;
			$refcustmsg ='';
			if ($refInventory == "true") {
					$refcustmsg = ' دون استرداد المنتجات ';
					$message .= $refcustmsg;
					$shtype = 1;
			}
			
			//=================insert ORDERS_LOG===================
			//$logtext = "تم تحديث بيانات امر البيع من ".$this->user_group."*تم استرداد امر البيع من المندوب ".$record['DELIVERY_NAME'].' '.$refcustmsg;
			$stOLog = $this->conn->prepare("
				INSERT INTO ORDERS_LOG (ORDER_ID, USER_ACTION, LOG_TEXT)
					SELECT
					  o.ORDER_ID,
					  ?,
					  CONCAT(
					    'تم تحديث بيانات امر البيع من ',
					    ?, 
					    ' * تم استرداد امر البيع من المندوب ',
					    CONCAT(u.FORENAME,' ',u.LASTNAME),
					    ?
					  )
					FROM orders o
					LEFT JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID
					LEFT JOIN users u ON u.USER_ID = co.USER_ID
					WHERE o.ORDER_ID IN ($placeholders)
				");
		    $params = array_merge([$this->user_action, $this->user_group, $refcustmsg],$successOrderIds);
			if (!$stOLog->execute($params)) {
				throw new Exception('حدث خطاء اثناء انشاء سجلات اوامر البيع');
			}
			$uniqid = uniqid('IM_', true);
			//=============insert shipping_movement================
			$stShMov = $this->conn->prepare("
					INSERT INTO shipping_movement (UNIQUE_ID,movement_type, delivery_id, user_id, shipment_orders) 
					SELECT 
						? AS UNIQUE_ID,
						? AS movement_type,
						DELIVERY_ID AS delivery_id,
						? AS user_id,
				        ORDER_ID
				    FROM ORDERS WHERE ORDER_ID IN($placeholders);
			");
			$params = array_merge([$uniqid,$shtype, $this->user_action],$successOrderIds);
			if (!$stShMov->execute($params) || $stShMov->rowCount() != $successCount) throw new Exception('حدث خطاء اثناء انشاء سجلات اوامر البيع');
			
			//===========حذف اوامر الاستبدال من عهدة المندوب
			$stDelete = $this->conn->prepare("
				DELETE cro
				FROM custody_return_orders cro
				JOIN orders o 
					ON o.EXCHANGE = cro.ORDER_ID
				WHERE o.EXCHANGE IS NOT NULL AND o.ORDER_ID IN($placeholders)
			");
			if (!$stDelete->execute($successOrderIds)) {
				throw new ErrorException('تعذر حذف اوامر الاستبدال من عهدة المندوب . يرجى التأكد من سلامة البيانات.');
			}
			//=============استرداد امر البيع بدون رد العهده================
			if ($refInventory === 'true') { 
				//تجميد امر البيع لدى المندوب
				$stCustody = $this->conn->prepare("
							UPDATE custody_orders co
							SET 
							    co.CUST_STAT  = 'FREEZE',
							    co.LAST_UPDATE = NOW(3)
							WHERE ORDER_ID IN($placeholders) AND co.CUST_STAT = 'WAITING'; 
				");
				if (!$stCustody->execute($successOrderIds) || $stCustody->rowCount() != $successCount) throw new ErrorException(' خطاء غير قادر على تنفيذ هذا الاجراء (update_custody->rowCount');
			}
		
			//=================سترداد العهدة بالكامل====================
			if ($refInventory === 'false') {
				$this->InventoryManager->processInventory('REFUND', $successOrderIds, $this->user_action, $this->branch_id, $uniqid, $this->store_id);

				//===========insert inventoryMovement And update stores_product=================
				/*$referencekey = $this->user_action . date('ymdHis') . random_int(100, 999);
				//تسجيل حركة المخزون 
				$stmtInvMov = $this->conn->prepare("INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED) VALUES (?,?,?,?,?,?,1,1);");
				$params=['REFUND', $this->user_action, $this->branch_id , $this->store_id, $referencekey, implode(", ", $successOrderIds)];
					
				// التحقق من تنفيد الاستعلام
				if (!$stmtInvMov->execute($params)) {
					throw new Exception("خطأ فى تسجيل حركة المخزون.");
				}
				//التحقق من عدد الصفوف المتأثرة
				if ($stmtInvMov->rowCount() === 0) {
					throw new Exception("لم يتم إدخال سجل حركة مخزون");
				}			
				// جلب مفتاح اخر تسجل
				$lastInvMovementId = $this->conn->lastInsertId();
				// التحقق من وجد مفتاح التسجيل
				if (!$lastInvMovementId) {
					throw new Exception("لم يتم إنشاء ID لحركة المخزون");
				}
				
				// تسجيل منتجات حركة المخزون 
				$stmtInvProMov = $this->conn->prepare("
					INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT)
					    SELECT
					     ? AS IM_ID,
					     op.ORDER_ID,
					     'IN' AS IPM_TYPE_MOV,
					     op.PRO_CODE AS IPM_CODE,
					     COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY,
					     op.QTY AS IPM_QTY,
					     NOW(6) AS CREATE_AT
					    FROM order_product op
					    INNER JOIN orders o ON o.ORDER_ID = op.ORDER_ID
					    LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = ?
					    WHERE op.ORDER_ID IN ($placeholders);
				");
					
				$params = array_merge([$lastInvMovementId, $this->store_id],$successOrderIds);
				if(!$stmtInvProMov->execute($params) || $stmtInvProMov->rowCount() <=0){
					throw new Exception("خطاء فى تسجيل منتجات حركة المخزون."); 
				}
			    
			    // 1️⃣ استدعاء الإجراء
				$stmtUpdateStore = $this->conn->prepare("CALL update_stores(@p_return)");
				$stmtUpdateStore->execute();
				$stmtUpdateStore->closeCursor(); // مهم جدًا
						
				// 2️⃣ قراءة قيمة OUT
				$result = $this->conn->query("SELECT @p_return AS p_return")->fetch(PDO::FETCH_ASSOC);
				
				$p_return = (int)$result['p_return'];
				if($p_return <= 0){
					throw new Exception("خطاء فى معالجة صرف المنتجات من المخزن."); 
				} 
				*/
				//==========حذف العهدة من على المندوب===================
				$stDelete = $this->conn->prepare("
					DELETE co
					FROM custody_orders co
					JOIN orders o 
					    ON o.ORDER_ID = co.ORDER_ID
					   AND o.DELIVERY_ID  = co.USER_ID
					WHERE O.ORDER_ID IN($placeholders);
				");
				if (!$stDelete->execute($successOrderIds) || $stDelete->rowCount() != $successCount) {
					throw new ErrorException('تعذر حذف اوامر الاستبدال من عهدة المندوب . يرجى التأكد من سلامة البيانات.');
				}
			}
			//==================update orders======================
			$placeholders = implode(',', array_fill(0, count($successOrderIds), '?'));
			$stOUpdate = $this->conn->prepare("
				UPDATE orders 
				SET 
				  EXISTS_ID      = 8, 
				  SHIPPING_DATE  = NULL, 
				  DELIVERY_ID    = NULL, 
				  ORDER_STATUS   = 2,
				  LAST_UPDATE    = now(3)
				WHERE ORDER_ID IN($placeholders) ;
			");
			
			if (!$stOUpdate->execute($successOrderIds) || $stOUpdate->rowCount() != $successCount) throw new Exception('حدث خطاء اثناء تحديث بيانات امر البيع');
			
			//========استعادة وضع منتجات اوامر البيع الى ما قبل الشحن
			$stRE1Update = $this->conn->prepare("
				UPDATE order_product 
				SET
				  DELIVERED_QTY = 0,
				  PRO_STATUSE  = 'WAITING'
				WHERE ORDER_ID IN($placeholders);
			");
			if (!$stRE1Update->execute($successOrderIds)){
			    throw new ErrorException('تعذر استعادة حالة أوامر البيع قبل الشحن. يرجى إعادة المحاولة أو مراجعة سجل العمليات.');
			}
			
			$this->conn->commit();
			return array('success','نجح',$message, $oISNotConditions);
					
		} catch (PDOException $e) {
			$this->conn->rollBack();
			return ['error',
				'خطأ',
				$this->mapPDOExceptionMessage($e)];
		} catch (Exception $e) {
			$this->conn->rollBack();
			return ['error','خطأ',  $e->getMessage(),$oISNotConditions];
		}finally {
		   $this->releaseGlobalInventoryLock();
		   $this->conn = null;
		}
	}
	/**
	 * =========================================================================
	 * 🚚 ShipmentDelivery - إدارة تسليم الشحنات (Ultimate Documentation)
	 * =========================================================================
	 *
	 * 📌 الوصف العام:
	 * -------------------------------------------------------------------------
	 * هذه الدالة مسؤولة عن إدارة عملية تسليم الشحنات، وتعمل كـ Controller
	 * لتحديد مسار التنفيذ بناءً على نوع العملية ($type).
	 *
	 * تدعم الدالة وضعين رئيسيين:
	 * - view    : عرض بيانات الشحنة
	 * - confirm : تأكيد عملية التسليم
	 *
	 * -------------------------------------------------------------------------
	 * الهدف:
	 * - توحيد نقطة الدخول لعمليات التسليم
	 * - فصل منطق العرض عن منطق التنفيذ
	 * - إدارة الأخطاء والـ logging بشكل مركزي
	 *
	 * =========================================================================
	 * ⚙️ الفلو الكامل (Execution Flow):
	 * =========================================================================
	 *
	 * [Start]
	 *   ↓
	 * تهيئة الاتصال (query)
	 *   ↓
	 * تحديد نوع العملية (type)
	 *   ↓
	 * ┌───────────────────────────────┐
	 * │   هل type = 'view' ؟          │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   هل عدد الطلبات = 1 ؟
	 *        ├── لا → تجاهل
	 *        └── نعم ↓
	 *             ↓
	 *   handleViewMode(sql)
	 *             ↓
	 *           Return
	 *
	 * ┌───────────────────────────────┐
	 * │   هل type = 'confirm' ؟       │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   confirmShipmentDelivery()
	 *        ↓
	 *      Return
	 *
	 * ┌───────────────────────────────┐
	 * │   نوع غير معروف              │
	 * └───────────────────────────────┘
	 *        ↓
	 *   تسجيل خطأ في logManager
	 *        ↓
	 *   (يفضل Return error)
	 *
	 * =========================================================================
	 * 🧠 حالات التشغيل (Modes):
	 * =========================================================================
	 *
	 * 🟢 1. View Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'view'
	 * - عدد الطلبات = 1
	 *
	 * الهدف:
	 * - عرض تفاصيل شحنة واحدة فقط
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   handleViewMode($sql)
	 *
	 * المتوقع داخلها:
	 * - جلب بيانات الطلب
	 * - تجهيز بيانات العرض
	 * - إرجاع response مناسب للـ UI
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🟡 2. Confirm Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'confirm'
	 *
	 * الهدف:
	 * - تنفيذ عملية تسليم الشحنة
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   confirmShipmentDelivery()
	 *
	 * المتوقع داخلها:
	 * - تحديث حالة الطلب
	 * - تسجيل حركات الشحن
	 * - التعامل مع المخزون
	 * - تسجيل logs
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🔴 3. Invalid Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - type لا يساوي 'view' أو 'confirm'
	 *
	 * السلوك الحالي:
	 * - تسجيل error فقط
	 *
	 * ⚠️ المشكلة:
	 * - لا يوجد return واضح
	 *
	 * =========================================================================
	 * 🧱 Dependencies:
	 * =========================================================================
	 *
	 * - query()
	 *   → تجهيز اتصال قاعدة البيانات
	 *
	 * - handleViewMode()
	 *   → منطق عرض الشحنة
	 *
	 * - confirmShipmentDelivery()
	 *   → منطق تنفيذ التسليم
	 *
	 * - logManager
	 *   → تسجيل الأخطاء والـ logs
	 *
	 * =========================================================================
	 * 🛑 Error Handling:
	 * =========================================================================
	 *
	 * 1️⃣ PDOException
	 * -------------------------------------------------------------------------
	 * - أخطاء قاعدة البيانات
	 * - يتم:
	 *   • تسجيل الخطأ
	 *   • تحويل الرسالة
	 *   • إرجاع response error
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 2️⃣ ErrorException
	 * -------------------------------------------------------------------------
	 * - أخطاء منطقية داخل التطبيق
	 * - يتم:
	 *   • تسجيل (file + line)
	 *   • إرجاع رسالة واضحة
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 3️⃣ Exception (عام)
	 * -------------------------------------------------------------------------
	 * - أي خطأ غير متوقع
	 * - يتم:
	 *   • تسجيله
	 *   • إرجاع رسالة
	 *
	 * =========================================================================
	 * 🔚 finally:
	 * =========================================================================
	 *
	 * - logManager->finalize()
	 *   → إنهاء عملية التسجيل
	 *   → ممكن تشمل:
	 *     • flush logs
	 *     • إغلاق handlers
	 *
	 * =========================================================================
	 * 📥 @param string $type
	 * -------------------------------------------------------------------------
	 * نوع العملية:
	 * - 'view'    → عرض بيانات الشحنة
	 * - 'confirm' → تأكيد التسليم
	 *
	 * =========================================================================
	 * 📤 @return array
	 * -------------------------------------------------------------------------
	 * [
	 *   0 => string, // success | error
	 *   1 => string, // title
	 *   2 => string  // message
	 * ]
	 *
	 * =========================================================================
	 * 📌 أمثلة الاستخدام:
	 * =========================================================================
	 *
	 * // عرض شحنة
	 * $result = $this->ShipmentDelivery('view');
	 *
	 * // تأكيد التسليم
	 * $result = $this->ShipmentDelivery('confirm');
	 *
	 * =========================================================================
	 * ⚠️ مشاكل محتملة (Critical Issues):
	 * =========================================================================
	 *
	 * 1. لا يوجد return في حالة type غير صحيح
	 *    → قد يسبب undefined behavior
	 *
	 * 2. $sql يتم إنشاؤه دائمًا
	 *    → لكنه لا يستخدم في confirm mode
	 *
	 * 3. الاعتماد على string بدل constants
	 *
	 * 4. عدم التحقق من count(orderobj) في confirm
	 *
	 * =========================================================================
	 * 💡 تحسينات مقترحة (Recommended Improvements):
	 * =========================================================================
	 *
	 * ✔ إضافة return واضح في invalid type:
	 *   return ['error','خطأ','نوع العملية غير صحيح'];
	 *
	 * ✔ استخدام constants:
	 *   const MODE_VIEW = 'view';
	 *   const MODE_CONFIRM = 'confirm';
	 *
	 * ✔ Lazy loading للـ query():
	 *   يتم فقط عند الحاجة (view mode)
	 *
	 * ✔ توحيد شكل response دائمًا
	 *
	 * ✔ إضافة validation قبل confirm:
	 *   - التأكد من وجود orders
	 *
	 * =========================================================================
	 * @throws PDOException
	 * @throws ErrorException
	 * @throws Exception
	 * =========================================================================
	 */
	public function ShipmentDelivery(string $type = 'view'): array {
		try {
			$sql = $this->query();
			// حالة العرض: الواجهة الحالية ترسل رقم الطلب مباشرة، والواجهات القديمة ترسل مصفوفة.
			if ($type === 'view') {
				if (is_array($this->orderobj) && count($this->orderobj) === 1) {
					return $this->handleViewMode($sql);
				}
				if (!is_array($this->orderobj) && filter_var($this->orderobj, FILTER_VALIDATE_INT) !== false) {
					return $this->handleViewMode($sql);
				}
			}
			// حالة التأكيد
			if ($type === 'confirm') {
			    $results = $this->confirmShipmentDelivery();
				
				return $results;
					
			}
			$this->logManager->error("حدث خطاء اثناء تنفيذ المعامله ShipmentDelivery >> error type");
			// إذا لم يتطابق مع أي حالة
			//throw new ErrorException(" الطلب غير صحيح ",1001,  كود الخطأ E_USER_ERROR,  مستوى الخطأ __FILE__,  اسم الملف الحالي __LINE__  رقم السطر الحالي);
		} catch (PDOException $e) { // معالجة الأخطاء
			$this->logManager->exception("SQL ERROR : ".$this->mapPDOExceptionMessage($e));
			return ['error','خطأ',$this->mapPDOExceptionMessage($e)];
		} catch (ErrorException $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} catch (Exception $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} finally {
			$this->logManager->finalize();
		}
	}
	/**
	 * =========================================================================
	 * 🧾 ShipmentRefund - معالجة استرداد الشحنات (Ultimate Documentation)
	 * =========================================================================
	 *
	 * 📌 الوصف العام:
	 * -------------------------------------------------------------------------
	 * هذه الدالة مسؤولة عن تنفيذ عملية استرداد أوامر البيع المرتبطة بالمندوب،
	 * بدايةً من التحقق من صلاحية الأوامر وحتى تحديث المخزون والعهدة وتسجيل الحركات.
	 *
	 * يتم تنفيذ جميع العمليات داخل Transaction واحدة لضمان:
	 * - التكامل (Consistency)
	 * - الذرّية (Atomicity)
	 * - عدم فقدان البيانات
	 *
	 * =========================================================================
	 * ⚙️ الفلو الكامل (Execution Flow):
	 * =========================================================================
	 *
	 * [Start]
	 *   ↓
	 * Begin Transaction
	 *   ↓
	 * تنظيف orderIds (إزالة التكرار + القيم غير الصالحة)
	 *   ↓
	 * هل يوجد أوامر؟
	 *   ├── لا → Exception ❌
	 *   └── نعم ↓
	 *   ↓
	 * تحديد store_id
	 *   ↓
	 * Lock الأوامر (FOR UPDATE NOWAIT)
	 *   ↓
	 * جلب بيانات الأوامر
	 *   ↓
	 * تحليل وتصنيف:
	 *   ├── صالح → success
	 *   │     ├── Normal
	 *   │     ├── Freeze
	 *   │     └── Exchange
	 *   └── غير صالح → rejected
	 *   ↓
	 * هل يوجد فشل؟
	 *   ├── نعم → Rollback ❌
	 *   └── لا ↓
	 *   ↓
	 * INSERT → shipping_movement
	 *   ↓
	 * Inventory Processing
	 *   ↓
	 * INSERT → orders_log
	 *   ↓
	 * تحديث العهدة
	 *   ↓
	 * UPDATE orders (LAST_UPDATE)
	 *   ↓
	 * Commit ✅
	 *   ↓
	 * [End]
	 *
	 * =========================================================================
	 * 🧱 الجداول المستخدمة (Tables & Operations):
	 * =========================================================================
	 *
	 * 1️⃣ orders
	 * -------------------------------------------------------------------------
	 * - SELECT: قراءة بيانات الطلب
	 * - UPDATE:
	 *     SET LAST_UPDATE = NOW(6)
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 2️⃣ order_product
	 * -------------------------------------------------------------------------
	 * - SELECT فقط (لضمان وجود منتجات + Lock)
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 3️⃣ custody_orders (عهدة المندوب)
	 * -------------------------------------------------------------------------
	 * - SELECT: تحديد الحالة
	 * - DELETE:
	 *     • حذف FREEZE
	 *     • حذف المرفوض بالكامل
	 * - UPDATE:
	 *     • RETURNS = 1
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 4️⃣ custody_return_orders
	 * -------------------------------------------------------------------------
	 * - SELECT
	 * - DELETE (حذف المرتجعات المستلمة)
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 5️⃣ shipping_movement
	 * -------------------------------------------------------------------------
	 * - INSERT
	 *
	 * movement_type:
	 *   2  → FREEZE
	 *   6  → مرفوضات
	 *   8  → مرتجع بعد البيع
	 *   9  → مرتجع جزئي
	 *   10 → رفض استلام مرتجع
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 6️⃣ orders_log
	 * -------------------------------------------------------------------------
	 * - INSERT (تسجيل العمليات)
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 7️⃣ users
	 * - SELECT
	 *
	 * 8️⃣ order_statuse
	 * - SELECT
	 *
	 * =========================================================================
	 * 🔄 حالات العمل (Business Logic Cases):
	 * =========================================================================
	 *
	 * 🟢 1. Normal Return
	 * -------------------------------------------------------------------------
	 * الشروط:
	 * - DELIVERED
	 * - ليس FREEZE
	 *
	 * العمليات:
	 * - Inventory: RETURN_SHIPP
	 * - custody_orders:
	 *     DELETE (لو مرفوض بالكامل)
	 *     UPDATE RETURNS = 1
	 * - shipping_movement: type = 6
	 * - orders_log: "استلام مرفوضات"
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🟡 2. Freeze Return
	 * -------------------------------------------------------------------------
	 * الشروط:
	 * - CUST_STAT = FREEZE
	 *
	 * العمليات:
	 * - Inventory: REFUND
	 * - custody_orders: DELETE
	 * - shipping_movement: type = 2
	 * - orders_log: "استرداد من المندوب"
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🔵 3. Exchange Return
	 * -------------------------------------------------------------------------
	 * الشروط:
	 * - RETURNED
	 *
	 * العمليات:
	 * - Inventory: RETURN_SALES
	 * - custody_return_orders: DELETE
	 * - shipping_movement: type = (8 / 9 / 10)
	 * - orders_log: "مرتجعات بعد البيع"
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🔴 4. Rejected Orders
	 * -------------------------------------------------------------------------
	 * - لا يتم تعديل أي بيانات
	 * - يتم إرجاعها فقط
	 *
	 * =========================================================================
	 * 🔐 Concurrency Control:
	 * =========================================================================
	 *
	 * - استخدام:
	 *   SELECT ... FOR UPDATE NOWAIT
	 *
	 * الهدف:
	 * - منع المعالجة المزدوجة
	 * - حماية البيانات
	 *
	 * =========================================================================
	 * 🛑 Error Handling:
	 * =========================================================================
	 *
	 * - أي خطأ → Rollback كامل
	 * - إرجاع رسالة الخطأ
	 *
	 * =========================================================================
	 * 🔚 finally:
	 * =========================================================================
	 *
	 * - releaseGlobalInventoryLock()
	 * - إغلاق الاتصال
	 *
	 * =========================================================================
	 * ⚠️ مشاكل محتملة (Critical Issues):
	 * =========================================================================
	 *
	 * 1. Partial Failure غير مدعوم
	 *    → أي فشل يلغي العملية بالكامل
	 *
	 * 2. تكرار osucceeded
	 *    → قد يؤدي لأخطاء في INSERT/DELETE
	 *
	 * 3. rowCount غير موثوق
	 *
	 * 4. NOWAIT قد يسبب فشل متكرر
	 *
	 * 5. batchSize غير مستخدم
	 *
	 * 6. تعقيد عالي (Mixed Responsibilities)
	 *
	 * =========================================================================
	 * 💡 تحسينات احترافية (Recommended Improvements):
	 * =========================================================================
	 *
	 * ✔ استخدام array_unique
	 * ✔ استخدام SKIP LOCKED بدلاً من NOWAIT
	 * ✔ تقسيم العمليات إلى batch
	 * ✔ فصل المنطق إلى Services
	 * ✔ دعم Partial Success
	 * ✔ تحسين Logging
	 *
	 * =========================================================================
	 * 📥 @param bool $check
	 *
	 * =========================================================================
	 * 📤 @return array
	 * [
	 *   0 => success | error,
	 *   1 => title,
	 *   2 => message,
	 *   3 => rejected orders (optional)
	 * ]
	 *
	 * =========================================================================
	 * @throws Exception
	 * @throws PDOException
	 * =========================================================================
	 */	
 	public function ShipmentRefund($check = true) {
	    $lockedOrders     = [];
	    $oAllConditions   = [];
	    $oISConditions    = [];
	    $oISNotConditions = [];
	    $osucceeded       = [];
	    $osucceedNormale  = [];
	    $osucceedFreeze   = [];
	    $osucceedExchange = [];
		$osucceedDeliverd = [];
	    $orejected        = [];
	    $successCount     = 0;
	    $failedCount      = 0;
	    $batchSize        = 100;
		$sessionId = uniqid('IM_', true);
	    $inventoryLocked  = false;
	    
	    try {
	        $this->conn->beginTransaction();
	        
	        // تنظيف مصفوفة أوامر البيع
	        $orderIds = array_values(array_unique(array_filter(
	            array_map('intval', array_keys($this->orderobj)), 
	            fn($v) => $v > 0 
	        )));
	        if (empty($orderIds)) {
	            throw new Exception("❌ لا توجد أوامر للمعالجة");
	        }
	        
	        // جلب بيانات مخزن الاستلام
	        if (!$this->store_id) {
	            $this->store_id = $this->get_inventory_id($this->branch_id);
	            if (!$this->store_id) {
	                throw new Exception("❌ لم يتم العثور على مخزن الاستلام");
	            }
	        }
	        
	        // ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
	        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	       
	        $stmtLock = $this->conn->prepare("
	            SELECT o.ORDER_ID, op.PRO_CODE
	            FROM orders o
	            JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
	            WHERE o.ORDER_ID IN ($placeholders)
	            FOR UPDATE NOWAIT
	        ");
	        $stmtLock->execute($orderIds);

	        // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }
	        
	        // ========== التحقق من الأوامر الناجحة ==========
	        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	        $stmt = $this->conn->prepare("
	            SELECT 
	                O.ORDER_ID,
	                O.SUB_ORDER_ID,
	                CASE WHEN O.LAST_UPDATE <= ? THEN 1 ELSE 0 END AS lastup,
	                CASE WHEN CO.CUST_STAT = 'FREEZE' THEN 1 ELSE 0 END AS freeze,
	                CASE WHEN CR.CUST_STAT = 'RETURNED' AND CR.ORDER_STATUSE IN(6,7) THEN O.EXCHANGE ELSE NULL END AS EXCHANGE,
	                CASE WHEN O.ORDER_STATUS IN(1,2,4,5,6,7) THEN 1 ELSE 0 END AS statuse,
					CASE WHEN O.ORDER_STATUS = 3 THEN 1 ELSE 0 END AS delivered
	            FROM orders O
	            LEFT JOIN custody_orders CO ON O.ORDER_ID = CO.ORDER_ID AND CO.USER_ID = ?
	            LEFT JOIN custody_return_orders CR ON O.EXCHANGE = CR.ORDER_ID AND CR.USER_ID = ?
	            WHERE O.ORDER_ID IN ($placeholders)
	        ");
	        $params = array_merge([$this->last_update, $this->delivery_id, $this->delivery_id], $orderIds);
	        $stmt->execute($params);
	        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
	        if (empty($results)) {
	            throw new Exception("❌ لا توجد بيانات للأوامر المطلوبة");
	        }
	        
	        // ================== تجميع النتائج ==================
	        foreach ($results as $row) {
	            $orderId = (int)$row['ORDER_ID'];
	            $conditions = [
	                'osubid' => $row['SUB_ORDER_ID'] ?? 'غير متوفر',
	                'lastup' => (int)$row['lastup'],
	                'status' => (int)$row['statuse']
	            ];
	            
	            $oAllConditions[$orderId] = $conditions;
	            
	            if ($row['lastup'] && ($row['statuse'] || $row['EXCHANGE'])) {
	                $successCount++;
	                $osucceeded[] = $orderId;
	                $oISConditions[$orderId] = $conditions;
	                
	                if($row['EXCHANGE'] && !$row['freeze']){
	                    $osucceedExchange[] = (int)$row['EXCHANGE'];
	                    //$osucceeded[] = $row['EXCHANGE'];
	                }
	                if($row['delivered']){
						$osucceedDeliverd[] = $orderId;
					}
	                if (!$row['freeze']) {
	                    $osucceedNormale[] = $orderId;
	                } else {
	                    $osucceedFreeze[] = $orderId;
	                }
	            } else {
	                $oISNotConditions[$orderId] = $conditions;
	                $orejected[] = $orderId;
	                $failedCount++;
	            }
	        }
	        
	        // ================== التحليل العام ==================
	        $total = count($oAllConditions);
	        $statuse = 'error';
	        
	        if ($successCount > 0 && $failedCount <= 0) {
	            $statuse = "success";
	            $message = "
	                <div style='text-align:center'>
	                    <b>نجح استرداد امر البيع</b>
	                </div>
	            ";
	        } else {
	            $message = "
	                <div style='text-align:center'>
	                    <b>فشل استرداد امر البيع</b><br>
	                    📦 <b>إجمالي عدد الأوامر المطلوب استردادها :</b> {$total}<br>
	                    ❌ <b>عدد الاوامر التى لا تستوفى شروط الاسترداد:</b> {$failedCount}<br><br>
	                    <span style='color:#d33'>يرجى مراجعة قائمة الأوامر المرفوضة.</span>
	                </div>
	            ";
	        }
	        
	        if ($statuse == 'error') {
	            throw new Exception($message);
	        }
	        
	        // ================== تسجيل حركة الشحن ==================
	        $placeholders = implode(',', array_fill(0, count($osucceeded), '?'));
	        $stmtShip = $this->conn->prepare("
	            INSERT INTO shipping_movement
	                (UNIQUE_ID, movement_type, delivery_id, user_id, shipment_orders)
	            SELECT
					? AS UNIQUE_ID,
	                x.movement_type,
	                x.delivery_id,
	                ? AS user_id,
	                x.ORDER_ID AS shipment_orders
	            FROM (
	                SELECT
	                    o.ORDER_ID,
	                    CASE
	                        WHEN co.CUST_STAT = 'FREEZE'   THEN 2
	                        WHEN co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN(4,5) THEN 6
	                        WHEN cr.CUST_STAT = 'RETURNED' AND cr.ORDER_STATUSE IN (3,4) THEN 10
	                        WHEN cr.CUST_STAT = 'RETURNED' AND cr.ORDER_STATUSE = 6 THEN 8
	                        WHEN cr.CUST_STAT = 'RETURNED' AND cr.ORDER_STATUSE = 7 THEN 9
	                        ELSE NULL
	                    END AS movement_type,
	                    CASE
	                        WHEN co.USER_ID IS NOT NULL THEN co.USER_ID
	                        WHEN cr.USER_ID IS NOT NULL THEN cr.USER_ID
	                        ELSE NULL
	                    END AS delivery_id
	                FROM orders o
	                LEFT JOIN custody_orders co
	                    ON o.ORDER_ID = co.ORDER_ID
	                    AND co.USER_ID = ?
	                LEFT JOIN custody_return_orders cr
	                    ON o.ORDER_ID = cr.ORDER_ID
	                    AND cr.USER_ID = ?
	                WHERE o.ORDER_ID IN ($placeholders)
	            ) x
	            WHERE x.movement_type IS NOT NULL
	              AND x.delivery_id IS NOT NULL
	        ");
	        
	        $params = array_merge([$sessionId, $this->user_action, $this->delivery_id, $this->delivery_id], $osucceeded);
	        if (!$stmtShip->execute($params) || $stmtShip->rowCount() == 0) {
	            throw new Exception("خطاء فى تسجيل حر1كة الشحن.");
	        }
	        
	        // ================== تسجيل حركة ومنتجات المخزون ==================
	       
	        // تسجيل حركة مخزون لمرفوضات اوامر التسليم
	        $succNO = array_diff($osucceedNormale,$osucceedDeliverd);
			if (!empty($succNO)) $this->InventoryManager->processInventory('RETURN_SHIPP', $osucceedNormale, $this->user_action, $this->branch_id, $sessionId, $this->store_id);
	        // تسجيل حركة مخزون الاوامر المجمدة
	        if (!empty($osucceedFreeze)) $this->InventoryManager->processInventory('REFUND',       $osucceedFreeze, $this->user_action, $this->branch_id, $sessionId, $this->store_id);
	        // تسجيل حركة مخزون الاوامر الاستبدال
	        // تسجيل حركة مخزون المرتجعات بعد البيع، ثم قيد عكس المبيعات داخل المعاملة نفسها.
        // إذا فشل القيد المحاسبي، يتم التراجع عن حركة المخزون وباقي التغييرات معًا.
        $osucceedExchange = array_values(array_unique(array_filter(array_map('intval', $osucceedExchange), static fn($id) => $id > 0)));
        if (!empty($osucceedExchange)) {
            $this->InventoryManager->processInventory('RETURN_SALES', $osucceedExchange, $this->user_action, $this->branch_id, $sessionId, $this->store_id);

            $accounting = new AccountingEvents($this->conn);
            foreach ($osucceedExchange as $returnOrderId) {
                $accounting->salesReturn($returnOrderId);
            }
        }
	        
	        // ================== حفظ سجلات الحركه لاوامر البيع ==================
			
			$succ = array_diff($osucceeded,$osucceedDeliverd);
	        $placeholders = implode(',', array_fill(0, count($succ), '?'));
	        $logtext = 'تم إجراء تحديث على بيانات أمر البيع من قِبل : ' . $this->user_group;
	        $stmtOrderLog = $this->conn->prepare("
	                INSERT INTO orders_log (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`, `DATE_ACTION`)
	                SELECT 
	                    o.ORDER_ID,
	                    ? AS USER_ACTION,
	                    CASE 
	                        WHEN co.CUST_STAT = 'FREEZE' AND co.ORDER_STATUSE = 2          THEN CONCAT(? , '* تم استرداد منتجات امر البيع من المندوب: ', CONCAT(us.FORENAME, ' ', us.LASTNAME))
	                        WHEN co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN(4,5)   THEN CONCAT(? , '* تم استلام المرفوضات - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
	                        WHEN cro.CUST_STAT = 'RETURNED' AND cro.ORDER_STATUSE IN (3,4) THEN CONCAT(? , '* رفض العميل تسليم المرتجعات - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
	                        WHEN cro.CUST_STAT = 'RETURNED' AND cro.ORDER_STATUSE IN(6,7)  THEN CONCAT(? , '* تم استلام مرتجعات بعد البيع - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
	                        ELSE NULL
	                    END AS LOG_TEXT,
	                    NOW(6) AS DATE_ACTION
	                FROM orders o 
	                LEFT JOIN custody_orders co ON o.ORDER_ID = co.ORDER_ID AND co.USER_ID = ?
	                LEFT JOIN custody_return_orders cro ON o.ORDER_ID = cro.ORDER_ID AND cro.USER_ID = ?
	                LEFT JOIN users us ON co.USER_ID = us.USER_ID
	                LEFT JOIN order_statuse ost ON o.ORDER_STATUS = ost.STA_ID
	                WHERE o.ORDER_ID IN ($placeholders)
	        ");
	        
	        $params = array_merge([$this->user_action, $logtext, $logtext, $logtext, $logtext, $this->delivery_id, $this->delivery_id],$succ);
	            
	        if (!$stmtOrderLog->execute($params) || $stmtOrderLog->rowCount() === 0) {
	        	throw new Exception("خطاء فى حفظ سجلات أوامر البيع للنوع: $successKey");
	        }
	        
	        // ================== تحديث عهدة المندوب ==================
	        
	        // حذف اوامر البيع المجمدة
	        if (!empty($osucceedFreeze)) {
	            $placeholdersFr = implode(',', array_fill(0, count($osucceedFreeze), '?'));
	            $stDelCustOrder = $this->conn->prepare("
	                DELETE FROM custody_orders 
	                WHERE CUST_STAT = 'FREEZE'
	                  AND ORDER_ID IN($placeholdersFr)
	                  AND USER_ID = ?
	            ");
	            
	            $params = array_merge($osucceedFreeze, [$this->delivery_id]);
	            if (!$stDelCustOrder->execute($params) || $stDelCustOrder->rowCount() != count($osucceedFreeze)) {
	                throw new Exception('خطأ فى حذف أوامر البيع المجمدة');
	            }
	        }
	        
	        // تحديث اوامر الاستبدال فى عهدة المندوب
	        if (!empty($osucceedExchange)) {
	            $placeholdersEx = implode(',', array_fill(0, count($osucceedExchange), '?'));
	            $stUpCustReturn = $this->conn->prepare("
	                UPDATE custody_return_orders 
	                SET RETURNS = 1, 
	                    LAST_UPDATE = NOW(6) 
	                WHERE CUST_STAT = 'RETURNED'
	                  AND ORDER_ID IN($placeholdersEx)
	                  AND USER_ID = ?
	            ");
	            
	            $params = array_merge($osucceedExchange, [$this->delivery_id]);
	            if (!$stUpCustReturn->execute($params) || $stUpCustReturn->rowCount() != count($osucceedExchange)) {
	                throw new Exception('خطأ فى حذف أوامر الاستبدال');
	            }
	        }
	        
	        // تحديث أوامر المرفوضات بالكامل
	        $succNormal = array_diff($osucceedNormale,$osucceedDeliverd);
			if (!empty($succNormal)) {
	            $placeholdersNo = implode(',', array_fill(0, count($succNormal), '?'));
	            
	            // حذف الاوامر المرفوضه بالكامل
	            $stDelCustOrderRej = $this->conn->prepare("
	                DELETE FROM custody_orders 
	                WHERE CUST_STAT = 'DELIVERED' 
	                  AND ORDER_STATUSE = 5
	                  AND ORDER_ID IN($placeholdersNo)
	                  AND USER_ID = ?
	            ");
	            
	            $params = array_merge($succNormal, [$this->delivery_id]);
	            if (!$stDelCustOrderRej->execute($params)) {
	                throw new Exception('خطأ فى حذف الأوامر المرفوضة بالكامل');
	            }
	            
	            // تأكيد استلام المرفوضات فى عهدة المندوب
	            $stCustOrders = $this->conn->prepare("
	                UPDATE custody_orders 
	                SET RETURNS = 1, 
	                    LAST_UPDATE = NOW(6) 
	                WHERE CUST_STAT = 'DELIVERED'
	                  AND ORDER_ID IN($placeholdersNo)
	                  AND USER_ID = ?
	            ");
	            
	            if (!$stCustOrders->execute($params)) {
	                throw new Exception('خطأ فى تحديث تأكيد استلام المرتجعات');
	            }
				
				
	        }
	        
	        // =============== ادخال تاريخ التحديث لاوامر البيع ===============
	        
	        $placeholders = implode(',', array_fill(0, count($osucceeded), '?'));
	        $stmtLastUpdate = $this->conn->prepare("
	            UPDATE orders 
	            SET LAST_UPDATE = NOW(6) 
	            WHERE ORDER_ID IN ($placeholders)
	        ");
	        
	        if (!$stmtLastUpdate->execute($osucceeded)) {
	            throw new Exception("خطاء فى تحديث تاريخ الأوامر");
	        }
	        
	        $this->conn->commit();
	        
	        return [
	            'success', 
	            'ناجح', 
	            $message, 
	            $oISNotConditions
	        ];
	        
	    } catch (Exception $e) {
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollBack();
	        }
	        return ['error', 'خطأ', $e->getMessage(),$oISNotConditions];
	    } catch (PDOException $e) {
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollBack();
	        }
	        return ['error', 'خطأ', $e->getMessage()];
	    }finally{
	    	// 🔓 تحرير الأقفال
			$this->releaseGlobalInventoryLock();
			$this->conn = null;
	    }
	}
	// تعريب اخطاء قواعد البيانات
	public function mapPDOExceptionMessage(PDOException $e): string {
	    $errorInfo = $e->errorInfo;
	
	    // لو مفيش errorInfo نرجع الرسالة العادية
	    if (!$errorInfo || !isset($errorInfo[0])) {
	        return "خطأ غير معروف: " . $e->getMessage();
	    }
	
	    $sqlState = $errorInfo[0]; // SQLSTATE
	    $driverCode = $errorInfo[1] ?? null; // كود MySQL أو غيره
	
	    
	    switch ($sqlState) {
	        case '23000': // Integrity constraint violation
	            if ($driverCode == 1062) {
	                return "خطأ: ادخال بيانات متكرره (Duplicate entry). " . $e->getMessage();
	            } elseif ($driverCode == 1452) {
	                return "خطأ: فشل الربط بمفتاح أجنبي (Foreign key constraint). " . $e->getMessage();
	            } else {
	                return "خطأ في سلامة البيانات (Integrity constraint). " . $e->getMessage();
	            }
	
	        case '42000': // Syntax error or access violation
	            return "خطأ في صياغة الاستعلام SQL (Syntax error). " . $e->getMessage();
	
	        case '42S02': // Table not found
	            return "خطأ: الجدول المطلوب غير موجود. " . $e->getMessage();
	
	        case '42S22': // Column not found
	            return "خطأ: الحقل المطلوب غير موجود. " . $e->getMessage();
	
	        case 'HY093': // Invalid parameter number
	            return "خطأ: عدد أو أسماء المعاملات غير صحيح. " . $e->getMessage();
	
	        case '08004': // Connection refused
	        case '08006':
	            return "خطأ: فشل الاتصال بقاعدة البيانات. " . $e->getMessage();
	
	        case 'HY000': // General error
	        	// ======= معالجة مشاكل القفل =======
			    if ($driverCode === 1205 || strpos($e->getMessage(), 'NOWAIT') !== false) {
			        return "عذراً، بعض أوامر البيع أو المخزون قيد المعالجة حالياً، يرجى المحاولة لاحقاً.";
			    } else {
					return "خطأ عام في قاعدة البيانات. " . $e->getMessage();
			    }
	        default:
	            return "خطأ SQLSTATE {$sqlState}: " . $e->getMessage();
	    }
	}
		
	
}

class DirectSale_BE {
	private $InventoryManager;
	private $logManager;
	public $branch_id;
	public $user_action;
	public $user_group;
	public $store_id;
	public $last_update;
	private $shipment;
	private $dtz;
	private $conn;
	
	public function __construct($db) {
		$this->InventoryManager = new InventoryManager($db);
		$this->logManager = new LogManager(null, 'shipping');
		$this->dtz = new configDateTime();
		$this->shipment = new shipment_BE($db);
		$this->conn = $db;
	}
	private function fetchOrderProducts(int $orderId): array {
		$return = [
			'no' => [],
			'ex' => []
		];
		try {
			
			// إعداد وتنفيذ الاستعلام
			$stmt = $this->conn->prepare("SELECT OP.ORDER_ID,
					OP.PRO_CODE,
					PD.PRO_NAME,
                    OD.DELIVERY_ID AS USER_ID,
					CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
					OP.PRICE,
					OP.DISCOUNT,
					OP.QTY,
					OP.DELIVERED_QTY,
					OP.RETURNED_QTY,
					OD.ORDER_STATUS AS ORDER_STATUSE,
					OP.PRO_STATUSE,
					(OP.PRICE-OP.DISCOUNT) AS SUB_PRICE,
					'SELSE' AS TYPE
				  FROM  order_product AS OP
				  LEFT JOIN ORDERS   OD 
					ON OD.ORDER_ID = OP.ORDER_ID 
				  LEFT JOIN product_options_view PD 
					ON OP.PRO_CODE = PD.PRO_CODE
				  WHERE OD.ORDER_SUCCESS = 0 AND OD.ORDER_STATUS < 3 And OP.ORDER_ID = :oid 
			");

			$stmt->execute(['oid' => $orderId]);
			
			$stmt2 = $this->conn->prepare("SELECT SHIPPING_VAL FROM orders WHERE ORDER_ID = :oid");

			$stmt2->execute(['oid' => $orderId]);
			
			$shippingVal = $stmt2->fetchColumn();
			
			// معالجة كل منتج في النتيجة
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($row['TYPE'] == 'SELSE') {
					$return['no'][] = [
						'oid'    		=> $row['ORDER_ID'] ?? '',
						'did'    		=> $row['USER_ID']  ?? '0',
						'code'   		=> $row['PRO_CODE'] ?? '',
						'name'   		=> $row['PRO_NAME'] ?? '',
						'price'  		=> $row['SUB_PRICE'] ?? 0,
						'option' 		=> isset($row['OPTION']) ? explode(',', $row['OPTION']) : [],
						'qty'    		=> (float) ($row['QTY'] ?? 0),
						'delivered_qty' => (float) (($row['PRO_STATUSE'] ?? '') === 'WAITING' ? ($row['QTY'] ?? 0) : ($row['DELIVERED_QTY'] ?? 0)),
						'returned_qty'  => (float) $row['RETURNED_QTY'] ?? 0,
						'status' 		=> $row['PRO_STATUSE'] ?? 'UNKNOWN'
					];
				}
			}
			$return['SHIPPING_VAL'] = $shippingVal;
			
			return $return;

		} catch (PDOException $e) {
			// تسجيل الخطأ ثم إعادة رميه للتعامل معه في مستوى أعلى
			throw new PDOException("فشل في جلب بيانات المنتجات: " . $e->getMessage());
		}
	}
	public function get_inventory_id($branch_id) {
		$query = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';
		$stStoreid = $this->conn->prepare($query);
		$stStoreid->execute([':branchid' => $branch_id]);
		$result = $stStoreid->fetch(PDO::FETCH_ASSOC);
		return $result['STO_ID'];

	}

	/**
	* معالجة وضع العرض لتسليم الشحنة
	*
	* @param array $sql مصفوفة تحتوي على استعلامات SQL اللازمة
	* @return array نتيجة المعالجة تحتوي على نوع النتيجة وبيانات المنتجات
	*/
	private function handleViewMode(int $orderId): array {
		try {
			// جلب منتجات امر البيع
			$products = $this->fetchOrderProducts($orderId);

			// إرجاع نتيجة نجاح مع بيانات المنتجات
			return [
				'type' =>'success',
				'products' => $products['no'],
				'returned' => $products['ex']
			];

		} catch (Exception $e) {
			// معالجة أي استثناء قد يحدث وإرجاع رسالة خطأ
			return [
				'type' => 'error',
				'title' => 'خطأ',
				'message' => 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage()
			];
		}
	}
	
	public function confirmOrderSales($orderId) {
	    $lockedOrders     = [];
		$sessionId = uniqid('IM_', true);
	    $inventoryLocked  = false;
	    try {
	        $this->conn->beginTransaction();
	        $oidarray = array();
			 $oidarray[$orderId] = [$orderId];
	        // تنظيف مصفوفة أوامر البيع
	        $orderIds = array_values(array_unique(array_filter(
	            array_map('intval', array_keys($oidarray)), 
	            fn($v) => $v > 0 
	        )));
	        if (empty($orderIds)) {
	            throw new Exception("❌ لا توجد أوامر للمعالجة");
	        }
	        
	        // جلب بيانات مخزن الاستلام
	        if (!$this->store_id) {
	            $this->store_id = $this->shipment->get_inventory_id($this->branch_id);
	            if (!$this->store_id) {
	                throw new Exception("❌ لم يتم العثور على مخزن الاستلام");
	            }
	        }
	        
	        // ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
	        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	       
	        $stmtLock = $this->conn->prepare("
	            SELECT o.ORDER_ID, op.PRO_CODE
	            FROM orders o
	            JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
	            WHERE o.ORDER_ID IN ($placeholders)
	            FOR UPDATE NOWAIT
	        ");
	        $stmtLock->execute($orderIds);

	        // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->shipment->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }
	        
	        // ========== التحقق من الأوامر الناجحة ==========
	        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	        $stmt = $this->conn->prepare("SELECT O.ORDER_ID FROM orders O WHERE  O.LAST_UPDATE <= ? AND O.ORDER_ID IN ($placeholders);");
	        $params = array_merge([$this->last_update], $orderIds);
	        $stmt->execute($params);
	        $results =  $stmt->rowCount();
	        if ($stmt->rowCount()<=0) {
	            throw new Exception("❌ تم تحديث بيانات امر البيع من مستخدم اخر من فضلك قم بتحديث بيانات قائمة اوامر البيع ثم اعد المحاولة");
	        }
			
			// ================== تسليم امر البيع =====================
			
			$stmtOrders_UP = $this->conn->prepare("UPDATE orders SET LAST_UPDATE = NOW(6), ORDER_STATUS = 3 WHERE ORDER_ID IN($placeholders)");
			$stmtOrders_UP->execute($orderIds);
			if ($stmtOrders_UP->rowCount()<=0) {
	            throw new Exception("❌ خطاء اثناء تحديث بيانات امر البيع");
	        }
			
			$stmtOrderPro_UP = $this->conn->prepare("UPDATE ORDER_PRODUCT SET DELIVERED_QTY = QTY, PRO_STATUSE ='complete' WHERE ORDER_ID IN($placeholders)");
			$stmtOrderPro_UP->execute($orderIds);
			if ($stmtOrderPro_UP->rowCount()<=0) {
	            throw new Exception("❌ خطاء اثناء تحديث بيانات منتجات امر البيع");
	        }
			
			// ================== تحديث ارصدة العملاء =====================
			
			$stmtClientBalance_UP = $this->conn->prepare("
							UPDATE partners_balance pb
							INNER JOIN
							(
								SELECT o.ORDER_ID, c.CLIENT_ID, o.ORDER_SUCCESS, o.ORDER_STATUS,
									SUM((op.PRICE - op.DISCOUNT) * op.QTY)           AS SUM_ORDER,
									SUM((op.PRICE - op.DISCOUNT) * op.DELIVERED_QTY) AS SUM_DELIVERED,
									SUM((op.PRICE - op.DISCOUNT) * op.RETURNED_QTY)  AS SUM_RETURNED
								FROM orders o
								LEFT JOIN addresses a ON a.ADDRESS_ID = o.ADDRESS_ID
								LEFT JOIN client c ON c.CLIENT_ID = a.CLIENT_ID
								LEFT JOIN order_product op ON op.ORDER_ID = o.ORDER_ID
								WHERE o.ORDER_ID IN ($placeholders)
								GROUP BY o.ORDER_ID, c.CLIENT_ID, o.ORDER_SUCCESS, o.ORDER_STATUS
							) src 
							ON pb.PARTNER_ID = src.CLIENT_ID AND pb.REFERENCE_ID = src.ORDER_ID AND pb.PARTNER_TYPE = 'CLIENT' 
							AND pb.TRANSACTION_TYPE = CASE WHEN src.ORDER_SUCCESS > 0 AND src.SUM_RETURNED > 0 AND src.ORDER_STATUS IN (6,7) THEN 'CREDIT' WHEN src.ORDER_SUCCESS <= 0 AND src.SUM_RETURNED = 0 AND src.ORDER_STATUS IN (0,1,2,3,4) THEN 'DEBIT' ELSE NULL END
							AND pb.TRANSACTION_CATEGORY_ID = CASE WHEN src.ORDER_SUCCESS > 0 AND src.SUM_RETURNED > 0 AND src.ORDER_STATUS IN (6,7) THEN 14 WHEN src.ORDER_SUCCESS <= 0 AND src.SUM_RETURNED = 0 AND src.ORDER_STATUS IN (0,1,2,3,4) THEN 8 ELSE NULL END
							SET
							pb.BALANCE = CASE WHEN src.ORDER_SUCCESS <= 0 AND src.SUM_RETURNED = 0 AND src.ORDER_STATUS IN (0,1,2) THEN src.SUM_ORDER WHEN src.ORDER_SUCCESS <= 0 AND src.SUM_RETURNED = 0 AND src.ORDER_STATUS IN (3,4) THEN src.SUM_DELIVERED WHEN src.ORDER_SUCCESS > 0 AND src.SUM_RETURNED > 0 AND src.ORDER_STATUS IN (6,7) THEN src.SUM_RETURNED ELSE 0 END,
							pb.LAST_UPDATE = NOW(6);
							");
			
			if (!$stmtClientBalance_UP->execute($orderIds)){
				throw new Exception("خطاء اثناء تحديث ارصدة العميل");
			}
			
			if($stmtClientBalance_UP->rowCount() == 0) {
				$stmtClientBalance_IN = $this->conn->prepare("
								INSERT INTO partners_balance ( PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT, LAST_UPDATE)
								SELECT
									'CLIENT'   		AS PARTNER_TYPE,
									src.CLIENT_ID   AS PARTNER_ID,
									src.ORDER_ID    AS REFERENCE_ID,
									CASE 
										WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN 'CREDIT'
										WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2,3,4) AND src.SUM_RETURNED = 0 THEN 'DEBIT'
										ELSE NULL
									END   			AS TRANSACTION_TYPE,
									CASE
										WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2) AND src.SUM_RETURNED = 0 THEN src.SUM_ORDER
										WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (3,4) AND src.SUM_RETURNED = 0 THEN src.SUM_DELIVERED
										WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN src.SUM_RETURNED
										ELSE 0
									END    			AS BALANCE,
									CASE
										WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN 'رصيد دائن نتيجة ارجاع منتجات'
										WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2,3,4) AND src.SUM_RETURNED = 0 THEN 'مديونية عميل ناتجة عن أمر بيع' 
										ELSE NULL
									END    			AS NOTE,
									CASE
										WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN 14
										WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2,3,4) AND src.SUM_RETURNED = 0 THEN 8
										ELSE NULL
									END   			AS TRANSACTION_CATEGORY_ID,
									0      			AS USE_OF_CREDIT,
									NOW(6) 			AS LAST_UPDATE

								FROM
								(
									SELECT
										o.ORDER_ID,
										c.CLIENT_ID,
										o.ORDER_SUCCESS,
										o.ORDER_STATUS,
										SUM((op.PRICE - op.DISCOUNT) * op.QTY)              AS SUM_ORDER,
										SUM((op.PRICE - op.DISCOUNT) * op.DELIVERED_QTY)    AS SUM_DELIVERED,
										SUM((op.PRICE - op.DISCOUNT) * op.RETURNED_QTY)     AS SUM_RETURNED
									FROM orders o
									LEFT JOIN addresses a ON a.ADDRESS_ID = o.ADDRESS_ID
									LEFT JOIN client c ON c.CLIENT_ID = a.CLIENT_ID
									LEFT JOIN order_product op ON op.ORDER_ID = o.ORDER_ID
									WHERE o.ORDER_ID IN ($placeholders)
									GROUP BY o.ORDER_ID, c.CLIENT_ID, o.ORDER_SUCCESS, o.ORDER_STATUS
								) src
								WHERE NOT EXISTS
								(
									SELECT 1
									FROM partners_balance pb
									WHERE pb.PARTNER_TYPE = 'CLIENT'
									  AND pb.PARTNER_ID = src.CLIENT_ID
									  AND pb.REFERENCE_ID = src.ORDER_ID
									  AND pb.TRANSACTION_TYPE =
											CASE
												WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN 'CREDIT'
												WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2,3,4) AND src.SUM_RETURNED = 0 THEN 'DEBIT'
												ELSE NULL
											END
									  AND pb.TRANSACTION_CATEGORY_ID =
											CASE
												WHEN src.ORDER_SUCCESS > 0 AND src.ORDER_STATUS IN (6,7) AND src.SUM_RETURNED > 0 THEN 14
												WHEN src.ORDER_SUCCESS <= 0 AND src.ORDER_STATUS IN (0,1,2,3,4) AND src.SUM_RETURNED = 0 THEN 8
												ELSE NULL
											END
								);");
				if (!$stmtClientBalance_IN->execute($orderIds) || $stmtClientBalance_IN->rowCount() == 0) {
					throw new Exception("خطاء اثناء اضافة ارصدة العملاء");
				}
	        }
	        
	        // ================== تسجيل حركة الشحن ==================
	        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
	        $stmtShip = $this->conn->prepare("
	            INSERT INTO shipping_movement
	                (UNIQUE_ID, movement_type, delivery_id, user_id, shipment_orders)
	                SELECT
						? AS UNIQUE_ID,
						O.ORDER_STATUS AS movement_type,
						? AS delivery_id,
						? AS user_id,
	                    o.ORDER_ID AS shipment_orders
	                FROM orders o
	                WHERE o.ORDER_ID IN ($placeholders);
	        ");
	        
	        $params = array_merge([$sessionId, $this->user_action, $this->user_action], $orderIds);
	        if (!$stmtShip->execute($params) || $stmtShip->rowCount() == 0) {
	            throw new Exception("خطاء فى تسجيل حر1كة الشحن.");
	        }
	        
	        // ================== تسجيل حركة ومنتجات المخزون ==================
	       
			$this->InventoryManager->processInventory('SALES', $orderIds, $this->user_action, $this->branch_id, $sessionId, $this->store_id);
	        	        
	        // ================== حفظ سجلات الحركه لاوامر البيع ==================
			
	        $logtext = 'تم إجراء تحديث على بيانات أمر البيع من قِبل : ' . $this->user_group;
	        $stmtOrderLog = $this->conn->prepare("
	                INSERT INTO orders_log (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`, `DATE_ACTION`)
	                SELECT 
	                    o.ORDER_ID,
	                    ? AS USER_ACTION,
	                    CASE 
	                        WHEN o.ORDER_STATUS = 3 THEN CONCAT(? , '* تم تسليم امر البيع المباشر للعميل: ')
	                        WHEN o.ORDER_STATUS = 4 THEN CONCAT(? , '* تم تسليم امر البيع المباشر بشكل جزئى ')
	                        WHEN o.ORDER_STATUS = 5 THEN CONCAT(? , '* رفض العميل استلام امر البيع المباشر')
	                        ELSE NULL
	                    END AS LOG_TEXT,
	                    NOW(6) AS DATE_ACTION
	                FROM orders o 
	                WHERE o.ORDER_ID IN ($placeholders)
	        ");
	        
	        $params = array_merge([$this->user_action, $logtext, $logtext, $logtext],$orderIds);
	            
	        if (!$stmtOrderLog->execute($params) || $stmtOrderLog->rowCount() === 0) {
	        	throw new Exception("خطاء فى حفظ سجلات أوامر البيع للنوع: $orderIds");
	        }

		    $accounting = new AccountingEvents($this->conn);
			
			// ================== تسجيل القيود المحاسبية ================== 
			foreach ($orderIds as $orderId) {
		        $accounting->directSale($orderId);
		    }
	        
	        $this->conn->commit();
	        
	        return [
	            'success', 
	            'ناجح', 
	            'تم التسليم بنجاح', 
	            []
	        ];
	        
	    } catch (Exception $e) {
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollBack();
	        }
	        return ['error', 'خطأ', $e->getMessage(),[]];
	    } catch (PDOException $e) {
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollBack();
	        }
			if ($e->errorInfo[1] == 3572) {
				// ER_LOCK_NOWAIT
				return ['error', 'خطأ',"لا يمكن تنفيذ العملية الآن، يوجد مستخدم آخر يقوم بالتعديل على هذه الطلبات."];
			}
	        return ['error', 'خطأ', $e->getMessage()];
	    }finally{
	    	// 🔓 تحرير الأقفال
			$this->shipment->releaseGlobalInventoryLock();
			$this->conn = null;
	    }
	}
	
	
	/**
	* تحديث أوامر البيع والمرتجعات وعهدة المندوبين بسرعة فائقة مع ضمان الاتساق الكامل
	*
	* @param array $records مصفوفة تحتوي على بيانات الأوامر والمنتجات المراد تحديثها
	*    هيكل المصفوفة:
	*    [
	*        [
	*            'ORDER_ID' => 101,                    // رقم أمر البيع أو المرتجع
	*            'PRO_CODE' => 'SKU123',               // كود المنتج
	*            'DELIVERY_ID' => 12,                  // رقم المندوب
	*            'TYPE' => 'SELSE',                    // نوع السجل: SELSE (بيع) أو REFUND (مرتجع)
	*            'productData' => [
	*                'appQty' => 2,                    // الكمية المطلوبة
	*                'defqty' => 5,                    // الكمية المعتمدة
	*                'recqty' => null                  // الكمية المستلمة (للمرتجع فقط)
	*            ]
	*        ],
	*        ...
	*    ]
	* ------------------------------------------
	* # المنطق:
	*   1 - التحقق من وجود سجلات في confirm_staging للجلسة.
	*   2 - تحديث order_produc  → DELIVERED_QTY و PRO_STATUSE.
	*   3 - تحديث orders → ORDER_STATUS = 3 
	*   4 - تحديث أرصدة العملاء دفعة واحدة عبر:
	*         - restClientDebtBatch → مديونية العملاء.
	*         - reduceClientCreditBatch → الرصيد الدائن للمرتجعات.
	*   
	* 	5 - اضافة حركات الشحن لأمر البيع 
	*   6 - اضافة حركة المخزون (صرف منتجات امر البيع للعميل)
	*   7 - النتيجة النهائية.
	* ------------------------------------------
	* # الجداول المستخدمة:
	*   - order_product
	*   - orders
	* ------------------------------------------
	* قواعد recqty:
	* - إذا كان TYPE = SELSE: recqty يجب أن يكون null دائماً
	* - إذا كان TYPE = REFUND: recqty يجب أن يكون رقم صالح
	*
	* @param array &$orejected مصفوفة مرجعية لتسجيل الأوامر المرفوضة
	* @param mixed &$ecode متغير مرجعي لتسجيل كود الخطأ
	*
	* @return bool true في حالة النجاح، false في حالة الفشل
	*
	* @throws PDOException في حالة حدوث خطأ في قاعدة البيانات
	*/ 
	private function confirmOrderSales1(){
	    // التحقق من البيانات المدخلة
	    if (empty($this->shipmentobj['ship']) || !is_array($this->shipmentobj['ship'])) {
	        return ['error', 'خطأ', 'بيانات الشحنة غير صالحة',[]];
	    }
	    
		if($_POST['oshipp']['ptype'] != 1 && ($this->ShipApproved === TRUE || $this->ShipApproved === 'TRUE' || $this->ShipApproved == 1)){
			return ['error', 'خطأ', 'انت لا تملك صلاحية انشاء اذن استلام مخزنى',[]];
		}
	    $items = $this->shipmentobj['ship'];
	    $sessionId = uniqid('sess_', true);
	    $executedBy = $this->user_action;
	    $executedGr = $this->user_group;
	
	    $lockedOrders = [];
	    $inventoryLocked = false;
	
	    try {
	        $this->conn->beginTransaction();

			$orderids = array_map(function ($row) {
			    return (int)$row['ORDER_ID'];
			}, $items);
			
			// ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
		    $placeholders = implode(',', array_fill(0, count($orderids), '?'));
		    $stmtLock = $this->conn->prepare("
		        SELECT o.ORDER_ID, op.PRO_CODE
		        FROM orders o
		        JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
		        WHERE o.ORDER_ID IN ($placeholders)
		        FOR UPDATE NOWAIT
		    ");
		    $stmtLock->execute($orderids);

	        // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }
	        
	
	        // 3. أكمل العملية عادي
	        //TEMPORARY
	        /*$createTmp = "
	            CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
	                SESSION_ID    VARCHAR(64) NOT NULL,
	                ORDER_ID      INT NOT NULL,
	                PRO_CODE      VARCHAR(100) NOT NULL,
	                USER_ID       INT NOT NULL,
	                APP_QTY       DECIMAL(18,3) NOT NULL,
	                STATUSE       VARCHAR(50) NOT NULL,
	                TYPE          ENUM('SELSE','REFUND') NOT NULL,
	                CREATED_AT    DATETIME(3) DEFAULT NOW(3),
	                PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, TYPE)
	            ) ENGINE=InnoDB;
	            

	        ";
	        $this->conn->exec($createTmp);

	        // إعداد استعلام الإدخال
	        $insertSql = "
	            INSERT INTO confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE) 
	            VALUES (:session_id, :order_id, :pro_code, :user_id, :app_qty, :statuse, :type)";
	        $insStmt = $this->conn->prepare($insertSql);
	        
	        foreach ($items as $index => $row) {
	            if (empty($row['ORDER_ID']) || empty($row['PRO_CODE'])) {
	                throw new Exception("بيانات غير مكتملة في الصف " . ($index + 1));
	            }
	            $orderId = (int)$row['ORDER_ID'];
	            $proCode = trim($row['PRO_CODE']);
	            $userId  = isset($row['DELIVERY_ID']) ? (int)$row['DELIVERY_ID'] : $executedBy;
	            $appQty  = isset($row['productData']['appQty']) && is_numeric($row['productData']['appQty']) ? 
	                       (float)$row['productData']['appQty'] : 0.0;
	            $recQty  = $row['productData']['recqty'] ?? null;
	            $statuse = (!empty($recQty) && is_numeric($recQty) && (float)$recQty > 0) ? 'returned' : 'pending';
	            $type = in_array($row['TYPE'] ?? '', ['SELSE', 'REFUND']) ? $row['TYPE'] : 'SELSE';
	            
	            $insStmt->execute([
	                ':session_id' => $sessionId,
	                ':order_id'   => $orderId,
	                ':pro_code'   => $proCode,
	                ':user_id'    => $userId,
	                ':app_qty'    => $appQty,
	                ':statuse'    => $statuse,
	                ':type'       => $type
	            ]);
	        }*/
	        /*
			|--------------------------------------------------------------------------
			| CREATE TEMP TABLE
			|--------------------------------------------------------------------------
			*/
			
			$this->conn->exec("
			    CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
			        SESSION_ID VARCHAR(64) NOT NULL,
			        ORDER_ID INT NOT NULL,
			        PRO_CODE VARCHAR(100) NOT NULL,
			        USER_ID INT NOT NULL,
			        APP_QTY DECIMAL(18,3) NOT NULL,
			        STATUSE VARCHAR(50) NOT NULL,
			        TYPE ENUM('SELSE', 'REFUND', 'SHIPPING', 'FREEZE', 'DEFREEZE') NOT NULL,
			        CREATED_AT DATETIME(3) DEFAULT NOW(3),
			
			        PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, TYPE),
			        
			        INDEX idx_session (SESSION_ID),
			        INDEX idx_session_type (SESSION_ID, TYPE),
			        INDEX idx_order_product (ORDER_ID, PRO_CODE),
			        INDEX idx_order_user (ORDER_ID, USER_ID)
			
			    ) ENGINE=InnoDB
			");
			
			/*
			|--------------------------------------------------------------------------
			| BULK INSERT
			|--------------------------------------------------------------------------
			*/
			
			$chunkSize = 1000;
			
			foreach (array_chunk($items, $chunkSize) as $chunk) {
			
			    $placeholders = [];
			    $params = [];
			
			    foreach ($chunk as $index => $row) {
			
			        if (!isset($row['ORDER_ID']) || !isset($row['PRO_CODE'])) {
			            throw new Exception('بيانات غير مكتملة فى الصف رقم ' . ($index + 1));
			        }
			        $orderId = (int)$row['ORDER_ID'];
			        $proCode = trim((string)$row['PRO_CODE']);
			        $userId = isset($row['DELIVERY_ID']) ? (int)$row['DELIVERY_ID'] : (int)$executedBy;
			        $appQty = (isset($row['productData']['appQty']) && is_numeric($row['productData']['appQty'])) ? (float)$row['productData']['appQty'] : 0.0;
			        $recQty = (isset($row['productData']['recqty']) && is_numeric($row['productData']['recqty'])) ? (float)$row['productData']['recqty'] : 0.0;
			        $statuse = $recQty > 0 ? 'returned' : 'pending';
			        $type = strtoupper( trim($row['TYPE'] ?? 'SELSE'));
			        if (!in_array($type,['SELSE','REFUND', 'SHIPPING', 'FREEZE', 'DEFREEZE'], true)) {
			            $type = 'SELSE';
			        }
			
			        $placeholders[] = '(?,?,?,?,?,?,?)';
			
			        $params[] = $sessionId;
			        $params[] = $orderId;
			        $params[] = $proCode;
			        $params[] = $userId;
			        $params[] = $appQty;
			        $params[] = $statuse;
			        $params[] = $type;
			    }
			
			    if (!$placeholders) {
			        continue;
			    }
			
			    $sql = "INSERT INTO confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE ) VALUES " . implode(',', $placeholders);
			
			    $insStmt = $this->conn->prepare($sql);
			
			    $insStmt->execute($params);
			}
	        
	        
	        
	        $insStmt->closeCursor();
	 
	        // استدعاء البروسيدجر
	        $call = $this->conn->prepare("CALL setDeliveryConfirmationOptimized(:session_id, :executed_by, :executed_gr, :branch_id, :ShipApproved)");
	        $call->execute([
	            ':session_id'   => $sessionId,
	            ':executed_by'  => $executedBy,
	            ':executed_gr'  => $executedGr,
	            ':branch_id'    => $this->branch_id,
	            ':ShipApproved' => $this->ShipApproved
	        ]);
	        
	        $result = $call->fetchAll(PDO::FETCH_ASSOC);
	        $call->closeCursor();
	       
	        if (!empty($result) && isset($result[0]['status']) && $result[0]['status'] == 'success') {
	            $this->conn->commit();
	            return [
	                'success',
	                'نجح',
	                'تم تأكيد الشحنة بنجاح',
	                ''
	            ];
	        } else {
	        	
	            $errorMessage = $result[0]['message'] ?? 'فشل في تأكيد الشحنة';
	            //print_r($result);
	            throw new Exception($errorMessage);
	        }
	        
	    } catch (PDOException $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ في قاعدة البيانات', $e->getMessage()];
	    } catch (Exception $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ', $e->getMessage()];
	    } finally {
	        // 🔓 تحرير الأقفال
			$inventoryLocked = $this->releaseGlobalInventoryLock();
	        // الـ Inventory Lock بيتحرر أوتوماتيك مع انتهاء الـ Transaction
	        if ($inventoryLocked) {
	            $this->logManager->info("🔓 تم تحرير أقفال المخزون تلقائياً");
	        }
	    }
	}
	/**
	 * =========================================================================
	 * 🚚 Customer delivery of products - إدارة تسليم المنتجات مباشر للعميل (Ultimate Documentation)
	 * =========================================================================
	 *
	 * 📌 الوصف العام:
	 * -------------------------------------------------------------------------
	 * هذه الدالة مسؤولة عن إدارة عملية تسليم  المنتجات للعميل مباشر من المخزن ، وتعمل كـ Controller
	 * لتحديد مسار التنفيذ بناءً على نوع العملية ($type).
	 *
	 * تدعم الدالة وضعين رئيسيين:
	 * - view    : عرض بيانات امر البيع
	 * - confirm : تأكيد عملية التسليم
	 *
	 * -------------------------------------------------------------------------
	 * الهدف:
	 * - توحيد نقطة الدخول لعمليات التسليم
	 * - فصل منطق العرض عن منطق التنفيذ
	 * - إدارة الأخطاء والـ logging بشكل مركزي
	 *
	 * =========================================================================
	 * ⚙️ الفلو الكامل (Execution Flow):
	 * =========================================================================
	 *
	 * [Start]
	 *   ↓
	 * تهيئة الاتصال (query)
	 *   ↓
	 * تحديد نوع العملية (type)
	 *   ↓
	 * ┌───────────────────────────────┐
	 * │   هل type = 'view' ؟          │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   هل عدد الطلبات = 1 ؟
	 *        ├── لا → تجاهل
	 *        └── نعم ↓
	 *             ↓
	 *   handleViewMode(sql)
	 *             ↓
	 *           Return
	 *
	 * ┌───────────────────────────────┐
	 * │   هل type = 'confirm' ؟       │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   confirmShipmentDelivery()
	 *        ↓
	 *      Return
	 *
	 * ┌───────────────────────────────┐
	 * │   نوع غير معروف              │
	 * └───────────────────────────────┘
	 *        ↓
	 *   تسجيل خطأ في logManager
	 *        ↓
	 *   (يفضل Return error)
	 *
	 * =========================================================================
	 * 🧠 حالات التشغيل (Modes):
	 * =========================================================================
	 *
	 * 🟢 1. View Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'view'
	 * - عدد الطلبات = 1
	 *
	 * الهدف:
	 * - عرض تفاصيل منتجات امر واحد فقط
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   handleViewMode($sql)
	 *
	 * المتوقع داخلها:
	 * - جلب بيانات الطلب
	 * - تجهيز بيانات العرض
	 * - إرجاع response مناسب للـ UI
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🟡 2. Confirm Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'confirm'
	 *
	 * الهدف:
	 * - تنفيذ عملية تسليم الشحنة
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   confirmShipmentDelivery()
	 *
	 * المتوقع داخلها:
	 * - تحديث حالة الطلب
	 * - تسجيل حركات الشحن
	 * - التعامل مع المخزون
	 * - تسجيل logs
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🔴 3. Invalid Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - type لا يساوي 'view' أو 'confirm'
	 *
	 * السلوك الحالي:
	 * - تسجيل error فقط
	 *
	 * ⚠️ المشكلة:
	 * - لا يوجد return واضح
	 *
	 * =========================================================================
	 * 🧱 Dependencies:
	 * =========================================================================
	 *
	 * - query()
	 *   → تجهيز اتصال قاعدة البيانات
	 *
	 * - handleViewMode()
	 *   → منطق عرض الشحنة
	 *
	 * - confirmShipmentDelivery()
	 *   → منطق تنفيذ التسليم
	 *
	 * - logManager
	 *   → تسجيل الأخطاء والـ logs
	 *
	 * =========================================================================
	 * 🛑 Error Handling:
	 * =========================================================================
	 *
	 * 1️⃣ PDOException
	 * -------------------------------------------------------------------------
	 * - أخطاء قاعدة البيانات
	 * - يتم:
	 *   • تسجيل الخطأ
	 *   • تحويل الرسالة
	 *   • إرجاع response error
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 2️⃣ ErrorException
	 * -------------------------------------------------------------------------
	 * - أخطاء منطقية داخل التطبيق
	 * - يتم:
	 *   • تسجيل (file + line)
	 *   • إرجاع رسالة واضحة
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 3️⃣ Exception (عام)
	 * -------------------------------------------------------------------------
	 * - أي خطأ غير متوقع
	 * - يتم:
	 *   • تسجيله
	 *   • إرجاع رسالة
	 *
	 * =========================================================================
	 * 🔚 finally:
	 * =========================================================================
	 *
	 * - logManager->finalize()
	 *   → إنهاء عملية التسجيل
	 *   → ممكن تشمل:
	 *     • flush logs
	 *     • إغلاق handlers
	 *
	 * =========================================================================
	 * 📥 @param string $type
	 * -------------------------------------------------------------------------
	 * نوع العملية:
	 * - 'view'    → عرض بيانات الشحنة
	 * - 'confirm' → تأكيد التسليم
	 *
	 * =========================================================================
	 * 📤 @return array
	 * -------------------------------------------------------------------------
	 * [
	 *   0 => string, // success | error
	 *   1 => string, // title
	 *   2 => string  // message
	 * ]
	 *
	 * =========================================================================
	 * 📌 أمثلة الاستخدام:
	 * =========================================================================
	 *
	 * // عرض شحنة
	 * $result = $this->ShipmentDelivery('view');
	 *
	 * // تأكيد التسليم
	 * $result = $this->ShipmentDelivery('confirm');
	 *
	 * =========================================================================
	 * ⚠️ مشاكل محتملة (Critical Issues):
	 * =========================================================================
	 *
	 * 1. لا يوجد return في حالة type غير صحيح
	 *    → قد يسبب undefined behavior
	 *
	 * 2. $sql يتم إنشاؤه دائمًا
	 *    → لكنه لا يستخدم في confirm mode
	 *
	 * 3. الاعتماد على string بدل constants
	 *
	 * 4. عدم التحقق من count(orderobj) في confirm
	 *
	 * =========================================================================
	 * 💡 تحسينات مقترحة (Recommended Improvements):
	 * =========================================================================
	 *
	 * ✔ إضافة return واضح في invalid type:
	 *   return ['error','خطأ','نوع العملية غير صحيح'];
	 *
	 * ✔ استخدام constants:
	 *   const MODE_VIEW = 'view';
	 *   const MODE_CONFIRM = 'confirm';
	 *
	 * ✔ Lazy loading للـ query():
	 *   يتم فقط عند الحاجة (view mode)
	 *
	 * ✔ توحيد شكل response دائمًا
	 *
	 * ✔ إضافة validation قبل confirm:
	 *   - التأكد من وجود orders
	 *
	 * =========================================================================
	 * @throws PDOException
	 * @throws ErrorException
	 * @throws Exception
	 * =========================================================================
	 */
	public function CustomerDeliveryOfProducts(array $orderData, string $type = 'view'): array {
		try {
			$orderId = array_key_first($orderData);
			
			// حالة العرض: عندما يكون هناك طلب واحد فقط
			if ($type === 'view') {
				return $this->handleViewMode($orderId);
			}
			// حالة التأكيد
			if ($type === 'confirm') {
			    $results = $this->confirmOrderSales();
				return $results;
			}
			$this->logManager->error("حدث خطاء اثناء تنفيذ المعامله ShipmentDelivery >> error type");
			// إذا لم يتطابق مع أي حالة
			//throw new ErrorException(" الطلب غير صحيح ",1001,  كود الخطأ E_USER_ERROR,  مستوى الخطأ __FILE__,  اسم الملف الحالي __LINE__  رقم السطر الحالي);
		} catch (PDOException $e) { // معالجة الأخطاء
			$this->logManager->exception("SQL ERROR : ".$this->mapPDOExceptionMessage($e));
			return ['error','خطأ',$this->mapPDOExceptionMessage($e)];
		} catch (ErrorException $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} catch (Exception $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} finally {
			$this->logManager->finalize();
		}
	}
	
}

class RetrnSale_BE {
    private $InventoryManager;
	private $logManager;
	
	public $orderobj;
	public $urlControl;   
	public $ShipApproved;
	public $shipmentobj;
	public $ShipExchange;
	public $user_action;
	public $branch_id;
	public $user_id;
	public $user_group;
	
	public $delivery_id;
	public $shipment_id;
	
	private $dtz;
	private $conn;
	
	public function __construct($db) {
		$this->InventoryManager = new InventoryManager($db);
		$this->logManager = new LogManager(null, 'shipping');
		$this->dtz = new configDateTime();
		$this->conn = $db;
	}
	private function fetchOrderProducts(int $orderId): array {
		$return = [
			'no' => [],
			'ex' => []
		];
		try {
			
			// إعداد وتنفيذ الاستعلام
			$stmt = $this->conn->prepare("SELECT OP.ORDER_ID,
					OP.PRO_CODE,
					PD.PRO_NAME,
                    OD.DELIVERY_ID AS USER_ID,
					CASE WHEN OPT_JSON IS NOT NULL THEN OPT_JSON ELSE '' END AS OPTION,
					OP.PRICE,
					OP.DISCOUNT,
					OP.QTY,
					OP.DELIVERED_QTY,
					OP.RETURNED_QTY,
					OD.ORDER_STATUS AS ORDER_STATUSE,
					OP.PRO_STATUSE,
					'SELSE' AS TYPE
				  FROM  order_product AS OP
				  LEFT JOIN ORDERS OD ON OD.ORDER_ID = OP.ORDER_ID 
				  LEFT JOIN product_options_view PD ON OP.PRO_CODE = PD.PRO_CODE
				  WHERE OD.ORDER_SUCCESS > 0 AND OD.ORDER_STATUS IN(3,4) AND OP.PRO_STATUSE IN('partial','complete') AND OP.ORDER_ID = :oid 
			");

			$stmt->execute(['oid' => $orderId]);
			
			// معالجة كل منتج في النتيجة
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($row['TYPE'] == 'SELSE') {
					$return['ex'][] = [
						'oid'    		=> $row['ORDER_ID'] ?? '',
						'did'    		=> $row['USER_ID']  ?? '0',
						'code'   		=> $row['PRO_CODE'] ?? '',
						'name'   		=> $row['PRO_NAME'] ?? '',
						'option' 		=> isset($row['OPTION']) ? explode(',', $row['OPTION']) : [],
						'qty'    		=> (float) ($row['QTY'] ?? 0),
						'delivered_qty' => (float) (($row['PRO_STATUSE'] ?? '') === 'WAITING' ? ($row['QTY'] ?? 0) : ($row['DELIVERED_QTY'] ?? 0)),
						'returned_qty'  => (float) $row['RETURNED_QTY'] ?? 0,
						'status' 		=> $row['PRO_STATUSE'] ?? 'UNKNOWN'
					];
				}
			}
			return $return;

		} catch (PDOException $e) {
			// تسجيل الخطأ ثم إعادة رميه للتعامل معه في مستوى أعلى
			throw new PDOException("فشل في جلب بيانات المنتجات: " . $e->getMessage());
		}
	}
	
	/**
	* معالجة وضع العرض لتسليم الشحنة
	*
	* @param array $sql مصفوفة تحتوي على استعلامات SQL اللازمة
	* @return array نتيجة المعالجة تحتوي على نوع النتيجة وبيانات المنتجات
	*/
	private function handleViewMode(int $orderId): array {
		try {
			// جلب منتجات امر البيع
			$products = $this->fetchOrderProducts($orderId);

			// إرجاع نتيجة نجاح مع بيانات المنتجات
			return [
				'type' =>'success',
				'products' => $products['no'],
				'returned' => $products['ex']
			];

		} catch (Exception $e) {
			// معالجة أي استثناء قد يحدث وإرجاع رسالة خطأ
			return [
				'type' => 'error',
				'title' => 'خطأ',
				'message' => 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage()
			];
		}
	}
	/**
	* تحديث أوامر البيع والمرتجعات وعهدة المندوبين بسرعة فائقة مع ضمان الاتساق الكامل
	*
	* @param array $records مصفوفة تحتوي على بيانات الأوامر والمنتجات المراد تحديثها
	*    هيكل المصفوفة:
	*    [
	*        [
	*            'ORDER_ID' => 101,                    // رقم أمر البيع أو المرتجع
	*            'PRO_CODE' => 'SKU123',               // كود المنتج
	*            'DELIVERY_ID' => 12,                  // رقم المندوب
	*            'TYPE' => 'SELSE',                    // نوع السجل: SELSE (بيع) أو REFUND (مرتجع)
	*            'productData' => [
	*                'appQty' => 2,                    // الكمية المطلوبة
	*                'defqty' => 5,                    // الكمية المعتمدة
	*                'recqty' => null                  // الكمية المستلمة (للمرتجع فقط)
	*            ]
	*        ],
	*        ...
	*    ]
	* ------------------------------------------
	* # المنطق:
	*   1 - التحقق من وجود سجلات في confirm_staging للجلسة.
	*   2 - تحديث order_produc  → DELIVERED_QTY و PRO_STATUSE.
	*   3 - تحديث orders → ORDER_STATUS = 3 
	*   4 - تحديث أرصدة العملاء دفعة واحدة عبر:
	*         - restClientDebtBatch → مديونية العملاء.
	*         - reduceClientCreditBatch → الرصيد الدائن للمرتجعات.
	*   
	* 	5 - اضافة حركات الشحن لأمر البيع 
	*   6 - اضافة حركة المخزون (صرف منتجات امر البيع للعميل)
	*   7 - النتيجة النهائية.
	* ------------------------------------------
	* # الجداول المستخدمة:
	*   - order_product
	*   - orders
	* ------------------------------------------
	* قواعد recqty:
	* - إذا كان TYPE = SELSE: recqty يجب أن يكون null دائماً
	* - إذا كان TYPE = REFUND: recqty يجب أن يكون رقم صالح
	*
	* @param array &$orejected مصفوفة مرجعية لتسجيل الأوامر المرفوضة
	* @param mixed &$ecode متغير مرجعي لتسجيل كود الخطأ
	*
	* @return bool true في حالة النجاح، false في حالة الفشل
	*
	* @throws PDOException في حالة حدوث خطأ في قاعدة البيانات
	*/ 
	private function returnedOrderSales(){
		
		
	    // التحقق من البيانات المدخلة
	    if (empty($this->shipmentobj['ship']) || !is_array($this->shipmentobj['ship'])) {
	        return ['error', 'خطأ', 'بيانات الشحنة غير صالحة',[]];
	    }
	    
		if($_POST['oshipp']['ptype'] != 1 && ($this->ShipApproved === TRUE || $this->ShipApproved === 'TRUE' || $this->ShipApproved == 1)){
			return ['error', 'خطأ', 'انت لا تملك صلاحية انشاء اذن استلام مخزنى',[]];
		}
	    $items = $this->shipmentobj['ship'];
	    $sessionId = uniqid('sess_', true);
	    $executedBy = $this->user_action;
	    $executedGr = $this->user_group;
	
	    $lockedOrders = [];
	    $inventoryLocked = false;
	
	    try {
	        $this->conn->beginTransaction();

			$orderids = array_map(function ($row) {
			    return (int)$row['ORDER_ID'];
			}, $items);
			
			// ========== قفل أوامر البيع FOR UPDATE NOWAIT ==========
		    $placeholders = implode(',', array_fill(0, count($orderids), '?'));
		    $stmtLock = $this->conn->prepare("
		        SELECT o.ORDER_ID, op.PRO_CODE, op.DELIVERED_QTY
		        FROM orders o
		        JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
		        WHERE o.ORDER_ID IN ($placeholders)
		        FOR UPDATE NOWAIT
		    ");
		    $stmtLock->execute($orderids);
		    // الكمية المسلّمة من قاعدة البيانات هي المرجع، لا بيانات الواجهة وحدها.
		    $deliveredQtyByProduct = [];
		    foreach ($stmtLock->fetchAll(PDO::FETCH_ASSOC) as $lockedProduct) {
		        $productKey = (int)$lockedProduct['ORDER_ID'] . '|' . (string)$lockedProduct['PRO_CODE'];
		        $deliveredQtyByProduct[$productKey] = (float)$lockedProduct['DELIVERED_QTY'];
		    }

	        // 🔒 2. أقفل المخزون والحركات (الجديد)
	        $inventoryLocked = $this->acquireGlobalInventoryLock();
	        if (!$inventoryLocked) {
	        	throw new Exception(" النظام يقوم بتحديث المخزون حالياً.. حاول بعد ثوانٍ قليلة.");
	        }
	        

			
			$this->conn->exec("
			    CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
			        SESSION_ID VARCHAR(64) NOT NULL,
			        ORDER_ID INT NOT NULL,
			        PRO_CODE VARCHAR(100) NOT NULL,
			        USER_ID INT NOT NULL,
			        APP_QTY DECIMAL(18,3) NOT NULL,
			        STATUSE VARCHAR(50) NOT NULL,
			        TYPE ENUM('SELSE','REFUND') NOT NULL,
			        CREATED_AT DATETIME(3) DEFAULT NOW(3),
			
			        PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, TYPE),
			        
			        INDEX idx_session (SESSION_ID),
			        INDEX idx_session_type (SESSION_ID, TYPE),
			        INDEX idx_order_product (ORDER_ID, PRO_CODE),
			        INDEX idx_order_user (ORDER_ID, USER_ID)
			
			    ) ENGINE=InnoDB
			");
			
			/*
			|--------------------------------------------------------------------------
			| BULK INSERT
			|--------------------------------------------------------------------------
			*/
			
			$chunkSize = 1000;
			
			foreach (array_chunk($items, $chunkSize) as $chunk) {
			
			    $placeholders = [];
			    $params = [];
			
			    foreach ($chunk as $index => $row) {
			
			        if (!isset($row['ORDER_ID']) || !isset($row['PRO_CODE'])) {
			            throw new Exception('بيانات غير مكتملة فى الصف رقم ' . ($index + 1));
			        }
			        $orderId = (int)$row['ORDER_ID'];
			        $proCode = trim((string)$row['PRO_CODE']);
			        $userId = isset($row['DELIVERY_ID']) ? (int)$row['DELIVERY_ID'] : (int)$executedBy;
			        $appQty = (isset($row['productData']['appQty']) && is_numeric($row['productData']['appQty'])) ? (float)$row['productData']['appQty'] : 0.0;
			        $recQty = (isset($row['productData']['recqty']) && is_numeric($row['productData']['recqty'])) ? (float)$row['productData']['recqty'] : 0.0;
			        $statuse = $recQty > 0 ? 'returned' : 'pending';
			        $type = strtoupper( trim($row['TYPE'] ?? 'REFUND'));
			        if (!in_array($type,['SELSE','REFUND', 'SHIPPING', 'FREEZE', 'DEFREEZE'], true)) {
			            $type = 'REFUND';
			        }
		        // منع إرجاع كمية أكبر من الكمية المسلّمة فعليًا حسب قاعدة البيانات.
		        if ($type === 'REFUND') {
		            $productKey = $orderId . '|' . $proCode;
		            if (!array_key_exists($productKey, $deliveredQtyByProduct)) {
		                throw new Exception("المنتج {$proCode} غير مرتبط بأمر البيع {$orderId}.");
		            }
		            if ($appQty < 0 || $appQty > $deliveredQtyByProduct[$productKey]) {
		                throw new Exception("كمية المرتجع للمنتج {$proCode} تتجاوز الكمية المسلّمة فعليًا أو غير صالحة.");
		            }
		        }
			
			        $placeholders[] = '(?,?,?,?,?,?,?)';
			
			        $params[] = $sessionId;
			        $params[] = $orderId;
			        $params[] = $proCode;
			        $params[] = $userId;
			        $params[] = $appQty;
			        $params[] = $statuse;
			        $params[] = $type;
			    }
			
			    if (!$placeholders) {
			        continue;
			    }
			
			    $sql = "INSERT INTO confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE ) VALUES " . implode(',', $placeholders);
			
			    $insStmt = $this->conn->prepare($sql);
			
			    $insStmt->execute($params);
			}
	        
	        
	        
	        $insStmt->closeCursor();
	 
	        // استدعاء البروسيدجر
	        $call = $this->conn->prepare("CALL setRetunedConfirmationOptimized(:session_id, :executed_by, :executed_gr, :branch_id, :ShipApproved)");
	        $call->execute([
	            ':session_id'   => $sessionId,
	            ':executed_by'  => $executedBy,
	            ':executed_gr'  => $executedGr,
	            ':branch_id'    => $this->branch_id,
	            ':ShipApproved' => $this->ShipApproved
	        ]);
	        
	        $result = $call->fetchAll(PDO::FETCH_ASSOC);
	        $call->closeCursor();
	       
	        if (!empty($result) && isset($result[0]['status']) && $result[0]['status'] == 'success') {
	            
	            $this->conn->commit();
	            //$this->conn->rollBack();
	            return [
	                'success',
	                'نجح',
	                'تم تأكيد الشحنة بنجاح',
	                ''
	            ];
	        } else {
	        	
	            $errorMessage = $result[0]['message'] ?? 'فشل في تأكيد الشحنة';
	            //print_r($result);
	            throw new Exception($errorMessage);
	        }
	        
	    } catch (PDOException $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ في قاعدة البيانات', $e->getMessage()];
	    } catch (Exception $e) {
	        $this->conn->rollBack();
	        return ['error', 'خطأ', $e->getMessage()];
	    } finally {
	        // 🔓 تحرير الأقفال
			$inventoryLocked = $this->releaseGlobalInventoryLock();
	        // الـ Inventory Lock بيتحرر أوتوماتيك مع انتهاء الـ Transaction
	        if ($inventoryLocked) {
	            $this->logManager->info("🔓 تم تحرير أقفال المخزون تلقائياً");
	        }
	    }
	}
	/**
	 * =========================================================================
	 * 🚚 Customer delivery of products - إدارة تسليم المنتجات مباشر للعميل (Ultimate Documentation)
	 * =========================================================================
	 *
	 * 📌 الوصف العام:
	 * -------------------------------------------------------------------------
	 * هذه الدالة مسؤولة عن إدارة عملية تسليم  المنتجات للعميل مباشر من المخزن ، وتعمل كـ Controller
	 * لتحديد مسار التنفيذ بناءً على نوع العملية ($type).
	 *
	 * تدعم الدالة وضعين رئيسيين:
	 * - view    : عرض بيانات امر البيع
	 * - confirm : تأكيد عملية التسليم
	 *
	 * -------------------------------------------------------------------------
	 * الهدف:
	 * - توحيد نقطة الدخول لعمليات التسليم
	 * - فصل منطق العرض عن منطق التنفيذ
	 * - إدارة الأخطاء والـ logging بشكل مركزي
	 *
	 * =========================================================================
	 * ⚙️ الفلو الكامل (Execution Flow):
	 * =========================================================================
	 *
	 * [Start]
	 *   ↓
	 * تهيئة الاتصال (query)
	 *   ↓
	 * تحديد نوع العملية (type)
	 *   ↓
	 * ┌───────────────────────────────┐
	 * │   هل type = 'view' ؟          │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   هل عدد الطلبات = 1 ؟
	 *        ├── لا → تجاهل
	 *        └── نعم ↓
	 *             ↓
	 *   handleViewMode(sql)
	 *             ↓
	 *           Return
	 *
	 * ┌───────────────────────────────┐
	 * │   هل type = 'confirm' ؟       │
	 * └───────────────────────────────┘
	 *        ↓ نعم
	 *   confirmShipmentDelivery()
	 *        ↓
	 *      Return
	 *
	 * ┌───────────────────────────────┐
	 * │   نوع غير معروف              │
	 * └───────────────────────────────┘
	 *        ↓
	 *   تسجيل خطأ في logManager
	 *        ↓
	 *   (يفضل Return error)
	 *
	 * =========================================================================
	 * 🧠 حالات التشغيل (Modes):
	 * =========================================================================
	 *
	 * 🟢 1. View Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'view'
	 * - عدد الطلبات = 1
	 *
	 * الهدف:
	 * - عرض تفاصيل منتجات امر واحد فقط
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   handleViewMode($sql)
	 *
	 * المتوقع داخلها:
	 * - جلب بيانات الطلب
	 * - تجهيز بيانات العرض
	 * - إرجاع response مناسب للـ UI
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🟡 2. Confirm Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - $type === 'confirm'
	 *
	 * الهدف:
	 * - تنفيذ عملية تسليم الشحنة
	 *
	 * التنفيذ:
	 * - استدعاء:
	 *   confirmShipmentDelivery()
	 *
	 * المتوقع داخلها:
	 * - تحديث حالة الطلب
	 * - تسجيل حركات الشحن
	 * - التعامل مع المخزون
	 * - تسجيل logs
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 🔴 3. Invalid Mode
	 * -------------------------------------------------------------------------
	 * الشرط:
	 * - type لا يساوي 'view' أو 'confirm'
	 *
	 * السلوك الحالي:
	 * - تسجيل error فقط
	 *
	 * ⚠️ المشكلة:
	 * - لا يوجد return واضح
	 *
	 * =========================================================================
	 * 🧱 Dependencies:
	 * =========================================================================
	 *
	 * - query()
	 *   → تجهيز اتصال قاعدة البيانات
	 *
	 * - handleViewMode()
	 *   → منطق عرض الشحنة
	 *
	 * - confirmShipmentDelivery()
	 *   → منطق تنفيذ التسليم
	 *
	 * - logManager
	 *   → تسجيل الأخطاء والـ logs
	 *
	 * =========================================================================
	 * 🛑 Error Handling:
	 * =========================================================================
	 *
	 * 1️⃣ PDOException
	 * -------------------------------------------------------------------------
	 * - أخطاء قاعدة البيانات
	 * - يتم:
	 *   • تسجيل الخطأ
	 *   • تحويل الرسالة
	 *   • إرجاع response error
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 2️⃣ ErrorException
	 * -------------------------------------------------------------------------
	 * - أخطاء منطقية داخل التطبيق
	 * - يتم:
	 *   • تسجيل (file + line)
	 *   • إرجاع رسالة واضحة
	 *
	 * -------------------------------------------------------------------------
	 *
	 * 3️⃣ Exception (عام)
	 * -------------------------------------------------------------------------
	 * - أي خطأ غير متوقع
	 * - يتم:
	 *   • تسجيله
	 *   • إرجاع رسالة
	 *
	 * =========================================================================
	 * 🔚 finally:
	 * =========================================================================
	 *
	 * - logManager->finalize()
	 *   → إنهاء عملية التسجيل
	 *   → ممكن تشمل:
	 *     • flush logs
	 *     • إغلاق handlers
	 *
	 * =========================================================================
	 * 📥 @param string $type
	 * -------------------------------------------------------------------------
	 * نوع العملية:
	 * - 'view'    → عرض بيانات الشحنة
	 * - 'confirm' → تأكيد التسليم
	 *
	 * =========================================================================
	 * 📤 @return array
	 * -------------------------------------------------------------------------
	 * [
	 *   0 => string, // success | error
	 *   1 => string, // title
	 *   2 => string  // message
	 * ]
	 *
	 * =========================================================================
	 * 📌 أمثلة الاستخدام:
	 * =========================================================================
	 *
	 * // عرض شحنة
	 * $result = $this->ShipmentDelivery('view');
	 *
	 * // تأكيد التسليم
	 * $result = $this->ShipmentDelivery('confirm');
	 *
	 * =========================================================================
	 * ⚠️ مشاكل محتملة (Critical Issues):
	 * =========================================================================
	 *
	 * 1. لا يوجد return في حالة type غير صحيح
	 *    → قد يسبب undefined behavior
	 *
	 * 2. $sql يتم إنشاؤه دائمًا
	 *    → لكنه لا يستخدم في confirm mode
	 *
	 * 3. الاعتماد على string بدل constants
	 *
	 * 4. عدم التحقق من count(orderobj) في confirm
	 *
	 * =========================================================================
	 * 💡 تحسينات مقترحة (Recommended Improvements):
	 * =========================================================================
	 *
	 * ✔ إضافة return واضح في invalid type:
	 *   return ['error','خطأ','نوع العملية غير صحيح'];
	 *
	 * ✔ استخدام constants:
	 *   const MODE_VIEW = 'view';
	 *   const MODE_CONFIRM = 'confirm';
	 *
	 * ✔ Lazy loading للـ query():
	 *   يتم فقط عند الحاجة (view mode)
	 *
	 * ✔ توحيد شكل response دائمًا
	 *
	 * ✔ إضافة validation قبل confirm:
	 *   - التأكد من وجود orders
	 *
	 * =========================================================================
	 * @throws PDOException
	 * @throws ErrorException
	 * @throws Exception
	 * =========================================================================
	 */
	public function CustomerReturnOfProducts(array $orderData, string $type = 'view'): array {
		try {
			$orderId = array_key_first($orderData);
			
			// حالة العرض: عندما يكون هناك طلب واحد فقط
			if ($type === 'view') {
				return $this->handleViewMode($orderId);
			}
			// حالة التأكيد
			if ($type === 'confirm') {
			    $results = $this->returnedOrderSales();
				return $results;
			}
			$this->logManager->error("حدث خطاء اثناء تنفيذ المعامله ShipmentDelivery >> error type");
			// إذا لم يتطابق مع أي حالة
			//throw new ErrorException(" الطلب غير صحيح ",1001,  كود الخطأ E_USER_ERROR,  مستوى الخطأ __FILE__,  اسم الملف الحالي __LINE__  رقم السطر الحالي);
		} catch (PDOException $e) { // معالجة الأخطاء
			$this->logManager->exception("SQL ERROR : ".$this->mapPDOExceptionMessage($e));
			return ['error','خطأ',$this->mapPDOExceptionMessage($e)];
		} catch (ErrorException $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} catch (Exception $e) {
			$this->logManager->exception("url file : " .$e->getFile() ."\n on-line : ".$e->getLine(). "\n msg : فشلت العمليه فى المعامله ShipmentDelivery");
			return ['error','خطأ','فشلت العملية: ' . $e->getMessage()];
		} finally {
			$this->logManager->finalize();
		}
	}
	
	/**
	 * 🔒 دالة جديدة: Lock عام للمخزون (بديل)
	 */
	private function acquireGlobalInventoryLock(): bool {
	    try {
	        $stmt = $this->conn->prepare("SELECT GET_LOCK('global_inventory_lock', 2) as locked");
	        $stmt->execute();
	        $result = $stmt->fetch(PDO::FETCH_ASSOC);
	        
	        if ($result && $result['locked'] == 1) {
	            return true;
	        }
	        
	        // قفل المخزون مشغول - نرمي استثناء
	        throw new Exception(
	            "⏳ **المخزون قيد الاستخدام**\n\n" .
	            "هناك عملية تحديث أخرى جارية حالياً على المخزون.\n" .
	            "يرجى الانتظار بضع ثوانٍ ثم المحاولة مرة أخرى.\n\n" .
	            "💡 **نصيحة**: حاول إعادة المحاولة بعد 5-10 ثوانٍ."
	        );
	        
	    } catch (PDOException $e) {
	        // خطأ في قاعدة البيانات
	        throw new Exception("خطأ في الاتصال بقاعدة البيانات");
	    }
	}
	
	/**
	 * 🔓 دالة جديدة: تحرير Lock المخزون العام
	 */
	private function releaseGlobalInventoryLock(): void {
	    try {
	        $stmt = $this->conn->prepare("SELECT RELEASE_LOCK('global_inventory_lock') as released");
	        $stmt->execute();
	        $this->logManager->info("🔓 تم تحرير تأقف المخزون العام");
	    } catch (Exception $e) {
	        $this->logManager->error("❌ فشل تحرير تأقف المخزون العام: " . $e->getMessage());
	    }
	}
	
}

?>