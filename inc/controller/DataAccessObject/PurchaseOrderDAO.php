<?php
require_once ABSPATH . '/inc/Classes/Accounting/SupplierAccountingManager.php';
include_once(ABSPATH.'/inc/configDateTimeFN.php');
include_once(ABSPATH.'/inc/Databases/DBSchema.php');
include_once(ABSPATH.'/inc/Databases/DataProcessor.php');
include_once(ABSPATH.'/inc/Databases/TableInserterDAO.php');
include_once(ABSPATH.'/inc/Databases/TableUpdaterDAO.php');
include_once(ABSPATH.'/inc/Databases/TableDeleterDAO.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
class PurchaseOrderDAO {
	public $pdo; // خاصية لتمثيل اتصال PDO مع قاعدة البيانات
	private $DPro;
	private $DBTables;
	private $DBInsert;
	private $DBupdate;
	private $finance;
	private $dtz;
	// الباني (Constructor) لتلقي اتصال قاعدة البيانات عند إنشاء الكائن
	public function __construct($pdo) {
		$this->pdo = $pdo; // تخزين اتصال PDO في الخاصية $pdo
		$this->DPro = new DataProcessor();
		$this->DBTables = DBSchema::getInstance();
		$this->DBInsert = new TableInserter($pdo);
		$this->DBupdate = new TableUpdater($pdo);
		$this->finance  = new finance_BE($pdo);
		$this->dtz = new configDateTime();
	}
	//===============================================================================≠================
	// Assistant function
	//================================================================================================
	//داله لتحديد عدد الصفوف المعروضه فى DataTables لقائمة اوامر الشراء
	private function PurchaseData_limit (&$request) {
		// إنشاء جملة  limit  لاستعلام SQL لمعالجة البيانات من جانب الخادم
		$limit = '';
		if (isset($request['start']) && $request['length'] != -1) {
			$limit = " LIMIT ".intval($request['start']).", ".intval($request['length']." ");
		}
		return $limit;
	}
	// دالة لترتيب نتيجة الاستعلم لقائمة الشراء لعرضها فى DataTables
	private function PurchaseData_orderby (&$request) {
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
		if (isset($request['order'])) {
			$order = ' ORDER BY ORDER_ID  '.strtoupper($request['order']['0']['dir']);
		}
		return $order;
	}
	// دالة لتحديد شروط عرض البيانات لقئمة اوامر الشراء المعروضه فى DataTables
	private function PurchaseData_Where(&$request, $fields, $globalWhere = [], $bool = true) {
		$globalSearch = [];
		$params = []; // لتخزين القيم المرتبطة بالاستعلام
		// ====================== Tab Conditions ==========================
		if (isset($request['tab'])) {
			switch ($request['tab']) {
				case "OLCompleted":
					$globalWhere[] = $fields['SUPPLIED_STATUSE']." = 'مكتمل'";
					break;
				case "OLUnderReceipt":
					$globalWhere[] = $fields['SUPPLIED_STATUSE']." IN ('جزئي','قيد التوريد')";
					break;
				case "OLReturnPurchase":
					$globalWhere[] = $fields['RETURN_STATUS']." = 1";
					break;
					
				default:
					$globalWhere[] = $fields['SUPPLIED_STATUSE']." IN ('جزئي','قيد التوريد')";
					break;
			}
		} else {
			$globalWhere[] = $fields['SUPPLIED_STATUSE']." IN ('جزئي','قيد التوريد')";
		}

		// ====================== Global Search ==========================

		if (isset($request['search']['value']) && $bool && $request['search']['value'] !='') {
			$str = $this->DPro->sanitizeInput($request['search']['value']);
			$globalSearch[] = $fields['SUP_NAME']. ' LIKE "%'.$str.'%"';
			$globalSearch[] = $fields['CREATE_AT'].' LIKE "%'.$str.'%"';
			$globalSearch[] = $fields['NOTE']. ' LIKE "%'.$str.'%"';
			
		}

		// ====================== Combine where ==========================
		$where = '';
		$search = '';
		if (count($globalWhere)) {
			$where = implode(' AND ', $globalWhere);
		}
		
		if ($where !== '') {
			$where = ' WHERE ' . $where;
		}
		
		if (count($globalSearch)) {
			$searchClause = '(' . implode(' OR ', $globalSearch) . ')';
			$where = $where === '' ? 'WHERE '.$searchClause : $where.' AND (' . $searchClause . ')';
		}
		return ['where' => $where];
	}
	// دالة لإدخال المنتجات المرتبطة بأمر الشراء في جدول `purchases_products`
	private function insertProducts($orderId, $products) {
		$tbln = $this->DBTables->getTableName('purchases_products');
		$fields = $this->DBTables->getTableFieldsName('purchases_products');
		$error = 0;
		foreach ($products as $product) {
			$data = [
				$fields['ORDER_ID'] => $orderId,
				$fields['PRO_CODE'] => $product['PRO_CODE'],
				$fields['UNIT_PRICE'] => $product['PURCHASE_UNIT_PRICE'],
				$fields['QTY'] => $product['QTY'],
				$fields['QTY_SUPPLIED'] => $product['QTY_SUPPLIED']
			];
			$stmt = $this->DBInsert->insert($tbln, $data);
			if (!$stmt['result']) {
				$error++;
				throw new Exception("Error inserting purchase product ".$product['PRO_CODE']."because of - ".$stmt['message']); // التحقق من البيانات النصية
			}
		}
		if (!$error) {
			return true;
		}
	}
	// دالة لإدخال المنتجات المرتبطة بأمر الشراء في جدول `purchases_products`
	private function insertProducts_new($orderId, $products) {
		$tbln = $this->DBTables->getTableName('purchases_products');
		$fields = $this->DBTables->getTableFieldsName('purchases_products');
		$error = 0;
		foreach ($products as $product) {
			$data = [
				$fields['ORDER_ID'] => $orderId,
				$fields['PRO_CODE'] => $product['PRO_CODE'],
				$fields['UNIT_PRICE'] => $product['PURCHASE_UNIT_PRICE'],
				$fields['QTY'] => $product['QTY'],
				$fields['QTY_SUPPLIED'] => $product['QTY_SUPPLIED']
			];
			$stmt = $this->DBInsert->insert($tbln, $data);
			if (!$stmt['result']) {
				$error++;
				throw new Exception("Error inserting purchase product ".$product['PRO_CODE']."because of - ".$stmt['message']); // التحقق من البيانات النصية
			}
		}
		if (!$error) {
			return true;
		}
	}
	// دالة لإدخال المنتجات المرتبطة بأمر الشراء في جدول `purchases_return_products`
	private function insertReturnProducts($orderId, $products) {
		$tbln = $this->DBTables->getTableName('purchases_return_products');
		$fields = $this->DBTables->getTableFieldsName('purchases_return_products');
		$error = 0;
		foreach ($products as $product) {
			$data = [
				$fields['ORDER_ID'] => $orderId,
				$fields['PRO_CODE'] => $product['PRO_CODE'],
				$fields['UNIT_PRICE'] => $product['PURCHASE_UNIT_PRICE'],
				$fields['QTY_SUPPLIED'] => $product['QTY_SUPPLIED'],
				$fields['QTY_RETURN'] => $product['QTY_RETURN'],
				
			];
			
			$stmt = $this->DBInsert->insert($tbln, $data);
			if (!$stmt['result']) {
				$error++;
				throw new Exception("Error inserting purchase product ".$product['PRO_CODE']."because of - ".$stmt['message']); // التحقق من البيانات النصية
			}
		}
		if (!$error) {
			return true;
		}
	}
	//===============================================================================≠================
	// Main-functions / Basic function
	//================================================================================================
	// دالة لعرض اوامر الشراء فى جدول DataTables
	public function getPurchaseData($request, $where = null, $Datatable = true) {
		// تعريف الجدول والحقول
		$tableName = $this->DBTables->getTableName('purchases_orders_view');
		$fieldsName = $this->DBTables->getTableFieldsName('purchases_orders_view');
		$fieldsType = $this->DBTables->getFieldsType('purchases_orders_view');
		$selectedFields = implode(", ", $fieldsName);

		// استعلامات SQL الأساسية
		$query = "SELECT * FROM $tableName";
		$queryCount = "SELECT COUNT(*) AS TROW FROM $tableName ";

		// معالجة WHERE و ORDER BY و LIMIT
		$whereClause = $this->PurchaseData_Where($request, $fieldsName, $where, true);
		$totalWhereClause = $this->PurchaseData_Where($request, $fieldsName, $where, false);
		$orderBy = $this->PurchaseData_orderby($request);
		$limit = $this->PurchaseData_limit($request);

		// تنفيذ استعلام البيانات
		$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		$stmt = $this->pdo->prepare($query . $whereClause['where'] . $orderBy . $limit);
		$stmt->execute();
		$records = $this->DPro->fetchDataTypeDetection($stmt, $fieldsName, $fieldsType);
		$stmt->closeCursor();

		// تنفيذ استعلام العدد الإجمالي
		$stmtTotal = $this->pdo->prepare($queryCount . $totalWhereClause['where']);
		$stmtTotal->execute();
		$totalRows = $stmtTotal->fetch(PDO::FETCH_ASSOC);
		//$totalRows = $totalRows['trow'];
		$stmtTotal->closeCursor();

		// تجهيز البيانات للعرض
		return [
			"draw" => intval(isset($_POST["draw"]) ? $_POST["draw"]  : 0),
			"iTotalRecords" => count($records),
			"iTotalDisplayRecords" =>  $totalRows["TROW"],
			"data" => $records ?: []
		];
	}
	// دالة لإدخال أمر الشراء في جدول `purchases_orders`
	public function insertOrder($supplierId, $reference, $total_price, $total_payments, $status, $note, $products) {
		// إيقاف تسجيل الدفعة الأولية مؤقتًا حتى يُعتمد مسارها المحاسبي في GL.
		if ((float)$total_payments > 0) {
			throw new Exception('لا يمكن تسجيل دفعة أولية مع أمر الشراء حاليًا. سجّل أمر الشراء بدون دفعة، ثم سجّل الصرف من شاشة صرف مستحقات المورد.');
		}
		$tbln = $this->DBTables->getTableName('purchases_orders');
		$fields = $this->DBTables->getTableFieldsName('purchases_orders');

		$data = [
			$fields['SUPPLIER_ID'] => $supplierId,
			$fields['REFERENCE'] => $reference,
			$fields['TOTAL_PRICE'] => $total_price,
			$fields['TOTAL_PAYMENTS'] => $total_payments,
			$fields['STATUSE'] => $status, // enum('DEFERRED', 'COMPLETED')
			$fields['NOTE'] => $note
		];
		$stmt = $this->DBInsert->insert($tbln, $data);
		if (!$stmt['result']) {
			throw new Exception("Purchase order is not confirmed");
		}
		
		// إرجاع آخر ID تم إدخاله
		$orderId  = $stmt['insertedId'];
		$response = $this->insertProducts($orderId, $products);
		if (!$orderId || !$response) {
			throw new Exception("purchases products not confirmed");
		}
		$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
		$userId = (int)($_SESSION['USER_ID'] ?? 0);
		(new SupplierAccountingManager($this->pdo))->recordPurchaseOrder((int)$orderId, (int)$supplierId, $branchId, $userId);
		
		return $orderId;
	}
	// دالة لإدخال أمر الشراء في جدول `purchases_return_orders`
	public function insertReturnOrder($supplierId, $reference, $total_price, $total_payments, $status, $note, $products) {
		$tbln = $this->DBTables->getTableName('purchases_return_orders');
		$fields = $this->DBTables->getTableFieldsName('purchases_return_orders');

		$data = [
			$fields['SUPPLIER_ID'] 	=> $supplierId,
			$fields['REFERENCE'] 	=> $reference,
			$fields['TOTAL_PRICE'] 	=> $total_price,
			$fields['TOTAL_PAYMENTS'] => $total_payments,
			$fields['STATUSE'] 		=> $status,
			// enum('DEFERRED', 'COMPLETED', RETURN)
			$fields['NOTE'] 		=> $note
		];
		$stmt = $this->DBInsert->insert($tbln, $data);
		if (!$stmt['result']) {
			throw new Exception("Return Purchase order is not confirmed");
		}
		// إرجاع آخر ID تم إدخاله
		$orderId = $stmt['insertedId'];
		$response = $this->insertReturnProducts($orderId, $products);

		if (!$orderId || !$response) {
			throw new Exception("Return Purchase product is not confirmed");
		}
		
		// يُسجل تخفيض مستحق المورد بعد اعتماد حركة الصرف المخزني للمرتجع فقط.
		return $orderId;
	}
	// داله لتحديث بينات امر الشراء فى جدول purchases_orders
	public function updateOrder($orderId, $supplierId = null, $reference = null, $total_Price = null, $total_payments = null, $statuse = null, $note = null) {
		$tbln = $this->DBTables->getTableName('purchases_orders');
		$fields = $this->DBTables->getTableFieldsName('purchases_orders');
		// التأكد من وجود رقم الطلب
		if (!$orderId) {
			throw new Exception("Purchase order number is not set");
		}
		
		// تجهيز بيانات التحديث فقط إذا كانت القيمة موجودة
		$data = [];
		if ($supplierId !== null) $data[$fields['SUPPLIER_ID']] = $supplierId;
		if ($reference !== null) $data[$fields['REFERENCE']] = $reference;
		if ($total_Price !== null) $data[$fields['TOTAL_PRICE']] = $total_Price;
		if ($total_payments !== null) $data[$fields['TOTAL_PAYMENTS']] = $total_payments;
		if ($statuse !== null) $data[$fields['STATUSE']] = $statuse;
		if ($note !== null) $data[$fields['NOTE']] = $note;

		// التأكد من أن هناك بيانات للتحديث
		if (empty($data)) {
			throw new Exception("No data provided for update.");
		}

		// شرط التنفيذ
		$where = ['ORDER_ID = :ORDER_ID',
			[':ORDER_ID' => $orderId]];

		// استدعاء الدالة
		$result = $this->DBupdate->update($tbln, $data, $where);

		// طباعة النتيجة
		if (!$result['result']) {
			throw new Exception("Failed to update purchase order: " . $result['message']);
		}

		return $result;
	}
	// دالة لتحديث بيانات المنتجات المرتبطة بأمر الشراء في جدول purchases_products
	public function updateRecProducts(array $data) {
		try {
			include_once(ABSPATH.'/inc/Databases/TableUpdaterDAO.php');
			$this->DBupdate = new TableUpdater($this->pdo);

			// اسم الجدول
			$tblName = $this->DBTables->getTableName('PURCHASES_PRODUCTS');
			// حقول الجدول
			$fields = $this->DBTables->getTableFieldsName('PURCHASES_PRODUCTS');
			// التحقق من الحقول

			foreach ($data as $row) {
				foreach ($row as $field => $value) {
					// تحقق من وجود الحقل في المصفوفة $fields
					if (!array_key_exists($field, $fields)) {
						throw new Exception("Error: Update field ($field) is invalid");
					}
				}
			}

			// تحديث البيانات
			foreach ($data as $row) {
				$orderId = $row['ORDER_ID'] ?? null;
				$productCode = $row['PRO_CODE'] ?? null;
				$qtySupplied = isset($row['QTY_SUPPLIED']) && is_numeric($row['QTY_SUPPLIED'])
					? (float)$row['QTY_SUPPLIED']
					: 0.0;

				// التحقق من القيم مع الحفاظ على الكسور العشرية للكميات.
				if (!$orderId || !$productCode || !is_finite($qtySupplied) || $qtySupplied <= 0) {
					throw new Exception("بيانات الاستلام الجزئي غير مكتملة أو الكمية غير صالحة.");
				}

				// بيانات التحديث
				$updateData = [
					$fields['QTY_SUPPLIED'] => $fields['QTY_SUPPLIED'].' + :QTY_SUPPLIED '
				];
				// الشروط
				$where = [
					$fields["ORDER_ID"].' = :'.$fields['ORDER_ID'].' AND '.$fields['PRO_CODE'].' = :'.$fields['PRO_CODE'].' AND '.$fields['QTY'].' >= ('.$fields['QTY_SUPPLIED'].' + :'.$fields['QTY_SUPPLIED'].') ',
					[
						':'.$fields["ORDER_ID"] => $orderId,
						':'.$fields['PRO_CODE'] => $productCode,
						':'.$fields['QTY_SUPPLIED'] => $qtySupplied
					]
				];

				// استدعاء دالة التحديث
				$result = $this->DBupdate->update($tblName, $updateData, $where);

				// التحقق من النتيجة
				if (!$result['result'] || (int)($result['affectedRows'] ?? 0) !== 1) {
					throw new Exception("تعذر تسجيل الاستلام الجزئي للمنتج $productCode في أمر الشراء $orderId؛ قد تكون الكمية أكبر من المتبقي أو المنتج غير موجود.");
				}
			}

			return $result;
		}catch (Exception $e) {
			throw $e;
		}
	}
}
?>