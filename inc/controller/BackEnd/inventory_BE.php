<?php
include_once(__dir__.'/../../configDateTimeFN.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
include_once(ABSPATH.'/inc/controller/BackEnd/purchases_BE.php');
include_once(ABSPATH.'/inc/Databases/DBSchema.php');
include_once(ABSPATH.'/inc/Databases/DataProcessor.php');
include_once(ABSPATH.'/inc/Databases/TableInserterDAO.php');
include_once(ABSPATH.'/inc/Databases/TableUpdaterDAO.php');
include_once(ABSPATH.'/inc/Databases/TableDeleterDAO.php');
include_once(ABSPATH.'/inc/Classes/InventoryManager.php');
include_once(ABSPATH.'/inc/Classes/Accounting/SupplierAccountingManager.php');


class inventory_BE {
	private $dtz;
	private $conn;
	private $DBTables;
	private $DBInsert;
	private $DBupdate;
	private $DBdelete;
	private $finance;
	private $purchase;
	
	private $InventoryManager;
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
	
	public $rq_parm;
	public $delivery_id;
	public $comment;
	public function __construct ($db) {
		$this->conn 	= $db;
		$this->DPro 	= new DataProcessor();
		$this->DBTables = DBSchema::getInstance();
		$this->DBInsert = new TableInserter($db);
		$this->DBupdate = new TableUpdater($db);
		$this->DBdelete = new TableDeleter($db);
		$this->dtz 		= new configDateTime();
		$this->InventoryManager = new InventoryManager($db);
		
		$this->finance  = new finance_BE($db);
		$this->purchase = new purchases_BE($db);
		    
	}
	private function get_inventory_id($branch_id) {
		$query = 'SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = :branchid ';
		$stStoreid = $this->conn->prepare($query);
		$stStoreid->execute([':branchid' => $branch_id]);
		$result = $stStoreid->fetch(PDO::FETCH_ASSOC);
		return $result['STO_ID'];

	}
	
	function refreshStoreCountingLock(int $storeId): void {
		$this->InventoryManager->refreshStoreCountingLock( $storeId, $_SESSION['counting_token'] );
	}
	
	function unlockStoreAfterCounting(int $storeId): void {
		$this->InventoryManager->unlockStoreAfterCounting($storeId,$_SESSION['counting_token']);
	}
	
	/*public function inventoryMovement ($productArray, $reference, $Partner, $Movement_type, $store_id_dest = null) {
		        
		
		$branch_id 	 	= $_SESSION["BRANCH_ID"];
		$store_id 	 	= self::get_inventory_id($branch_id);
		$user_action 	= $_SESSION["USER_ID"];
		$Partner 	 	= htmlspecialchars(strip_tags(trim($Partner)));
		$reference   	= htmlspecialchars(strip_tags(trim($reference)));
		$response 		= false;
		$IPMT 			= array();
		$resetinvent 	= null ;
		$movStr 		= '';
		$Movement_type 	= strtolower($Movement_type);
		
		if ($Movement_type && count($productArray)>0) {
			/*
			'RECEIVING',
			'SHIPPING',
			'SALES',
			'TRANSFER',
			'SETTLEMENT',
			'REFUND',
			'RETURN_SHIPP',
			'RETURN_SALES',
			'REFUNDPURCHISES'
			*
			
			switch (true) {
				case ($Movement_type === 'receiving') :
					
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = 'استلام المنتجات اوامر الشراء من المورد';
					break;
				case ($Movement_type === 'refund_purchises') :
					
					$IPMT = array(
						
						array(
							'type'=>'out',
							'fromsto'=>$store_id_dest,
							'tosto'=>null
							)
						);
					$movStr = 'صرف مرتجعات اوامر الشراء للمورد';
					break;
				case ($Movement_type === 'refund') :
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = ' استرداد منتجات امر البيع من المندوب';
					break;
				case ($Movement_type === 'return_shipp') :
					
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = ' إستلام المنتجات المرفوضه بأمر البيع من المندوب';
					break;
				case ($Movement_type === 'return_sales') :
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = 'استلام مرتجعات بعد البيع ';
					break;
				case ($Movement_type === 'shipping') :
					$IPMT = array(
						array(
							'type'=>'OUT',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = ' صرف منتجات امر البيع للمندوب';
					break;
				case ($Movement_type === 'sales') :
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id,
							'tosto'=>null
							)
						);
					$movStr = 'صرف منتجات امر البيع المباشر';
					break;
				case ($Movement_type === 'transfer' && $store_id_dest) :
					$IPMT = array(
						array(
							'type'=>'OUT',
							'fromsto'=>$store_id,
							'tosto'=>$store_id_dest
							),
						array(
							'type'=>'in',
							'fromsto'=>$store_id_dest,
							'tosto'=>$store_id
							)
					);
					$movStr = 'تحويل داخلى';
					break;
				case ($Movement_type === 'settlement') :
					$IPMT = array(
						array(
							'type'=>'IN',
							'fromsto'=>$store_id_dest,
							'tosto'=>null
					));
					$resetinvent = 'reset';
					$movStr = 'تسويه مخزنية';
					break;
			}
			
			if (!$resetinvent && count($IPMT)) {
				
				foreach ($IPMT as $value) {
					
					$query2 = "INSERT INTO inventory_movement(
							IM_MOVE_TYPE, 
							IM_USER, 
							IM_BRANCH, 
							STO_ID, 
							STO_DEST, 
							REFERENCE, 
							IM_DETAILS, 
							IM_APPROVAL_DISBURSED, 
							IM_APPROVAL_RECEIVED
							) VALUES (
												:typemove,
												:userid,
												:branchid,
												:stoid,
												:stdest,
												:reference,
												:details,
												1,
												1
											)";
					$details = [$movStr,$Partner,$reference];
					$stInvMov = $this->conn->prepare($query2);
					$stInvMov->bindValue(':typemove', strtoupper($Movement_type), PDO::PARAM_STR);
					$stInvMov->bindValue(':userid', 	$user_action, 		PDO::PARAM_INT);
					$stInvMov->bindValue(':branchid',   $branch_id, 		PDO::PARAM_INT);
					$stInvMov->bindValue(':stoid',  	$value['fromsto'], 	PDO::PARAM_INT);
					$stInvMov->bindValue(':stdest', 	$value['tosto'],    PDO::PARAM_INT);
					$stInvMov->bindValue(':reference', 	$reference,    		PDO::PARAM_INT);
					$stInvMov->bindValue(':details', json_encode($details), PDO::PARAM_INT);
					if ($stInvMov->execute()) {
						$imid = (int) $this->conn->lastInsertId();
						//==========insert inventory_product_movement==========

						// تهيئة المتغيرات
						$errinsert = 0;
						$batchSize = 50;
						
						// استعلام مسبق لجلب كميات المخزون لكل منتج من المخزن المحدد
						$storeQuantities = [];
						foreach ($productArray as $product) {
						    $code = $product['PRO_CODE'];
						
						    // جلب الكمية من جدول المخزن
						    $qtyStmt = $this->conn->prepare('SELECT STO_QTY FROM stores_product WHERE STO_ID = :sto_id AND PRO_CODE = :code');
						    $qtyStmt->bindValue(':sto_id', $value['fromsto'], PDO::PARAM_INT);
						    $qtyStmt->bindValue(':code', $code);
						    $qtyStmt->execute();
						    
						    $result = $qtyStmt->fetch(PDO::FETCH_ASSOC);
						    $storeQuantities[$code] = $result ? $result['STO_QTY'] : 0; // في حالة عدم وجود المنتج نعتبر الكمية صفر
						}
						
						// تجهيز استعلام الإدخال
						$query3 = 'INSERT INTO `inventory_product_movement` (`IM_ID`, `IPM_TYPE_MOV`, `IPM_CODE`, `IPM_STO_QTY`, `IPM_QTY`) VALUES ';
						
						// تقسيم البيانات إلى دفعات كل 50 منتج
						for ($i = 0; $i < count($productArray); $i += $batchSize) {
						    $placeholders = [];
						    $bindValues = [];
						
						    for ($j = $i; $j < $i + $batchSize && $j < count($productArray); $j++) {
						        $product = $productArray[$j];
						        $code = $product['PRO_CODE'];
						        $quantity = $product['QTY'];
						        $sto_qty = $storeQuantities[$code];
						
						        // لكل صف يتم تكوين مجموعة placeholders (?, ?, ?, ?, ?)
						        $placeholders[] = '(?, ?, ?, ?, ?)';
						
						        // جمع القيم بالترتيب
						        array_push($bindValues, $imid, $value['type'], $code, $sto_qty, $quantity);
						    }
						
						    // دمج الـ placeholders وتنفيذ الاستعلام
						    $fullQuery = $query3 . implode(', ', $placeholders);
						    $stmt = $this->conn->prepare($fullQuery);
						    $stmt->execute($bindValues);
						
						    // التحقق من نجاح الإدخال
						    if (!$stmt->rowCount()) {
						        $errinsert++;
						    }
						}
						
						$query3 = 'INSERT INTO `inventory_product_movement` (`IM_ID`, `ORDER_ID`, `IPM_CODE`, `IPM_TYPE_MOV`, `IPM_QTY`, `IPM_STO_QTY`) ';
						$errinsert = 0;
						// تحويل البيانات إلى جمل استعلام MySQL لكل 50 عنصر
						for ($i = 0; $i < count($productArray); $i += 50) {
							$valueArr = array();
							// حلقة لإضافة العناصر ضمن حدود 50 عنصر
							for ($j = $i; $j < $i + 50 && $j < count($productArray); $j++) {
								$code = $productArray[$j]['PRO_CODE'];
								$quantity = $productArray[$j]['QTY'];
								$subquery = 'SELECT STO_QTY FROM stores_product WHERE STO_ID = '.$value['fromsto'].'  AND PRO_CODE = '.$code;
								$valueArr[] = "(".implode(',',array($imid, $reference,"'".$code."'","'".$value['type']."'","'".$quantity."'","(".$subquery.")")) .")";
							}
							
							$valuequery = ' VALUES '.implode(',', $valueArr).';';
							
							$stInvPMov = $this->conn->prepare($query3 . $valuequery);
							
							$stInvPMov->execute();
							if (!$stInvPMov->rowCount()) {
								$errinsert++;
							}
						}
						if ($errinsert == 0) {
						
							$query1 = "UPDATE 
							               stores_product STP 
							            LEFT JOIN (
											SELECT IM.STO_ID,
											 IPM.IPM_CODE,
											 Sum(CASE
											    WHEN IPM.IPM_TYPE_MOV = 'IN'  THEN IPM.IPM_QTY
											    WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN-IPM.IPM_QTY
											    ELSE 0
											   END) AS TQTY
											FROM  inventory_product_movement AS IPM
											 LEFT JOIN inventory_movement AS IM ON IPM.IM_ID = IM.IM_ID
											 LEFT JOIN stores_product AS SP ON IM.STO_ID = SP.STO_ID AND SP.PRO_CODE = IPM.IPM_CODE
											WHERE IPM.CREATE_AT > SP.LAST_UPDATE
											GROUP BY IM.STO_ID,
											 IPM.IPM_CODE) AS VSP ON STP.STO_ID = VSP.STO_ID AND STP.PRO_CODE = VSP.IPM_CODE
										
										SET STP.STO_QTY = STP.STO_QTY + VSP.TQTY,
										 STP.LAST_UPDATE = NOW(6)
										WHERE  VSP.STO_ID IS NOT NULL AND VSP.IPM_CODE IS NOT NULL";
							$stmt1 = $this->conn->prepare($query1);
							
							if ($stmt1->execute() && $stmt1->rowCount() && $errinsert === 0) {
								$queryUpMovPro = "UPDATE inventory_product_movement 
											    SET IS_PROCESSED = TRUE 
											    WHERE IS_PROCESSED = FALSE";
								$stmtx2 = $this->conn->prepare($queryUpMovPro);
								if ($stmt1->execute() && $stmt1->rowCount() && $errinsert === 0) {
									$response = true;
								}
							}
						}
					}
				}
			} else {
				foreach ($IPMT as $value) {
				
				//'RECEIVING','SHIPPING','SALES','TRANSFER','SETTLEMENT','REFUND','RETURN_SHIPP','RETURN_SALES'
				
					$query2 = "INSERT INTO `inventory_movement`(`IM_MOVE_TYPE`, `IM_USER`, `IM_BRANCH`, `STO_ID`, `STO_DEST`, `IM_DETAILS`, `IM_APPROVAL_DISBURSED`, `IM_APPROVAL_RECEIVED`) VALUES
											(
												:typemove,
												:userid,
												:branchid,
												:stoid,
												:stdest,
												:details,
												1,
												1
											)";
					$Movement_type = 'SETTLEMENT' ;
					$details = [$movStr.$Partner,	$reference];
					$stInvMov = $this->conn->prepare($query2);
					$stInvMov->bindValue(':typemove', strtoupper($Movement_type), PDO::PARAM_STR);
					$stInvMov->bindValue(':userid', 	$user_action, 		PDO::PARAM_INT);
					$stInvMov->bindValue(':branchid',   $branch_id, 		PDO::PARAM_INT);
					$stInvMov->bindValue(':stoid',  	$value['fromsto'], 	PDO::PARAM_INT);
					$stInvMov->bindValue(':stdest', 	$value['tosto'],    PDO::PARAM_INT);
					$stInvMov->bindValue(':details', json_encode($details), PDO::PARAM_INT);
					if ($stInvMov->execute()) {
						$imid = (int) $this->conn->lastInsertId();
						//==========insert inventory_product_movement==========
						$query3 = 'INSERT INTO `inventory_product_movement` (`IM_ID`, IPM_TYPE_MOV,`IPM_CODE`,`IPM_STO_QTY`,`IPM_QTY`) ';
						$errinsert = 0;
						// تحويل البيانات إلى جمل استعلام MySQL لكل 50 عنصر
						for ($i = 0; $i < count($productArray); $i += 50) {
							$valueArr = array();
							// حلقة لإضافة العناصر ضمن حدود 50 عنصر
							for ($j = $i; $j < $i + 50 && $j < count($productArray); $j++) {
								$typemoverest = 'IN';
								$code = $productArray[$j]['PRO_CODE'];
								$quantity = $productArray[$j]['QTY'];
								if($quantity < 0 ){
									$typemoverest = 'OUT';
									$quantity = abs($quantity);
								}
								$subquery = 'SELECT STO_QTY FROM stores_product WHERE STO_ID = '.$value['fromsto'].'  AND PRO_CODE = '.$code;
								$valueArr[] = "(".implode(',',array($imid,"'".$typemoverest."'","'".$code."'","(".$subquery.")","'".$quantity."'")) .")";
							}
							
							$valuequery = ' VALUES '.implode(',', $valueArr).';';
							$stInvPMov = $this->conn->prepare($query3 . $valuequery);
							$stInvPMov->execute();
							if (!$stInvPMov->rowCount()) {
								$errinsert++;
							}
						}
						if ($errinsert == 0) {
							
							$query1 = "UPDATE stores_product STP LEFT JOIN (
											SELECT IM.STO_ID,
											 IPM.IPM_CODE,
											 Sum(CASE
											    WHEN IPM.IPM_TYPE_MOV = 'IN'  THEN IPM.IPM_QTY
											    WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN-IPM.IPM_QTY
											    ELSE 0
											   END) AS TQTY
											FROM  inventory_product_movement AS IPM
											 LEFT JOIN inventory_movement AS IM ON IPM.IM_ID = IM.IM_ID
											 LEFT JOIN stores_product AS SP ON IM.STO_ID = SP.STO_ID AND SP.PRO_CODE = IPM.IPM_CODE
											WHERE IPM.CREATE_AT > SP.LAST_UPDATE
											GROUP BY IM.STO_ID,
											 IPM.IPM_CODE) AS VSP ON STP.STO_ID = VSP.STO_ID AND STP.PRO_CODE = VSP.IPM_CODE
										SET STP.STO_QTY = STP.STO_QTY + VSP.TQTY,
										 STP.LAST_UPDATE = NOW(6)
										WHERE  VSP.STO_ID IS NOT NULL AND VSP.IPM_CODE IS NOT NULL";
							$stmt1 = $this->conn->prepare($query1);
							if ($stmt1->execute() && $stmt1->rowCount() && $errinsert ===0) {
								$response = true;
							}
						}
					}
				}
			}
		}
	
		return $response;
	}*/

	public function AddStock($supplierId, $reference, $comment, $products , $receivedProduct = true,$storeId = null) {
		
		$branch_id 	 = $_SESSION["BRANCH_ID"];
		$userActionId =  $_SESSION["USER_ID"];
		$store_id 	 = !$storeId ? $this->get_inventory_id($branch_id) : $storeId;
	    $startpoint = 'spSTAR1001';
	    $totalPrice = 0;
	    foreach ($products as $pro) {
	    	$totalPrice += ($pro['PURCHASE_UNIT_PRICE'] * $pro['QTY']);
	    }
		try {
		    $this->conn->beginTransaction();
		    		
		    $this->conn->exec("SET innodb_lock_wait_timeout = 0");
		    		
		    if ($totalPrice === 0) {
		        throw new Exception("لا يمكن حفظ أمر الشراء بدون سعر الشراء.");
		    }
		
		    $resA = $this->purchase->addPurchaseOrder($supplierId, $reference, $totalPrice, 0, 'DEFERRED', $comment, $products, true);
		    if (!$resA) {
		        throw new Exception("لم يتم تأكيد حفظ أمر الشراء.");
		    }
		    // إثبات كامل مديونية أمر الشراء في GL قبل إعادة تصنيف الجزء المستلم للمخزون.
		    (new SupplierAccountingManager($this->conn))->recordPurchaseOrder(
		        (int)$resA, (int)$supplierId, (int)$branch_id, (int)$userActionId
		    );
		    $movementUniqueId = 'PURCHASE_RECEIPT_' . bin2hex(random_bytes(16));
		    $this->InventoryManager->processInventory('receiving', [$resA], $userActionId, $branch_id, $movementUniqueId, $store_id);
		    (new SupplierAccountingManager($this->conn))->recordReceiptByMovementUniqueId($movementUniqueId);
			//processPurchaseOrder('receiving', $resA, $userActionId, $branch_id, );     
		    		
		    $this->conn->commit();
		    return array('success', 'نجح', 'تم الحفظ بنجاح', []);
		        
		} catch (PDOException $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
		    if ($this->conn->inTransaction()){
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
	}
	
	public function returnPurchisesFormStock($stoid ,$supplierId, $reference, $comment, $products , $deliveryProduct = true) {
		$store_id = (int)$stoid;
		$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
		$userActionId = (int)($_SESSION['USER_ID'] ?? 0);

		try {
			if ($store_id <= 0 || (int)$supplierId <= 0 || (int)$reference <= 0 || $branchId <= 0 || $userActionId <= 0) {
				throw new Exception("بيانات المورد أو أمر الشراء أو المخزن غير صحيحة.");
			}
			if (!is_array($products) || !$products) {
				throw new Exception("لا توجد منتجات محددة في مرتجع المورد.");
			}

			$this->conn->beginTransaction();
			$this->conn->exec("SET innodb_lock_wait_timeout = 0");

			// منع تنفيذ مرتجع من مخزن تابع لفرع آخر.
			$storeStmt = $this->conn->prepare("SELECT STO_ID FROM stores WHERE STO_ID = ? AND BRANCH_ID = ? FOR UPDATE");
			$storeStmt->execute([$store_id, $branchId]);
			if (!$storeStmt->fetchColumn()) {
				throw new Exception("المخزن المحدد غير تابع للفرع الحالي.");
			}

			// قفل أمر الشراء الأصلي حتى لا يتجاوز مرتجعان متزامنان الكمية المستلمة.
			$sourceStmt = $this->conn->prepare("
				SELECT ORDER_ID, SUPPLIER_ID
				FROM purchases_orders
				WHERE ORDER_ID = ? AND SUPPLIER_ID = ?
				FOR UPDATE
			");
			$sourceStmt->execute([(int)$reference, (int)$supplierId]);
			if (!$sourceStmt->fetch(PDO::FETCH_ASSOC)) {
				throw new Exception("أمر الشراء المحدد غير موجود أو لا يتبع المورد المختار.");
			}

			// اعتماد الكميات والأسعار من أمر الشراء الأصلي، وعدم الثقة في قيم الواجهة.
			$sourceProductsStmt = $this->conn->prepare("
				SELECT PRO_CODE, PURCHASE_UNIT_PRICE, QTY_SUPPLIED
				FROM purchases_products
				WHERE ORDER_ID = ?
				FOR UPDATE
			");
			$sourceProductsStmt->execute([(int)$reference]);
			$sourceProducts = [];
			foreach ($sourceProductsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$sourceProducts[(string)$row['PRO_CODE']] = $row;
			}
			if (!$sourceProducts) {
				throw new Exception("أمر الشراء الأصلي لا يحتوي على منتجات مستلمة.");
			}

			// احتساب المرتجعات السابقة من حركات الصرف الفعلية فقط؛ طلبات المرتجع غير المصروفة لا تخصم من المتاح.
			$returnedStmt = $this->conn->prepare("
				SELECT IPM.IPM_CODE, COALESCE(SUM(IPM.IPM_QTY), 0) AS RETURNED_QTY
				FROM inventory_movement IM
				INNER JOIN inventory_product_movement IPM ON IPM.IM_ID = IM.IM_ID
				INNER JOIN purchases_return_orders PRO ON PRO.ORDER_ID = IM.REFERENCE
				WHERE PRO.REFERANC = ?
				  AND PRO.SUPPLIER_ID = ?
				  AND LOWER(IM.IM_MOVE_TYPE) = 'refundpurchises'
				  AND IPM.IPM_TYPE_MOV = 'OUT'
				GROUP BY IPM.IPM_CODE
			");
			$returnedStmt->execute([(int)$reference, (int)$supplierId]);
			$alreadyReturned = [];
			foreach ($returnedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$alreadyReturned[(string)$row['IPM_CODE']] = (float)$row['RETURNED_QTY'];
			}

			$normalizedProducts = [];
			$productCodes = [];
			$totalPrice = 0.0;
			foreach ($products as $product) {
				$code = isset($product['PRO_CODE']) ? trim((string)$product['PRO_CODE']) : '';
				$requestedQty = $product['QTY_RETURN'] ?? $product['QTY'] ?? null;
				if ($code === '' || !isset($sourceProducts[$code]) || !is_numeric($requestedQty)) {
					throw new Exception("بيانات أحد منتجات المرتجع غير صحيحة أو المنتج غير تابع لأمر الشراء.");
				}
				$qty = (float)$requestedQty;
				if (!is_finite($qty) || $qty <= 0 || isset($productCodes[$code])) {
					throw new Exception("كمية مرتجع المنتج $code غير صالحة أو المنتج مكرر.");
				}
				$source = $sourceProducts[$code];
				$receivedQty = (float)$source['QTY_SUPPLIED'];
				$remainingReturnQty = $receivedQty - (float)($alreadyReturned[$code] ?? 0);
				if ($qty - $remainingReturnQty > 0.00001) {
					throw new Exception("كمية مرتجع المنتج $code أكبر من الكمية المستلمة والمتبقية للمرتجع.");
				}
				$unitPrice = (float)$source['PURCHASE_UNIT_PRICE'];
				if (!is_finite($unitPrice) || $unitPrice <= 0) {
					throw new Exception("سعر شراء المنتج $code غير صالح في أمر الشراء الأصلي.");
				}
				$productCodes[$code] = true;
				$totalPrice += $unitPrice * $qty;
				$normalizedProducts[] = [
					'PRO_CODE' => $code,
					'PURCHASE_UNIT_PRICE' => $unitPrice,
					'QTY_SUPPLIED' => $receivedQty,
					'QTY_RETURN' => $qty
				];
			}
			if (!is_finite($totalPrice) || $totalPrice <= 0) {
				throw new Exception("قيمة مرتجع المورد غير صالحة.");
			}

			$resA = $this->purchase->returnPurchaseOrder(
				(int)$supplierId,
				(int)$reference,
				round($totalPrice, 2),
				0,
				'DEFERRED',
				'',
				$normalizedProducts,
				true
			);
			if (!$resA) {
				throw new Exception("لم يتم تأكيد حفظ مرتجع أمر الشراء.");
			}

			$resB2 = $this->finance->Add_invoice($resA, 'PURCHASES', round($totalPrice, 2), 0, 0, 0);
			if (!$resB2) {
				throw new Exception("لم يتم تأكيد حفظ فاتورة مرتجع الشراء.");
			}

			if ($deliveryProduct) {
				$movementUniqueId = 'PURCHASE_RETURN_' . bin2hex(random_bytes(16));
				$this->InventoryManager->processInventory('refundpurchises', [$resA], $userActionId, $branchId, $movementUniqueId, $store_id);
				(new SupplierAccountingManager($this->conn))->recordReturnByMovementUniqueId($movementUniqueId);
			}

			$this->conn->commit();
			return array('success', 'نجح', 'تم الحفظ بنجاح', []);
		} catch (PDOException $e) {
			if ($this->conn->inTransaction()) {
				$this->conn->rollBack();
			}
			return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
			if ($this->conn->inTransaction()) {
				$this->conn->rollBack();
			}
			return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
	}
	public function SuppliedStock($orderid, $note, $supplierId, $products) {
		
		$branch_id 	 = $_SESSION["BRANCH_ID"];
	    $userActionId = $_SESSION["USER_ID"];
	    $startpoint = 'spSTAR1001';
	    $totalPrice = 0;
	  
		try {
		    $this->conn->beginTransaction();
		    $this->conn->exec("SET innodb_lock_wait_timeout = 0");
		    
			$store_id 	 = self::get_inventory_id($branch_id);

		    $resC = $this->purchase->updatePurchaseOrder($orderid,$note,$products, $self=false);
		    if (!$resC['result']) {
		        throw new Exception("لم يتم تأكيد حفظ أمر الشراء.");
		    }

			$movementUniqueId = 'PURCHASE_PARTIAL_RECEIPT_' . bin2hex(random_bytes(16));
			$this->InventoryManager->processInventory('partialreceipt', [$orderid], $userActionId, $branch_id, $movementUniqueId, $store_id , null, $products);
			(new SupplierAccountingManager($this->conn))->recordReceiptByMovementUniqueId($movementUniqueId);
			
		    if ($resC) {
		        $this->conn->commit();

		        return array('success', 'نجح', 'تم الحفظ بنجاح', []);
		    } else {
		        //if ($this->conn->inTransaction()) {
		            $this->conn->exec("ROLLBACK TO SAVEPOINT $startpoint");
		        //}
		        throw new Exception("لم يتم تنفيذ الاجراء .. حدث خطاء غير معروف.");
		    }
	 
		} catch (PDOException $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
	}
	
	public function addScrap($stoId, $note, $products) {
		
		try {
		    $this->conn->beginTransaction();
			$this->conn->exec("SET innodb_lock_wait_timeout = 0");
			$this->InventoryManager->
			$response = $this->InventoryManager->processInventory(
				'scrap', 			    // @param string $type نوع الحركة (sales, shipping, receiving, ...)
				[0], 					// @param array $ordersIds مصفوفة IDs للأوامر المرتبطة بالحركة
				$_SESSION['USER_ID'], 	// @param int $userActionId معرف المستخدم المنفذ
				$_SESSION['BRANCH_ID'], // @param int $branchId معرف الفرع
				null, 					// @param string|null $uniqueId معرف فريد للحركة (auto generate إذا فارغ)
				$stoId, 				// @param int|null $fromStoreId مخزن الصرف/الإستلام
				null, 					// @param int|null $toStoreId مخزن التحويل (للحركة Transfer)
				$products 				// @param array $productArray مصفوفة المنتجات (لـ partialreceipt أو settlement)
			);
			
			if(!$response){
				throw new Exception("لم يتم تنفيذ الاجراء .. حدث خطاء غير معروف.");
			}
			$this->conn->commit();
			return ['success','تم الحفظ','تم حفظ محضر الاهلاك بنجاح'];
			
		} catch (PDOException $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
	}
	
	/*public function inventoryReconciliationList_old (){
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
		$stmt->execute($whereClause['params']);
		$records = $this->DPro->fetchDataTypeDetection($stmt, $fieldsName, $fieldsType);
		$stmt->closeCursor();

		// تنفيذ استعلام العدد الإجمالي
		$stmtTotal = $this->pdo->prepare($queryCount . $totalWhereClause['where']);
		$stmtTotal->execute($totalWhereClause['params']);
		$totalRows = $stmtTotal->fetch(PDO::FETCH_ASSOC);
		$stmtTotal->closeCursor();

		// تجهيز البيانات للعرض
		return [
			"draw" => intval(isset($_POST["draw"]) ? $_POST["draw"]  : 0),
			"iTotalRecords" => count($records),
			"iTotalDisplayRecords" => 1,
			//intval($totalRows["TROW"] ?? 0),
			"data" => $records ?: []
		];
	}*/
	
	public function inventoryReconciliationList ($stoId,$catId,$proId){
		$isTransaction = false;
		try {
			if (!$this->conn->inTransaction()) {
			    $this->conn->beginTransaction();
			    $isTransaction = true;
			}
		//	$this->conn->exec("SET innodb_lock_wait_timeout = 5");
		    if (!isset($stoId) || empty($stoId) || $stoId <= 0) {
		        throw new Exception("معرّف المخزون غير صالح.");
		    }
		    
		    $token = $this->InventoryManager->lockStoreForCounting($stoId);
			$_SESSION['counting_token'] = $token;
			
			// تعريف الجدول والحقول
			$tblproducts = $this->DBTables->getTableName('products');
			$tblproduct_category = $this->DBTables->getTableName('product_category');
			$tblproduct_codes = $this->DBTables->getTableName('product_codes');
			$tblproduct_options_view = $this->DBTables->getTableName('product_options_view');
			$tblstores_product = $this->DBTables->getTableName('stores_product');
			$tblstores = $this->DBTables->getTableName('stores');
			
			$fieldsNamePR = $this->DBTables->getTableFieldsName('products');
			$fieldsTypePR = $this->DBTables->getFieldsType('products');
			$fieldsNamePCAT = $this->DBTables->getTableFieldsName('product_category');
			$fieldsTypePCAT = $this->DBTables->getFieldsType('product_category');
			$fieldsNamePC = $this->DBTables->getTableFieldsName('product_codes');
			$fieldsTypePC = $this->DBTables->getFieldsType('product_codes');
			$fieldsNamePOV = $this->DBTables->getTableFieldsName('product_options_view');
			$fieldsTypePOV = $this->DBTables->getFieldsType('product_options_view');
			$fieldsNameSP = $this->DBTables->getTableFieldsName('stores_product');
			$fieldsTypeSP = $this->DBTables->getFieldsType('stores_product');
			$fieldsNameST = $this->DBTables->getTableFieldsName('stores');
			$fieldsTypeST = $this->DBTables->getFieldsType('stores');

			// استعلامات SQL الأساسية
			$query="SELECT SP.".$fieldsNameSP['STO_ID'].",
					 ST.".$fieldsNameST['STO_NAME'].",
					 PCAT.".$fieldsNamePCAT['CAT_ID'].",
					 PCAT.".$fieldsNamePCAT['CAT_NAME'].",
					 PC.".$fieldsNamePC['PRO_CODE'].",
					 PR.".$fieldsNamePR['PRO_NAME'].",
					 POV.".$fieldsNamePOV['OPT_JSON'].",
					 SP.".$fieldsNameSP['STO_QTY'].",
					 '' AS SETQTY
					FROM  ".$tblproducts." AS PR
					 LEFT JOIN ".$tblproduct_category." AS PCAT
					 ON PR.".$fieldsNamePR['CAT_ID']." = PCAT.".$fieldsNamePCAT['CAT_ID']."
					 LEFT JOIN ".$tblproduct_codes." AS PC
					 ON PR.".$fieldsNamePR['PRO_ID']." = PC.".$fieldsNamePC['PRODUCT_ID']."
					 LEFT JOIN ".$tblproduct_options_view." AS POV
					 ON PC.".$fieldsNamePC['PRO_CODE']." = POV.".$fieldsNamePOV['PRO_CODE']."
					 LEFT JOIN ".$tblstores_product." AS SP
					 ON PC.".$fieldsNamePC['PRO_CODE']." = SP.".$fieldsNameSP['PRO_CODE']."
					 LEFT JOIN ".$tblstores." AS ST
					 ON SP.".$fieldsNameSP['STO_ID']." = ST.".$fieldsNameST['STO_ID'];
			
			$queryCount = "SELECT COUNT(".$fieldsNameSP['STO_ID'].") AS TROW FROM ($query) AS TBL ";
			
			$where = [];
			//====================== Global Search ==========================
			        
			// معالجة WHERE و ORDER BY و LIMIT
			if (is_array($catId) && count($catId) > 0) {
		        $where[] = 'PCAT.'.$fieldsNamePCAT['CAT_ID']. ' IN ('.implode(',',$catId).')';
		    }
		    if (is_array($proId) && count($proId) > 0) {
		        $where[] = 'PR.'.$fieldsNamePR['PRO_ID']. ' IN ('.implode(',',$proId).')';
		    }
		    
		    if (isset($stoId) && !empty($stoId) &&  $stoId > 0) {
		        
		        $where[] = 'SP.'.$fieldsNameSP['STO_ID']. ' = '.$stoId;
		    }
		    if(count($where) > 0){
		    	$query = $query.' WHERE '.implode(' AND ', $where);
		    }else{
		    	throw new Exception("يجب اختيار منتج او فئة واحده على الاقل.");
		    }

			// تنفيذ استعلام البيانات
			$this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
			$stmt = $this->conn->prepare($query. ' ORDER BY PR.'.$fieldsNamePR['PRO_ID']);
			$stmt->execute();
			$rows = [];
			while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) { 
				$row = $record;
				$rows[] = $row;
			}
			
			$stmt->closeCursor();
			$this->conn->commit();
			return $rows;
		} catch (PDOException $e) { // التقاط أخطاء PDO
		    if ($this->conn->inTransaction() && $isTransaction) {
			    $this->conn->rollBack();
			}
		    return "خطأ في قاعدة البيانات: " . $e->getMessage();
		} catch (Exception $e) {
		    if ($this->conn->inTransaction() && $isTransaction) {
			    $this->conn->rollBack();
			}
		    return "خطأ: " . $e->getMessage();
		}
	}
	
	public function setStockAudit($ProData,$stoid){
		try{
			$this->conn->beginTransaction();
			$this->conn->exec("SET innodb_lock_wait_timeout = 0");
			$response = $this->InventoryManager->processInventory(
				'SETTLEMENT', 			// @param string $type نوع الحركة (sales, shipping, receiving, ...)
				[0], 					// @param array $ordersIds مصفوفة IDs للأوامر المرتبطة بالحركة
				$_SESSION['USER_ID'], 	// @param int $userActionId معرف المستخدم المنفذ
				$_SESSION['BRANCH_ID'], // @param int $branchId معرف الفرع
				null, 					// @param string|null $uniqueId معرف فريد للحركة (auto generate إذا فارغ)
				$stoid, 				// @param int|null $fromStoreId مخزن الصرف/الإستلام
				null, 					// @param int|null $toStoreId مخزن التحويل (للحركة Transfer)
				$ProData 				// @param array $productArray مصفوفة المنتجات (لـ partialreceipt أو settlement)
			);
			
			if($response){
				$this->conn->commit();
				return ['success','تم الحفظ','تم حفظ محضر الجرد بنجاح'];
			}else{
				$this->conn->rollBack();
				return ['error','خطاء','حدث خطاء غير معروف'];
			}
			//$this->InventoryManager->unlockStoreAfterCounting($stoid);
		} catch (Exception $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
		
	}
	
	public function setInTransfer ($ProData,$stoid,$tostoId){
		try{
			$this->conn->beginTransaction();
			$this->conn->exec("SET innodb_lock_wait_timeout = 5");
			$this->InventoryManager->imdetails = $this->comment;
			$response = $this->InventoryManager->processInventory('transfer', [0], $_SESSION['USER_ID'], $_SESSION['BRANCH_ID'], null, $stoid, $tostoId, $ProData);
			if($response){
				$this->conn->commit();
				return ['success','تم الحفظ','تم حفظ اذن التحويل بنجاح'];
			}else{
				return ['error','خطاء','حدث خطاء غير معروف'];
			}
		} catch (Exception $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
		}
		
	}
	
	private function acquireGlobalInventoryLock(): bool {
	    $startTime = microtime(true);
	    $attempts = 0;
	    
	    while (true) {
	        $attempts++;
	        
	        try {
	            $stmt = $this->conn->prepare("SELECT GET_LOCK('global_inventory_lock', 0) as locked");
	            $stmt->execute();
	            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	            
	            if (!empty($rows) && $rows[0]['locked'] == 1) {
	                $waitTime = round(microtime(true) - $startTime, 2);
	                $this->logManager->info("🔒 تم تأقف المخزون العام بعد {$attempts} محاولة في {$waitTime} ثانية");
	                return true;
	            }
	        } catch (Exception $e) {
	            $this->logManager->error("❌ خطأ في تأقف المخزون العام: " . $e->getMessage());
	        }
	        
	        if ((microtime(true) - $startTime) >= 5) { // 5 ثواني timeout
	            $this->logManager->warning("⏰ انتهى وقت انتظار تأقف المخزون العام بعد {$attempts} محاولة");
	            return false;
	        }
	        
	        usleep(100000); // انتظر 100ms
	    }
	}
	
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