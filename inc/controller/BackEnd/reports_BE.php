<?php
/*
CLIENT  Credit خارج (له)
CLIENT  Debit  داخل (عليه)

SUPPLIER Credit خارج (له)
SUPPLIER Debit  داخل (عليه)

MARKETER Credit خارج (له)
MARKETER Debit  داخل (عليه)

DELIVERY Credit خارج (له) 
DELIVERY Debit  داخل (عليه)

DELIVERY Credit  خارج (له)
DELIVERY Debit  داخل (عليه)

*/
include_once(__dir__.'/../../configDateTimeFN.php');
class reports_BE {

	private $conn;
	private $dtz;
	
	public function __construct($db) {
		$this->conn = $db;
		$this->dtz = new configDateTime();
	}

	public function stockShortageReport(){

		// استقبال المعلمات من DataTables
		$start  = $_POST['start'];
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		$where = "";
		// الاستعلام الرئيسي
		$sql = "
			SELECT 
			    B.BR_NAME,
			    concat(S.STO_NAME,' (',ST.STTY_NAME,')') AS STO_NAME,
			    PC.PRO_CODE,
			    PR.PRO_NAME,
			    IF(E.OPTION_NAME IS NULL,'-',
			    GROUP_CONCAT(
			        CONCAT(E.OPTION_NAME, ' : ', COALESCE(D.VALUE, '')) 
			        ORDER BY E.OPTION_ID 
			        SEPARATOR ' | '
			    )) AS POPTION,
			PC.CODE_MIN_STOCK,
			SP.STO_QTY
			FROM 
			    PRODUCTS PR
			    LEFT JOIN PRODUCT_CODES PC ON PC.PRODUCT_ID = PR.PRO_ID
			    LEFT JOIN PRODUCT_CODE_JOIN_OPTION C ON PC.PRO_CODE = C.PRODUCT_CODE
			    LEFT JOIN PRODUCT_OPTION D ON D.ID = C.OPTION_VALUE_ID
			    LEFT JOIN PRODUCT_OPTION_GROUP E ON E.OPTION_ID = D.OPTION_ID
			    LEFT JOIN stores_product SP ON PC.PRO_CODE = SP.PRO_CODE
			    LEFT JOIN stores S ON S.STO_ID= SP.STO_ID
			    LEFT JOIN stores_type ST ON ST.STTY_ID = S.STTY_ID
			    LEFT JOIN branches B ON S.BRANCH_ID=B.BR_ID
			WHERE PC.CODE_MIN_STOCK > SP.STO_QTY ";
		$group = " GROUP BY 
			    SP.STO_ID,
			    PC.PRO_CODE,
			    PR.PRO_NAME ";
		$limit = " LIMIT :start, :length";
		
		// إضافة البحث
		if (!empty($search)) {
		    $where .= " AND (B.BR_NAME LIKE :search OR S.STO_NAME LIKE :search1 OR PR.PRO_NAME LIKE :search2)";
		}
		
		$stmtAll = $this->conn->prepare($sql.$group);
		$stmtAll->execute();
		
		$stmt = $this->conn->prepare($sql.$where.$group.$limit);
	
		// تمرير القيم إلى الاستعلام
		if (!empty($search)) {
		    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
		    $stmt->bindValue(':search1', "%$search%", PDO::PARAM_STR);
		    $stmt->bindValue(':search2', "%$search%", PDO::PARAM_STR);

		}
		$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
		$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmtAll->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
			
	}

	public function SalesMovementReport(){

		$start  = max(0, (int)($_POST['start'] ?? 0));
		$length = max(1, (int)($_POST['length'] ?? 40000));
		$search = trim((string)($_POST['search']['value'] ?? ''));

		// الفترة الافتراضية: آخر 30 يومًا شاملة اليوم.
		$today = new DateTimeImmutable('today');
		$defaultFrom = $today->modify('-29 days')->format('Y-m-d');
		$defaultTo = $today->format('Y-m-d');

		$isValidDate = static function ($value) {
			if (!is_string($value) || $value === '') {
				return false;
			}
			$date = DateTime::createFromFormat('!Y-m-d', $value);
			return $date instanceof DateTime && $date->format('Y-m-d') === $value;
		};

		$dateFrom = $_POST['start_date'] ?? '';
		$dateTo = $_POST['end_date'] ?? '';
		if (!$isValidDate($dateFrom) || !$isValidDate($dateTo) || $dateFrom > $dateTo) {
			$dateFrom = $defaultFrom;
			$dateTo = $defaultTo;
		}

		$sql = 'SELECT *
				FROM  (SELECT  A.ORDER_TYPE_ID,
				   A.SUB_ORDER_ID,
				   A.ADDRESS,
				   A.COMMENT,
				   A.TELEPHONE,
				   A.SHIPPING_VAL,
				   A.ORDER_SUCCESS,
				   C.STA_NAME,
				   B.EXISTS_NAME,
				   D.ORDER_TYPE_NAME,
				   Concat(E.FORENAME, " ", E.LASTNAME) AS ORDERBY,
				   Concat(F.FORENAME, " ", F.LASTNAME) AS DELIVERYBY,
				   H.CLIENT_NAME,
				   H.CLIENT_EMAIL,
				   K.CI_NAME,
				   R.GOV_NAME,
				   
				   M.PRO_CODE,
				   A.DATE_CREATED,
				   A.DELIVERY_DATE,
				   A.SHIPPING_DATE,
				   A.DELIVERED_DATE,
				   POV.PRO_NAME,
				   POV.OPT_JSON,
				   PQTY.QTY AS TOTAL_ORDER_QTY,
				   PQTY.TOTAL_PRICE,
				   ((M.PRICE - M.DISCOUNT) * M.QTY) AS TOTAL,
				   M.QTY,
				   M.PRICE,
				   M.DISCOUNT,
				   M.DELIVERED_QTY,
				   CASE WHEN M.PRO_STATUSE <> "waiting" AND C.STA_ID > 2 THEN (M.QTY - M.DELIVERED_QTY) ELSE 0 END AS RETURN_QTY,
				   M.PRO_STATUSE
				 FROM  orders A
				   LEFT JOIN ORDER_EXISTS B ON A.EXISTS_ID = B.EXISTS_ID
				   LEFT JOIN ORDER_STATUSE C ON A.ORDER_STATUS = C.STA_ID
				   LEFT JOIN ORDER_TYPE D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
				   LEFT JOIN USERS E ON A.ORDER_BY = E.USER_ID
				   LEFT JOIN USERS F ON A.DELIVERY_ID = F.USER_ID
				   LEFT JOIN ADDRESSES G ON A.ADDRESS_ID = G.ADDRESS_ID
				   LEFT JOIN CLIENT H ON G.CLIENT_ID = H.CLIENT_ID
				   LEFT JOIN TELEPHONES J ON A.TEL_ID = J.TEL_ID
				   LEFT JOIN CITY K ON G.CITY_ID = K.CI_ID
				   LEFT JOIN GOV R ON K.GOV_ID = R.GOV_ID
				   LEFT JOIN ZONES L ON K.ZONE_ID = L.ZONE_ID
				   LEFT JOIN ORDER_PRODUCT M ON A.ORDER_ID = M.ORDER_ID
				   LEFT JOIN product_options_view POV ON M.PRO_CODE = POV.PRO_CODE
				   LEFT JOIN PRODUCT_CODES N ON M.PRO_CODE = N.PRO_CODE
				   LEFT JOIN PRODUCTS O ON N.PRODUCT_ID = O.PRO_ID
				   LEFT JOIN invoices P ON A.ORDER_ID = P.REFERENCE_ID AND REFERENCE_TYPE = "SALES"
				   LEFT JOIN ORDERS AS EXO ON A.EXCHANGE = EXO.ORDER_ID
				   LEFT JOIN (
				   				SELECT ORDER_ID,
				   				SUM(QTY) AS QTY ,
				   				SUM(QTY * (PRICE-DISCOUNT)) AS TOTAL_PRICE
				   				FROM ORDER_PRODUCT
				   				GROUP BY ORDER_ID
				   ) PQTY ON A.ORDER_ID = PQTY.ORDER_ID
				 GROUP BY 
				 A.ORDER_TYPE_ID,
				   A.SUB_ORDER_ID,
				   A.ADDRESS,
				   A.COMMENT,
				   A.TELEPHONE,
				   A.DATE_CREATED,
				   A.DELIVERY_DATE,
				   A.SHIPPING_VAL,
				   A.ORDER_SUCCESS,
				   C.STA_NAME,
				   D.ORDER_TYPE_NAME,
				   Concat(E.FORENAME, " ", E.LASTNAME),
				   Concat(F.FORENAME, " ", F.LASTNAME),
				   H.CLIENT_NAME,
				   H.CLIENT_EMAIL,
				   K.CI_NAME,
				   R.GOV_NAME,
				   M.PRO_CODE,
				   POV.PRO_NAME,
				   POV.OPT_JSON,
				   M.QTY,
				   M.PRICE,
				   M.DISCOUNT,
				   M.DELIVERED_QTY,
				   M.PRO_STATUSE) AS ORDER_VIEW ';

		// فلترة تاريخ الإنشاء على الخادم قبل إرجاع النتائج.
		$where = " WHERE ORDER_VIEW.DATE_CREATED >= :date_from
		           AND ORDER_VIEW.DATE_CREATED < DATE_ADD(:date_to, INTERVAL 1 DAY)";

		$searchColumns = [
			'SUB_ORDER_ID', 'ORDERBY', 'DELIVERYBY', 'CLIENT_NAME',
			'TELEPHONE', 'GOV_NAME', 'ADDRESS', 'PRO_NAME', 'PRO_CODE',
			'EXISTS_NAME', 'STA_NAME', 'ORDER_TYPE_NAME', 'CI_NAME',
			'COMMENT', 'OPT_JSON'
		];

		$searchConditions = [];
		foreach ($searchColumns as $index => $columnName) {
			$searchConditions[] = "COALESCE(ORDER_VIEW.$columnName, '') LIKE :search_$index";
		}

		$filteredWhere = $where;
		if ($search !== '') {
			$filteredWhere .= " AND (" . implode(" OR ", $searchConditions) . ")";
		}

		// إجمالي السجلات داخل الفترة، ثم العدد بعد تطبيق البحث النصي.
		$countTotal = $this->conn->prepare("SELECT COUNT(*) FROM ($sql) AS REPORT_COUNT $where");
		$countTotal->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
		$countTotal->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
		$countTotal->execute();
		$recordsTotal = (int)$countTotal->fetchColumn();

		$countFiltered = $this->conn->prepare("SELECT COUNT(*) FROM ($sql) AS REPORT_COUNT $filteredWhere");
		$countFiltered->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
		$countFiltered->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
		if ($search !== '') {
			foreach ($searchColumns as $index => $columnName) {
				$countFiltered->bindValue(':search_'.$index, "%".$search."%", PDO::PARAM_STR);
			}
		}
		$countFiltered->execute();
		$recordsFiltered = (int)$countFiltered->fetchColumn();

		$stmt = $this->conn->prepare($sql.$filteredWhere." LIMIT :start, :length");
		$stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
		$stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
		if ($search !== '') {
			foreach ($searchColumns as $index => $columnName) {
				$stmt->bindValue(':search_'.$index, "%".$search."%", PDO::PARAM_STR);
			}
		}
		$stmt->bindValue(':start', $start, PDO::PARAM_INT);
		$stmt->bindValue(':length', $length, PDO::PARAM_INT);
		$stmt->execute();

		return [
			'draw' => (int)($_POST['draw'] ?? 0),
			'recordsTotal' => $recordsTotal,
			'recordsFiltered' => $recordsFiltered,
			'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
		];
	}

	public function suppliersQtyReportIN(){
		// استقبال المعلمات من DataTables
		//$start  = $_POST['start'];
		//$length = $_POST['length'];
		//$search = $_POST['search']['value'];
		$where = "";
		$limit = "";
		// الاستعلام الرئيسي
		$sql = 'SELECT PO.ORDER_ID, 
		            PO.CREATE_AT, 
		            S.SUP_NAME, 
					POV.PRO_NAME,
					POV.OPT_JSON, 
					sum(QTY) AS QTY, 
					sum(PURCHASE_UNIT_PRICE *QTY) AS QTY_PRICE, SUM(QTY_SUPPLIED) AS QTY_SUPPLIED, sum(PURCHASE_UNIT_PRICE * QTY_SUPPLIED) AS SUPPLIED_PRICE 
				FROM  purchases_orders PO
				 LEFT JOIN purchases_products PP ON PO.ORDER_ID = PP.ORDER_ID
				 LEFT JOIN suppliers S ON PO.SUPPLIER_ID = S.SUP_ID
				 LEFT JOIN product_options_view POV ON PP.PRO_CODE = POV.PRO_CODE ';
		$GroupBy = ' GROUP BY SUPPLIER_ID,PP.PRO_CODE '; 
		if(!empty($start) && !empty($length) && $length >0){
			$limit = " LIMIT :start, :length";
		}
		// إضافة البحث
		if (!empty($search)) {
		    $where .= " WHERE (S.SUP_NAME LIKE '%".$search."%' OR POV.PRO_NAME LIKE '%".$search."%')";
		}
		
		$stmtAll = $this->conn->prepare($sql);
		$stmtAll->execute();
		
		$stmt = $this->conn->prepare($sql.$where.$GroupBy.$limit);
	
		//if(!empty($start) && !empty($length) && $length >0){
		//	$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
		//	$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
		//}
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		/*$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmtAll->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);*/
		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		
	}

	public function AccountStatement(){
		// استقبال المعلمات من DataTables
		$start  = $_POST['start'].' 00:00:00';
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		$partnerType = $_POST['partner_type'];
		$partnerId = $_POST['partner_id'] == '' ? 0 : $_POST['partner_id'];
		$dateRange = $_POST['daterange'];
		// تقسيم التاريخ بناءً على " إلى "
		$dates = explode(' - ', $dateRange);
		$start = isset($dates[0]) ? trim($dates[0]) : null;
		$end = isset($dates[1]) ? trim($dates[1].' 23:59:59') : null;

		$where = "";
		$limit = "";
		$sql = "SELECT 
				  'رصيد سابق' AS note,
				  NULL AS id,
				  NULL AS ORDER_ID,
				  NULL AS QTY_ORDER,
				  NULL AS QTY_SUPPLIED,
				  NULL AS QTY_RETURN,
				  NULL AS transaction_date,
				  NULL AS TRANSACTION_TYPE,
				  NULL AS BALANCE,
				  NULL AS debit,
				  NULL AS credit,
				  COALESCE(SUM(
				  	(CASE 
				      WHEN TRANSACTION_TYPE = 'DEBIT' THEN -BALANCE
				      ELSE 0
				    END) +
				    (CASE 
				      WHEN TRANSACTION_TYPE = 'CREDIT' THEN BALANCE
				      ELSE 0
				    END)
				  ),'0.00')  AS running_balance
				FROM partners_balance
				
				WHERE 
				  CREATE_AT < :start
				  AND PARTNER_ID = :partnerId
				  AND PARTNER_TYPE = :partnerType
				
				UNION ALL
				
				SELECT 
				  NOTE,
				  id,
				  ORDER_ID,
				  QTY_ORDER,
				  QTY_SUPPLIED,
				  QTY_RETURN,
				  DATE(transaction_date) AS transaction_date,
				  TRANSACTION_TYPE,
				  BALANCE,
				  CASE WHEN TRANSACTION_TYPE = 'DEBIT' THEN BALANCE ELSE NULL END AS debit,
				  CASE WHEN TRANSACTION_TYPE = 'CREDIT' THEN BALANCE ELSE NULL END AS credit,
				  running_balance
				FROM (
				  SELECT 
				    pb.id,
				    pb.PARTNER_TYPE,
				    CASE WHEN pb.PARTNER_TYPE = 'SUPPLIER' THEN REFERENCE_ID ELSE o.SUB_ORDER_ID END AS ORDER_ID,
				    
				    CASE WHEN pb.PARTNER_TYPE = 'SUPPLIER' AND PB.TRANSACTION_CATEGORY_ID = 10 THEN PP.QTY
				    	 WHEN pb.PARTNER_TYPE = 'SUPPLIER' AND PB.TRANSACTION_CATEGORY_ID = 15 THEN ''
				    	 ELSE oq.qty 
				    END AS QTY_ORDER , 
				    
				    CASE WHEN pb.PARTNER_TYPE = 'SUPPLIER' AND PB.TRANSACTION_CATEGORY_ID = 10 THEN pp.QTY_SUPPLIED
				    	 WHEN pb.PARTNER_TYPE = 'SUPPLIER' AND PB.TRANSACTION_CATEGORY_ID = 15 THEN PRP.QTY_SUPPLIED 
				    	 WHEN pb.PARTNER_TYPE = 'MARKETER' AND PB.TRANSACTION_CATEGORY_ID = 12 THEN oq.dqty  
				    	 ELSE oq.dqty 
				    END AS QTY_SUPPLIED,
				    CASE WHEN pb.PARTNER_TYPE = 'SUPPLIER' AND PB.TRANSACTION_CATEGORY_ID = 15 THEN PRP.QTY_RETURN
				    	 WHEN pb.PARTNER_TYPE = 'MARKETER' AND PB.TRANSACTION_CATEGORY_ID = 16 THEN rqty
				    	 
				    	 ELSE '' END AS QTY_RETURN,
				    pb.CREATE_AT AS transaction_date,
				    pb.TRANSACTION_TYPE,
				    pb.BALANCE,
				    pb.NOTE,
				    CASE WHEN pb.TRANSACTION_TYPE = 'DEBIT' THEN pb.BALANCE ELSE '' END AS debit,
				    CASE WHEN pb.TRANSACTION_TYPE = 'CREDIT' THEN pb.BALANCE ELSE '' END AS credit,
				    SUM(
				     CASE 
				     	WHEN pb.TRANSACTION_TYPE = 'DEBIT' THEN -pb.BALANCE 
				     	ELSE pb.BALANCE END
				     ) 
				     OVER (ORDER BY pb.CREATE_AT, pb.ID ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW ) 
				     + ( SELECT Coalesce(SUM( 
				     	CASE 
				     		WHEN TRANSACTION_TYPE = 'DEBIT' THEN -BALANCE 
				     		ELSE BALANCE END
				     	),0.00)
				      FROM partners_balance
				      WHERE CREATE_AT < :start
				        AND PARTNER_ID = :partnerId
				        AND PARTNER_TYPE = :partnerType
				    ) AS running_balance
				  FROM partners_balance pb 
				  LEFT JOIN orders o ON o.ORDER_ID = pb.REFERENCE_ID AND pb.PARTNER_TYPE <> 'SUPPLIER'
				  
				  LEFT JOIN ( SELECT  ORDER_ID,
					  10  AS TRANSACTION_CATEGORY_ID,
					  Sum(QTY)  AS QTY,
					  Sum(QTY_SUPPLIED) AS QTY_SUPPLIED
					 FROM   purchases_products
					 GROUP BY ORDER_ID
				  ) AS PP ON PB.REFERENCE_ID = PP.ORDER_ID AND pb.PARTNER_TYPE = 'SUPPLIER' AND pb.TRANSACTION_CATEGORY_ID = pp.TRANSACTION_CATEGORY_ID
				  LEFT JOIN (SELECT  ORDER_ID,
					   15  AS TRANSACTION_CATEGORY_ID,
					   ''  AS QTY,
					   Sum(QTY_SUPPLIED) AS QTY_SUPPLIED,
					   Sum(QTY_RETURN)  AS QTY_RETURN
					 FROM   purchases_return_products
					 GROUP BY ORDER_ID 
				  ) AS PRP ON PB.REFERENCE_ID = PRP.ORDER_ID AND pb.PARTNER_TYPE = 'SUPPLIER' AND pb.TRANSACTION_CATEGORY_ID = PRP.TRANSACTION_CATEGORY_ID
				  LEFT JOIN (SELECT  ORDER_ID,
				    Sum(QTY)   qty,
				    Sum(DELIVERED_QTY) dqty,
				    Sum(RETURNED_QTY) rqty
				   FROM   order_product
				   GROUP BY ORDER_ID) oq ON oq.ORDER_ID = o.ORDER_ID
				  WHERE pb.CREATE_AT BETWEEN :start AND :end
				   AND pb.PARTNER_ID = :partnerId
				   AND pb.PARTNER_TYPE = :partnerType
				) AS details
				
				UNION ALL
				
				SELECT 
				  'رصيد حالي' AS note,
				  NULL AS id,
				  NULL AS ORDER_ID,
				  NULL AS QTY_ORDER,
				  NULL AS QTY_SUPPLIED,
				  NULL AS QTY_RETURN,
				  NULL AS transaction_date,
				  NULL AS TRANSACTION_TYPE,
				  NULL AS BALANCE,
				  COALESCE(SUM(
					  CASE 
					    WHEN TRANSACTION_TYPE = 'DEBIT' THEN BALANCE
					    ELSE 0
					  END
					), 0.00) AS debit,
					
					COALESCE(SUM(
					  CASE 
					    WHEN TRANSACTION_TYPE = 'CREDIT' THEN BALANCE
					    ELSE 0
					  END
					), 0.00) AS credit,
				  
				  Coalesce(SUM(
				  
				    (CASE 
				      WHEN TRANSACTION_TYPE = 'DEBIT' THEN -BALANCE
				      ELSE 0
				    END) +
				    (CASE 
				      WHEN TRANSACTION_TYPE = 'CREDIT' THEN BALANCE
				      ELSE 0
				    END)
				  ),'0.00') AS running_balance
				FROM partners_balance
				
				WHERE CREATE_AT <= :end
				  AND PARTNER_ID = :partnerId
				  AND PARTNER_TYPE = :partnerType ;"; 
				  
	/*	if(!empty($start) && !empty($length) && $length >0){
			$limit = " LIMIT :start, :length";
		}
		// إضافة البحث
		if (!empty($search)) {
		   $where .= " WHERE (S.SUP_NAME LIKE '%".$search."%' OR POV.PRO_NAME LIKE '%".$search."%')";
		}*/
		
	/*	$stmtAll = $this->conn->prepare($sql);
		$stmtAll->execute();*/
		
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(':partnerType', $partnerType, PDO::PARAM_STR);
		$stmt->bindValue(':partnerId', $partnerId, PDO::PARAM_INT);
		$stmt->bindValue(':start', $start, PDO::PARAM_STR);
		$stmt->bindValue(':end', $end, PDO::PARAM_STR);
	/*	if(!empty($start) && !empty($length) && $length >0){
			$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
			$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
		}*/
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
		
	}

	public function getDelegatesReportTotal(){
		$rt = null;
		/*
		old statuse
		0 = Delivery خروج المندوب
		1 = freeze استرداد العهده دون استرداد المنتجات
		2 = Delivered الوصول للعميل
		3 = Return استرداد العهده بالكتمل
		4 = refund مرتجع بعد البيع 
			
		new statuse
		0 = 'استلام العهده'
        1 = 'استرداد امر البيع بدون المنتجات'
        2 = 'استرداد الامر بالكامل'
        3 = 'تم التسليم'
        4 = 'تسليم جزئي'
        5 = 'مرفوض'
        6 = 'تسويه مخزنيه'
        7 = 'تسويه ماليه'
        8 = 'مرتجع بعد البيع'
        9 = 'مرتجع جزئى بعد البيع'
        
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
		$ofset  = $_POST['start'];
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		
		$dateRange = isset($_POST['daterange']) ? $_POST['daterange'] == ''? '1990-01-01': $_POST['daterange']:'1990-01-01';
		// تقسيم التاريخ "
		$dates = explode(' - ', $dateRange);
		$start = !empty($dates[0]) ? trim($dates[0]) : null;
		$end = !empty($dates[1]) ? trim($dates[1]) : null;
		$deliveryId = isset($_POST['did'])  ? $_POST['did'] == '' ? 0 : $_POST['did'] :0;
		
		$where = "";
		$limit = "";
		$orderBy = "";
		$whquery =array();
		//موظف الشحن
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			$whquery[] = "delivery_id = :deliveryId";
		}
		//فلاتر التاريخ
		if(!empty($start) && !empty($end)){
			$whquery[] = "date BETWEEN :start AND :end";
		}
		// إضافة البحث
		if (!empty($search)) {
		    $whquery[] = "(O.SUB_ORDER_ID LIKE '%:search%' OR CONCAT(U.FORENAME,' ',U.LASTNAME) LIKE '%:search%')";
		}
		//where
		if ( count( $whquery ) ) {
			$where = ' WHERE '.implode(' AND ', $whquery);
		}
		//limit
		if(!empty($ofset) && !empty($length) && $length >0){
			$limit = " LIMIT ".$ofset.", ".$length." ";
		}
		//order by
		if (isset($_POST['order']) && isset($_POST['columns'])) {
		    $orderByArray = [];
		    foreach ($_POST['order'] as $orderItem) {
		        $columnIndex = $orderItem['column'];
		        $columnName = $_POST['columns'][$columnIndex]['data']; // اسم العمود حسب ما معرف في DataTables
		        $direction = strtoupper($orderItem['dir']) === 'DESC' ? 'DESC' : 'ASC';
		        
		        // تأكد من أن اسم العمود آمن للاستخدام (من قائمة أعمدة فقط)
		        $allowedColumns = ['DELIVERY_NAME', 'date', 'SUB_ORDER_ID','TOTAL_QTY','WAITING_QTY','DELIVERED_QTY','REFOUND_PARTIAL_QTY','REJECTED_QTY','REJECTED_PERSENT']; // عدلها حسب أعمدتك
		        if (in_array($columnName, $allowedColumns)) {
		            $orderByArray[] = "$columnName $direction";
		        }
		    }
		    if (!empty($orderByArray)) {
		        $orderBy = ' ORDER BY ' . implode(', ', $orderByArray);
		    }
		}
		
		$sqltemp = "
		WITH ordered_movements AS (
			SELECT *,
				ROW_NUMBER() OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS rn
			FROM shipping_movement
		),

		session_marker AS (
			SELECT *,
				SUM(CASE WHEN movement_type = 0 THEN 1 ELSE 0 END)
				OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS session_num
			FROM ordered_movements
		),

		sessions_with_start AS (
			SELECT *
			FROM session_marker
			WHERE movement_type = 0
		),

		filtered_sessions AS (
			SELECT sm.*
			FROM session_marker sm
			JOIN sessions_with_start ss
				ON sm.shipment_orders = ss.shipment_orders
				AND sm.session_num = ss.session_num
		),

		latest_per_type AS (
			SELECT *
			FROM (
				SELECT *,
					ROW_NUMBER() OVER (
						PARTITION BY shipment_orders, session_num, movement_type
						ORDER BY shipmov_id DESC, MOVING_DATE DESC
					) AS rn_per_type
				FROM filtered_sessions
			) ranked
			WHERE rn_per_type = 1
		),

		latest_orders AS (
			SELECT 
				A.delivery_id,
				CONCAT(U.FORENAME,' ',U.LASTNAME) AS DELIVERY_NAME,
				A.shipment_orders,
				SO.SUB_ORDER_ID,
				DATE(MIN(A.MOVING_DATE)) AS date,
				COALESCE(CP.QTY, OP.QTY) AS TOTAL_QTY,
				COALESCE(CP.QTY_WAITING, OP.QTY_WAITING) AS WAITING_QTY,
				COALESCE(CP.QTY_DELIVERED, OP.QTY_DELIVERED) AS DELIVERED_QTY,
				COALESCE(CP.QTY_REFOUND_PARTIAL, OP.QTY_REFOUND_PARTIAL) AS REFOUND_PARTIAL_QTY,
				COALESCE(CP.QTY_REJECTED, OP.QTY_REJECTED) AS REJECTED_QTY
			FROM latest_per_type A
			LEFT JOIN custody_orders CO ON CO.ORDER_ID = A.shipment_orders AND A.delivery_id = CO.USER_ID
			LEFT JOIN orders O ON O.ORDER_ID = A.shipment_orders  AND A.delivery_id = O.DELIVERY_ID
			LEFT JOIN USERS U ON U.USER_ID = A.delivery_id
			LEFT JOIN orders SO ON SO.ORDER_ID = A.shipment_orders
			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE IN ('REJECTED','PARTIAL') THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM custody_product
				GROUP BY ORDER_ID
			) CP ON CP.ORDER_ID = CO.ORDER_ID
			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE = 'REJECTED' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM order_product
				GROUP BY ORDER_ID
			) OP ON OP.ORDER_ID = O.ORDER_ID 
			GROUP BY 
				A.delivery_id,
				A.shipment_orders,
				A.session_num
		)
		";
		
		$sql = "
		SELECT 
			delivery_id,
			DELIVERY_NAME,
			COUNT(DISTINCT shipment_orders) AS TOTAL_ORDERS,
			SUM(TOTAL_QTY) AS TOTAL_QTY,
			SUM(WAITING_QTY) AS WAITING_QTY,
			SUM(DELIVERED_QTY) AS DELIVERED_QTY,
			SUM(REFOUND_PARTIAL_QTY) AS REFOUND_PARTIAL_QTY,
			SUM(REJECTED_QTY) AS REJECTED_QTY,
			IFNULL(
				CONCAT(
					ROUND(
						SUM(REJECTED_QTY) * 100 /
						NULLIF(SUM(TOTAL_QTY) - (SUM(WAITING_QTY) + SUM(REFOUND_PARTIAL_QTY)), 0),
					0),
				'%'),
			'') AS REJECTED_PERSENT

		FROM latest_Orders 
		". $where ." GROUP BY delivery_id ";
		
//echo $sqltemp.$sql.$orderBy.$limit;
		$stmt = $this->conn->prepare($sqltemp.$sql.$orderBy.$limit);
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){

			$stmt->bindValue(':deliveryId', $deliveryId, PDO::PARAM_INT);
		}
		
		if(!empty($start) && !empty($end)){

			$stmt->bindValue(':start', $start, PDO::PARAM_STR);
			$stmt->bindValue(':end', $end, PDO::PARAM_STR); 
		}
		if (!empty($search)) {
			//$stmt->bindValue(':search', $search, PDO::PARAM_STR);
		}
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
		
		

	}

	public function getDelegatesDailyReport(){
		/*
		old statuse
		0 = Delivery خروج المندوب
		1 = freeze استرداد العهده دون استرداد المنتجات
		2 = Delivered الوصول للعميل
		3 = Return استرداد العهده بالكتمل
		4 = refund مرتجع بعد البيع 
			
		new statuse
		0 = 'استلام العهده'
        1 = 'استرداد امر البيع بدون المنتجات'
        2 = 'استرداد الامر بالكامل'
        3 = 'تم التسليم'
        4 = 'تسليم جزئي'
        5 = 'مرفوض'
        6 = 'تسويه مخزنيه'
        7 = 'تسويه ماليه'
        8 = 'مرتجع بعد البيع'
        9 = 'مرتجع جزئى بعد البيع'
        
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
		$ofset  = $_POST['start'];
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		$deliveryId = isset($_POST['did'])  ? $_POST['did'] == '' ? 0 : $_POST['did'] :0;
		$dateRange = isset($_POST['daterange']) ? $_POST['daterange'] == ''? '1990-01-01': $_POST['daterange']:'1990-01-01';
		// تقسيم التاريخ "
		$dates = explode(' - ', $dateRange);
		$start = isset($dates[0]) ? trim($dates[0]) : null;
		$end = isset($dates[1]) ? trim($dates[1]) : null;

		$where = "";
		$limit = "";
		$orderBy = "";
		$whquery =array();
		//موظف الشحن
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			$whquery[] = "delivery_id = :deliveryId";
		}
		//فلاتر التاريخ
		if(!empty($start) && !empty($end)){
			$whquery[] = "date BETWEEN :start AND :end";
		}
		// إضافة البحث
		if (!empty($search)) {
		    $whquery[] = "(O.SUB_ORDER_ID LIKE '%:search%' OR CONCAT(U.FORENAME,' ',U.LASTNAME) LIKE '%:search%')";
		}
		//where
		if ( count( $whquery ) ) {
			$where = ' WHERE '.implode(' AND ', $whquery);
		}
		//limit
		if(!empty($ofset) && !empty($length) && $length >0){
			$limit = " LIMIT ".$ofset.", ".$length." ";
		}
		//order by
		if (isset($_POST['order']) && isset($_POST['columns'])) {
		    $orderByArray = [];
		    foreach ($_POST['order'] as $orderItem) {
		        $columnIndex = $orderItem['column'];
		        $columnName = $_POST['columns'][$columnIndex]['data']; // اسم العمود حسب ما معرف في DataTables
		        $direction = strtoupper($orderItem['dir']) === 'DESC' ? 'DESC' : 'ASC';
		        
		        // تأكد من أن اسم العمود آمن للاستخدام (من قائمة أعمدة فقط)
		        $allowedColumns = ['DELIVERY_NAME', 'date', 'SUB_ORDER_ID','TOTAL_QTY','WAITING_QTY','DELIVERED_QTY','REFOUND_PARTIAL_QTY','REJECTED_QTY','REJECTED_PERSENT']; // عدلها حسب أعمدتك
		        if (in_array($columnName, $allowedColumns)) {
		            $orderByArray[] = "$columnName $direction";
		        }
		    }
		    if (!empty($orderByArray)) {
		        $orderBy = ' ORDER BY ' . implode(', ', $orderByArray);
		    }
		}
		
		/*$movement_history = $rt == 2 ? ",JSON_ARRAYAGG(
				    CONCAT(
				      CASE A.movement_type
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
				      END,
				      ' | ',
				      DATE(A.MOVING_DATE),
				      ' | ',
				      TIME(A.MOVING_DATE)
				    )
				    ORDER BY A.MOVING_DATE
				  ) AS movement_history" : '' ;
		$dateColum = 'date,';
		$sqltemp ="WITH ordered_movements AS (
				  SELECT 
				    *,
				    ROW_NUMBER() OVER (PARTITION BY shipment_orders ORDER BY MOVING_DATE, shipmov_id) AS rn
				  FROM shipping_movement
				),
				session_marker AS (
				  SELECT *,
				    SUM(CASE WHEN movement_type = 0 THEN 1 ELSE 0 END)
				      OVER (PARTITION BY shipment_orders ORDER BY MOVING_DATE, shipmov_id) AS session_num
				  FROM ordered_movements
				),
				sessions_with_start AS (
				  SELECT *
				  FROM session_marker
				  WHERE movement_type = 0
				),
				filtered_sessions AS (
				  SELECT sm.*
				  FROM session_marker sm
				  JOIN sessions_with_start ss
				    ON sm.shipment_orders = ss.shipment_orders
				    AND sm.session_num = ss.session_num
				),
				latest_per_type AS (
				  SELECT *
				  FROM (
				    SELECT *,
				      ROW_NUMBER() OVER (
				        PARTITION BY shipment_orders, session_num, movement_type
				        ORDER BY shipmov_id DESC, MOVING_DATE DESC
				      ) AS rn_per_type
				    FROM filtered_sessions
				  ) AS ranked
				  WHERE rn_per_type = 1
				),
				latest_Orders AS (
				SELECT 
				  A.delivery_id,
				  CONCAT(U.FORENAME,' ',U.LASTNAME) DELIVERY_NAME,
				  A.shipment_orders,
				  SO.SUB_ORDER_ID,
				  DATE(MOVING_DATE) As date,
				  COALESCE(CP.QTY, OP.QTY) AS TOTAL_QTY,
				  COALESCE(CP.QTY_WAITING, OP.QTY_WAITING) AS WAITING_QTY,
				  COALESCE(CP.QTY_DELIVERED, OP.QTY_DELIVERED) AS DELIVERED_QTY,
				  COALESCE(CP.QTY_REFOUND_PARTIAL, OP.QTY_REFOUND_PARTIAL) AS REFOUND_PARTIAL_QTY,
				  COALESCE(CP.QTY_REJECTED, OP.QTY_REJECTED) AS REJECTED_QTY
				  ".$movement_history." FROM latest_per_type A
				LEFT JOIN custody_orders CO ON CO.ORDER_ID = A.shipment_orders AND A.delivery_id = CO.USER_ID
				LEFT JOIN orders O ON O.ORDER_ID  = A.shipment_orders AND A.delivery_id = O.DELIVERY_ID
				LEFT JOIN USERS U ON U.USER_ID = A.delivery_id
				LEFT JOIN orders SO ON SO.ORDER_ID = A.shipment_orders
				LEFT JOIN (
				 SELECT 
				  ORDER_ID ,
				  SUM(QTY)AS QTY ,
				  SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
				  SUM(DELIVERED_QTY)AS QTY_DELIVERED, 
				  SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
				  SUM(CASE WHEN PRO_STATUSE IN('REJECTED','PARTIAL') THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				 FROM custody_product GROUP BY ORDER_ID
				) CP ON CP.ORDER_ID = CO.ORDER_ID
				LEFT JOIN (SELECT 
				  ORDER_ID ,
				  SUM(QTY)AS QTY ,
				  SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
				  SUM(DELIVERED_QTY)AS QTY_DELIVERED, 
				  SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
				  SUM(CASE WHEN PRO_STATUSE = 'REJECTED' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				 FROM order_product GROUP BY ORDER_ID) OP ON OP.ORDER_ID = O.ORDER_ID 
				GROUP BY  A.delivery_id, A.shipment_orders, A.session_num ) ";

		$sql = "SELECT 
				delivery_id,
				".$dateColum."
				DELIVERY_NAME,
				COUNT(DISTINCT shipment_orders) AS TOTAL_ORDERS,
				sum(TOTAL_QTY) AS TOTAL_QTY,
				SUM(WAITING_QTY) AS WAITING_QTY,
				SUM(DELIVERED_QTY) AS DELIVERED_QTY,
				SUM(REFOUND_PARTIAL_QTY) AS REFOUND_PARTIAL_QTY,
				SUM(REJECTED_QTY) AS REJECTED_QTY,
				IFNULL(CONCAT(ROUND(SUM(REJECTED_QTY) * 100 / (sum(TOTAL_QTY) - (SUM(WAITING_QTY) + SUM(REFOUND_PARTIAL_QTY))),0),'%'),'') AS REJECTED_PERSENT
				FROM latest_Orders
				".$where;*/
		
		$movement_history = ($rt == 2) ? "
		,JSON_ARRAYAGG(
			CONCAT(
				CASE A.movement_type
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
				END,
				' | ',
				DATE(A.MOVING_DATE),
				' | ',
				TIME(A.MOVING_DATE)
			)
			ORDER BY A.MOVING_DATE
		) AS movement_history
		" : "";


		$dateColum = ($rt == 1 || $rt == 2) ? "DATE(MIN(MOVING_DATE)) AS date," : "";


		$sqltemp = "
		WITH ordered_movements AS (
			SELECT *,
				ROW_NUMBER() OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS rn
			FROM shipping_movement
		),

		session_marker AS (
			SELECT *,
				SUM(CASE WHEN movement_type = 0 THEN 1 ELSE 0 END)
				OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS session_num
			FROM ordered_movements
		),

		sessions_with_start AS (
			SELECT *
			FROM session_marker
			WHERE movement_type = 0
		),

		filtered_sessions AS (
			SELECT sm.*
			FROM session_marker sm
			JOIN sessions_with_start ss
				ON sm.shipment_orders = ss.shipment_orders
				AND sm.session_num = ss.session_num
		),

		latest_per_type AS (
			SELECT *
			FROM (
				SELECT *,
					ROW_NUMBER() OVER (
						PARTITION BY shipment_orders, session_num, movement_type
						ORDER BY shipmov_id DESC, MOVING_DATE DESC
					) AS rn_per_type
				FROM filtered_sessions
			) ranked
			WHERE rn_per_type = 1
		),

		latest_orders AS (
			SELECT 
				A.delivery_id,
				CONCAT(U.FORENAME,' ',U.LASTNAME) AS DELIVERY_NAME,
				A.shipment_orders,
				SO.SUB_ORDER_ID,

				DATE(MIN(A.MOVING_DATE)) AS date,

				COALESCE(CP.QTY, OP.QTY) AS TOTAL_QTY,
				COALESCE(CP.QTY_WAITING, OP.QTY_WAITING) AS WAITING_QTY,
				COALESCE(CP.QTY_DELIVERED, OP.QTY_DELIVERED) AS DELIVERED_QTY,
				COALESCE(CP.QTY_REFOUND_PARTIAL, OP.QTY_REFOUND_PARTIAL) AS REFOUND_PARTIAL_QTY,
				COALESCE(CP.QTY_REJECTED, OP.QTY_REJECTED) AS REJECTED_QTY

				$movement_history

			FROM latest_per_type A

			LEFT JOIN custody_orders CO 
				ON CO.ORDER_ID = A.shipment_orders 
				AND A.delivery_id = CO.USER_ID

			LEFT JOIN orders O 
				ON O.ORDER_ID = A.shipment_orders 
				AND A.delivery_id = O.DELIVERY_ID

			LEFT JOIN USERS U 
				ON U.USER_ID = A.delivery_id

			LEFT JOIN orders SO 
				ON SO.ORDER_ID = A.shipment_orders

			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE IN ('REJECTED','PARTIAL') THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM custody_product
				GROUP BY ORDER_ID
			) CP ON CP.ORDER_ID = CO.ORDER_ID

			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE = 'REJECTED' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM order_product
				GROUP BY ORDER_ID
			) OP ON OP.ORDER_ID = O.ORDER_ID

			GROUP BY 
				A.delivery_id,
				A.shipment_orders,
				A.session_num
		)
		";


		$sql = "
		SELECT 
			delivery_id,
			$dateColum
			DELIVERY_NAME,
			COUNT(DISTINCT shipment_orders) AS TOTAL_ORDERS,

			SUM(TOTAL_QTY) AS TOTAL_QTY,
			SUM(WAITING_QTY) AS WAITING_QTY,
			SUM(DELIVERED_QTY) AS DELIVERED_QTY,
			SUM(REFOUND_PARTIAL_QTY) AS REFOUND_PARTIAL_QTY,
			SUM(REJECTED_QTY) AS REJECTED_QTY,

			IFNULL(
				CONCAT(
					ROUND(
						SUM(REJECTED_QTY) * 100 /
						NULLIF(SUM(TOTAL_QTY) - (SUM(WAITING_QTY) + SUM(REFOUND_PARTIAL_QTY)), 0),
					0),
				'%'),
			'') AS REJECTED_PERSENT

		FROM latest_Orders
		";
		if($rt == 1){
			$sql .= " GROUP BY delivery_id, date ";
		} else if($rt == 2){
			$sql = " SELECT * FROM latest_Orders ".$where ;
		} else {
			$sql.= " GROUP BY delivery_id ";
		}
		
		$stmt = $this->conn->prepare($sqltemp.$sql.$orderBy.$limit);
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			
			$stmt->bindValue(':deliveryId', $deliveryId, PDO::PARAM_INT);
		}
		if(!empty($start) && !empty($end)){
			$stmt->bindValue(':start', $start, PDO::PARAM_STR);
			$stmt->bindValue(':end', $end, PDO::PARAM_STR);
		}
		if (!empty($search)) {
			$stmt->bindValue(':search', $search, PDO::PARAM_STR);
		}
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
		
		

	}

	public function getDelegatesDetailsReport($rt = null){
		/*
		old statuse
		0 = Delivery خروج المندوب
		1 = freeze استرداد العهده دون استرداد المنتجات
		2 = Delivered الوصول للعميل
		3 = Return استرداد العهده بالكتمل
		4 = refund مرتجع بعد البيع 
			
		new statuse
		0 = 'استلام العهده'
        1 = 'استرداد امر البيع بدون المنتجات'
        2 = 'استرداد الامر بالكامل'
        3 = 'تم التسليم'
        4 = 'تسليم جزئي'
        5 = 'مرفوض'
        6 = 'تسويه مخزنيه'
        7 = 'تسويه ماليه'
        8 = 'مرتجع بعد البيع'
        9 = 'مرتجع جزئى بعد البيع'
        
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
		$ofset  = $_POST['start'];
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		$deliveryId = isset($_POST['did'])  ? $_POST['did'] == '' ? 0 : $_POST['did'] :0;
		$dateRange = isset($_POST['daterange']) ? $_POST['daterange'] == ''? '1990-01-01': $_POST['daterange']:'1990-01-01';
		// تقسيم التاريخ "
		$dates = explode(' - ', $dateRange);
		$start = isset($dates[0]) ? trim($dates[0]) : null;
		$end = isset($dates[1]) ? trim($dates[1]) : null;

		$where = "";
		$limit = "";
		$orderBy = "";
		$whquery =array();
		//موظف الشحن
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			$whquery[] = "delivery_id = :deliveryId";
		}
		//فلاتر التاريخ
		if(!empty($start) && !empty($end)){
			$whquery[] = "date BETWEEN :start AND :end";
		}
		// إضافة البحث
		if (!empty($search)) {
		    $whquery[] = "(O.SUB_ORDER_ID LIKE '%:search%' OR CONCAT(U.FORENAME,' ',U.LASTNAME) LIKE '%:search%')";
		}
		//where
		if ( count( $whquery ) ) {
			$where = ' WHERE '.implode(' AND ', $whquery);
		}
		//limit
		if(!empty($ofset) && !empty($length) && $length >0){
			$limit = " LIMIT ".$ofset.", ".$length." ";
		}
		//order by
		if (isset($_POST['order']) && isset($_POST['columns'])) {
		    $orderByArray = [];
		    foreach ($_POST['order'] as $orderItem) {
		        $columnIndex = $orderItem['column'];
		        $columnName = $_POST['columns'][$columnIndex]['data']; // اسم العمود حسب ما معرف في DataTables
		        $direction = strtoupper($orderItem['dir']) === 'DESC' ? 'DESC' : 'ASC';
		        
		        // تأكد من أن اسم العمود آمن للاستخدام (من قائمة أعمدة فقط)
		        $allowedColumns = ['DELIVERY_NAME', 'date', 'SUB_ORDER_ID','TOTAL_QTY','WAITING_QTY','DELIVERED_QTY','REFOUND_PARTIAL_QTY','REJECTED_QTY','REJECTED_PERSENT']; // عدلها حسب أعمدتك
		        if (in_array($columnName, $allowedColumns)) {
		            $orderByArray[] = "$columnName $direction";
		        }
		    }
		    if (!empty($orderByArray)) {
		        $orderBy = ' ORDER BY ' . implode(', ', $orderByArray);
		    }
		}
		
		/*$movement_history = $rt == 2 ? ",JSON_ARRAYAGG(
				    CONCAT(
				      CASE A.movement_type
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
				      END,
				      ' | ',
				      DATE(A.MOVING_DATE),
				      ' | ',
				      TIME(A.MOVING_DATE)
				    )
				    ORDER BY A.MOVING_DATE
				  ) AS movement_history" : '' ;
		$dateColum = 'date,';
		$sqltemp ="WITH ordered_movements AS (
				  SELECT 
				    *,
				    ROW_NUMBER() OVER (PARTITION BY shipment_orders ORDER BY MOVING_DATE, shipmov_id) AS rn
				  FROM shipping_movement
				),
				session_marker AS (
				  SELECT *,
				    SUM(CASE WHEN movement_type = 0 THEN 1 ELSE 0 END)
				      OVER (PARTITION BY shipment_orders ORDER BY MOVING_DATE, shipmov_id) AS session_num
				  FROM ordered_movements
				),
				sessions_with_start AS (
				  SELECT *
				  FROM session_marker
				  WHERE movement_type = 0
				),
				filtered_sessions AS (
				  SELECT sm.*
				  FROM session_marker sm
				  JOIN sessions_with_start ss
				    ON sm.shipment_orders = ss.shipment_orders
				    AND sm.session_num = ss.session_num
				),
				latest_per_type AS (
				  SELECT *
				  FROM (
				    SELECT *,
				      ROW_NUMBER() OVER (
				        PARTITION BY shipment_orders, session_num, movement_type
				        ORDER BY shipmov_id DESC, MOVING_DATE DESC
				      ) AS rn_per_type
				    FROM filtered_sessions
				  ) AS ranked
				  WHERE rn_per_type = 1
				),
				latest_Orders AS (
				SELECT 
				  A.delivery_id,
				  CONCAT(U.FORENAME,' ',U.LASTNAME) DELIVERY_NAME,
				  A.shipment_orders,
				  SO.SUB_ORDER_ID,
				  DATE(MOVING_DATE) As date,
				  COALESCE(CP.QTY, OP.QTY) AS TOTAL_QTY,
				  COALESCE(CP.QTY_WAITING, OP.QTY_WAITING) AS WAITING_QTY,
				  COALESCE(CP.QTY_DELIVERED, OP.QTY_DELIVERED) AS DELIVERED_QTY,
				  COALESCE(CP.QTY_REFOUND_PARTIAL, OP.QTY_REFOUND_PARTIAL) AS REFOUND_PARTIAL_QTY,
				  COALESCE(CP.QTY_REJECTED, OP.QTY_REJECTED) AS REJECTED_QTY
				  ".$movement_history." FROM latest_per_type A
				LEFT JOIN custody_orders CO ON CO.ORDER_ID = A.shipment_orders AND A.delivery_id = CO.USER_ID
				LEFT JOIN orders O ON O.ORDER_ID  = A.shipment_orders AND A.delivery_id = O.DELIVERY_ID
				LEFT JOIN USERS U ON U.USER_ID = A.delivery_id
				LEFT JOIN orders SO ON SO.ORDER_ID = A.shipment_orders
				LEFT JOIN (
				 SELECT 
				  ORDER_ID ,
				  SUM(QTY)AS QTY ,
				  SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
				  SUM(DELIVERED_QTY)AS QTY_DELIVERED, 
				  SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
				  SUM(CASE WHEN PRO_STATUSE IN('REJECTED','PARTIAL') THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				 FROM custody_product GROUP BY ORDER_ID
				) CP ON CP.ORDER_ID = CO.ORDER_ID
				LEFT JOIN (SELECT 
				  ORDER_ID ,
				  SUM(QTY)AS QTY ,
				  SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
				  SUM(DELIVERED_QTY)AS QTY_DELIVERED, 
				  SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
				  SUM(CASE WHEN PRO_STATUSE = 'REJECTED' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				 FROM order_product GROUP BY ORDER_ID) OP ON OP.ORDER_ID = O.ORDER_ID 
				GROUP BY  A.delivery_id, A.shipment_orders, A.session_num ) ";

		$sql = "SELECT 
				delivery_id,
				".$dateColum."
				DELIVERY_NAME,
				COUNT(DISTINCT shipment_orders) AS TOTAL_ORDERS,
				sum(TOTAL_QTY) AS TOTAL_QTY,
				SUM(WAITING_QTY) AS WAITING_QTY,
				SUM(DELIVERED_QTY) AS DELIVERED_QTY,
				SUM(REFOUND_PARTIAL_QTY) AS REFOUND_PARTIAL_QTY,
				SUM(REJECTED_QTY) AS REJECTED_QTY,
				IFNULL(CONCAT(ROUND(SUM(REJECTED_QTY) * 100 / (sum(TOTAL_QTY) - (SUM(WAITING_QTY) + SUM(REFOUND_PARTIAL_QTY))),0),'%'),'') AS REJECTED_PERSENT
				FROM latest_Orders
				".$where;*/
		
		$movement_history = ($rt == 2) ? "
		,JSON_ARRAYAGG(
			CONCAT(
				CASE A.movement_type
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
				END,
				' | ',
				DATE(A.MOVING_DATE),
				' | ',
				TIME(A.MOVING_DATE)
			)
			ORDER BY A.MOVING_DATE
		) AS movement_history
		" : "";


		$dateColum = ($rt == 1 || $rt == 2) ? "DATE(MIN(MOVING_DATE)) AS date," : "";


		$sqltemp = "
		WITH ordered_movements AS (
			SELECT *,
				ROW_NUMBER() OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS rn
			FROM shipping_movement
		),

		session_marker AS (
			SELECT *,
				SUM(CASE WHEN movement_type = 0 THEN 1 ELSE 0 END)
				OVER (
					PARTITION BY shipment_orders
					ORDER BY MOVING_DATE, shipmov_id
				) AS session_num
			FROM ordered_movements
		),

		sessions_with_start AS (
			SELECT *
			FROM session_marker
			WHERE movement_type = 0
		),

		filtered_sessions AS (
			SELECT sm.*
			FROM session_marker sm
			JOIN sessions_with_start ss
				ON sm.shipment_orders = ss.shipment_orders
				AND sm.session_num = ss.session_num
		),

		latest_per_type AS (
			SELECT *
			FROM (
				SELECT *,
					ROW_NUMBER() OVER (
						PARTITION BY shipment_orders, session_num, movement_type
						ORDER BY shipmov_id DESC, MOVING_DATE DESC
					) AS rn_per_type
				FROM filtered_sessions
			) ranked
			WHERE rn_per_type = 1
		),

		latest_orders AS (
			SELECT 
				A.delivery_id,
				CONCAT(U.FORENAME,' ',U.LASTNAME) AS DELIVERY_NAME,
				A.shipment_orders,
				SO.SUB_ORDER_ID,

				DATE(MIN(A.MOVING_DATE)) AS date,

				COALESCE(CP.QTY, OP.QTY) AS TOTAL_QTY,
				COALESCE(CP.QTY_WAITING, OP.QTY_WAITING) AS WAITING_QTY,
				COALESCE(CP.QTY_DELIVERED, OP.QTY_DELIVERED) AS DELIVERED_QTY,
				COALESCE(CP.QTY_REFOUND_PARTIAL, OP.QTY_REFOUND_PARTIAL) AS REFOUND_PARTIAL_QTY,
				COALESCE(CP.QTY_REJECTED, OP.QTY_REJECTED) AS REJECTED_QTY

				$movement_history

			FROM latest_per_type A

			LEFT JOIN custody_orders CO 
				ON CO.ORDER_ID = A.shipment_orders 
				AND A.delivery_id = CO.USER_ID

			LEFT JOIN orders O 
				ON O.ORDER_ID = A.shipment_orders 
				AND A.delivery_id = O.DELIVERY_ID

			LEFT JOIN USERS U 
				ON U.USER_ID = A.delivery_id

			LEFT JOIN orders SO 
				ON SO.ORDER_ID = A.shipment_orders

			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE IN ('REJECTED','PARTIAL') THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM custody_product
				GROUP BY ORDER_ID
			) CP ON CP.ORDER_ID = CO.ORDER_ID

			LEFT JOIN (
				SELECT 
					ORDER_ID,
					SUM(QTY) AS QTY,
					SUM(CASE WHEN PRO_STATUSE = 'WAITING' THEN QTY ELSE 0 END) AS QTY_WAITING,
					SUM(DELIVERED_QTY) AS QTY_DELIVERED,
					SUM(CASE WHEN PRO_STATUSE = 'PARTIAL' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REFOUND_PARTIAL,
					SUM(CASE WHEN PRO_STATUSE = 'REJECTED' THEN QTY - DELIVERED_QTY ELSE 0 END) AS QTY_REJECTED
				FROM order_product
				GROUP BY ORDER_ID
			) OP ON OP.ORDER_ID = O.ORDER_ID

			GROUP BY 
				A.delivery_id,
				A.shipment_orders,
				A.session_num
		)
		";


		$sql = "
		SELECT 
			delivery_id,
			$dateColum
			DELIVERY_NAME,
			COUNT(DISTINCT shipment_orders) AS TOTAL_ORDERS,

			SUM(TOTAL_QTY) AS TOTAL_QTY,
			SUM(WAITING_QTY) AS WAITING_QTY,
			SUM(DELIVERED_QTY) AS DELIVERED_QTY,
			SUM(REFOUND_PARTIAL_QTY) AS REFOUND_PARTIAL_QTY,
			SUM(REJECTED_QTY) AS REJECTED_QTY,

			IFNULL(
				CONCAT(
					ROUND(
						SUM(REJECTED_QTY) * 100 /
						NULLIF(SUM(TOTAL_QTY) - (SUM(WAITING_QTY) + SUM(REFOUND_PARTIAL_QTY)), 0),
					0),
				'%'),
			'') AS REJECTED_PERSENT

		FROM latest_Orders
		";
		if($rt == 1){
			$sql .= " GROUP BY delivery_id, date ";
		} else if($rt == 2){
			$sql = " SELECT * FROM latest_Orders ".$where ;
		} else {
			$sql.= " GROUP BY delivery_id ";
		}

		$stmt = $this->conn->prepare($sqltemp.$sql.$orderBy.$limit);
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			
			$stmt->bindValue(':deliveryId', $deliveryId, PDO::PARAM_INT);
		}
		if(!empty($start) && !empty($end)){
			$stmt->bindValue(':start', $start, PDO::PARAM_STR);
			$stmt->bindValue(':end', $end, PDO::PARAM_STR);
		}
		if (!empty($search)) {
			$stmt->bindValue(':search', $search, PDO::PARAM_STR);
		}
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
		
		

	}

	public function getShippingReport(){
	
		$ofset  = $_POST['start'];
		$length = $_POST['length'];
		$search = $_POST['search']['value'];
		$deliveryId = isset($_POST['did'])  ? $_POST['did'] == '' ? 0 : $_POST['did'] :0;
		$dateRange = isset($_POST['daterange']) ? $_POST['daterange'] == ''? '1990-01-01': $_POST['daterange']:'1990-01-01';
		// تقسيم التاريخ "
		$dates = explode(' - ', $dateRange);
		$start = isset($dates[0]) ? trim($dates[0]) : null;
		$end = isset($dates[1]) ? trim($dates[1]) : null;

		$where = "";
		$limit = "";
		$orderBy = "";
		$whquery =array();
        //موظف الشحن
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			$whquery[] = "o.DELIVERY_ID = :deliveryId";
		}
        //فلاتر التاريخ
		if(!empty($start) && !empty($end)){
			$whquery[] = "o.SHIPPING_DATE BETWEEN :start AND :end";
		}
		// إضافة البحث
		if (!empty($search)) {
		    $whquery[] = "(O.SUB_ORDER_ID LIKE '%:search%' ";
		}
		//where
		if ( count( $whquery ) ) {
			$where = ' WHERE '.implode(' AND ', $whquery);
		}
		
		//limit
		if(!empty($ofset) && !empty($length) && $length >0){
			$limit = " LIMIT ".$ofset.", ".$length." ";
		}
		
		
		
		$sql = "SELECT 
				cl.CLIENT_NAME AS CustName,
				(select COM_NAME FROM companies) AS company,
				sum((op.PRICE - op.DISCOUNT) * op.QTY) + o.SHIPPING_VAL AS coust,
				o.SUB_ORDER_ID AS Parcode,
				o.TELEPHONE AS Phone,
				GO.GOV_NAME AS City,
				ci.CI_NAME AS Region,
				a.ADDRESS AS address,
				o.DATE_CREATED AS createdDate,
				COMMENT AS notes,
				o.EXCHANGE AS Replacing,
				GROUP_CONCAT(
				    CONCAT(
				        'المنتج : ',
				        POV.PRO_NAME,
				        ' | ',
				        IFNULL(POV.OPT_JSON, ''),
				        ' | الكميه : ',
				        FORMAT(op.QTY, 2)
				    )
				    SEPARATOR ' x|x '
				) AS OrderContent,
				sum(op.QTY) AS QTY,
				sum((op.PRICE - op.DISCOUNT) * op.QTY) AS PRICE,
				o.SHIPPING_VAL
				FROM  orders o 
				 LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
				 LEFT JOIN addresses a ON a.ADDRESS_ID = o.ADDRESS_ID
				 LEFT JOIN city ci ON ci.CI_ID = a.CITY_ID
				 LEFT JOIN gov go ON go.GOV_ID = ci.GOV_ID
				 LEFT JOIN client cl ON cl.CLIENT_ID IS NOT NULL AND cl.CLIENT_ID = a.CLIENT_ID
				 LEFT JOIN product_options_view pov ON pov.PRO_CODE = op.PRO_CODE
				 ".$where."
				GROUP BY 
				cl.CLIENT_NAME,
				o.ORDER_ID,
				o.TELEPHONE,
				GO.GOV_NAME,
				ci.CI_NAME,
				a.ADDRESS,
				o.DATE_CREATED,
				COMMENT,
				EXCHANGE ";
		
		$stmt = $this->conn->prepare($sql.$limit);
		if(isset($_POST['did']) && !empty($_POST['did']) && $_POST['did'] > 0){
			
			$stmt->bindValue(':deliveryId', $deliveryId, PDO::PARAM_INT);
		}
		if(!empty($start) && !empty($end)){
			$stmt->bindValue(':start', $start, PDO::PARAM_STR);
			$stmt->bindValue(':end', $end, PDO::PARAM_STR);
		}
		if (!empty($search)) {
			$stmt->bindValue(':search', $search, PDO::PARAM_STR);
		}
		$stmt->execute();
		
		// إرجاع البيانات إلى DataTables
		$response = array(
		    "draw" => intval($_POST['draw']),
		    "iTotalRecords" => $stmt->rowCount(),
		    "iTotalDisplayRecords" => $stmt->rowCount(),
		    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
		);
		
		return $response;
		
		

	}
	
	public function inventoryProDailyReport($storeId, $dateFrom, $dateTo){
	    $sql = "SELECT  im.IM_ID,
			  im.CREATED_DATE,
			  CASE
			   WHEN im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES') THEN Concat('امر بيع',': ',o.SUB_ORDER_ID)
			   WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT') THEN Concat('امر شراء',': ',PO.ORDER_ID)
			   WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN Concat('امر مرتجع شراء',': ',pro.ORDER_ID)
			   ELSE Concat('رقم الحركه المخزنيه',': ', im.IM_ID)
			  END   AS ORDER_ID,
			  CASE
			   WHEN im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES') THEN Concat(U.FORENAME,' ',U.LASTNAME)
			   WHEN im.IM_MOVE_TYPE IN('REFUNDPURCHISES','RECEIVING','PARTIALRECEIPT') THEN s.SUP_NAME
			   ELSE Concat(ua.FORENAME,' ',ua.LASTNAME)
			  END   AS PARTNER_NAME,
			  CASE
			   WHEN im.IM_MOVE_TYPE = 'SALES' THEN 'صرف بيع مباشر'
			   WHEN im.IM_MOVE_TYPE = 'SHIPPING' THEN 'صرف عهده للمندوب'
			   WHEN im.IM_MOVE_TYPE = 'REFUND' THEN 'وارد استرداد من المندوب'
			   WHEN im.IM_MOVE_TYPE IN('RETURN_SHIPP','RETURN_SHIPP_SALES') THEN 'وارد مرتجع مندوب'
			   WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 10 THEN 'وارد مرتجع بعد البيع'
			   WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 11 THEN 'وارد مرتجع بيع مباشر'
			   WHEN im.IM_MOVE_TYPE = 'TRANSFER' THEN 'تحويل مخزنى داخلى'
			   WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'IN' THEN 'وارد تسوية مخزنيه'
			   WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'OUT' THEN 'منصرف تسوية مخزنية'
			   WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT') THEN 'وارد من مورد'
			   WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN 'صرف مرتجعات المورد'
			   ELSE im.IM_MOVE_TYPE
			  END   IM_MOVE_TYPE,
			  pov.PRO_NAME,
			  pov.OPT_JSON,
			  Sum(
			    CASE
			     WHEN ipm.IPM_TYPE_MOV = 'IN' THEN ipm.IPM_QTY
			     ELSE 0
			    END
			  ) AS QTY_IN,
			  Sum(
			    CASE
			     WHEN ipm.IPM_TYPE_MOV = 'OUT' THEN ipm.IPM_QTY
			     ELSE 0
			    END
			  ) AS QTY_OUT
			  
			FROM   inventory_product_movement ipm
			  INNER JOIN inventory_movement im ON im.IM_ID = ipm.IM_ID
			  LEFT JOIN orders o ON ipm.ORDER_ID = o.ORDER_ID AND im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES','RETURN_SALES')
			  LEFT JOIN purchases_orders po ON IPM.ORDER_ID = PO.ORDER_ID AND im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT')
			  LEFT JOIN purchases_return_orders pro ON PRO.ORDER_ID = IPM.ORDER_ID AND im.IM_MOVE_TYPE = 'REFUNDPURCHISES'
			  LEFT JOIN (
			    SELECT  UNIQUE_ID,
			      shipment_orders AS REFERENCE,
			      DELIVERY_ID
			    FROM   shipping_movement
			    WHERE  UNIQUE_ID IS NOT NULL
			    GROUP BY UNIQUE_ID,
			      shipment_orders,
			      DELIVERY_ID
			  ) sm ON im.UNIQUE_ID = sm.UNIQUE_ID AND im.REFERENCE = sm.REFERENCE
			  LEFT JOIN users_delivery ud ON sm.DELIVERY_ID = ud.USER_ID
			  LEFT JOIN users u ON ud.USER_ID = u.USER_ID
			  LEFT JOIN users ua ON ua.USER_ID = im.IM_USER
			  LEFT JOIN suppliers s ON s.SUP_ID = po.SUPPLIER_ID OR s.SUP_ID = pro.SUPPLIER_ID
			  LEFT JOIN product_options_view pov ON ipm.IPM_CODE = pov.PRO_CODE
			WHERE  im.STO_ID = :store_id AND Date(ipm.CREATE_AT) BETWEEN :date_from AND :date_to
			GROUP BY im.IM_ID,
			  im.CREATED_DATE,
			  im.IM_MOVE_TYPE,
			  im.REFERENCE,
			  o.SUB_ORDER_ID,
			  pov.PRO_NAME,
			  pov.OPT_JSON,
			  CASE WHEN im.IM_MOVE_TYPE = 'SALES' THEN 'صرف بيع مباشر' 
			       WHEN im.IM_MOVE_TYPE = 'SHIPPING' THEN 'صرف عهده للمندوب' 
			       WHEN im.IM_MOVE_TYPE = 'REFUND' THEN 'وارد استرداد من المندوب' 
			       WHEN im.IM_MOVE_TYPE IN('RETURN_SHIPP','RETURN_SHIPP_SALES') THEN 'وارد مرتجع مندوب' 
			       WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 10 THEN 'وارد مرتجع بعد البيع' 
			       WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 11 THEN 'وارد مرتجع بيع مباشر' 
			       WHEN im.IM_MOVE_TYPE = 'TRANSFER' THEN 'تحويل مخزنى داخلى' 
			       WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'IN' THEN 'وارد تسوية مخزنيه' 
			       WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'OUT' THEN 'منصرف تسوية مخزنية' 
			       WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT') THEN 'وارد من مورد' 
			       WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN 'صرف مرتجعات المورد' 
			       ELSE im.IM_MOVE_TYPE 
			  END
			ORDER BY im.CREATED_DATE
			";
	
	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':store_id'  => $storeId,
	        ':date_from' => $dateFrom,
	        ':date_to'   => $dateTo
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function inventoryDailyReport($storeId, $dateFrom, $dateTo){
	    $sql = "WITH base AS (
    SELECT
        DATE(ipm.CREATE_AT) AS report_date,
        im.IM_MOVE_TYPE,
        ipm.IPM_QTY,
        ipm.IPM_TYPE_MOV,
        IF(o.ORDER_ID IS NOT NULL, 1, 0) AS DIRECT_SELSE
    FROM inventory_product_movement ipm
    INNER JOIN inventory_movement im
        ON im.IM_ID = ipm.IM_ID
    LEFT JOIN orders o ON ipm.ORDER_ID = o.ORDER_ID AND o.ORDER_TYPE_ID = 1 AND o.EXISTS_ID = 11
    WHERE im.STO_ID = :store_id
      AND ipm.CREATE_AT >= :date_from
      AND ipm.CREATE_AT < DATE_ADD(:date_to, INTERVAL 1 DAY)
),

daily AS (
    SELECT
        report_date,
        SUM(CASE WHEN IPM_TYPE_MOV = 'IN' THEN IPM_QTY ELSE -IPM_QTY END) AS total_net_movement,
        SUM(CASE WHEN IM_MOVE_TYPE IN ('RECEIVING','PARTIALRECEIPT') THEN IPM_QTY ELSE 0 END) AS supplier_receiving,
        SUM(CASE WHEN IM_MOVE_TYPE = 'REFUND' THEN IPM_QTY ELSE 0 END) AS refund_from_courier,
        SUM(CASE WHEN IM_MOVE_TYPE = 'RETURN_SALES' AND DIRECT_SELSE = 0 THEN IPM_QTY ELSE 0 END) AS return_change,
        SUM(CASE WHEN IM_MOVE_TYPE = 'RETURN_SALES' AND DIRECT_SELSE = 1 THEN IPM_QTY ELSE 0 END) AS return_sales,
        SUM(CASE WHEN IM_MOVE_TYPE = 'RETURN_SHIPP' THEN IPM_QTY ELSE 0 END) AS return_shipp,
        SUM(CASE WHEN IM_MOVE_TYPE = 'SETTLEMENT' AND IPM_TYPE_MOV='IN' THEN IPM_QTY ELSE 0 END) AS settlement_in,
		SUM(CASE WHEN IM_MOVE_TYPE = 'TRANSFER' AND IPM_TYPE_MOV='IN' THEN IPM_QTY ELSE 0 END) AS transfer_in,
        SUM(CASE WHEN IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN IPM_QTY ELSE 0 END) AS supplier_return,
        SUM(CASE WHEN IM_MOVE_TYPE = 'SHIPPING' THEN IPM_QTY ELSE 0 END) AS shipping_to_courier,
        SUM(CASE WHEN IM_MOVE_TYPE = 'SALES' THEN IPM_QTY ELSE 0 END) AS direct_sales,
        SUM(CASE WHEN IM_MOVE_TYPE = 'SETTLEMENT' AND IPM_TYPE_MOV='OUT' THEN IPM_QTY ELSE 0 END) AS settlement_out,
		SUM(CASE WHEN IM_MOVE_TYPE = 'TRANSFER' AND IPM_TYPE_MOV='OUT' THEN IPM_QTY ELSE 0 END) AS transfer_out
    FROM base
    GROUP BY report_date
),

opening AS (
    SELECT
        IFNULL(
            SUM(
                CASE
                    WHEN ipm.IPM_TYPE_MOV='IN'
                    THEN ipm.IPM_QTY
                    ELSE -ipm.IPM_QTY
                END
            ),0
        ) AS opening_balance
    FROM inventory_product_movement ipm
    INNER JOIN inventory_movement im
        ON im.IM_ID = ipm.IM_ID
    WHERE im.STO_ID = :store_id
      AND ipm.CREATE_AT < :date_from
),

calc AS (
    SELECT
        d.*,
        (
            supplier_receiving +
            refund_from_courier +
            return_change +
            return_sales +
            return_shipp +
            settlement_in +
			transfer_in
            -
            supplier_return -
            shipping_to_courier -
            direct_sales -
            settlement_out -
			transfer_out
        ) AS classified_net_movement,
        total_net_movement - (
            supplier_receiving +
            refund_from_courier +
            return_change +
            return_sales +
            return_shipp +
            settlement_in +
			transfer_in
            -
            supplier_return -
            shipping_to_courier -
            direct_sales -
            settlement_out -
			transfer_out
        ) AS unclassified_movement
    FROM daily d
)

SELECT
    report_date,
    op.opening_balance +
    COALESCE(
        SUM(total_net_movement) OVER(
            ORDER BY report_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ),0
    ) AS opening_balance,
    supplier_receiving,
    refund_from_courier,
    return_sales,
    return_change,
    return_shipp,
    settlement_in,
	transfer_in,
    supplier_return,
    shipping_to_courier,
    direct_sales,
    settlement_out,
	transfer_out,
    unclassified_movement,
    op.opening_balance +
    SUM(total_net_movement) OVER(
        ORDER BY report_date
    ) AS closing_balance
FROM calc c
CROSS JOIN opening op
ORDER BY report_date
";
	
	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':store_id'  => $storeId,
	        ':date_from' => $dateFrom,
	        ':date_to'   => $dateTo
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function inventoryDailyDetails($storeId, $reportDate){
	    $sql = "
	    SELECT
	        im.IM_ID,
	        CASE WHEN im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES') 
               THEN CONCAT('امر بيع',': ',o.SUB_ORDER_ID )
              WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT')
               THEN CONCAT('امر شراء',': ',PO.ORDER_ID)
              WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES'
               THEN CONCAT('',': ',pro.ORDER_ID)
               ELSE CONCAT('رقم الحركه المخزنيه',': ', im.IM_ID)
          END AS ORDER_ID,
          
	     im.CREATED_DATE,
         CASE WHEN im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES') 
                THEN CONCAT(U.FORENAME,' ',U.LASTNAME) 
                WHEN im.IM_MOVE_TYPE IN('REFUNDPURCHISES','RECEIVING','PARTIALRECEIPT')
                THEN s.SUP_NAME 
              ELSE CONCAT(ua.FORENAME,' ',ua.LASTNAME)
         END AS PARTNER_NAME,

	     CASE 
              WHEN im.IM_MOVE_TYPE = 'SALES'           THEN 'صرف بيع مباشر'
              WHEN im.IM_MOVE_TYPE = 'SHIPPING'        THEN 'صرف عهده للمندوب'  
              WHEN im.IM_MOVE_TYPE = 'REFUND'          THEN 'وارد استرداد من المندوب'
                    
              WHEN im.IM_MOVE_TYPE  IN('RETURN_SHIPP','RETURN_SHIPP_SALES') THEN 'وارد مرتجع مندوب'

              WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 10 THEN 'وارد مرتجع بعد البيع'
              WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 11 THEN 'وارد مرتجع بيع مباشر'
    
              WHEN im.IM_MOVE_TYPE = 'TRANSFER' THEN 'تحويل مخزنى داخلى'
              
              WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'IN' THEN 'وارد تسوية مخزنيه'
              WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'OUT' THEN 'منصرف تسوية مخزنية'

              WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT') THEN 'وارد من مورد'
              WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN 'صرف مرتجعات المورد'
              ELSE im.IM_MOVE_TYPE
          END IM_MOVE_TYPE,
	        
	      SUM(CASE WHEN ipm.IPM_TYPE_MOV = 'IN'  THEN ipm.IPM_QTY ELSE 0 END) AS QTY_IN,
		  SUM(CASE WHEN ipm.IPM_TYPE_MOV = 'OUT' THEN ipm.IPM_QTY ELSE 0 END) AS QTY_OUT,
		  im.IM_DETAILS
	
	    FROM inventory_product_movement ipm
	    INNER JOIN inventory_movement im ON im.IM_ID = ipm.IM_ID
	    LEFT JOIN orders o ON ipm.ORDER_ID = o.ORDER_ID AND im.IM_MOVE_TYPE IN('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SHIPP_SALES','RETURN_SALES') 
     LEFT JOIN purchases_orders po ON IPM.ORDER_ID = PO.ORDER_ID AND im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT')
     LEFT JOIN purchases_return_orders pro ON PRO.ORDER_ID = IPM.ORDER_ID AND im.IM_MOVE_TYPE = 'REFUNDPURCHISES'
     LEFT JOIN (SELECT UNIQUE_ID, shipment_orders AS REFERENCE, DELIVERY_ID FROM shipping_movement
                 WHERE UNIQUE_ID IS NOT NULL
                 GROUP BY UNIQUE_ID, shipment_orders,DELIVERY_ID
                ) sm ON im.UNIQUE_ID = sm.UNIQUE_ID AND im.REFERENCE = sm.REFERENCE
     LEFT JOIN users_delivery ud ON sm.DELIVERY_ID = ud.USER_ID
     LEFT JOIN users u ON ud.USER_ID = u.USER_ID
     LEFT JOIN users ua ON ua.USER_ID = im.IM_USER
     LEFT JOIN suppliers s ON s.SUP_ID = po.SUPPLIER_ID OR s.SUP_ID = pro.SUPPLIER_ID
     WHERE im.STO_ID = :store_id
	      AND DATE(ipm.CREATE_AT) = :report_date
	    
	 GROUP BY im.IM_ID,
		    
	        im.CREATED_DATE,
	        im.IM_MOVE_TYPE,
	        im.REFERENCE,
	        o.SUB_ORDER_ID,
	        CASE 
              WHEN im.IM_MOVE_TYPE = 'SALES'           THEN 'صرف بيع مباشر'
              WHEN im.IM_MOVE_TYPE = 'SHIPPING'        THEN 'صرف عهده للمندوب'  
              WHEN im.IM_MOVE_TYPE = 'REFUND'          THEN 'وارد استرداد من المندوب'
                    
              WHEN im.IM_MOVE_TYPE  IN('RETURN_SHIPP','RETURN_SHIPP_SALES') THEN 'وارد مرتجع مندوب'

              WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 10 THEN 'وارد مرتجع بعد البيع'
              WHEN im.IM_MOVE_TYPE = 'RETURN_SALES' AND O.EXISTS_ID = 11 THEN 'وارد مرتجع بيع مباشر'
    
              WHEN im.IM_MOVE_TYPE = 'TRANSFER' THEN 'تحويل مخزنى داخلى'
              
              WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'IN' THEN 'وارد تسوية مخزنيه'
              WHEN im.IM_MOVE_TYPE = 'SETTLEMENT' AND ipm.IPM_TYPE_MOV = 'OUT' THEN 'منصرف تسوية مخزنية'

              WHEN im.IM_MOVE_TYPE IN('RECEIVING','PARTIALRECEIPT') THEN 'وارد من مورد'
              WHEN im.IM_MOVE_TYPE = 'REFUNDPURCHISES' THEN 'صرف مرتجعات المورد'
              ELSE im.IM_MOVE_TYPE
          END,
          im.IM_DETAILS
	    ORDER BY im.CREATED_DATE
	    
	    ";

	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':store_id'   => $storeId,
	        ':report_date'=> $reportDate
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function inventoryDailyProduct($imid,$dirmov){
	    $sql = "
	    SELECT 
	      POV.PRO_NAME,
		  POV.OPT_JSON,
		  CASE
		   WHEN IPM.IPM_TYPE_MOV = 'IN'  THEN 'وارد'
		   ELSE 'منصرف'
		  END AS IPM_TYPE_MOV,
		  IPM.IPM_QTY
		FROM  inventory_product_movement IPM
		LEFT JOIN product_options_view POV ON IPM.IPM_CODE = POV.PRO_CODE
		WHERE IPM_TYPE_MOV = :dirmov AND IM_ID = :imid
	    ";
	
	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':dirmov'   => $dirmov,
	        ':imid'=> $imid
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function marketerDailyReport($partner_id, $dateFrom, $dateTo){
	    $sql = "
	     select 
			ORDER_STATUSE,
			COUNT(ORDER_ID) AS COUNT_ORDERS, 
			SUM(QTY) AS QTY,
			CASE WHEN ORDER_STATUS IN(4,5,6,7) THEN SUM(QTY-DELIVERED_QTY) ELSE 0 END AS REJECTED_QTY,
			SUM(DELIVERED_QTY) AS DELIVERED_QTY,
			 SUM(RETURNED_QTY) AS RETURNED_QTY
			from(
			SELECT o.ORDER_ID,
			 o.ORDER_BY,
			 o.DATE_CREATED,
			 DELIVERY_DATE,
			 o.SHIPPING_DATE,
			 o.DELIVERED_DATE,
			 o.LAST_UPDATE,
			 o.SUB_ORDER_ID,
			 o.EXISTS_ID,
			 o.ORDER_STATUS,
			 CASE
			  WHEN ORDER_STATUS = 0
			  THEN oe.EXISTS_NAME
			  ELSE os.STA_NAME
			 END    AS ORDER_STATUSE,
			 op.QTY,
			 op.D_QTY AS DELIVERED_QTY,
			 op.R_QTY AS RETURNED_QTY,
			 CASE
			  WHEN pb.credit IS NULL
			    AND o.ORDER_STATUS IN(3,4)
			  THEN 'قيد التسوية الماليه'
			  WHEN pb.credit IS NULL
			    AND o.ORDER_STATUS IN(0,1,2,5)
			  THEN '-'
			  ELSE pb.credit
			 END    AS credit,
			 CASE
			  WHEN pb.debit IS NULL
			    AND o.ORDER_STATUS IN(6,7)
			  THEN 'قيد التسوية الماليه'
			  WHEN pb.debit IS NULL
			    AND o.ORDER_STATUS IN(0,1,2,3,4,5)
			  THEN '-'
			  ELSE pb.debit
			 END    AS debit
			FROM  orders o
			 LEFT JOIN order_statuse os
			 ON o.ORDER_STATUS = os.STA_ID
			 LEFT JOIN order_exists oe
			 ON o.EXISTS_ID = oe.EXISTS_ID
			 LEFT JOIN (SELECT  ORDER_ID,
			     Sum(QTY)   AS QTY,
			     Sum(DELIVERED_QTY) AS D_QTY,
			     Sum(RETURNED_QTY) AS R_QTY
			   FROM   order_product
			   GROUP BY ORDER_ID) op
			 ON o.ORDER_ID = op.ORDER_ID
			 LEFT JOIN (SELECT  PARTNER_ID,
			     REFERENCE_ID,
			     Sum(CASE
			     WHEN TRANSACTION_CATEGORY_ID = 12
			     THEN BALANCE
			     ELSE 0
			    END) AS CREDIT,
			     Sum(CASE
			     WHEN TRANSACTION_CATEGORY_ID = 16
			     THEN BALANCE
			     ELSE 0
			    END) AS DEBIT
			   FROM   partners_balance
			   WHERE  TRANSACTION_CATEGORY_ID IN (12,16)
			     AND PARTNER_TYPE = 'MARKETER'
			   GROUP BY PARTNER_ID,
			     REFERENCE_ID) pb
			 ON o.ORDER_ID = pb.REFERENCE_ID
			  AND o.ORDER_BY = pb.PARTNER_ID 
			 ) tbl  WHERE ORDER_BY = :partner_id AND date(LAST_UPDATE) BETWEEN :dateFrom AND :dateTo
			 GROUP BY ORDER_STATUSE 
			 ORDER BY ORDER_STATUS,EXISTS_ID
	    ";
	
	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':partner_id'   => $partner_id,
	        ':dateFrom' =>$dateFrom,
	        ':dateTo' =>$dateTo 
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function marketerDailyDetails($partner_id, $order_statuse, $dateFrom, $dateTo){
	    $sql = "SELECT * FROM (
			 SELECT o.ORDER_ID,
			   o.ORDER_BY,
			   o.DATE_CREATED,
			   DELIVERY_DATE,
			   o.SHIPPING_DATE,
			   o.DELIVERED_DATE,
			   o.SUB_ORDER_ID,
			   o.LAST_UPDATE,
			   o.EXISTS_ID,
			   o.ORDER_STATUS,
			   CASE WHEN ORDER_STATUS = 0 THEN oe.EXISTS_NAME ELSE os.STA_NAME END   AS ORDER_STATUSE_NAME,
			   op.QTY,
			   op.D_QTY AS DELIVERED_QTY,
			   CASE WHEN o.ORDER_STATUS IN(5,4,6,7) THEN (SUM(op.QTY - op.D_QTY)) ELSE 0 END AS REJACTED_QTY,
			   op.R_QTY AS RETURNED_QTY,
			   CASE WHEN pb.credit IS NULL AND o.ORDER_STATUS IN(3,4) THEN 'قيد التسوية الماليه'
			        WHEN pb.credit IS NULL AND o.ORDER_STATUS IN(0,1,2,5) THEN '-' ELSE pb.credit END   AS credit,
			   CASE WHEN pb.debit IS NULL AND o.ORDER_STATUS IN(6,7) THEN 'قيد التسوية الماليه' 
			        WHEN pb.debit IS NULL AND o.ORDER_STATUS IN(0,1,2,3,4,5) THEN '-' ELSE pb.debit END   AS debit
			   FROM  orders o
			   LEFT JOIN order_statuse os ON o.ORDER_STATUS = os.STA_ID
			   LEFT JOIN order_exists oe ON o.EXISTS_ID = oe.EXISTS_ID
			   LEFT JOIN (SELECT  ORDER_ID,
			       Sum(QTY)   AS QTY,
			       Sum(DELIVERED_QTY) AS D_QTY,
			       Sum(RETURNED_QTY) AS R_QTY
			      FROM   order_product
			      GROUP BY ORDER_ID) op
			   ON o.ORDER_ID = op.ORDER_ID
			   LEFT JOIN (SELECT  PARTNER_ID,
			       REFERENCE_ID,
			       Sum(CASE
			       WHEN TRANSACTION_CATEGORY_ID = 12
			       THEN BALANCE
			       ELSE 0
			      END) AS CREDIT,
			       Sum(CASE
			       WHEN TRANSACTION_CATEGORY_ID = 16
			       THEN BALANCE
			       ELSE 0
			      END) AS DEBIT
			      FROM   partners_balance
			      WHERE  TRANSACTION_CATEGORY_ID IN (12,16)
			       AND PARTNER_TYPE = 'MARKETER'
			      GROUP BY PARTNER_ID,
			       REFERENCE_ID) pb
			   ON o.ORDER_ID = pb.REFERENCE_ID
			     AND o.ORDER_BY = pb.PARTNER_ID
			     GROUP BY ORDER_ID
			) tbl
			 WHERE  ORDER_BY = :partner_id AND ORDER_STATUSE_NAME = :order_statuse AND Date(LAST_UPDATE) BETWEEN :dateFrom AND :dateTo
			 
	    ";
	
	    $stmt = $this->conn->prepare($sql);
	
	    $stmt->execute([
	        ':partner_id'   => $partner_id,
	        ':order_statuse'=> $order_statuse,
	        ':dateFrom' =>$dateFrom,
	        ':dateTo' =>$dateTo
	        
	    ]);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	

}
?>