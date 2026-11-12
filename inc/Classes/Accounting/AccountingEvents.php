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