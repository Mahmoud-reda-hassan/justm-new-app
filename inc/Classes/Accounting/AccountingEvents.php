<?php
include_once(__dir__.'/../../Classes/Accounting/JournalManager.php');
include_once(__dir__.'/../../Classes/Accounting/AccountSettings.php');

class AccountingEvents {

    // Database Connection
    protected $db;


    // Journal Manager
    protected JournalManager $journal;


    // Account Settings
    protected AccountSettings $settings;


    // Constructor
    public function __construct($db) {
        $this->db = $db;

        $this->journal = new JournalManager($db);

        $this->settings = new AccountSettings($db);
    }
    
    // جلب منشئ الحركه
    protected function getCurrentUserId(): int{
	    return (int)$_SESSION['USER_ID'];
	}
	
	// جلب فرع منشئ الحركه
	protected function getCurrentBranchId(): int{
	    return (int)$_SESSION['BRANCH_ID'];
	}
	
	// جلب بيانات أمر البيع
	protected function getSalesOrder(int $orderId): array{
		
	    $stmt = $this->db->prepare("
	        SELECT o.ORDER_ID,
			 o.SUB_ORDER_ID,
			 st.BRANCH_ID,
			 st.STO_ID   AS STORE_ID,
			 te.CLIENT_ID,
			 NOW(6) AS JOURNAL_DATE,
			 
			 SUM(op.COST_PRICE * op.DELIVERED_QTY) AS COST_AMOUNT,
			 SUM((op.PRICE-op.DISCOUNT) * DELIVERED_QTY) AS SALES_AMOUNT,
			 
			 SUM((op.PRICE-op.DISCOUNT) * RETURNED_QTY) AS RETURN_AMOUNT,
			 SUM(op.COST_PRICE * op.RETURNED_QTY) AS RETURN_COST_AMOUNT,
			 
			 o.ORDER_TYPE_ID AS ORDER_TYPE,
			 o.ORDER_STATUS,
			 
			 o.SALE_ENTRY_ID,
			 o.RETURN_ENTRY_ID
			FROM  orders o
			 LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
			 LEFT JOIN stores st ON o.STO_ID = st.STO_ID
			 LEFT JOIN telephones te ON te.TEL_ID = o.TEL_ID
			WHERE o.ORDER_ID = ?
			GROUP BY o.ORDER_ID
			LIMIT 1
	    ");
	
	    $stmt->execute([$orderId]);
	
	    $order = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$order) {
	        throw new Exception("Order not found.");
	    }
	
	    return $order;
	}
	
/*****************************************************
* تسليم المندوب: إثبات البيع وإيراد الشحن وتكلفة البضاعة
* (لا يغير الشاشات؛ يُستدعى من داخل معاملة تأكيد الشحنة)
*****************************************************/

	// جلب بيانات أمر شحن مندوب
	protected function getCourierOrder(int $orderId): array{
	    $stmt = $this->db->prepare("
	        SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_TYPE_ID, o.ORDER_STATUS,
	               o.SHIPPING_VAL, o.SHIPP_PAYMENT, o.SALE_ENTRY_ID,
	               st.BRANCH_ID, st.STO_ID AS STORE_ID, te.CLIENT_ID,
	               NOW(6) AS JOURNAL_DATE,
	               COALESCE(SUM((op.PRICE-op.DISCOUNT) * op.DELIVERED_QTY),0) AS SALES_AMOUNT,
	               COALESCE(SUM(op.COST_PRICE * op.DELIVERED_QTY),0)          AS COST_AMOUNT,
	               COALESCE(SUM(CASE WHEN op.DELIVERED_QTY > 0 THEN 1 ELSE 0 END),0) AS DELIVERED_LINES,
	               COALESCE(SUM(CASE WHEN op.DELIVERED_QTY > 0 AND (op.COST_PRICE IS NULL OR op.COST_PRICE <= 0) THEN 1 ELSE 0 END),0) AS INVALID_COST_LINES
	        FROM orders o
	        LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
	        LEFT JOIN stores st ON o.STO_ID = st.STO_ID
	        LEFT JOIN telephones te ON te.TEL_ID = o.TEL_ID
	        WHERE o.ORDER_ID = ?
	        GROUP BY o.ORDER_ID
	        LIMIT 1
	        FOR UPDATE
	    ");
	    $stmt->execute([$orderId]);
	    $order = $stmt->fetch(PDO::FETCH_ASSOC);
	    if (!$order) {
	        throw new Exception("Order not found.");
	    }
	    return $order;
	}

	/**
	 * قيد تسليم أمر شحن بالمندوب (مرة واحدة لكل أمر، محمي بـ SALE_ENTRY_ID).
	 * - تسليم كلي/جزئي (3,4): مدين العملاء بقيمة المسلَّم + الشحن، دائن المبيعات وإيراد الشحن،
	 *   مدين تكلفة البضاعة ودائن المخزون بتكلفة المسلَّم.
	 * - مرفوض بعد محاولة (5): إيراد الشحن فقط إذا دفعه العميل (SHIPP_PAYMENT=1).
	 * لا يسجل عمولة المندوب (تُسجل عند التحصيل) ولا حركة العهدة.
	 * @param bool $blockOnMissingCost true: امنع الترحيل لو منتج مُسلَّم بلا تكلفة. false: يرحّل المبيعات بدون تكلفة.
	 * @return int رقم القيد، أو 0 إن لم يكن هناك ما يُرحَّل.
	 */
	public function courierDelivery(int $orderId, bool $blockOnMissingCost = true): int{
	    $order  = $this->getCourierOrder($orderId);
	    $status = (int)$order['ORDER_STATUS'];

	    if ($order['SALE_ENTRY_ID'] !== null) {
	        throw new Exception("Order already posted.");
	    }
	    if (!in_array($status, [3, 4, 5], true)) {
	        throw new Exception("Order has not been delivered or rejected after an attempt.");
	    }
	    if ((int)($order['CLIENT_ID'] ?? 0) <= 0) {
	        throw new Exception("لا يمكن ترحيل التسليم: العميل غير مرتبط برقم هاتف الطلب.");
	    }

	    $shipping = round((float)$order['SHIPPING_VAL'], 2);
	    $sales    = 0.0;
	    $cost     = 0.0;

	    if ($status === 5) {
	        // مرفوض: لا بيع ولا تكلفة؛ إيراد الشحن فقط إذا دفعه العميل
	        if ((int)$order['SHIPP_PAYMENT'] !== 1) {
	            $shipping = 0.0;
	        }
	    } else {
	        if ((int)$order['DELIVERED_LINES'] <= 0) {
	            throw new Exception("Order has no delivered product lines.");
	        }
	        $sales = round((float)$order['SALES_AMOUNT'], 2);
	        $cost  = round((float)$order['COST_AMOUNT'], 2);
	        if ((int)$order['INVALID_COST_LINES'] > 0) {
	            if ($blockOnMissingCost) {
	                throw new Exception("Cannot post delivery: one or more delivered products have a missing or non-positive cost price.");
	            }
	            $cost = 0.0;
	        }
	    }

	    $receivable = round($sales + $shipping, 2);
	    if ($receivable <= 0) {
	        return 0; // لا شيء يستحق الترحيل (مثل رفض دون شحن)
	    }

	    $this->journal->reset();
	    $this->journal->create([
	        'branch_id'      => (int)$order['BRANCH_ID'],
	        'entry_date'     => $order['JOURNAL_DATE'],
	        'reference_type' => 'COURIER_DELIVERY',
	        'reference_id'   => $orderId,
	        'description'    => 'تسليم شحنة مندوب أمر رقم '.$order['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);

	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => (int)$order['CLIENT_ID'],
	        'debit'        => $receivable
	    ]);
	    if ($sales > 0) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->sales(),
	            'credit'     => $sales
	        ]);
	    }
	    if ($shipping > 0) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->shippingRevenue(),
	            'credit'     => $shipping
	        ]);
	    }
	    if ($cost > 0) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->cogs(),
	            'debit'      => $cost
	        ]);
	        $this->journal->addLine([
	            'account_id' => $this->settings->inventory(),
	            'store_id'   => (int)$order['STORE_ID'],
	            'credit'     => $cost
	        ]);
	    }

	    $journalId = $this->journal->post();

	    $stmt = $this->db->prepare("UPDATE orders SET SALE_ENTRY_ID = ? WHERE ORDER_ID = ? AND SALE_ENTRY_ID IS NULL");
	    $stmt->execute([$journalId, $orderId]);
	    if ($stmt->rowCount() !== 1) {
	        throw new Exception("Order was already posted or changed concurrently.");
	    }

	    return $journalId;
	}

	/**
	 * عكس قيد تسليم مندوب (عند رد الأمر للمخزن للتعديل).
	 * يُنشأ قيد عكسي ولا يُحذف الأصل، ثم يُفرَّغ SALE_ENTRY_ID ليُرحَّل الأمر من جديد بعد التعديل.
	 * يُمنع إن كان قد حُصّل من المندوب (ORDER_SUCCESS=1 أو فاتورة تحصيل ناجحة): يجب عكس التحصيل أولاً.
	 * @return int رقم القيد العكسي
	 */
	public function reverseCourierDelivery(int $orderId, string $reason): int{
	    if (trim($reason) === '') {
	        throw new Exception("Reason is required.");
	    }
	    $stmt = $this->db->prepare("SELECT ORDER_ID, SALE_ENTRY_ID, ORDER_SUCCESS FROM orders WHERE ORDER_ID = ? FOR UPDATE");
	    $stmt->execute([$orderId]);
	    $order = $stmt->fetch(PDO::FETCH_ASSOC);
	    if (!$order) {
	        throw new Exception("Order not found.");
	    }
	    if ($order['SALE_ENTRY_ID'] === null) {
	        throw new Exception("Order has no posted delivery entry.");
	    }
	    $inv = $this->db->prepare("SELECT COUNT(*) FROM invoices WHERE reference_type = 'SALES' AND reference_id = ? AND PAYMENT_SUCCESS = 1");
	    $inv->execute([$orderId]);
	    if ((int)$order['ORDER_SUCCESS'] === 1 || (int)$inv->fetchColumn() > 0) {
	        throw new Exception("Order already collected: reverse the collection first.");
	    }

	    $this->journal->reset();
	    $reverseId = $this->journal->reverse((int)$order['SALE_ENTRY_ID'], $reason);

	    $upd = $this->db->prepare("UPDATE orders SET SALE_ENTRY_ID = NULL WHERE ORDER_ID = ? AND SALE_ENTRY_ID = ?");
	    $upd->execute([$orderId, (int)$order['SALE_ENTRY_ID']]);
	    if ($upd->rowCount() !== 1) {
	        throw new Exception("Order changed concurrently.");
	    }
	    return $reverseId;
	}

	/**
	 * قيد استلام نقدية أمر شحن من المندوب (عند التحصيل في شاشة استلام العهدة).
	 * مدين الصندوق (فرع المستخدم) بالمبلغ المستلم، دائن العملاء (الطرف: العميل) بنفس المبلغ،
	 * ومعه عمولة المندوب إن وُجدت: مدين عمولات المندوبين (5700) / دائن عمولات مستحقة (2210، الطرف: المندوب).
	 * يُستدعى داخل معاملة التحصيل. إن لم يكن التسليم مُرحَّلاً يُرحِّله أولاً.
	 * @return int رقم القيد، أو 0 إن لم يكن هناك مبلغ ولا عمولة.
	 */
	public function courierCollection(int $orderId, int $deliveryUserId, float $amount, float $commission, bool $blockOnMissingCost = true, array $marketerCommissions = []): int{
	    $amount     = round($amount, 2);
	    $commission = round($commission, 2);
	    if ($amount < 0 || $commission < 0) {
	        throw new Exception("Collection amounts cannot be negative.");
	    }

	    $order = $this->getCourierOrder($orderId);
	    if ($order['SALE_ENTRY_ID'] === null && in_array((int)$order['ORDER_STATUS'], [3, 4, 5], true)) {
	        $this->courierDelivery($orderId, $blockOnMissingCost);
	    }
	    $mk = [];
	    foreach ($marketerCommissions as $row) {   // [[marketer_user_id, amount], ...] (المسوق ثم قائد الفريق)
	        $mid = (int)($row[0] ?? 0); $mamt = round((float)($row[1] ?? 0), 2);
	        if ($mid > 0 && $mamt > 0) { $mk[] = [$mid, $mamt]; }
	    }
	    if ($amount <= 0 && $commission <= 0 && !$mk) {
	        return 0;
	    }

	    $dup = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'COURIER_COLLECTION' AND reference_id = ? AND status = 'POSTED'");
	    $dup->execute([$orderId]);
	    if ((int)$dup->fetchColumn() > 0) {
	        throw new Exception("Collection already posted.");
	    }
	    if ($amount > 0 && (int)($order['CLIENT_ID'] ?? 0) <= 0) {
	        throw new Exception("لا يمكن ترحيل التحصيل: العميل غير مرتبط برقم هاتف الطلب.");
	    }
	    if ($commission > 0 && $deliveryUserId <= 0) {
	        throw new Exception("Delivery user is required for commission.");
	    }

	    $branchId = $this->getCurrentBranchId();
	    $this->journal->reset();
	    $this->journal->create([
	        'branch_id'      => $branchId,
	        'entry_date'     => $order['JOURNAL_DATE'],
	        'reference_type' => 'COURIER_COLLECTION',
	        'reference_id'   => $orderId,
	        'description'    => 'استلام نقدية من المندوب أمر رقم '.$order['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);

	    if ($amount > 0) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->cash($branchId),
	            'debit'      => $amount
	        ]);
	        $this->journal->addLine([
	            'account_id'   => $this->settings->customers(),
	            'partner_type' => 'CLIENT',
	            'partner_id'   => (int)$order['CLIENT_ID'],
	            'credit'       => $amount
	        ]);
	    }
	    if ($commission > 0) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->deliveryCommission(),
	            'debit'      => $commission
	        ]);
	        $this->journal->addLine([
	            'account_id'   => $this->settings->deliveryCommissionPayable(),
	            'partner_type' => 'DELIVERY',
	            'partner_id'   => $deliveryUserId,
	            'credit'       => $commission
	        ]);
	    }
	    foreach ($mk as [$mid, $mamt]) {
	        $this->journal->addLine([
	            'account_id' => $this->settings->marketerCommission(),
	            'debit'      => $mamt
	        ]);
	        $this->journal->addLine([
	            'account_id'   => $this->settings->marketerCommissionPayable(),
	            'partner_type' => 'MARKETER',
	            'partner_id'   => $mid,
	            'credit'       => $mamt
	        ]);
	    }
	    return $this->journal->post();
	}

	/**
	 * قيد صرف مستحقات مندوب/مسوق نقداً: مدين العمولات المستحقة (الطرف: المستفيد) / دائن الصندوق.
	 * @param string $partnerType 'DELIVERY' أو 'MARKETER'
	 * @param int $transactionId رقم الحركة المالية (financial_transactions) كمرجع
	 */
	public function commissionPayout(string $partnerType, int $partnerId, float $amount, int $transactionId): int{
	    $amount = round($amount, 2);
	    if ($amount <= 0 || $partnerId <= 0) {
	        throw new Exception("Invalid payout.");
	    }
	    if ($partnerType === 'DELIVERY') {
	        $payable = $this->settings->deliveryCommissionPayable(); $ref = 'DELIVERY_PAYOUT'; $label = 'صرف مستحقات مندوب';
	    } elseif ($partnerType === 'MARKETER') {
	        $payable = $this->settings->marketerCommissionPayable(); $ref = 'MARKETER_PAYOUT'; $label = 'صرف مستحقات مسوق';
	    } else {
	        throw new Exception("Unsupported partner type.");
	    }
	    $branchId = $this->getCurrentBranchId();
	    $this->journal->reset();
	    $this->journal->create([
	        'branch_id'      => $branchId,
	        'entry_date'     => date('Y-m-d H:i:s'),
	        'reference_type' => $ref,
	        'reference_id'   => $transactionId,
	        'description'    => $label,
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	    $this->journal->addLine([
	        'account_id'   => $payable,
	        'partner_type' => $partnerType,
	        'partner_id'   => $partnerId,
	        'debit'        => $amount
	    ]);
	    $this->journal->addLine([
	        'account_id' => $this->settings->cash($branchId),
	        'credit'     => $amount
	    ]);
	    return $this->journal->post();
	}

/*****************************************************
* المشتريات والموردون
*****************************************************/

	// قيد بسيط من طرفين (مدين/دائن) على حساب المورد والمخزون أو الصندوق
	protected function postSupplierEntry(string $refType, int $refId, string $desc, int $supplierId, float $amount, string $other, bool $supplierDebit, ?int $storeId = null): int{
	    $amount = round($amount, 2);
	    if ($supplierId <= 0 || $amount <= 0) {
	        throw new Exception("Invalid supplier amount.");
	    }
	    $branchId = $this->getCurrentBranchId();
	    $otherLine = ['account_id' => $other === 'cash' ? $this->settings->cash($branchId) : $this->settings->inventory()];
	    if ($other === 'inventory') {
	        if (!$storeId) { throw new Exception("Store is required."); }
	        $otherLine['store_id'] = $storeId;
	    }
	    $supLine = [
	        'account_id'   => $this->settings->suppliers(),
	        'partner_type' => 'SUPPLIER',
	        'partner_id'   => $supplierId
	    ];
	    $this->journal->reset();
	    $this->journal->create([
	        'branch_id'      => $branchId,
	        'entry_date'     => date('Y-m-d H:i:s'),
	        'reference_type' => $refType,
	        'reference_id'   => $refId,
	        'description'    => $desc,
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	    if ($supplierDebit) {
	        $this->journal->addLine($supLine + ['debit' => $amount]);
	        $this->journal->addLine($otherLine + ['credit' => $amount]);
	    } else {
	        $this->journal->addLine($otherLine + ['debit' => $amount]);
	        $this->journal->addLine($supLine + ['credit' => $amount]);
	    }
	    return $this->journal->post();
	}

	/** استلام بضاعة من مورد: مدين المخزون / دائن المورد. */
	public function supplierReceipt(int $purchaseOrderId, int $supplierId, int $storeId, float $amount): int{
	    return $this->postSupplierEntry('PURCHASE_RECEIPT', $purchaseOrderId, 'استلام بضاعة من مورد - أمر شراء '.$purchaseOrderId, $supplierId, $amount, 'inventory', false, $storeId);
	}

	/** استلام جزئي لأمر شراء: يحسب القيمة من الكميات المستلمة × سعر الشراء. @param array $rows [[ORDER_ID, PRO_CODE, QTY_SUPPLIED], ...] */
	public function purchasePartialReceipt(int $purchaseOrderId, array $rows, int $storeId): int{
	    $st = $this->db->prepare("SELECT PURCHASE_UNIT_PRICE FROM purchases_products WHERE ORDER_ID = ? AND PRO_CODE = ?");
	    $amount = 0.0;
	    foreach ($rows as $r) {
	        $st->execute([$purchaseOrderId, $r['PRO_CODE']]);
	        $price = $st->fetchColumn();
	        if ($price === false) { throw new Exception("Purchase line not found."); }
	        $amount += (float)$price * (float)$r['QTY_SUPPLIED'];
	    }
	    $so = $this->db->prepare("SELECT SUPPLIER_ID FROM purchases_orders WHERE ORDER_ID = ?");
	    $so->execute([$purchaseOrderId]);
	    $supplierId = (int)$so->fetchColumn();
	    return $this->supplierReceipt($purchaseOrderId, $supplierId, $storeId, $amount);
	}

	/** مرتجع مشتريات للمورد: مدين المورد / دائن المخزون. */
	public function supplierReturn(int $returnOrderId, int $supplierId, int $storeId, float $amount): int{
	    return $this->postSupplierEntry('PURCHASE_RETURN', $returnOrderId, 'مرتجع مشتريات للمورد - أمر '.$returnOrderId, $supplierId, $amount, 'inventory', true, $storeId);
	}

	/** دفعة للمورد: مدين المورد / دائن الصندوق. */
	public function supplierPayment(int $supplierId, float $amount, int $transactionId): int{
	    return $this->postSupplierEntry('SUPPLIER_PAYMENT', $transactionId, 'دفعة للمورد', $supplierId, $amount, 'cash', true);
	}

	/** تحصيل من المورد (مرتجع نقدي): مدين الصندوق / دائن المورد. */
	public function supplierRefund(int $supplierId, float $amount, int $transactionId): int{
	    return $this->postSupplierEntry('SUPPLIER_REFUND', $transactionId, 'تحصيل من مورد', $supplierId, $amount, 'cash', false);
	}

/*****************************************************
* البيع المباشر(دوال الاستخدام)
*****************************************************/
		
	// تحديث حالة الترحيل المحاسبى
	protected function markOrderPosted(int $orderId, int $journalId): void{
	    $stmt = $this->db->prepare("
	        UPDATE orders
	           SET SALE_ENTRY_ID = ?
	         WHERE ORDER_ID = ?
	    ");
	
	    $stmt->execute([
	        $journalId,
	        $orderId
	    ]);
	}
	
	// التحقق من صحة أمر البيع المباشر
	protected function validateDirectSale(array $order): void{
	    if ($order['ORDER_TYPE'] != 1) {
	        throw new Exception("Order is not direct sale.");
	    }
		
	    if (!in_array((int)$order['ORDER_STATUS'], [3, 4], true)) {
		    throw new Exception("Order has not been delivered.");
		}
		
		if ((int)$order['SALES_AMOUNT'] === 0) {
	        throw new Exception("Order sales amount is zero.");
	    }
	    
	    if ((int)$order['COST_AMOUNT'] === 0) {
	        throw new Exception("Order cost amount is zero.");
	    }
	    
	    if ($order['SALE_ENTRY_ID'] !== null) {
		    throw new Exception("Order already posted.");
		}
	    
	}
	
    // الدالة الرئيسيه لصرف المنتجات من المخزن للعميل فى حالة البيع المباشر
	public function directSale(int $orderId): int{
	    
	    // جلب بيانات أمر البيع
	    $order = $this->getSalesOrder($orderId);
	
	    // التحقق من حالة أمر البيع
	    $this->validateDirectSale($order);
	
	    // حساب إجمالى البيع والتكلفة
	    $salesAmount = (float)$order['SALES_AMOUNT'];
		$costAmount  = (float)$order['COST_AMOUNT'];
		
	    // إنشاء قيد اليومية
	    $this->journal->reset();

	    $this->journal->create([
	        'branch_id'      => $this->getCurrentBranchId(),
	        'entry_date'     => $order['JOURNAL_DATE'],
	        'reference_type' => 'DIRECT_SALE',
	        'reference_id'   => $orderId,
	        'description'    => 'بيع مباشر رقم '.$order['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	
	    // إثبات مديونية العميل
	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => $order['CLIENT_ID'],
	        'debit'        => $salesAmount
	    ]);
	
	    // إثبات إيراد المبيعات
	    $this->journal->addLine([
	        'account_id' => $this->settings->sales(),
	        'credit'     => $salesAmount
	    ]);
	
	    // إثبات تكلفة البضاعة
	    $this->journal->addLine([
	        'account_id' => $this->settings->cogs(),
	        'debit'      => $costAmount
	    ]);
	
	    // تخفيض المخزون
	    $this->journal->addLine([
	        'account_id' => $this->settings->inventory(),
	        'store_id'   => $order['STORE_ID'],
	        'credit'     => $costAmount
	    ]);
	
	    // ترحيل القيد
	    $journalId = $this->journal->post();
	
	    // ربط أمر البيع بالقيد
	    $this->markOrderPosted($orderId, $journalId);
	
	    return $journalId;
	}    
	
	//================================================
	
	// جلب بيانات تحصيل البيع المباشر
	protected function getDirectSalePayment(int $orderId): array{
	
	    $stmt = $this->db->prepare("
	        SELECT
	            FT.id,
	            FT.branch_id,
	            FT.transaction_date AS JOURNAL_DATE,
	            FT.invoice_id,
	            FT.amount,
	            FT.payment_method_id,
	            FT.entity_type,
	            FT.entity_id,
	            FT.status,
	            FT.accounting_posted,
	            I.reference_id,
	            I.reference_type,
	            O.ORDER_TYPE_ID,
	            O.SUB_ORDER_ID
	        FROM orders O 
	        LEFT JOIN invoices I ON O.ORDER_ID = I.reference_id AND I.reference_type = 'SALES'
	        LEFT JOIN financial_transactions FT ON I.INVO_ID = FT.invoice_id AND I.reference_type = 'SALES'
	        WHERE O.ORDER_ID = ?
	        LIMIT 1
	    ");
	
	    $stmt->execute([$orderId]);
	
	    $trx = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$trx) {
	        throw new Exception("Financial transaction not found.");
	    }
	
	    return $trx;
	}
	
	// التحقق من صحة تحصيل البيع المباشر
	protected function validateDirectSalePayment(array $trx): void{

	    if ($trx['reference_type'] !== 'SALES') {
	        throw new Exception("Transaction is not sales.");
	    }
	
	    if ((int)$trx['ORDER_TYPE_ID'] !== 1) {
	        throw new Exception("Order is not direct sale.");
	    }
	
	    if ($trx['entity_type'] !== 'CLIENT') {
	        throw new Exception("Transaction is not client payment.");
	    }
	
	    if ($trx['status'] !== 'COMPLETED') {
	        throw new Exception("Transaction is not completed.");
	    }
	
	    if ((float)$trx['amount'] <= 0) {
	        throw new Exception("Transaction amount is zero.");
	    }
	
	    if ((int)$trx['accounting_posted'] === 1) {
	        throw new Exception("Transaction already posted.");
	    }
	
	}
	
	// تحديث حالة ترحيل التحصيل
	protected function markPaymentPosted(int $transactionId, int $journalId): void{   
	
	    $stmt = $this->db->prepare("
	        UPDATE financial_transactions
	           SET accounting_posted   = 1,
	               accounting_entry_id = ?
	         WHERE id = ?
	    ");
	
	    $stmt->execute([
	        $journalId,
	        $transactionId
	    ]);
	
	}
	
	// الدالة الرئيسيه لسداد العميل لامر البيع المباشر
	public function directSalePayment(int $transactionId): int{
	
	    // جلب بيانات التحصيل
	    $trx = $this->getDirectSalePayment($transactionId);
	
	    // التحقق
	    $this->validateDirectSalePayment($trx);
	
	    // إنشاء القيد
	    $this->journal->reset();
	
	    $this->journal->create([
	        'branch_id'      => $trx['branch_id'],
	        'entry_date'     => $trx['JOURNAL_DATE'],
	        'reference_type' => 'DIRECT_SALE_PAYMENT',
	        'reference_id'   => $transactionId,
	        'description'    => 'تحصيل بيع مباشر رقم '.$trx['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	
	    // مدين الصندوق
	    $this->journal->addLine([
	        'account_id' => $this->settings->cash($trx['branch_id']),
	        'debit'      => (float)$trx['amount']
	    ]);
	
	    // دائن العملاء
	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => $trx['entity_id'],
	        'credit'       => (float)$trx['amount']
	    ]);
	
	    // ترحيل القيد
	    $journalId = $this->journal->post();
	
	    // تحديث الحركة
	    $this->markPaymentPosted($transactionId, $journalId);
	
	    return $journalId;
	}

/*****************************************************
* مرتجع بيع
*****************************************************/
	
	// التحقق من صحة مرتجع البيع 
	protected function validateSalesReturn(array $order): void {
	    if ((float)$order['RETURN_AMOUNT'] <= 0)
	        throw new Exception("No returned items.");
	
	    if ((float)$order['RETURN_COST_AMOUNT'] <= 0)
	        throw new Exception("Returned cost is zero.");
		
	    if (!in_array((int)$order['ORDER_STATUS'], [6, 7], true)) 
	        throw new Exception("Sales return already posted.");
	        
	    if (!empty($order['RETURN_ENTRY_ID'])) 
	        throw new Exception("Sales return already posted.");
	}
	
	// تحديث حالة ترحيل مرتجع البيع
	protected function markSalesReturnPosted(int $orderId, int $journalId): void{
	    $stmt = $this->db->prepare("
	        UPDATE orders
	        SET RETURN_ENTRY_ID = ?
	        WHERE ORDER_ID = ?
	    ");
	
	    $stmt->execute([
	        $journalId,
	        $orderId
	    ]);
	}
	
	/**
	 * قيد مرتجع أمر شحن مندوب (بعد التسليم والتحصيل) عند استلام المرتجعات في المخزن.
	 * يرحّل الفرق فقط بين إجمالي المرتجع الحالي وما رُحِّل سابقاً، فيدعم أكثر من استلام جزئي للأمر نفسه.
	 * مدين مردودات المبيعات / دائن العملاء، ومدين المخزون / دائن تكلفة البضاعة.
	 * يتجاهل (يرجع 0) الأمر الذي لم يُرحَّل بيعه (أوامر قديمة قبل التحويل) كي لا يخلق رصيداً دائناً بلا مقابل.
	 * @return int رقم القيد أو 0
	 */
	public function courierSalesReturn(int $orderId): int{
	    $order = $this->getSalesOrder($orderId);
	    if ($order['SALE_ENTRY_ID'] === null) {
	        return 0;
	    }
	    if (!in_array((int)$order['ORDER_STATUS'], [6, 7], true)) {
	        throw new Exception("Order has no return status.");
	    }
	    if ((int)($order['CLIENT_ID'] ?? 0) <= 0) {
	        throw new Exception("لا يمكن ترحيل المرتجع: العميل غير مرتبط برقم هاتف الطلب.");
	    }

	    $prev = $this->db->prepare("
	        SELECT COALESCE(SUM(CASE WHEN a.id = ? THEN d.debit ELSE 0 END),0) AS SALES_POSTED,
	               COALESCE(SUM(CASE WHEN a.id = ? THEN d.debit ELSE 0 END),0) AS COST_POSTED
	        FROM journal_entries e
	        JOIN journal_entry_details d ON d.journal_entry_id = e.id
	        JOIN accounts a ON a.id = d.account_id
	        WHERE e.reference_type = 'COURIER_SALES_RETURN' AND e.reference_id = ? AND e.status = 'POSTED'
	    ");
	    $prev->execute([$this->settings->salesReturns(), $this->settings->inventory(), $orderId]);
	    $p = $prev->fetch(PDO::FETCH_ASSOC);

	    $sales = round((float)$order['RETURN_AMOUNT'] - (float)$p['SALES_POSTED'], 2);
	    $cost  = round((float)$order['RETURN_COST_AMOUNT'] - (float)$p['COST_POSTED'], 2);
	    if ($sales <= 0) {
	        return 0;
	    }
	    if ($cost <= 0) {
	        throw new Exception("Returned cost is zero.");
	    }

	    $this->journal->reset();
	    $this->journal->create([
	        'branch_id'      => (int)$order['BRANCH_ID'],
	        'entry_date'     => $order['JOURNAL_DATE'],
	        'reference_type' => 'COURIER_SALES_RETURN',
	        'reference_id'   => $orderId,
	        'description'    => 'مرتجع أمر شحن رقم '.$order['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	    $this->journal->addLine(['account_id' => $this->settings->salesReturns(), 'debit' => $sales]);
	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => (int)$order['CLIENT_ID'],
	        'credit'       => $sales
	    ]);
	    $this->journal->addLine([
	        'account_id' => $this->settings->inventory(),
	        'store_id'   => (int)$order['STORE_ID'],
	        'debit'      => $cost
	    ]);
	    $this->journal->addLine(['account_id' => $this->settings->cogs(), 'credit' => $cost]);
	    $journalId = $this->journal->post();
	    $this->markSalesReturnPosted($orderId, $journalId);
	    return $journalId;
	}

	// الدالة الرئيسية لاستلام مرتجع مبيعات للمخزون
	public function salesReturn(int $orderId): int{
	    // جلب بيانات أمر المرتجع
	    $order = $this->getSalesOrder($orderId);
	
	    // التحقق من صحة المرتجع
	    $this->validateSalesReturn($order);
	
	    // قيمة البيع وتكلفة البضاعة المرتجعة
	    $salesAmount = (float)$order['RETURN_AMOUNT'];
	    $costAmount  = (float)$order['RETURN_COST_AMOUNT'];
	
	    // إنشاء القيد
	    $this->journal->reset();
	
	    $this->journal->create([
	        'branch_id'      => $order['BRANCH_ID'],
	        'entry_date'     => $order['JOURNAL_DATE'],
	        'reference_type' => 'SALES_RETURN',
	        'reference_id'   => $orderId,
	        'description'    => 'مرتجع بيع رقم '.$order['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	
	    // إلغاء الإيراد
	     $this->journal->addLine([
	        'account_id' => $this->settings->salesReturns(),
	        'debit'      => $salesAmount
	    ]);
	
	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => $order['CLIENT_ID'],
	        'credit'       => $salesAmount
	    ]);
	
	    // إعادة المخزون
	    $this->journal->addLine([
	        'account_id' => $this->settings->inventory(),
	        'store_id'   => $order['STORE_ID'],
	        'debit'      => $costAmount
	    ]);
	
	    $this->journal->addLine([
	        'account_id' => $this->settings->cogs(),
	        'credit'     => $costAmount
	    ]);
	
	    // ترحيل القيد
	    $journalId = $this->journal->post();
	
	    // تحديث حالة الترحيل
	    $this->markSalesReturnPosted($orderId, $journalId);
	
	    return $journalId;
	}
    
    //================================================
    
	// جلب بيانات صرف قيمة مرتجع البيع
	protected function getSalesReturnPayment(int $transactionId): array{
	
	    $stmt = $this->db->prepare("
	        SELECT
	            FT.id,
	            FT.branch_id,
	            FT.transaction_date AS JOURNAL_DATE,
	            FT.invoice_id,
	            FT.amount,
	            FT.payment_method_id,
	            FT.entity_type,
	            FT.entity_id,
	            FT.status,
	            FT.accounting_posted,
	            I.reference_id,
	            I.reference_type,
	            O.ORDER_TYPE_ID,
	            O.ORDER_STATUS,
	            O.SUB_ORDER_ID,
	            TC.name
	        FROM financial_transactions FT
	
	        INNER JOIN invoices I
	            ON I.INVO_ID = FT.invoice_id
	
	        INNER JOIN orders O
	            ON O.ORDER_ID = I.reference_id
	
	        INNER JOIN transaction_categories TC
	            ON TC.id = FT.category_id
	
	        WHERE FT.id = ?
	
	        LIMIT 1
	    ");
	
	    $stmt->execute([$transactionId]);
	
	    $trx = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$trx)
	        throw new Exception("Financial transaction not found.");
	
	    return $trx;
	}
	
	// التحقق من صحة صرف قيمة المرتجع
	protected function validateSalesReturnPayment(array $trx): void{
	
	    if ($trx['reference_type'] !== 'SALES')
	        throw new Exception("Transaction is not sales.");
	
	    /*if ((int)$trx['ORDER_TYPE_ID'] !== 1)
	        throw new Exception("Order is not direct sale.");*/
	
	    if (
	        (int)$trx['ORDER_STATUS'] !== 6 &&
	        (int)$trx['ORDER_STATUS'] !== 7
	    ){
	        throw new Exception("Order is not returned.");
	    }
	
	    if ($trx['entity_type'] !== 'CLIENT')
	        throw new Exception("Transaction is not client.");
	
	    if ($trx['status'] !== 'COMPLETED')
	        throw new Exception("Transaction is not completed.");
	
	    if ((float)$trx['amount'] <= 0)
	        throw new Exception("Transaction amount is zero.");
	
	    if ((int)$trx['accounting_posted'] === 1)
	        throw new Exception("Transaction already posted.");
	
	    // التأكد أنها حركة "مدفوعات مرتجع مبيعات"
	    if (trim($trx['name']) !== 'مدفوعات مرتجع مبيعات')
	        throw new Exception("Invalid transaction category.");
	
	}
    
    // صرف قيمة مرتجع بيع
	public function salesReturnPayment(int $transactionId): int{
	
	    // جلب بيانات الحركة المالية
	    $trx = $this->getSalesReturnPayment($transactionId);
		
	    // التحقق
	    $this->validateSalesReturnPayment($trx);
	
	    // إنشاء القيد
	    
	    $this->journal->create([
	        'branch_id'      => $trx['branch_id'],
	        'entry_date'     => $trx['JOURNAL_DATE'],
	        'reference_type' => 'SALES_RETURN_PAYMENT',
	        'reference_id'   => $transactionId,
	        'description'    => 'صرف قيمة مرتجع بيع رقم '.$trx['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
		
	    // مدين العملاء
	    $this->journal->addLine([
	        'account_id'   => $this->settings->customers(),
	        'partner_type' => 'CLIENT',
	        'partner_id'   => $trx['entity_id'],
	        'debit'        => (float)$trx['amount']
	    ]);
		
	    // دائن صندوق الفرع
	    $this->journal->addLine([
	        'account_id' => $this->settings->cash($trx['branch_id']),
	        'credit'     => (float)$trx['amount']
	    ]);
	    
	
	    // ترحيل القيد
	    $journalId = $this->journal->post();
	    // تحديث الحركة
	    $this->markPaymentPosted($transactionId, $journalId);
	 	
	    return $journalId;
	}
	
    
    
    
    
    
}