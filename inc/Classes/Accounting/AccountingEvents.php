<?php
include_once(__dir__.'/../../Classes/Accounting/JournalManager.php');
include_once(__dir__.'/../../Classes/Accounting/AccountSettings.php');
include_once(__dir__.'/../../Classes/Accounting/FinancialEventManager.php');

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

    /** تنفيذ الحدث المحاسبي كوحدة واحدة إذا لم تكن هناك معاملة خارجية. */
    private function runAtomically(callable $operation): int {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $operation();
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            return (int)$result;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
			 NOW() AS JOURNAL_DATE,
			 
			 SUM(op.COST_PRICE * op.DELIVERED_QTY) AS COST_AMOUNT,
			 COUNT(CASE WHEN COALESCE(op.DELIVERED_QTY, 0) > 0 THEN 1 END) AS DELIVERED_LINES_COUNT,
			 COUNT(CASE WHEN COALESCE(op.DELIVERED_QTY, 0) > 0
			                AND (op.COST_PRICE IS NULL OR op.COST_PRICE <= 0) THEN 1 END) AS INVALID_COST_LINES_COUNT,
			 SUM((op.PRICE-op.DISCOUNT) * DELIVERED_QTY) AS SALES_AMOUNT,
			 
			 SUM((op.PRICE-op.DISCOUNT) * op.RETURNED_QTY) AS RETURN_AMOUNT,
			 SUM(op.COST_PRICE * op.RETURNED_QTY) AS RETURN_COST_AMOUNT,
			 
			 o.ORDER_TYPE_ID AS ORDER_TYPE,
			 o.ORDER_BY,
			 o.ORDER_STATUS,
			 o.SHIPPING_VAL,
			 MAX(UA.USER_ID) AS MARKETER_ID,
			 MAX(UA.AFFILIATEED_WITH) AS LEADER_ID,
			 MAX(UA.COMMISSION_SALES) AS MARKETER_COMMISSION_RATE,
			 MAX(UB.TEAM_COMMISSION) AS LEADER_COMMISSION_RATE,
			 SUM(op.RETURNED_QTY) AS RETURNED_QTY,
			 o.SALE_ENTRY_ID,
			 o.RETURN_ENTRY_ID
			FROM  orders o
			 LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
			 LEFT JOIN stores st ON o.STO_ID = st.STO_ID
			 LEFT JOIN telephones te ON te.TEL_ID = o.TEL_ID
			 LEFT JOIN users_affiliate UA ON UA.USER_ID = o.ORDER_BY
			 LEFT JOIN users_affiliate UB ON UB.USER_ID = UA.AFFILIATEED_WITH
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
	

    /**
     * ترحيل رسوم الشحن كحدث مستقل عن بيع المنتجات.
     * ORDER_TYPE_ID = 5 هو البيع المباشر وفق جدول order_type؛ لا يثبت له شحن.
     * بقية أنواع الطلبات تثبت رسوم الشحن مرة واحدة على مستوى الطلب عند التسليم.
     */
    private function postOrderShippingCharge(int $orderId): int
    {
        $order = $this->getSalesOrder($orderId);
        $orderTypeId = (int)$order['ORDER_TYPE'];
        $shippingAmount = round((float)$order['SHIPPING_VAL'], 2);

        // البيع المباشر لا يحتوي رسوم شحن، والطلب بلا رسوم لا ينشئ حدثًا صفريًا.
        if ($orderTypeId === 5 || $shippingAmount <= 0) {
            return 0;
        }
        if (!in_array((int)$order['ORDER_STATUS'], [3, 4], true)) {
            throw new RuntimeException('لا يمكن إثبات إيراد الشحن قبل تسليم الطلب كليًا أو جزئيًا.');
        }

        $clientId = (int)$order['CLIENT_ID'];
        $branchId = (int)$order['BRANCH_ID'];
        if ($clientId <= 0 || $branchId <= 0) {
            throw new RuntimeException('بيانات العميل أو الفرع غير صالحة لترحيل رسوم الشحن.');
        }

        $eventManager = new FinancialEventManager($this->db);
        $result = $eventManager->post([
            'idempotency_key' => 'ORDER_SHIPPING:ORDER:' . $orderId . ':SHIPPING_REVENUE',
            'event_type' => 'SHIPPING_REVENUE_RECOGNIZED',
            'source_type' => 'ORDER',
            'source_id' => $orderId,
            'branch_id' => $branchId,
            'event_date' => (string)$order['JOURNAL_DATE'],
            'created_by' => $this->getCurrentUserId(),
            'payload' => [
                'order_id' => $orderId,
                'order_type_id' => $orderTypeId,
                'client_id' => $clientId,
                'shipping_amount' => $shippingAmount,
            ],
        ], function () use ($orderId, $orderTypeId, $clientId, $branchId, $shippingAmount): int {
            $lock = $this->db->prepare(
                'SELECT ORDER_ID FROM orders WHERE ORDER_ID = ? LIMIT 1 FOR UPDATE'
            );
            $lock->execute([$orderId]);
            if (!$lock->fetchColumn()) {
                throw new RuntimeException('أمر البيع غير موجود أثناء ترحيل رسوم الشحن.');
            }

            $fresh = $this->getSalesOrder($orderId);
            if (
                (int)$fresh['ORDER_TYPE'] !== $orderTypeId ||
                (int)$fresh['CLIENT_ID'] !== $clientId ||
                (int)$fresh['BRANCH_ID'] !== $branchId ||
                round((float)$fresh['SHIPPING_VAL'], 2) !== $shippingAmount ||
                !in_array((int)$fresh['ORDER_STATUS'], [3, 4], true)
            ) {
                throw new RuntimeException('تغيرت بيانات رسوم الشحن أو حالة الطلب أثناء الترحيل؛ أعد المحاولة.');
            }
            if ($orderTypeId === 5 || $shippingAmount <= 0) {
                throw new RuntimeException('لا ينطبق حدث إيراد الشحن على هذا الطلب.');
            }

            $this->journal->reset()->create([
                'branch_id' => $branchId,
                'entry_date' => $fresh['JOURNAL_DATE'],
                'reference_type' => 'ORDER_SHIPPING',
                'reference_id' => $orderId,
                'description' => 'إيراد شحن أمر بيع رقم ' . $fresh['SUB_ORDER_ID'],
                'created_by' => $this->getCurrentUserId(),
            ]);
            $this->journal->addLine([
                'account_id' => $this->settings->customers(),
                'partner_type' => 'CLIENT',
                'partner_id' => $clientId,
                'debit' => $shippingAmount,
                'reference_type' => 'ORDER_SHIPPING',
                'reference_id' => $orderId,
                'note' => 'مديونية رسوم شحن الطلب مرة واحدة',
            ]);
            $this->journal->addLine([
                'account_id' => $this->settings->shippingRevenue(),
                'credit' => $shippingAmount,
                'reference_type' => 'ORDER_SHIPPING',
                'reference_id' => $orderId,
                'note' => 'إيراد شحن الطلب مرة واحدة',
            ]);
            return $this->journal->post();
        });

        return (int)$result['journal_entry_id'];
    }

/*****************************************************
* البيع المباشر(دوال الاستخدام)
*****************************************************/
		
	// تحديث حالة الترحيل المحاسبى
	    protected function markOrderPosted(int $orderId, int $journalId): void {
        $stmt = $this->db->prepare("
            UPDATE orders SET SALE_ENTRY_ID = ?
             WHERE ORDER_ID = ? AND SALE_ENTRY_ID IS NULL
        ");
        $stmt->execute([$journalId, $orderId]);
        if ($stmt->rowCount() !== 1) {
            throw new Exception("Order was already posted or changed concurrently.");
        }
    }

	// التحقق من صحة أمر البيع المباشر
	protected function validateDirectSale(array $order): void{
	    // كل أنواع أوامر البيع المخزنية تُرحّل عند التسليم؛ نوع العميل/المسوق لا يلغي قيد البيع.
		
	    if (!in_array((int)$order['ORDER_STATUS'], [3, 4], true)) {
		    throw new Exception("Order has not been delivered.");
		}
		
		if ((float)$order['SALES_AMOUNT'] <= 0) {
	        throw new Exception("Order sales amount is zero.");
	    }
	    if ((int)($order['CLIENT_ID'] ?? 0) <= 0) {
	        throw new Exception("لا يمكن ترحيل البيع: العميل غير مرتبط برقم هاتف الطلب.");
	    }
	    // فحص تكلفة كل سطر تم تسليمه؛ SUM يتجاهل NULL وقد يخفي صنفًا بلا تكلفة.
	    if ((int)($order['DELIVERED_LINES_COUNT'] ?? 0) <= 0) {
	        throw new Exception("Order has no delivered product lines.");
	    }
	    if ((int)($order['INVALID_COST_LINES_COUNT'] ?? 0) > 0) {
	        throw new Exception("Cannot post sale: one or more delivered products have a missing or non-positive cost price.");
	    }
	    if ((float)$order['COST_AMOUNT'] <= 0) {
	        throw new Exception("Order cost amount is zero.");
	    }
	    
	    if ($order['SALE_ENTRY_ID'] !== null) {
		    throw new Exception("Order already posted.");
		}
	    
	}
	
    // البيع المباشر: قيمة المنتجات فقط، ولا تدخل رسوم الشحن في هذا القيد.
    public function directSale(int $orderId): int {
        if ($orderId <= 0) {
            throw new InvalidArgumentException('رقم أمر البيع غير صالح.');
        }

        $order = $this->getSalesOrder($orderId);
        $productsAmount = round((float)$order['SALES_AMOUNT'], 2);
        $costAmount = round((float)$order['COST_AMOUNT'], 2);
        $clientId = (int)$order['CLIENT_ID'];
        $storeId = (int)$order['STORE_ID'];
        $branchId = (int)$order['BRANCH_ID'];
        $deliveredLinesCount = (int)$order['DELIVERED_LINES_COUNT'];
        $invalidCostLinesCount = (int)$order['INVALID_COST_LINES_COUNT'];

        $eventManager = new FinancialEventManager($this->db);
        $result = $eventManager->post([
            'idempotency_key' => 'DIRECT_SALE:ORDER:' . $orderId . ':SALE_RECOGNIZED',
            'event_type' => 'SALE_RECOGNIZED',
            'source_type' => 'ORDER',
            'source_id' => $orderId,
            'branch_id' => $branchId,
            'event_date' => (string)$order['JOURNAL_DATE'],
            'created_by' => $this->getCurrentUserId(),
            'payload' => [
                'order_id' => $orderId,
                'client_id' => $clientId,
                'store_id' => $storeId,
                'products_amount' => $productsAmount,
                'cost_amount' => $costAmount,
                'delivered_lines_count' => $deliveredLinesCount,
                'invalid_cost_lines_count' => $invalidCostLinesCount,
            ],
        ], function () use (
            $orderId, $productsAmount, $costAmount, $clientId, $storeId,
            $branchId, $deliveredLinesCount, $invalidCostLinesCount
        ): int {
            // لا تُنفذ هذه الخطوات عند إعادة طلب حدث سبق ترحيله.
            // قفل رأس الطلب داخل معاملة الحدث قبل إعادة قراءة تفاصيله.
            $lockOrder = $this->db->prepare(
                "SELECT ORDER_ID FROM orders WHERE ORDER_ID = ? LIMIT 1 FOR UPDATE"
            );
            $lockOrder->execute([$orderId]);
            if (!$lockOrder->fetchColumn()) {
                throw new Exception("Order not found.");
            }

            // قفل بنود الطلب أيضًا حتى لا تتغير الكميات أو الأسعار أثناء احتساب القيد.
            $lockProducts = $this->db->prepare(
                "SELECT ORDER_ID FROM order_product WHERE ORDER_ID = ? FOR UPDATE"
            );
            $lockProducts->execute([$orderId]);
            $lockProducts->fetchAll(PDO::FETCH_COLUMN);

            $order = $this->getSalesOrder($orderId);
            $this->validateDirectSale($order);

            // تأكد أن بيانات الطلب لم تتغير بين تكوين بصمة الحدث وبدء الترحيل.
            if (
                (int)$order['CLIENT_ID'] !== $clientId ||
                (int)$order['STORE_ID'] !== $storeId ||
                (int)$order['BRANCH_ID'] !== $branchId ||
                (int)$order['DELIVERED_LINES_COUNT'] !== $deliveredLinesCount ||
                (int)$order['INVALID_COST_LINES_COUNT'] !== $invalidCostLinesCount ||
                round((float)$order['SALES_AMOUNT'], 2) !== $productsAmount ||
                round((float)$order['COST_AMOUNT'], 2) !== $costAmount
            ) {
                throw new RuntimeException('تغيرت بيانات البيع أثناء بدء الترحيل؛ أعد المحاولة بعد تحديث الطلب.');
            }

            $this->journal->reset();
            $this->journal->create([
                'branch_id' => (int)$order['BRANCH_ID'],
                'entry_date' => $order['JOURNAL_DATE'],
                'reference_type' => 'DIRECT_SALE',
                'reference_id' => $orderId,
                'description' => 'بيع مباشر رقم ' . $order['SUB_ORDER_ID'],
                'created_by' => $this->getCurrentUserId(),
            ]);

            // إثبات مديونية العميل بقيمة المنتجات فقط.
            $this->journal->addLine([
                'account_id' => $this->settings->customers(),
                'partner_type' => 'CLIENT',
                'partner_id' => $order['CLIENT_ID'],
                'debit' => $productsAmount,
            ]);

            // إثبات إيراد المنتجات فقط؛ لا يوجد حساب شحن في البيع المباشر.
            $this->journal->addLine([
                'account_id' => $this->settings->sales(),
                'credit' => $productsAmount,
            ]);

            // إثبات تكلفة البضاعة وتخفيض المخزون.
            $this->journal->addLine([
                'account_id' => $this->settings->cogs(),
                'debit' => $costAmount,
            ]);
            $this->journal->addLine([
                'account_id' => $this->settings->inventory(),
                'store_id' => $order['STORE_ID'],
                'credit' => $costAmount,
            ]);

            $journalId = $this->journal->post();
            $this->markOrderPosted($orderId, $journalId);
            return $journalId;
        });

        // الشحن حدث مستقل؛ لا يدخل في قيد مبيعات المنتجات.
        $this->postOrderShippingCharge($orderId);

        return (int)$result['journal_entry_id'];
    }
	
	//================================================
	
	// جلب بيانات تحصيل البيع المباشر
	protected function getDirectSalePayment(int $transactionId): array{
	    // جلب الحركة بواسطة رقمها الفعلي، وليس رقم الطلب.
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
	        FROM financial_transactions FT
	        INNER JOIN invoices I
	            ON I.INVO_ID = FT.invoice_id
	           AND I.REFERENCE_TYPE = 'SALES'
	        INNER JOIN orders O
	            ON O.ORDER_ID = I.REFERENCE_ID
	        WHERE FT.id = ?
	        LIMIT 1
	        FOR UPDATE
	    ");

	    $stmt->execute([$transactionId]);
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
	
	    // يدعم تحصيل كل أنواع أوامر البيع؛ نوع الأمر لا يغيّر طبيعة مديونية العميل.
	
	    if ($trx['entity_type'] !== 'CLIENT') {
	        throw new Exception("Transaction is not client payment.");
	    }
	    if ((int)($trx['entity_id'] ?? 0) <= 0) {
	        throw new Exception("Client ID is missing from the sales payment.");
	    }
	
	    if ($trx['status'] !== 'COMPLETED') {
	        throw new Exception("Transaction is not completed.");
	    }
	
	    if ((float)$trx['amount'] <= 0) {
	        throw new Exception("Transaction amount is zero.");
	    }

	    if (empty($trx['payment_method_id'])) {
	        throw new Exception("Payment method is required to determine the posting account.");
	    }
	
	    if ((int)$trx['accounting_posted'] === 1) {
	        throw new Exception("Transaction already posted.");
	    }
	
	}
	
	// تحديث حالة ترحيل التحصيل
	    protected function markPaymentPosted(int $transactionId, int $journalId): void {
        $stmt = $this->db->prepare("
            UPDATE financial_transactions
               SET accounting_posted = 1, accounting_entry_id = ?
             WHERE id = ?
               AND COALESCE(accounting_posted, 0) = 0
               AND accounting_entry_id IS NULL
        ");
        $stmt->execute([$journalId, $transactionId]);
        if ($stmt->rowCount() !== 1) {
            throw new Exception("Financial transaction was already posted or changed concurrently.");
        }
    }

    /**
     * تخصيص سداد العميل إلى سطر مديونية أمر البيع حتى يظهر الرصيد المفتوح بدقة.
     * يجب أن يكون جدول accounting_line_allocations مطبقًا قبل تفعيل ترحيل التحصيلات.
     */
    protected function allocateDirectSalePayment(int $orderId, int $customerId, int $journalId, float $amount): void {
        $orderStmt = $this->db->prepare("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID = ? FOR UPDATE");
        $orderStmt->execute([$orderId]);
        $saleEntryId = (int)$orderStmt->fetchColumn();
        if ($saleEntryId <= 0) {
            throw new Exception('لا يوجد قيد مبيعات معتمد لتخصيص التحصيل عليه.');
        }

        $customerAccountId = $this->settings->customers();
        $debitStmt = $this->db->prepare("SELECT id, debit FROM journal_entry_details
            WHERE journal_entry_id = ? AND account_id = ? AND partner_type = 'CLIENT'
              AND partner_id = ? AND debit > 0 ORDER BY id LIMIT 1 FOR UPDATE");
        $debitStmt->execute([$saleEntryId, $customerAccountId, $customerId]);
        $debitLine = $debitStmt->fetch(PDO::FETCH_ASSOC);
        if (!$debitLine) {
            throw new Exception('لم يتم العثور على مديونية العميل في قيد المبيعات.');
        }

        $usedStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM accounting_line_allocations
            WHERE debit_line_id = ? AND status = 'ACTIVE'");
        $usedStmt->execute([(int)$debitLine['id']]);
        $openAmount = round((float)$debitLine['debit'] - (float)$usedStmt->fetchColumn(), 2);
        if ($amount - $openAmount > 0.009) {
            throw new Exception('مبلغ التحصيل أكبر من الرصيد المفتوح لأمر البيع.');
        }

        $creditStmt = $this->db->prepare("SELECT id FROM journal_entry_details
            WHERE journal_entry_id = ? AND account_id = ? AND partner_type = 'CLIENT'
              AND partner_id = ? AND credit > 0 ORDER BY id LIMIT 1");
        $creditStmt->execute([$journalId, $customerAccountId, $customerId]);
        $creditLineId = (int)$creditStmt->fetchColumn();
        if ($creditLineId <= 0) {
            throw new Exception('تعذر تحديد سطر دائن العملاء في قيد التحصيل.');
        }

        $insert = $this->db->prepare("INSERT INTO accounting_line_allocations
            (debit_line_id, credit_line_id, amount, allocation_date, status, note, created_by)
            VALUES (?, ?, ?, NOW(), 'ACTIVE', ?, ?)");
        $insert->execute([
            (int)$debitLine['id'], $creditLineId, round($amount, 2),
            'تخصيص تحصيل مباشر لأمر بيع رقم ' . $orderId, $this->getCurrentUserId()
        ]);
    }

	// الدالة الرئيسيه لسداد العميل لامر البيع المباشر
	public function directSalePayment(int $transactionId): int {
        return $this->runAtomically(function () use ($transactionId) {
            return $this->directSalePaymentInternal($transactionId);
        });
    }

    private function directSalePaymentInternal(int $transactionId): int {
	
	    // جلب بيانات التحصيل
	    $trx = $this->getDirectSalePayment($transactionId);
	
	    // التحقق
	    $this->validateDirectSalePayment($trx);
	
	    // إنشاء القيد
	    $this->journal->reset();
	
	    $this->journal->create([
	        'branch_id'      => $trx['branch_id'],
	        'entry_date'     => $trx['JOURNAL_DATE'],
	        'reference_type' => 'SALE_PAYMENT',
	        'reference_id'   => $transactionId,
	        'description'    => 'تحصيل أمر بيع رقم '.$trx['SUB_ORDER_ID'],
	        'created_by'     => $this->getCurrentUserId()
	    ]);
	
	    // مدين الصندوق
	    $this->journal->addLine([
	        'account_id' => $this->settings->transactionAccount((int)$trx['payment_method_id'], (int)$trx['branch_id']),
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
	
	    // تخصيص التحصيل على مديونية أمر البيع قبل اعتماد الحركة.
	    $this->allocateDirectSalePayment((int)$trx['reference_id'], (int)$trx['entity_id'], $journalId, (float)$trx['amount']);

	    // تحديث الحركة
	    $this->markPaymentPosted($transactionId, $journalId);
	
	    return $journalId;
	}

/*****************************************************
* مرتجع بيع
*****************************************************/
	
	/**
	 * يمنع ترحيل مرتجع المبيعات قبل إثبات استلام الكميات المرتجعة مخزنيًا.
	 * تتم المطابقة على مستوى المنتج مع حركات RETURN_SALES المعتمدة والواردة.
	 */
	public function assertSalesReturnStockReceived(int $orderId): void {
	    $stmt = $this->db->prepare("
	        SELECT
	            COUNT(*) AS RETURNED_LINES,
	            SUM(
	                CASE
	                    WHEN COALESCE(SR.RECEIVED_QTY, 0) >= OP.RETURNED_QTY THEN 0
	                    ELSE 1
	                END
	            ) AS MISSING_LINES
	        FROM (
	            SELECT ORDER_ID, PRO_CODE, SUM(RETURNED_QTY) AS RETURNED_QTY
	            FROM order_product
	            WHERE ORDER_ID = ? AND RETURNED_QTY > 0
	            GROUP BY ORDER_ID, PRO_CODE
	        ) OP
	        LEFT JOIN (
	            SELECT
	                IPM.ORDER_ID,
	                IPM.IPM_CODE,
	                SUM(IPM.IPM_QTY) AS RECEIVED_QTY
	            FROM inventory_product_movement IPM
	            INNER JOIN inventory_movement IM ON IM.IM_ID = IPM.IM_ID
	            WHERE IM.IM_MOVE_TYPE = 'RETURN_SALES'
	              AND IM.REFERENCE = CAST(IPM.ORDER_ID AS CHAR)
	              AND IM.IM_APPROVAL_DISBURSED = 1
	              AND IM.IM_APPROVAL_RECEIVED = 1
	              AND IPM.IPM_TYPE_MOV = 'IN'
	            GROUP BY IPM.ORDER_ID, IPM.IPM_CODE
	        ) SR ON SR.ORDER_ID = OP.ORDER_ID AND SR.IPM_CODE = OP.PRO_CODE
	    ");
	    $stmt->execute([$orderId]);
	    $stock = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

	    if ((int)($stock['RETURNED_LINES'] ?? 0) < 1) {
	        throw new Exception("لا توجد كميات مرتجعة مسجلة على أمر البيع رقم {$orderId}.");
	    }

	    if ((int)($stock['MISSING_LINES'] ?? 0) > 0) {
	        throw new Exception("لا يمكن ترحيل المرتجع المالي قبل استلام كل الكميات المرتجعة مخزنيًا لأمر البيع رقم {$orderId}.");
	    }
	}

	// التحقق من صحة مرتجع البيع
	protected function validateSalesReturn(array $order): void {
	    if ((float)$order['RETURN_AMOUNT'] <= 0) {
	        throw new Exception("No returned items.");
	    }

	    if ((float)$order['RETURN_COST_AMOUNT'] <= 0) {
	        throw new Exception("Returned cost is zero.");
	    }

	    if (!in_array((int)$order['ORDER_STATUS'], [6, 7], true)) {
	        throw new Exception("Sales return already posted.");
	    }

	    if (!empty($order['RETURN_ENTRY_ID'])) {
	        throw new Exception("Sales return already posted.");
	    }
	}

	// تحديث حالة ترحيل مرتجع البيع
	protected function markSalesReturnPosted(int $orderId, int $journalId): void {
	    $stmt = $this->db->prepare("
	        UPDATE orders SET RETURN_ENTRY_ID = ?
	        WHERE ORDER_ID = ? AND RETURN_ENTRY_ID IS NULL
	    ");
	    $stmt->execute([$journalId, $orderId]);
	    if ($stmt->rowCount() !== 1) {
	        throw new Exception("Sales return was already posted or changed concurrently.");
	    }
	}

	// الدالة الرئيسية لاستلام مرتجع مبيعات للمخزون
	public function salesReturn(int $orderId): int {
        return $this->runAtomically(function () use ($orderId) {
            return $this->salesReturnInternal($orderId);
        });
    }

    private function salesReturnInternal(int $orderId): int {
	    // جلب بيانات أمر المرتجع
	    $order = $this->getSalesOrder($orderId);
	
	    // التحقق من صحة المرتجع
	    $this->validateSalesReturn($order);
	    $this->assertSalesReturnStockReceived($orderId);
	
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

        // عكس عمولة المسوق وقائد الفريق عن الكميات المرتجعة بعد استلامها مخزنيًا.
        // لا يتجاوز العكس ما سبق إثباته فعليًا في حساب المستحقات لنفس الطلب.
        if ((int)$order['ORDER_TYPE'] === 2 && (int)($order['MARKETER_ID'] ?? 0) > 0) {
            $payableAccountId = $this->settings->marketerCommissionPayable();
            $expenseAccountId = $this->settings->marketerCommissionExpense();
            $returnedQty = max(0.0, (float)($order['RETURNED_QTY'] ?? 0));
            $marketerId = (int)$order['MARKETER_ID'];
            $leaderId = (int)($order['LEADER_ID'] ?? 0);
            $marketerExpected = round($returnedQty * (float)$order['MARKETER_COMMISSION_RATE'], 2);
            $leaderExpected = ($leaderId > 0 && $leaderId !== $marketerId)
                ? round($returnedQty * (float)$order['LEADER_COMMISSION_RATE'], 2)
                : 0.0;

            $accruedStmt = $this->db->prepare("SELECT COALESCE(SUM(d.credit - d.debit),0)
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                WHERE d.account_id = ? AND d.partner_type = 'MARKETER' AND d.partner_id = ?
                  AND d.reference_type = 'MARKETER_COMMISSION_ACCRUAL'
                  AND d.reference_id = ?");
            $accruedStmt->execute([$payableAccountId, $marketerId, $orderId]);
            $marketerAccrued = max(0.0, round((float)$accruedStmt->fetchColumn(), 2));
            $marketerReverse = min($marketerExpected, $marketerAccrued);

            if ($leaderId === $marketerId && $leaderId > 0) {
                $leaderExpected += $marketerExpected;
                $marketerExpected = $leaderExpected;
                $marketerReverse = min($marketerExpected, $marketerAccrued);
                $leaderReverse = 0.0;
            } else {
                $leaderReverse = 0.0;
                if ($leaderId > 0 && $leaderExpected > 0) {
                    $accruedStmt->execute([$payableAccountId, $leaderId, $orderId]);
                    $leaderAccrued = max(0.0, round((float)$accruedStmt->fetchColumn(), 2));
                    $leaderReverse = min($leaderExpected, $leaderAccrued);
                }
            }

            if ($marketerReverse > 0) {
                $this->journal->addLine([
                    'account_id' => $payableAccountId,
                    'partner_type' => 'MARKETER',
                    'partner_id' => $marketerId,
                    'debit' => $marketerReverse,
                    'reference_type' => 'MARKETER_COMMISSION_REVERSAL',
                    'reference_id' => $orderId,
                    'note' => 'عكس عمولة المسوق عن الكميات المرتجعة',
                ]);
                $this->journal->addLine([
                    'account_id' => $expenseAccountId,
                    'credit' => $marketerReverse,
                    'reference_type' => 'MARKETER_COMMISSION_REVERSAL',
                    'reference_id' => $orderId,
                    'note' => 'عكس مصروف عمولة المسوق عن المرتجع',
                ]);
            }
            if ($leaderReverse > 0) {
                $this->journal->addLine([
                    'account_id' => $payableAccountId,
                    'partner_type' => 'MARKETER',
                    'partner_id' => $leaderId,
                    'debit' => $leaderReverse,
                    'reference_type' => 'MARKETER_COMMISSION_REVERSAL',
                    'reference_id' => $orderId,
                    'note' => 'عكس عمولة قائد الفريق عن الكميات المرتجعة',
                ]);
                $this->journal->addLine([
                    'account_id' => $expenseAccountId,
                    'credit' => $leaderReverse,
                    'reference_type' => 'MARKETER_COMMISSION_REVERSAL',
                    'reference_id' => $orderId,
                    'note' => 'عكس مصروف عمولة قائد الفريق عن المرتجع',
                ]);
            }
        }
    
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

	    if (empty($trx['payment_method_id']))
	        throw new Exception("Payment method is required to determine the posting account.");
	
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
	public function salesReturnPayment(int $transactionId): int {
        return $this->runAtomically(function () use ($transactionId) {
            return $this->salesReturnPaymentInternal($transactionId);
        });
    }

    private function salesReturnPaymentInternal(int $transactionId): int {
	
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
	        'account_id' => $this->settings->transactionAccount((int)$trx['payment_method_id'], (int)$trx['branch_id']),
	        'credit'     => (float)$trx['amount']
	    ]);
	    
	
	    // ترحيل القيد
	    $journalId = $this->journal->post();
	    // تحديث الحركة
	    $this->markPaymentPosted($transactionId, $journalId);
	 	
	    return $journalId;
	}
	
    
    
    
    
    
}