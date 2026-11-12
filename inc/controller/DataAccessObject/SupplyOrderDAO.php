<?php
include_once(ABSPATH.'/inc/configDateTimeFN.php');
include_once(ABSPATH.'/inc/Databases/DBSchema.php');
include_once(ABSPATH.'/inc/Databases/DataProcessor.php');
//include_once(ABSPATH.'/inc/Databases/TableDeleterDAO.php');

class SupplyOrderDAO {
	public  $pdo; // خاصية لتمثيل اتصال PDO مع قاعدة البيانات
	private $DPro;
	private $DBTables;
	private $DBInsert;
	private $DBupdate;
	private $dtz;
	// الباني (Constructor) لتلقي اتصال قاعدة البيانات عند إنشاء الكائن
	public function __construct($pdo) {
		$this->pdo = $pdo; // تخزين اتصال PDO في الخاصية $pdo
		$this->DPro = new DataProcessor();
		$this->DBTables  = DBSchema::getInstance();
		$this->dtz       = new configDateTime();
	}
	//===============================================================================≠================
// Sub-functions / Assistant function 
//================================================================================================
	// دالة للتحقق من صحة البيانات المدخلة فى طلب الشراء
/*	private function validateOrderData($totalPrice, $totalPayments, $status) {
		if (!is_numeric($totalPrice) || !is_numeric($totalPayments)) {
			throw new Exception("Invalid numeric data provided."); // التحقق من البيانات الرقمية
		}
		if (!is_string($status) || empty($status)) {
			throw new Exception("Invalid status."); // التحقق من البيانات النصية
		}
	}
	
	// دالة التحقق من صحة البيانات المدخلة فى منتجات طلب الشراء
	private function validateProductData($order_id, $pro_code, $qty, $qty_supplied) {
		if (!is_numeric($order_id) || !is_numeric($qty) || !is_numeric($qty_supplied) || !is_numeric($pro_code)){
			throw new Exception("Invalid numeric data provided."); // التحقق من البيانات الرقمية
		}
	}
*/
	//داله لتحديد عدد الصفوف المعروضه فى DataTables لقائمة اوامر الشراء
	private function limit   (&$request) {
    	// إنشاء جملة  limit  لاستعلام SQL لمعالجة البيانات من جانب الخادم
		$limit = '';
		if ( isset($request['start']) && $request['length'] != -1 ) {
			$limit = " LIMIT ".intval($request['start']).", ".intval($request['length']." ");
		}
		return $limit;
	}
	
	// دالة لترتيب نتيجة الاستعلم لقائمة الشراء لعرضها فى DataTables
	private function orderby (&$request ) {
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
            $order = ' ORDER BY ORDER_ID  '.strtoupper($request['order']['0']['dir']);
        }
		return $order;
	}
	
	// دالة لتحديد شروط عرض البيانات لقئمة اوامر الشراء المعروضه فى DataTables
    private function Where(&$request, $fields,$globalWhere= [], $bool = true){
	    $globalSearch = [];
	    $params = []; // لتخزين القيم المرتبطة بالاستعلام
		// ====================== Tab Conditions ==========================
	    if (isset($request['tab']) && $bool) {
	        switch ($request['tab']) {
	            case "OLCompleted":
	                $globalWhere[] = $fields['NAME']['SUPPLIED_STATUSE']." = 'مكتمل'";
	                break;
	            case "OLUnderReceipt":
	                $globalWhere[] = $fields['NAME']['SUPPLIED_STATUSE']." IN ('جزئي','قيد التوريد')";
	                break;
	            default:
	                $globalWhere[] = $fields['NAME']['SUPPLIED_STATUSE']." IN ('مكتمل','جزئي','قيد التوريد')";
	                break;
	        }
	    }
		
	    // ====================== Global Search ==========================

	    if (isset($request['search']['value']) && $bool && !empty($request['search']['value'])) {
	        $str = $this->DPro->sanitizeInput($request['search']['value']);
	        $globalSearch[] = $fields['NAME']['SUP_NAME']. ' LIKE :search_value';
	        $globalSearch[] = $fields['NAME']['CREATE_AT'].' LIKE :search_value';
	        $globalSearch[] = $fields['NAME']['NOTE'].	   ' LIKE :search_value';
	        $params[':search_value'] = "%$str%";
	    }

	    // ====================== Combine where ==========================
	    $where = '';
	
	    if (count($globalWhere)) {
	        $where = implode(' AND ', $globalWhere);
	    }
	
	    if (count($globalSearch)) {
	        $searchClause = '(' . implode(' OR ', $globalSearch) . ')';
	        $where = $where === '' ? $searchClause : $where . ' AND ' . $searchClause;
	    }
	
	    if ($where !== '') {
	        $where = ' WHERE ' . $where;
	    }
	    return ['where' => $where, 'params' => $params];
	}
//===============================================================================≠================
// Main-functions / Basic function 
//================================================================================================
	// دالة لإدخال المنتجات المرتبطة بأمر الشراء في جدول `purchases_products`
	public function insertProducts($orderId,$products) {
		include_once(ABSPATH.'/inc/Databases/TableInserterDAO.php');
		$this->DBInsert  = new TableInserter($pdo);
		$tbln 	= $this->DBTables->getTableName('SUPPLY_ORDER_DETAILS');
		$fields = $this->DBTables->getTableFields('SUPPLY_ORDER_DETAILS');
		$error  = 0;
		foreach ($products as $product) {
			$data = [
				$fields['NAME']['SUPPLY_ORDER_ID'] 	=> $orderId,
				$fields['NAME']['PRO_CODE'] 		=> $product['PRO_CODE'],
				$fields['NAME']['QUANTITY'] 		=> $product['PURCHASE_UNIT_PRICE'],
			];
			$stmt = $this->DBInsert->insert($tbln, $data);
			if(!$stmt['result']){
				$error++;
				throw new Exception("Error inserting purchase product ".$product['PRO_CODE']."because of - ".$stmt['message']); // التحقق من البيانات النصية
			}
		}
		if(!$error){
			return true;
		}
	}
	
	// دالة لإدخال أمر الشراء في جدول `purchases_orders`
	public function insertOrder($orderBy, $status, $notes, $products) {
		include_once(ABSPATH.'/inc/Databases/TableInserterDAO.php');
		$this->DBInsert  = new TableInserter($pdo);
		$tbln 	= $this->DBTables->getTableName('SUPPLY_ORDERS');
		$fields = $this->DBTables->getTableFields('SUPPLY_ORDERS');
		$data = [
			$fields['NAME']['ORDER_BY']		=> $supplierId,
			$fields['NAME']['STATUS']		=> $reference,
			$fields['NAME']['NOTES'] 		=> $total_price,
	    ];
		$stmt = $this->DBInsert->insert($tbln, $data);
		if(!$stmt['result']){
			throw new Exception("Supply order is not confirmed");
		}
		// إرجاع آخر ID تم إدخاله
		$orderId  = $stmt['insertedId'];
		$response = $this->insertProducts($orderId,$products);
		if($response){
			return $orderId;
		}else{
			throw new Exception("Purchase order is not confirmed");
		}
	}
	
	// داله لتحديث بينات امر الشراء فى جدول purchases_orders
	public function updateOrder(
	    $orderId,
	    $supplierId = null,
	    $reference = null,
	    $total_Price = null,
	    $total_payments = null,
	    $statuse = null,
	    $note = null
	) {
		include_once(ABSPATH.'/inc/Databases/TableUpdaterDAO.php');
		$this->DBupdate  = new TableUpdater($pdo);
	    $tbln = $this->DBTables->getTableName('purchases_orders');
	    $fields = $this->DBTables->getTableFields('purchases_orders');
	
	    // التأكد من وجود رقم الطلب
	    if (!$orderId) {
	        throw new Exception("Purchase order number is not set");
	    }
	    
	    // تجهيز بيانات التحديث فقط إذا كانت القيمة موجودة
	    $data = [];
	    if ($supplierId !== null) $data[$fields['NAME']['SUPPLIER_ID']] = $supplierId;
	    if ($reference !== null) $data[$fields['NAME']['REFERENCE']] = $reference;
	    if ($total_Price !== null) $data[$fields['NAME']['TOTAL_PRICE']] = $total_Price;
	    if ($total_payments !== null) $data[$fields['NAME']['TOTAL_PAYMENTS']] = $total_payments;
	    if ($statuse !== null) $data[$fields['NAME']['STATUSE']] = $statuse;
	    if ($note !== null) $data[$fields['NAME']['NOTE']] = $note;
	
	    // التأكد من أن هناك بيانات للتحديث
	    if (empty($data)) {
	        throw new Exception("No data provided for update.");
	    }
	
	    // شرط التنفيذ
	    $where = ['ORDER_ID = :ORDER_ID', [':ORDER_ID' => $orderId]];
	
	    // استدعاء الدالة
	    $result = $this->update($tbln, $data, $where);
	
	    // طباعة النتيجة
	    if (!$result['result']) {
	        throw new Exception("Failed to update purchase order: " . $result['message']);
	    }
	
	    return $result;
	}
	
	// دالة لتحديث بيانات المنتجات المرتبطة بأمر الشراء في جدول purchases_products
	public function updateRecProducts(array $data) {
	    include_once(ABSPATH.'/inc/Databases/TableUpdaterDAO.php');
		$this->DBupdate  = new TableUpdater($pdo);
		
	    // اسم الجدول
	    $tblName = $this->DBTables->getTableName('purchases_products');
	    // حقول الجدول
	    $fields = $this->DBTables->getTableFields('purchases_products');
	
	    // التحقق من الحقول
	    foreach ($data as $row) {
	        foreach ($row as $field => $value) {
	            if (!array_key_exists($field, $fields)) {
	                throw new Exception("Error: Update field ($field) is invalid");
	            }
	        }
	    }
	
	    // تحديث البيانات
	    foreach ($data as $row) {
	        $orderId = $row['ORDER_ID'] ?? null;
	        $productCode = $row['PRO_CODE'] ?? null;
	        $qtySupplied = intval($row['QTY_SUPPLIED']) ?? null;
	
	        // التحقق من القيم
	        if (!$orderId || !$productCode || !$qtySupplied) {
	            throw new Exception("Error: Missing required fields (ORDER_ID, PRO_CODE, QTY_SUPPLIED)");
	        }
	
	        // بيانات التحديث
	        $updateData = [
	            $fields['NAME']['QTY_SUPPLIED'] => $fields['NAME']['QTY_SUPPLIED'].' + :QTY_SUPPLIED '
	        ];
	        // الشروط
	        $where = [
	        	$fields['NAME']["ORDER_ID"].' = :'.$fields['NAME']['ORDER_ID'].' AND '.$fields['NAME']['PRO_CODE'].' = :'.$fields['NAME']['PRO_CODE'].' AND '.$fields['NAME']['QTY'].' >= ('.$fields['NAME']['QTY_SUPPLIED'].' + :'.$fields['NAME']['QTY_SUPPLIED'].') ',
				 [
		            ':'.$fields['NAME']["ORDER_ID"] 	=> $orderId,
		            ':'.$fields['NAME']['PRO_CODE'] 	=> $productCode,
		            ':'.$fields['NAME']['QTY_SUPPLIED'] => $qtySupplied
	        	]
	        ];
	        // استدعاء دالة التحديث
	        $result = $this->DBupdate->update($tblName, $updateData, $where);
	
	        // التحقق من النتيجة
	        if (!$result['result']) {
	            throw new Exception("Failed to update product (ORDER_ID: $orderId, PRO_CODE: $productCode): " . $result['message']);
	        }
	    }
	
	    return ['s',$result['message'],[]];
	}
	
	// دالة لعرض اوامر الشراء فى جدول DataTables
    public function getPurchaseData($request,$where=null,$Datatable=true){
	    // تعريف الجدول والحقول
	    $tableName = $this->DBTables->getTableName('purchases_orders_view');
	    $fields = $this->DBTables->getTableFields('purchases_orders_view');
	    $selectedFields = implode(", ", array_column($fields, 'NAME'));

	    // استعلامات SQL الأساسية
	    $query 			  = "SELECT * FROM $tableName";
	    $queryCount 	  = "SELECT COUNT(*) AS TROW FROM $tableName ";

	    // معالجة WHERE و ORDER BY و LIMIT
	    $whereClause	  = $this->Where($request, $fields, $where,  true);
	    $totalWhereClause = $this->Where($request, $fields, $where, false);
	    $orderBy 		  = $this->orderby($request);
	    $limit 			  = $this->limit($request);
	    
	    // تنفيذ استعلام البيانات
	    $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
	    $stmt =  $this->pdo->prepare($query . $whereClause['where'] . $orderBy . $limit);
	    $stmt->execute($whereClause['params']);
	    $records = $this->DPro->fetchDataTypeDetection($stmt, $fields);
		$stmt->closeCursor();
	    
	    // تنفيذ استعلام العدد الإجمالي
	    $stmtTotal =  $this->pdo->prepare($queryCount . $totalWhereClause['where']);
	    $stmtTotal->execute($totalWhereClause['params']);
	    $totalRows = $stmtTotal->fetch(PDO::FETCH_ASSOC);
		$stmtTotal->closeCursor();
	    
	    // تجهيز البيانات للعرض
	    return [
	        "draw" => intval(isset($_POST["draw"]) ? $_POST["draw"]  : 0),
	        "iTotalRecords" => count($records),
	        "iTotalDisplayRecords" => 1,//intval($totalRows["TROW"] ?? 0),
	        "data" => $records ?: []
	    ];
	}
}

?>