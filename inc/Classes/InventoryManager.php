<?php
/**
 * Class InventoryManager
 * ---------------------
 * مدير المخزون المركزي لجميع الحركات: صرف واستلام، مرتجعات، تسوية وتحويلات.
 * يدعم ربط كل حركة بالجدول الأصلي (Reference) ويسمح بتقارير دقيقة للمخزون.
 */
class InventoryManager {
    private $conn;
    private array $movType;
	public  $imdetails;
    /**
     * InventoryManager constructor.
     * --------------------------------
     * تهيئة الكلاس وتحديد أنواع الحركات المدعومة.
     * @param PDO $conn اتصال قاعدة البيانات
     */
    public function __construct($conn) {
        $this->conn = $conn;

        // تعريف كل أنواع الحركات المخزنية مع نوعها IN/OUT ووصفها
        $this->movType = [
            'sales'             => ['type' => 'OUT', 'desc' => 'صرف منتجات امر البيع المباشر'],
            'shipping'          => ['type' => 'OUT', 'desc' => 'صرف منتجات امر البيع للمندوب'],
            'refund'            => ['type' => 'IN',  'desc' => 'استرداد منتجات امر البيع من المندوب'],
            'return_shipp'      => ['type' => 'IN',  'desc' => 'استلام المنتجات المرفوضة من المندوب'],
            'return_sales'      => ['type' => 'IN',  'desc' => 'استلام مرتجعات بعد البيع'],
            'receiving'         => ['type' => 'IN',  'desc' => 'استلام منتجات اوامر الشراء من المورد'],
            'refundpurchises'   => ['type' => 'OUT', 'desc' => 'صرف مرتجعات اوامر الشراء للمورد'],
            'partialreceipt'    => ['type' => 'IN',  'desc' => 'استلام جزئي من المورد', 'typemov' => 'receiving'],
            'transfer'          => ['type' => 'OUT', 'desc' => 'تحويل داخلي بين المخازن'],
            'settlement'        => ['type' => 'OUT', 'desc' => 'تسوية مخزنية'],
            'scrap' 			=> ['type' => 'OUT', 'desc' => 'صرف هالك مخزون']
        ];
    }

    /**
     * واجهة رئيسية موحدة لجميع حركات المخزون
     * -------------------------------------------------
     * كل الحركات تمر من هذه الدالة، وتقوم بتوجيهها للدوال المختصة:
     * - Sales / Shipping / Refund / Return → processSalesOrder
     * - Receiving / REFUNDPURCHISES → processPurchaseOrder
     * - PartialReceipt → processPurchasePartialReceipt
     * - Transfer → processTransfer
     * - Settlement → processInventoryCheck
     * ------------------------------------------
     * # أنواع الحركات المدعومة:
     * 
	 *   SALES           → صرف منتجات أمر البيع المباشر
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
 	 * ------------------------------------------
     * @param string $type نوع الحركة (sales, shipping, receiving, ...)
     * @param array $ordersIds مصفوفة IDs للأوامر المرتبطة بالحركة
     * @param int $userActionId معرف المستخدم المنفذ
     * @param int $branchId معرف الفرع
     * @param string|null $uniqueId معرف فريد للحركة (auto generate إذا فارغ)
     * @param int|null $fromStoreId مخزن الصرف/الإستلام
     * @param int|null $toStoreId مخزن التحويل (للحركة Transfer)
     * @param array $productArray مصفوفة المنتجات (لـ partialreceipt أو settlement)
     * @return bool|int
     * @throws Exception
     */
    public function processInventory(string $type, array $ordersIds, int $userActionId, int $branchId, string $uniqueId = null, int $fromStoreId = null, int $toStoreId = null, array $productArray = []) {
        try {
            // التأكد من أن كل العمليات داخل Transaction
            if (!$this->conn->inTransaction()) {
                throw new Exception("⛔ يجب تنفيذ عمليات المخزون داخل Transaction.");
            }

            $type = strtolower($type);
            if(!isset($this->movType[$type])) {
                throw new Exception("نوع الحركة غير مدعوم: {$type}");
            }

            // تعيين المخزن الافتراضي إذا لم يتم تحديده
            $fromStoreId = $fromStoreId ?: $this->get_inventory_id($branchId);
            if(!$fromStoreId) throw new Exception("لم يتم التعرف على مخزن الحركة.");
            if(!$userActionId) throw new Exception("معرف منشئ الحركة مفقود.");
            if(!$branchId) throw new Exception("معرف الفرع مفقود.");
            if(!is_array($ordersIds) || count($ordersIds) === 0) throw new Exception("معرفات الأوامر غير صالحة.");

            $uniqueId = $uniqueId ?: uniqid('IM_', true);

            // الحصول على قفل عالمي لمنع التعارض مع حركات أخرى
            $this->acquireGlobalInventoryLock();

            // توجيه الحركة للدالة المختصة
            switch ($type) {
                case 'sales':
                case 'shipping':
                case 'refund':
                case 'return_shipp':
                case 'return_sales':
                    return $this->processSalesOrder($type, $ordersIds, $userActionId, $branchId, $fromStoreId, $uniqueId);
                
                case 'receiving':
                case 'refundpurchises':
                    return $this->processPurchaseOrder($type, $ordersIds, $userActionId, $branchId, $fromStoreId, $uniqueId);

                case 'partialreceipt':
                    return $this->processPurchasePartialReceipt($type, $productArray, $ordersIds, $userActionId, $branchId, $fromStoreId, $uniqueId);

                case 'transfer':
                    if(!$toStoreId) throw new Exception("مخزن الاستلام مطلوب لعملية التحويل");
                    return $this->processTransfer($productArray, $fromStoreId, $toStoreId, $userActionId, $branchId, $uniqueId);

                case 'settlement':
                    return $this->processInventoryCheck($type, $productArray, $userActionId, $branchId, $fromStoreId, $uniqueId);
				
				case 'scrap':
                    return $this->processScrap($productArray, $fromStoreId, null, $userActionId, $branchId, $uniqueId);
                    
                default:
                    throw new Exception("نوع العملية غير مدعوم: " . $type);
            }

        } catch (PDOException $e) {
            // تسجيل الخطأ في اللوج
            error_log("InventoryManager PDO Error: " . $e->getMessage());
            throw new Exception("خطأ في قاعدة البيانات أثناء معالجة المخزون".$e->getMessage());
        } catch (Exception $e) {
            error_log("InventoryManager Error: " . $e->getMessage());
            throw new Exception($e->getMessage());
        } finally {
            // فك القفل بعد الانتهاء
            $this->releaseGlobalInventoryLock();
        }
    }
	
	/*
	 * قفل المخزن للجرد
	 * @throws Exception
	 */
	public function lockStoreForCounting(int $storeId): string {
	    $token = bin2hex(random_bytes(32));
	
	    $stmt = $this->conn->prepare("
	        UPDATE stores
	        SET
	            IS_COUNTING = 1,
	            COUNTING_TOKEN = ?,
	            COUNTING_EXPIRES = DATE_ADD(NOW(), INTERVAL 2 MINUTE)
	        WHERE STO_ID = ?
	          AND (
	                IS_COUNTING = 0
	                OR COUNTING_EXPIRES IS NULL
	                OR COUNTING_EXPIRES < NOW()
	          )
	    ");
	
	    $stmt->execute([$token, $storeId]);
	
	    if ($stmt->rowCount() == 0) {
	        throw new Exception("المخزن مستخدم حالياً بواسطة مستخدم آخر.");
	    }
	
	    return $token;
	}
	
	/*
	 * اعدة تعين قفل المخزن
	 * @throws Exception
	 */
	public function refreshStoreCountingLock(int $storeId, string $token): void {
	    $stmt = $this->conn->prepare("
	        UPDATE stores
	        SET COUNTING_EXPIRES = DATE_ADD(NOW(), INTERVAL 2 MINUTE)
	        WHERE STO_ID = ?
	          AND IS_COUNTING = 1
	          AND COUNTING_TOKEN = ?
	    ");
	
	    $stmt->execute([$storeId, $token]);
	
	    if ($stmt->rowCount() == 0) {
	        throw new Exception("تم فقدان قفل الجرد.");
	    }
	}
	
	/*
	 * فتح المخزن بعد انتهاء الجرد
	 * @throws Exception
	 */
	public function unlockStoreAfterCounting(int $storeId, string $token): void {
	    $stmt = $this->conn->prepare("
	        UPDATE stores
	        SET
	            IS_COUNTING = 0,
	            COUNTING_TOKEN = NULL,
	            COUNTING_EXPIRES = NULL
	        WHERE STO_ID = ?
	          AND COUNTING_TOKEN = ?
	    ");
	
	    $stmt->execute([$storeId, $token]);
	
	    if ($stmt->rowCount() == 0) {
	        throw new Exception("تعذر فك قفل المخزن.");
	    }
	}	
	/*****************************************************
	 * التحقق أن المخزن غير مقفل للجرد
	 *****************************************************/
	public function validateStoreNotCounting(int $storeId): void {
	    $stmt = $this->conn->prepare("
	        SELECT
	            IS_COUNTING,
	            COUNTING_EXPIRES
	        FROM stores
	        WHERE STO_ID = ?
	        LIMIT 1
	    ");
	
	    $stmt->execute([$storeId]);
	
	    $store = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$store) {
	        throw new Exception("المخزن غير موجود.");
	    }
	
	    if (
	        (int)$store['IS_COUNTING'] === 1 &&
	        !empty($store['COUNTING_EXPIRES']) &&
	        strtotime($store['COUNTING_EXPIRES']) > time()
	    ) {
	        throw new Exception("لا يمكن تنفيذ الحركة، المخزن تحت الجرد.");
	    }
	}
	
    /**
     * الحصول على قفل عالمي لمنع تعارض تحديث المخزون
     * @return bool
     * @throws Exception
     */
    private function acquireGlobalInventoryLock(): bool {
        $stmt = $this->conn->prepare("SELECT GET_LOCK('global_inventory_lock', 2) AS locked");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result['locked'] == 1) return true;

        throw new Exception("⏳ المخزون قيد الاستخدام حاليًا. يرجى المحاولة بعد بضع ثوانٍ.");
    }

    /**
     * فك القفل العالمي للمخزون
     * @return void
     * @throws Exception
     */
    private function releaseGlobalInventoryLock(): void {
        $stmt = $this->conn->prepare("SELECT RELEASE_LOCK('global_inventory_lock') AS released");
        $stmt->execute();
    }

    /**
     * الحصول على المخزن الافتراضي للفرع
     * @param int $branch_id معرف الفرع
     * @return int|null معرف المخزن
     */
    private function get_inventory_id(int $branch_id): ?int {
        $stmt = $this->conn->prepare("SELECT STO_ID FROM STORES WHERE STTY_ID=2 AND BRANCH_ID=:branchid");
        $stmt->execute([':branchid' => $branch_id]);
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * تحديث ارصدة المخزون بناء على حركة الشحن
     */
   	private function update_stores(){
		// 1️⃣ استدعاء الإجراء
		$stmt2 = $this->conn->prepare("CALL update_stores(@p_return)");
		$stmt2->execute();
		$stmt2->closeCursor(); // مهم جدًا
						
		// 2️⃣ قراءة قيمة OUT
		$result = $this->conn->query("SELECT @p_return AS p_return")->fetch(PDO::FETCH_ASSOC);
			
		$p_return = (int)$result['p_return'];
		if($p_return === 0){
			throw new Exception("خطاء فى معالجة المنتجات من المخزن."); 
		}
		return $p_return;
	}
	
    /**
	 * =======================
	 * جلب رصيد المنتج في المخزن
	 * =======================
	 */
	private function getProductStock(int $storeId, string $proCode): int {
	    $stmt = $this->conn->prepare("SELECT STO_QTY FROM stores_product WHERE STO_ID=? AND PRO_CODE=?");
	    $stmt->execute([$storeId, $proCode]);
	    return (int)($stmt->fetchColumn() ?? 0);
	}
	
    /**
	 * =======================
	 * معالجة أوامر البيع ومرتجعات العملاء
	 * =======================
	 * تقوم بإدراج حركة المخزون لكل أمر، ثم تفاصيل المنتجات،
	 * وتحديث رصيد المخزون في المخازن.
	 *
	 * @param string $type نوع الحركة (sales, shipping, refund, return_shipp, return_sales)
	 * @param array $ordersIds مصفوفة IDs للأوامر
	 * @param int $userActionId معرف المستخدم المنفذ
	 * @param int $branchId معرف الفرع
	 * @param int $storeId معرف المخزن
	 * @param string $uniqueId معرف فريد للحركة
	 * @return int عدد الحركات المسجلة
	 * @throws Exception
	 */
	private function processSalesOrder(string $type, array $ordersIds, int $userActionId, int $branchId, int $storeId, string $uniqueId): int {
	    $imMoveType = $type;
	    $ipmTypeMov = $this->movType[$type]['type'];
	    $imDetails  = $this->movType[$type]['desc'];
	    $math = $ipmTypeMov == 'IN' ? '+' : '-';
	
	    $placeholders = implode(',', array_fill(0, count($ordersIds), '?'));
	
	    // ================= Insert inventory_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement 
	            (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        SELECT ? AS IM_MOVE_TYPE, ? AS IM_USER, ? AS IM_BRANCH, ? AS STO_ID, ORDER_ID, ? AS UNIQUE_ID, ? AS IM_DETAILS, 1, 1
	        FROM orders WHERE ORDER_ID IN ($placeholders)
	    ");
	    $params = array_merge([$imMoveType, $userActionId, $branchId, $storeId, $uniqueId, $imDetails], $ordersIds);
	    $stmt->execute($params);
	    if($stmt->rowCount() != count($ordersIds)) throw new Exception("فشل تسجيل أوامر حركة المخزون.");
	
	    // ================= Insert inventory_product_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT)
	        SELECT im.IM_ID, op.ORDER_ID, ?, op.PRO_CODE,
	            COALESCE(sp.STO_QTY,0) $math COALESCE(
	                SUM(
	                    CASE 
	                        WHEN im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') THEN op.QTY
	                        WHEN im.IM_MOVE_TYPE='RETURN_SHIPP' THEN op.QTY - op.DELIVERED_QTY
	                        WHEN im.IM_MOVE_TYPE='RETURN_SALES' THEN op.RETURNED_QTY
	                        ELSE 0
	                    END
	                ) OVER(PARTITION BY op.PRO_CODE ORDER BY im.IM_ID ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0
	            ),
	            CASE 
	                WHEN im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') THEN op.QTY
	                WHEN im.IM_MOVE_TYPE='RETURN_SHIPP' THEN op.QTY - op.DELIVERED_QTY
	                WHEN im.IM_MOVE_TYPE='RETURN_SALES' THEN op.RETURNED_QTY
	                ELSE 0
	            END,
	            NOW(6)
	        FROM order_product op
	        INNER JOIN orders o ON o.ORDER_ID = op.ORDER_ID
	        INNER JOIN inventory_movement im ON im.REFERENCE = o.ORDER_ID AND im.UNIQUE_ID = ?
	        LEFT JOIN stores_product sp ON sp.PRO_CODE = op.PRO_CODE AND sp.STO_ID = ?
	        WHERE op.ORDER_ID IN ($placeholders) 
	        AND (im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') OR (im.IM_MOVE_TYPE = 'RETURN_SHIPP' AND op.PRO_STATUSE IN ('rejected','partial')) OR (im.IM_MOVE_TYPE = 'RETURN_SALES' AND op.PRO_STATUSE IN ('partial_refund','refund')));
	        
	       
	    ");
	    $params = array_merge([$ipmTypeMov, $uniqueId, $storeId], $ordersIds);
	    $stmt->execute($params);
	    if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل منتجات حركة المخزون.");
	
	    // ================= تحديث المخزون =================
	    return $this->update_stores();
	}
	
	/**
	 * =======================
	 * معالجة أوامر الشراء ومرتجعات المورد
	 * =======================
	 *
	 * @param string $type receiving أو refundpurchises
	 * @param array $ordersIds IDs لأوامر الشراء
	 * @param int $userActionId معرف المستخدم
	 * @param int $branchId معرف الفرع
	 * @param int $storeId معرف المخزن
	 * @param string $uniqueId معرف فريد للحركة
	 * @return int
	 * @throws Exception
	 */
	private function processPurchaseOrder(string $type, array $ordersIds, int $userActionId, int $branchId, int $storeId, string $uniqueId): int {
	    $imMoveType = $type;
	    $ipmTypeMov = $this->movType[$type]['type'];
	    $imDetails  = $this->movType[$type]['desc'];
	    $math       = $ipmTypeMov == 'IN' ? '+' : '-';
	
	    $tableProduct = $type === 'refundpurchises' ? 'purchases_return_products' : 'purchases_products';
	    $tableOrder   = $type === 'refundpurchises' ? 'purchases_return_orders' : 'purchases_orders';
	    $colQty       = $type === 'refundpurchises' ? 'op.QTY_RETURN' : 'op.QTY_SUPPLIED';
	
	    $placeholders = implode(',', array_fill(0, count($ordersIds), '?'));
	
	    // بعد الحصول على القفل العالمي، افحص رصيد المخزن تحت قفل الصفوف لتجنب تعارض الحركات المتزامنة.
	    if ($type === 'refundpurchises') {
	        $returnProductsStmt = $this->conn->prepare("
	            SELECT PRO_CODE, QTY_RETURN
	            FROM purchases_return_products
	            WHERE ORDER_ID IN ($placeholders)
	        ");
	        $returnProductsStmt->execute($ordersIds);
	        $returnProducts = $returnProductsStmt->fetchAll(PDO::FETCH_ASSOC);
	        if (!$returnProducts) throw new Exception("لا توجد منتجات صالحة لصرف مرتجع المورد.");

	        $returnCodes = array_values(array_unique(array_map(
	            static function ($row): string { return (string)$row['PRO_CODE']; },
	            $returnProducts
	        )));
	        $stockPlaceholders = implode(',', array_fill(0, count($returnCodes), '?'));
	        $stockStmt = $this->conn->prepare("
	            SELECT PRO_CODE, STO_QTY
	            FROM stores_product
	            WHERE STO_ID = ? AND PRO_CODE IN ($stockPlaceholders)
	            FOR UPDATE
	        ");
	        $stockStmt->execute(array_merge([$storeId], $returnCodes));
	        $stockRows = [];
	        foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
	            $stockRows[(string)$row['PRO_CODE']] = (float)$row['STO_QTY'];
	        }
	        foreach ($returnProducts as $product) {
	            $code = (string)$product['PRO_CODE'];
	            $returnQty = (float)$product['QTY_RETURN'];
	            $availableQty = (float)($stockRows[$code] ?? 0);
	            if (!is_finite($returnQty) || $returnQty <= 0 || $returnQty - $availableQty > 0.00001) {
	                throw new Exception("رصيد المنتج $code في المخزن لا يكفي لتنفيذ المرتجع.");
	            }
	        }
	    }

	    // ================= Insert inventory_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement
	            (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        SELECT ?, ?, ?, ?, ORDER_ID, ?, ?, 1, 1
	        FROM $tableOrder WHERE ORDER_ID IN ($placeholders)
	    ");
	    $params = array_merge([$imMoveType, $userActionId, $branchId, $storeId, $uniqueId, $imDetails], $ordersIds);
	    $stmt->execute($params);
	    if($stmt->rowCount() != count($ordersIds)) throw new Exception("فشل تسجيل أوامر حركة المخزون.");
	
	    // ================= Insert inventory_product_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT)
	        SELECT im.IM_ID, op.ORDER_ID, ?, op.PRO_CODE,
	            COALESCE(sp.STO_QTY,0) $math COALESCE(
	                SUM($colQty) OVER(PARTITION BY op.PRO_CODE ORDER BY im.IM_ID ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0
	            ),
	            $colQty,
	            NOW(6)
	        FROM $tableProduct op
	        INNER JOIN $tableOrder o ON o.ORDER_ID = op.ORDER_ID
	        INNER JOIN inventory_movement im ON im.REFERENCE = o.ORDER_ID AND im.UNIQUE_ID = ?
	        LEFT JOIN stores_product sp ON sp.PRO_CODE = op.PRO_CODE AND sp.STO_ID = ?
	        WHERE op.ORDER_ID IN ($placeholders)
	    ");
	    $params = array_merge([$ipmTypeMov, $uniqueId, $storeId], $ordersIds);
	    $stmt->execute($params);
	    if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل منتجات حركة المخزون.");
	
	    // ================= تحديث المخزون =================
	    return $this->update_stores();
	}
	
	/**
	 * =======================
	 * معالجة الاستلام الجزئي للمنتجات
	 * =======================
	 *
	 * @param string $type partialreceipt
	 * @param array $productArray مصفوفة المنتجات مع الكميات
	 * @param array $ordersId مصفوفة تحتوي معرف امر واحد فقط
	 * @param int $userActionId معرف المستخدم
	 * @param int $branchId معرف الفرع
	 * @param int $storeId معرف المخزن
	 * @param string $uniqueId معرف فريد للحركة
	 * @return int
	 * @throws Exception
	 */
	private function processPurchasePartialReceipt(string $type, array $productArray, array $ordersId, int $userActionId, int $branchId, int $storeId, string $uniqueId): int {
	    if(count($productArray) === 0) throw new Exception("مصفوفة المنتجات فارغة");
	    if(count($ordersId) !== 1) throw new Exception("مسموح فقط بتنفيذ الإجراء لأمر واحد");
	
	    $reference   = $ordersId[0];
	    $imTypeMov   = $this->movType[$type]['typemov'];
	    $ipmTypeMov  = $this->movType[$type]['type'];
	    $imDetails   = $this->movType[$type]['desc'];
	
	    // ================= Insert inventory_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
	    ");
	    $stmt->execute([$imTypeMov, $userActionId, $branchId, $storeId, $reference, $uniqueId, $imDetails]);
	    if($stmt->rowCount() !== 1) throw new Exception("فشل تسجيل حركة المخزون");
	
	    $imId = (int)$this->conn->lastInsertId();
	
	    // ================= Insert inventory_product_movement =================
	    $storeQty = [];
	    $qtyStmt = $this->conn->prepare("SELECT STO_QTY FROM stores_product WHERE STO_ID=? AND PRO_CODE=?");
	    foreach($productArray as $prod) {
	        $qtyStmt->execute([$storeId, $prod['PRO_CODE']]);
	        $storeQty[$prod['PRO_CODE']] = (int)($qtyStmt->fetchColumn() ?? 0);
	    }
	
	    $batchSize = 100;
	    for($i=0;$i<count($productArray);$i+=$batchSize){
	        $placeholders = [];
	        $bindValues = [];
	        for($j=$i;$j<$i+$batchSize && $j<count($productArray);$j++){
	            $placeholders[] = "(?, ?, ?, ?, ?, ?)";
	            $bindValues[] = $imId;
	            $bindValues[] = $ordersId[0];
	            $bindValues[] = $ipmTypeMov;
	            $bindValues[] = $productArray[$j]['PRO_CODE'];
	            $bindValues[] = $storeQty[$productArray[$j]['PRO_CODE']];
	            $bindValues[] = $productArray[$j]['QTY_SUPPLIED'];
	        }
	        $sql = "INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES ".implode(',', $placeholders);
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute($bindValues);
	        if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل تفاصيل حركة المخزون");
	    }
	
	    // ================= تحديث المخزون =================
	    return $this->update_stores();
	}

	/**
	 * =======================
	 * معالجة التسوية المخزنية (Inventory Settlement)
	 * =======================
	 * تقوم هذه الدالة بمقارنة الرصيد الفعلي للمنتجات مع الرصيد المسجل في المخزون.
	 * في حال وجود فروق، تقوم بتسجيل حركة IN أو OUT لتعديل المخزون.
	 *
	 * @param string $type يجب أن يكون 'settlement'
	 * @param array $productArray مصفوفة المنتجات مع الرصيد الفعلي المطلوب
	 *      مثال: [['PRO_CODE'=>'9900000001', 'QTY'=>10], ...]
	 * @param int $userActionId معرف المستخدم
	 * @param int $branchId معرف الفرع
	 * @param int $storeId معرف المخزن
	 * @param string $uniqueId معرف فريد للحركة
	 * @return int عدد المنتجات التي تم تعديلها
	 * @throws Exception
	 */
	private function processInventoryCheck(string $type, array $productArray, int $userActionId, int $branchId, int $storeId, string $uniqueId): int {
	    if($type !== 'settlement') throw new Exception("نوع حركة التسوية غير صحيح");
	    if(count($productArray) === 0) throw new Exception("مصفوفة المنتجات فارغة");
	    
	    $this->validateStoreNotCounting($storeId);
	    
	    //if ((int)$stmt->fetchColumn() === 0) throw new Exception("لا يمكن تنفيذ الحركة، المخزن لم يتم اغلاق المخزن لتنفيذ الجرد.");
	    
	    $imDetails = $this->movType[$type]['desc'];
		
	    // ================= Insert inventory_movement =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement 
	            (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
	    ");
	    $stmt->execute([$type, $userActionId, $branchId, $storeId, $uniqueId, $imDetails]);
	    if($stmt->rowCount() !== 1) throw new Exception("فشل تسجيل حركة التسوية المخزنية");
	
	    $imId = (int)$this->conn->lastInsertId();
	
	    // ================= تعديل المنتجات =================
	    $qtyStmt = $this->conn->prepare("SELECT STO_QTY FROM stores_product WHERE STO_ID=? AND PRO_CODE=? FOR UPDATE");
	    $adjustProducts = [];
	
	    foreach($productArray as $prod){
	        if(!isset($prod['QTY'])) throw new Exception("الكمية الفعلية غير موجودة");
	        $actualQty = (int)$prod['QTY'];
	        $qtyStmt->execute([$storeId, $prod['PRO_CODE']]);
	        $currentQty = (int)($qtyStmt->fetchColumn() ?? 0);
	        $diff = $actualQty - $currentQty;
	        if($diff === 0) continue; // لا فرق → تجاهل
	
	        $adjustProducts[] = [
	            'PRO_CODE' => $prod['PRO_CODE'],
	            'IPM_TYPE' => ($diff>0 ? 'IN':'OUT'),
	            'IPM_QTY'  => abs($diff),
	            'STO_QTY'  => $currentQty
	        ];
	    }
	
	    if(count($adjustProducts) === 0) throw new Exception("لا يوجد فروق تستدعي تسوية مخزنية");
	
	    // ================= Insert inventory_product_movement =================
	    $batchSize = 50;
	    for($i=0;$i<count($adjustProducts);$i+=$batchSize){
	        $placeholders = [];
	        $bindValues = [];
	        for($j=$i;$j<$i+$batchSize && $j<count($adjustProducts);$j++){
	            $placeholders[] = "(?, ?, ?, ?, ?)";
	            $bindValues[] = $imId;
	            $bindValues[] = $adjustProducts[$j]['IPM_TYPE'];
	            $bindValues[] = $adjustProducts[$j]['PRO_CODE'];
	            $bindValues[] = $adjustProducts[$j]['STO_QTY'];
	            $bindValues[] = $adjustProducts[$j]['IPM_QTY'];
	        }
	        $sql = "INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES ".implode(',', $placeholders);
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute($bindValues);
	        if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل تفاصيل التسوية المخزنية");
	    }
	
	    // ================= تحديث المخزون =================
	    return $this->update_stores();
	    
	    //$this->unlockStoreAfterCounting($storeId);
	}
	
	/**
	 * =======================
	 * التحويل الداخلي بين المخازن (Inventory Transfer)
	 * =======================
	 * تقوم هذه الدالة بتحريك المنتجات من مخزن إلى آخر داخل نفس الفرع أو بين الفروع.
	 * يتم تسجيل حركة OUT في المخزن المصدر وحركة IN في المخزن الهدف.
	 *
	 * @param array $transferArray مصفوفة المنتجات للتحويل
	 *      مثال: [['PRO_CODE'=>'9900000001','QTY'=>5], ...]
	 * @param int $fromStoreId معرف المخزن المصدر
	 * @param int $toStoreId معرف المخزن الهدف
	 * @param int $userActionId معرف المستخدم
	 * @param int $branchId معرف الفرع
	 * @param string $uniqueId معرف فريد للحركة
	 * @return int عدد المنتجات التي تم تحويلها
	 * @throws Exception
	 */
	private function processTransfer(array $transferArray, int $fromStoreId, int $toStoreId, int $userActionId, int $branchId, string $uniqueId): int {
	    if(count($transferArray) === 0) throw new Exception("مصفوفة المنتجات فارغة للتحويل");
		if($fromStoreId === $toStoreId) throw new Exception("لايمكن التحويل من إلى نفس المخزن");
		$imDetails = empty($this->imDetails) ? "تحويل داخلى" : $this->imdetails;
		
		// ================= حركة المخزن المصدر (OUT) =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, STO_DEST, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        SELECT 
				'TRANSFER' AS IM_MOVE_TYPE, 
				? AS IM_USER, 
				BRANCH_ID AS IM_BRANCH, 
				STO_ID, 
				? AS STO_DEST, 
				? AS UNIQUE_ID, 
				? IM_DETAILS, 
				1 AS IM_APPROVAL_DISBURSED, 
				1 AS IM_APPROVAL_RECEIVED
			FROM stores 
			WHERE STO_ID = ?
	    ");
	    $stmt->execute([$userActionId, $toStoreId, $uniqueId, $imDetails, $fromStoreId]);
	    $imId = (int)$this->conn->lastInsertId();
		if(empty($imId)) throw new Exception("1فشل حفظ اذن التحويل");
	    
	    // ================= Insert inventory_product_movement (OUT) =================
	    $batchSize = 50;
	    for($i=0;$i<count($transferArray);$i+=$batchSize){
	        $placeholders = [];
	        $bindValues = [];
	        for($j=$i;$j<$i+$batchSize && $j<count($transferArray);$j++){
	            
	            $placeholders[] = "(?, ?, ?, ?, ?)";
	            $bindValues[] = $imId;
	            $bindValues[] = 'OUT';
	            $bindValues[] = $transferArray[$j]['PRO_CODE'];
	            $bindValues[] = $this->getProductStock($fromStoreId, $transferArray[$j]['PRO_CODE']);
	            $bindValues[] = $transferArray[$j]['QTY'];
	        }
	       
	        $sql = "INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES ".implode(',', $placeholders);
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute($bindValues);
	        if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل تفاصيل تحويل المخزون من المخزن المصدر");
	    }
	    
	    
		// ================= حركة المخزن المستلم (IN) =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, STO_DEST, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        SELECT 
				'TRANSFER' AS IM_MOVE_TYPE, 
				? AS IM_USER, 
				BRANCH_ID AS IM_BRANCH, 
				STO_ID, 
				? AS STO_DEST, 
				? AS UNIQUE_ID, 
				? IM_DETAILS, 
				1 AS IM_APPROVAL_DISBURSED, 
				1 AS IM_APPROVAL_RECEIVED
			FROM stores 
			WHERE STO_ID = ?
	    ");
	    $stmt->execute([$userActionId, $fromStoreId, $uniqueId, $imDetails, $toStoreId]);
	    $imIdTO = (int)$this->conn->lastInsertId();
		if(empty($imIdTO)) throw new Exception("فشل حفظ اذن التحويل");
	    
		// ================= Insert inventory_product_movement (IN) =================
	    for($i=0;$i<count($transferArray);$i+=$batchSize){
	        $placeholders = [];
	        $bindValues = [];
	        for($j=$i;$j<$i+$batchSize && $j<count($transferArray);$j++){
	            $placeholders[] = "(?, ?, ?, ?, ?)";
	            $bindValues[] = $imIdTO;
	            $bindValues[] = 'IN';
	            $bindValues[] = $transferArray[$j]['PRO_CODE'];
	            $bindValues[] = $this->getProductStock($toStoreId, $transferArray[$j]['PRO_CODE']);
	            $bindValues[] = $transferArray[$j]['QTY'];
	        }
	        $sql = "INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES ".implode(',', $placeholders);
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute($bindValues);
	        if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل تفاصيل تحويل المخزون للمخزن الهدف");
	    }
	
	    // ================= تحديث المخزون =================
	    
	    return $this->update_stores();
	}

	private function processScrap(array $productArray, int $fromStoreId, int $toStoreId, int $userActionId, int $branchId, string $uniqueId): int {
	    if(count($productArray) === 0) throw new Exception("مصفوفة المنتجات فارغة للتحويل");
		if($fromStoreId === $toStoreId) throw new Exception("لايمكن التحويل من إلى نفس المخزن");
		$imDetails = empty($this->imDetails) ? "تحويل داخلى" : $this->imdetails;
		
		// ================= حركة المخزن المصدر (OUT) =================
	    $stmt = $this->conn->prepare("
	        INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, STO_DEST, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED)
	        SELECT 
				'TRANSFER' AS IM_MOVE_TYPE, 
				? AS IM_USER, 
				BRANCH_ID AS IM_BRANCH, 
				STO_ID, 
				? AS UNIQUE_ID, 
				? IM_DETAILS, 
				1 AS IM_APPROVAL_DISBURSED, 
				1 AS IM_APPROVAL_RECEIVED
			FROM stores 
			WHERE STO_ID = ?
	    ");
	    $stmt->execute([$userActionId, $toStoreId, $uniqueId, $imDetails, $fromStoreId]);
	    $imId = (int)$this->conn->lastInsertId();
		if(empty($imId)) throw new Exception("1فشل حفظ اذن التحويل");
	    
	    // ================= Insert inventory_product_movement (OUT) =================
	    $batchSize = 50;
	    for($i=0;$i<count($productArray);$i+=$batchSize){
	        $placeholders = [];
	        $bindValues = [];
	        for($j=$i;$j<$i+$batchSize && $j<count($productArray);$j++){
	            
	            $placeholders[] = "(?, ?, ?, ?, ?)";
	            $bindValues[] = $imId;
	            $bindValues[] = 'OUT';
	            $bindValues[] = $transferArray[$j]['PRO_CODE'];
	            $bindValues[] = $this->getProductStock($fromStoreId, $productArray[$j]['PRO_CODE']);
	            $bindValues[] = $transferArray[$j]['QTY'];
	        }
	       
	        $sql = "INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES ".implode(',', $placeholders);
	        $stmt = $this->conn->prepare($sql);
	        $stmt->execute($bindValues);
	        if($stmt->rowCount() === 0) throw new Exception("فشل تسجيل تفاصيل تحويل المخزون من المخزن المصدر");
	    }
	
	    // ================= تحديث المخزون =================
	    
	    return $this->update_stores();
	}
	
	/*****************************************************
	 * إنشاء إذن جرد
	 *****************************************************/
	public function createInventoryCount(
	    int $branchId,
	    int $storeId,
	    array $products,
	    string $note = ''
	): int {
	
	    if (empty($products)) {
	        throw new Exception("لا توجد أصناف للجرد.");
	    }
	
	    $this->lockStoreForCounting($storeId);
	
	    $countCode = $this->generateCountCode();
	
	    $stmt = $this->db->prepare("
	        INSERT INTO inventory_counts
	        (
	            COUNT_CODE,
	            BRANCH_ID,
	            STORE_ID,
	            COUNT_DATE,
	            STATUS,
	            NOTE,
	            CREATED_BY
	        )
	        VALUES
	        (?, ?, ?, NOW(), 0, ?, ?)
	    ");
	
	    $stmt->execute([
	        $countCode,
	        $branchId,
	        $storeId,
	        $note,
	        $this->getCurrentUserId()
	    ]);
	
	    $countId = (int)$this->db->lastInsertId();
	
	    $stmtDetail = $this->db->prepare("
	        INSERT INTO inventory_count_details
	        (
	            COUNT_ID,
	            PRO_CODE,
	            SYSTEM_QTY,
	            ACTUAL_QTY,
	            DIFFERENCE_QTY,
	            UNIT_COST,
	            DIFFERENCE_COST,
	            NOTE
	        )
	        VALUES
	        (?, ?, ?, ?, 0, ?, 0, ?)
	    ");
	
	    foreach ($products as $row) {
	
	        $stmtDetail->execute([
	            $countId,
	            $row['PRO_CODE'],
	            $row['SYSTEM_QTY'],
	            $row['ACTUAL_QTY'],
	            $row['UNIT_COST'],
	            $row['NOTE'] ?? ''
	        ]);
	
	    }
	
	    return $countId;
	}
	
}