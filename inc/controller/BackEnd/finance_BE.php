<?php
/*
* ملحوظة مهمة جدا:-
* CREDIT = اضافه او له
* DEBIT  = صرف او عليه
*/
include_once(__dir__.'/../../configDateTimeFN.php');
include_once(__dir__.'/../../Classes/Accounting/AccountingEvents.php');
include_once(__dir__.'/../../Classes/Accounting/SupplierAccountingManager.php');
class finance_BE {

	public $transaction_date;
	public $transaction_type;
	public $invoice_id;
	public $entity_type;
	public $entity_id;
	public $amount;
	public $balance_before;
	public $balance_after;
	public $payment_method_id;
	public $category_id;
	public $status;
	public $note;
	
	public $user_group;
	public $transaction_user_id ;
	private $dtz ;
	private $Accounting;
    private $conn;

	public function __construct 		($db) {
        $this->conn = $db;
        $this->dtz = new configDateTime();
        $this->Accounting = new AccountingEvents($db);
        $this->transaction_user_id = $_SESSION['USER_ID'] ;
    }
    
	private function acquireGlobalFinanceLock(): bool {
        $stmt = $this->conn->prepare("SELECT GET_LOCK('global_Finance_lock', 2) AS locked");
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
    private function releaseGlobalFinanceLock(): void {
        $stmt = $this->conn->prepare("SELECT RELEASE_LOCK('global_Finance_lock') AS released");
        $stmt->execute();
    }
    
/*=========================================
 التعامل مع مشتريات واصدة ومدفوعات العميل
=========================================*/

	/*order seles*/ 
	/*function restClientdebt_old($orderid){
   		$sqlOrderData = "SELECT * FROM (SELECT 
				        A.CLIENT_ID,
				        O.ORDER_ID,
				        O.ORDER_STATUS,
						PB.BALANCE,
				        O.EXISTS_ID,
				        SUM(( OP.PRICE - OP.DISCOUNT ) * OP.QTY ) 
				        + (CASE WHEN O.ORDER_STATUS = 5 THEN 0 ELSE O.SHIPPING_VAL END) AS TOTAL_ORDER_PRICE,
				        SUM(( OP.PRICE - OP.DISCOUNT ) * 
				            ( CASE WHEN OP.PRO_STATUSE IN ('rejected','partial') 
				            THEN ( OP.QTY - OP.DELIVERED_QTY ) ELSE 0 END ) 
				        ) AS TOTAL_REFUND_ORDER
				    FROM orders O
          			LEFT JOIN partners_balance PB ON O.ORDER_ID = PB.REFERENCE_ID AND PARTNER_TYPE = 'CLIENT' AND TRANSACTION_CATEGORY_ID = 8
				    JOIN order_product OP ON O.ORDER_ID = OP.ORDER_ID
				    JOIN addresses A ON O.ADDRESS_ID = A.ADDRESS_ID
				    GROUP BY A.CLIENT_ID, O.ORDER_ID, O.ORDER_STATUS, O.EXISTS_ID ) tbl";
  		
  		$sql = "UPDATE partners_balance AS P
				JOIN (".$sqlOrderData.") AS TBL 
				  ON P.PARTNER_ID = TBL.CLIENT_ID 
				  AND P.REFERENCE_ID = TBL.ORDER_ID
				SET P.BALANCE = CASE WHEN TBL.EXISTS_ID = 5 THEN 0 ELSE (TBL.TOTAL_ORDER_PRICE - TBL.TOTAL_REFUND_ORDER) END,
				P.LAST_UPDATE = NOW(6)
				WHERE P.PARTNER_TYPE = 'CLIENT' 
				  AND P.REFERENCE_ID = :order_id;";

		$restClientDebtCh = $this->conn->prepare($sqlOrderData . " WHERE ORDER_ID = :order_id ");
		$restClientDebtCh->bindValue(":order_id", $orderid);
		$restClientDebtCh->execute();
		$row = $restClientDebtCh->fetch(PDO::FETCH_ASSOC);
			
		if($row['ORDER_ID']){
			$restClientDebt = $this->conn->prepare($sql);
			$restClientDebt->bindValue(":order_id", $orderid);
			$restClientDebt->execute();
			$result = $restClientDebt;
			return [
				"statuse" 		=> $result ? true:false,
				"result"		=> $restClientDebt->rowCount(),
				"message"		=> $result ? "تم تحديث ارصدة العميل بنجاح" : "غير قادر على تحديث ارصدة العميل",
				"insertedId" 	=> $result ? $this->conn->lastInsertId() : null
			];
		}else if($restClientDebtCh->rowCount()  == 1 ){
			$amount = 0;
			if ($row['EXISTS_ID'] != 5) {
				$amount = $row['TOTAL_ORDER_PRICE'] - $row['TOTAL_REFUND_ORDER'];
			}	    		
			return  $this->addClientDebt($row['CLIENT_ID'], $row['ORDER_ID'], $amount);
		}else{
			return [
				"statuse" 		=> false,
				"result"		=> 0,
				"message"		=> "غير قادر على تحديث ارصدة العميل",
				"insertedId" 	=> null
			];
		}
	}*/
  	
  	// يتم استدعاؤه عن تحديث بينات امر البيع.
  	function restClientdebt($orderid) {
	    $sqlOrderData = "
	        SELECT 
	            A.CLIENT_ID,
	            O.ORDER_ID,
	            O.ORDER_STATUS,
	            O.EXISTS_ID,
	            SUM((OP.PRICE - OP.DISCOUNT) * OP.QTY)
	            + (CASE WHEN O.ORDER_STATUS = 5 THEN 0 ELSE O.SHIPPING_VAL END) AS TOTAL_ORDER_PRICE,
	            SUM((OP.PRICE - OP.DISCOUNT) * 
	                (CASE WHEN OP.PRO_STATUSE IN ('rejected','partial') 
	                THEN (OP.QTY - OP.DELIVERED_QTY) ELSE 0 END)
	            ) AS TOTAL_REFUND_ORDER
	        FROM orders O
	        JOIN order_product OP ON O.ORDER_ID = OP.ORDER_ID
	        JOIN addresses A ON O.ADDRESS_ID = A.ADDRESS_ID
	        WHERE O.ORDER_ID = :order_id
	        GROUP BY A.CLIENT_ID, O.ORDER_ID, O.ORDER_STATUS, O.EXISTS_ID
	    ";
	
	    $stmt = $this->conn->prepare($sqlOrderData);
	    $stmt->bindValue(":order_id", $orderid);
	    $stmt->execute();
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$row) {
	        return [
	            "statuse" => false,
	            "result" => 0,
	            "message" => "لم يتم العثور على بيانات للطلب المحدد"
	        ];
	    }
	
	    // تحديث الرصيد إن كان موجودًا
	    $updateSql = "
	        UPDATE partners_balance AS P
	        SET 
	            P.BALANCE = CASE WHEN :exists_id = 5 THEN 0 ELSE (:total_order - :refund_order) END,
	            P.LAST_UPDATE = NOW(6)
	        WHERE P.PARTNER_TYPE = 'CLIENT'
	          AND P.REFERENCE_ID = :order_id
	          AND P.PARTNER_ID = :client_id
	    ";
	
	    $upd = $this->conn->prepare($updateSql);
	    $upd->execute([
	        ':exists_id' => $row['EXISTS_ID'],
	        ':total_order' => $row['TOTAL_ORDER_PRICE'],
	        ':refund_order' => $row['TOTAL_REFUND_ORDER'],
	        ':order_id' => $row['ORDER_ID'],
	        ':client_id' => $row['CLIENT_ID']
	    ]);
	
	    if ($upd->rowCount() > 0) {
	        return [
	            "statuse" => true,
	            "result" => $upd->rowCount(),
	            "message" => "تم تحديث أرصدة العميل بنجاح"
	        ];
	    } else {
	        // إضافة سجل جديد إن لم يكن موجود
	        $amount = ($row['EXISTS_ID'] == 5) ? 0 : ($row['TOTAL_ORDER_PRICE'] - $row['TOTAL_REFUND_ORDER']);
	        return $this->addClientDebt($row['CLIENT_ID'], $row['ORDER_ID'], $amount);
	    }
	}
  	
  	// يتم استدعاؤه عند تسجيل فاتورة آجلة للعميل.
    function addClientDebt($client_id, $order_id, $amount, $category_id = 8) {
	    $sqlcheck  = "SELECT ID FROM  partners_balance WHERE PARTNER_TYPE = 'CLIENT' AND PARTNER_ID = :client_id AND REFERENCE_ID = :order_id AND TRANSACTION_CATEGORY_ID = :category_id";
	    $sqlUpdate = "UPDATE partners_balance SET BALANCE = :amount WHERE PARTNER_TYPE = 'CLIENT' AND PARTNER_ID = :client_id AND REFERENCE_ID = :order_id AND TRANSACTION_CATEGORY_ID = :category_id";
	    $sqlInsert = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE,TRANSACTION_CATEGORY_ID) VALUES ('CLIENT', :client_id, :order_id, 'DEBIT', :amount, 'مديونية مستحقة على العميل لفاتورة مبيعات',:category_id)";
	    
	    $addClientDebt = $this->conn->prepare($sqlcheck);
	    $addClientDebt->bindValue(":client_id", $client_id);
		$addClientDebt->bindValue(":order_id", $order_id);
		$addClientDebt->bindValue(":category_id", $category_id);
	    $addClientDebt->execute();
	    $result = $addClientDebt;
	    if($result->rowCount()){
	    	$addClientDebt1 = $this->conn->prepare($sqlUpdate);
	    	$addClientDebt1->bindValue(":client_id", $client_id);
		    $addClientDebt1->bindValue(":order_id", $order_id);
		    $addClientDebt1->bindValue(":amount", $amount);
		    $addClientDebt1->bindValue(":category_id", $category_id);
		    $addClientDebt1->execute();	
		    return [
                "statuse" 		=> $addClientDebt1->rowCount(),
                "result"		=> $addClientDebt1->rowCount(),
                "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
                "insertedId" 	=> $result ? $this->conn->lastInsertId() : null
            ];
	    }else{
	    	$addClientDebt2 = $this->conn->prepare($sqlInsert);
		    $addClientDebt2->bindValue(":client_id", $client_id);
		    $addClientDebt2->bindValue(":order_id", $order_id);
		    $addClientDebt2->bindValue(":amount", $amount);
		    $addClientDebt2->bindValue(":category_id", $category_id);
		    $addClientDebt2->execute();
		    return [
		    	"statuse" 		=> $addClientDebt2->rowCount(),
                "result"		=> $addClientDebt2->rowCount(),
                "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
                "insertedId" 	=> $result ? $this->conn->lastInsertId() : null
            ];
	    }
	}
    
    //يتم استدعاؤه عند دفع العميل جزءًا من المبلغ المستحق
	function addClientCredit($client_id, $order_id, $amount) {
	    $sql = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE,TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
	            VALUES ('CLIENT', :client_id, :order_id, 'CREDIT', :amount, 'رصيد دائن نتيجة تحصيلات مبيعات','9',1)";
	    $addClientCredit = $this->conn->prepare($sql);
	    $addClientCredit->bindValue(":client_id", $client_id);
		$addClientCredit->bindValue(":order_id", $order_id);
		$addClientCredit->bindValue(":amount", $amount);
	    $result = $addClientCredit->execute();
	    return [
	    	"statuse" 		=> $result ? true : false,
            "result"		=> $addClientCredit->rowCount(),
            "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
            "insertedId" 	=> $result ? $this->conn->lastInsertId() : null
        ];
	}
    
    //يتم استدعاؤه عند دفع العميل جزءًا من المبلغ المستحق
	/*function addClientCredit55($client_id, $order_id, $amount) {
	    $sql = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE,TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
	            VALUES ('CLIENT', :client_id, :order_id, 'CREDIT', :amount, 'رصيد دائن نتيجة تحصيلات مبيعات','9',1)";
	    $addClientCredit = $this->conn->prepare($sql);
	    $addClientCredit->bindValue(":client_id", $client_id);
		$addClientCredit->bindValue(":order_id", $order_id);
		$addClientCredit->bindValue(":amount", $amount);
	    $result = $addClientCredit->execute();
	    return [
	    	"statuse" 		=> $result ? true : false,
            "result"		=> $addClientCredit->rowCount(),
            "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
            "insertedId" 	=> $result ? $this->conn->lastInsertId() : null
        ];
	}*/
	
	//  يتم استدعاؤه عند تسجيل مرتجع للعميل، ليكون لديه رصيد دائن يمكنه استخدامه لاحقًا
	function reduceClientCredit($client_id, $order_id) {
	    //جلب قيمة الرصيد المرتجع من منتجات امر البيع والتحقق مما اذا كان تم تسجيل مرتجعات سابقه لهذا الامر او لا
	    $sql0 = "-- CTE لحساب RETURN_PRICE
				WITH return_price_per_order AS (
					SELECT 
						ORDER_ID, 
						SUM((PRICE - DISCOUNT) * RETURNED_QTY) AS RETURN_PRICE
					FROM order_product
					GROUP BY ORDER_ID
				),
				
				-- CTE لاختيار أول صف من partners_balance لكل ORDER_ID
				first_balance_per_order AS (
					SELECT *
					FROM (
						SELECT *,
							   ROW_NUMBER() OVER (PARTITION BY REFERENCE_ID ORDER BY ID) AS rn
						FROM partners_balance
						WHERE TRANSACTION_CATEGORY_ID = 14
						  AND PARTNER_TYPE = 'CLIENT'
					) AS ranked
					WHERE rn = 1
				)
				
				-- الاستعلام النهائي
				SELECT  
					A.ORDER_ID,
					B.RETURN_PRICE,
					C.BALANCE
				FROM orders A
				LEFT JOIN return_price_per_order B ON B.ORDER_ID = A.ORDER_ID
				LEFT JOIN first_balance_per_order C ON C.REFERENCE_ID = A.ORDER_ID
				WHERE A.ORDER_ID = :order_id;";
		// فى حالة عدم وجود مرتجعات مسجله مسبقا يتم تسجيل سجل جديد
		$sql1 = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
	            VALUES ('CLIENT', :client_id, :order_id, 'CREDIT', :amount, 'رصيد دائن نتيجة ارجاع منتجات','14',1)";
	    // فى حالة وجود سجل مسجل بالفعل يتم تحديثه
	    $sql2 = "UPDATE partners_balance  
	            SET BALANCE = :amount  
	            WHERE 
	            	REFERENCE_ID 				= :order_id
	    			AND PARTNER_ID 				= :client_id 
		            AND PARTNER_TYPE 			= 'CLIENT' 
		            AND TRANSACTION_TYPE 		= 'CREDIT' 
		            AND TRANSACTION_CATEGORY_ID ='14'";
	    //يستخدم فى حالى حذف قيمة المرتجع 
	    $sql3 = "DELETE FROM partners_balance 
	    		WHERE 
	    			REFERENCE_ID 				= :order_id
	    			AND PARTNER_ID 				= :client_id 
	    			AND PARTNER_TYPE 			= 'CLIENT' 
	    			AND TRANSACTION_TYPE 		= 'CREDIT' 
	    			AND TRANSACTION_CATEGORY_ID = 14";
	    
	    $reduceClientDebt0 = $this->conn->prepare($sql0);
		$reduceClientDebt0->bindValue(":order_id", $order_id);
		$reduceClientDebt0->execute();
		$row = $reduceClientDebt0->fetch(PDO::FETCH_ASSOC);
		$parms =[];
		$inserted = false;
		if(!$row['BALANCE'] && $row['RETURN_PRICE'] > 0){
			//INSERT
			$inserted = true;
			$sql = $sql1;
			$parms = [
		        ':client_id' => $client_id,
		        ':order_id'  => $order_id,
		        ':amount'    => $row['RETURN_PRICE']
		    ];
		}else if($row['BALANCE'] && $row['RETURN_PRICE'] > 0){
			//UPDATE
			$sql = $sql2;
			$parms = [
		        ':client_id' => $client_id,
		        ':order_id'  => $order_id,
		        ':amount'    => $row['RETURN_PRICE']
		    ];
		}else if($row['RETURN_PRICE'] == 0){
			//DELETE
			$sql = $sql3;
			$parms = [
		        ':client_id' => $client_id,
		        ':order_id'  => $order_id,
		    ];
		}
	
	    $reduceClientDebt1 = $this->conn->prepare($sql);
	    $result =  $reduceClientDebt1->execute($parms); 
	    return [
			"statuse" 		=> $result ? true:false,
		    "result"		=> $reduceClientDebt1->rowCount(),
		    "message"		=> $result ? "update partners_balance successful" : "update partner balance failed",
		    "insertedId" 	=> $inserted ? $this->conn->lastInsertId() : null
		];
	}
	
	// يتم استدعاؤه عندما يريد العميل استخدام رصيده الدائن لدفع فاتورة جديدة.
	function useClientCredit($pdo, $client_id, $credit_used) {
	    $sql = "UPDATE partners_balance  
	            SET BALANCE = BALANCE - :credit_used  
	            WHERE PARTNER_TYPE = 'CLIENT' AND PARTNER_ID = :client_id AND TRANSACTION_TYPE = 'CREDIT'";
	    
	    $stmt = $pdo->prepare($sql);
	    return $stmt->execute([
	        ':client_id' => $client_id,
	        ':credit_used' => $credit_used
	    ]);
	}
/*=========================================
 التعامل مع مشتريات الشركه من المورد ارصده ومدفوعات 
=========================================*/
    
    //يتم استدعاؤه عند تسجيل أمر شراء مؤجل الدفع
	function addSupplierDebt($pdo,$supplier_id, $order_id, $amount) {
	    
		$sql = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
		        VALUES ('SUPPLIER', :supplier_id, :order_id, 'CREDIT', :amount, 'مديونية مستحقة على الشركة لأمر شراء مؤجل',10,1)";
		try{
		    $stmtaddSupplierDebt = $pdo->prepare($sql);
		    $stmtaddSupplierDebt->bindValue(":supplier_id", $supplier_id);
		    $stmtaddSupplierDebt->bindValue(":order_id", $order_id);
		    $stmtaddSupplierDebt->bindValue(":amount", $amount);
		    
		    $result = $stmtaddSupplierDebt->execute(); 
		   
            return [
                "result"		=> $stmtaddSupplierDebt->rowCount(),
                "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
                "insertedId" 	=> $result ? $pdo->lastInsertId() : null
            ];
		} catch (PDOException $e) {
	        throw $e;
		}
	}
    
    //يتم استدعاؤه عند تسجيل أمر شراء مؤجل الدفع
	function reduceSupplierDebt($pdo,$supplier_id, $order_id, $amount) {
	    
		$sql = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
		        VALUES ('SUPPLIER', :supplier_id, :order_id, 'DEBIT', :amount, 'مديونية مستحقة على الشركة لأمر شراء مؤجل',10,1)";
		try{
		    $stmtaddSupplierDebt = $pdo->prepare($sql);
		    $stmtaddSupplierDebt->bindValue(":supplier_id", $supplier_id);
		    $stmtaddSupplierDebt->bindValue(":order_id", $order_id);
		    $stmtaddSupplierDebt->bindValue(":amount", $amount);
		    
		    $result = $stmtaddSupplierDebt->execute(); 
		   
            return [
                "result"		=> $stmtaddSupplierDebt->rowCount(),
                "message"		=> $result ? "Insert into partners_balance successful" : "Insert into $tableName failed",
                "insertedId" 	=> $result ? $pdo->lastInsertId() : null
            ];
		} catch (PDOException $e) {
	        throw $e;
		}
	}
    
    //يتم استخدامه عند تسجيل دفعة للمورد لتقليل مديونية الشركة.
	/*function reduceSupplierDebt($pdo, $supplier_id, $payment_amount) {
	    $sql = "UPDATE partners_balance  
	            SET BALANCE = BALANCE - :payment_amount  
	            WHERE PARTNER_TYPE = 'SUPPLIER' AND PARTNER_ID = :supplier_id AND TRANSACTION_TYPE = 'DEBIT'";
	
	    $stmt = $pdo->prepare($sql);
	    return $stmt->execute([
	        ':supplier_id' => $supplier_id,
	        ':payment_amount' => $payment_amount
	    ]);
	}*/
    
    //يتم استدعاؤه عند تسجيل مرتجع مشتريات لإضافة رصيد دائن للمورد.
	function addSupplierCredit($pdo, $supplier_id, $return_order_id, $return_amount) {
	    $sql = "INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)
	            VALUES ('SUPPLIER', :supplier_id, :return_order_id, 'DEBIT', :return_amount, 'رصيد مدين نتيجة مرتجع مشتريات',15,1)";
	    
	    $stmt = $pdo->prepare($sql);
	    return $stmt->execute([
	        ':supplier_id' => $supplier_id,
	        ':return_order_id' => $return_order_id,
	        ':return_amount' => $return_amount
	    ]);
	}
	
	// يتم استدعائه عندما عندما تريد الشركه استخدام الرصيد المدين للمورد لسداد فواتيره
	function useSupplierCredit($pdo, $supplier_id, $credit_used) {
	    $sql = "UPDATE partners_balance  
	            SET BALANCE = BALANCE - :credit_used  
	            WHERE PARTNER_TYPE = 'SUPPLIER' AND PARTNER_ID = :supplier_id AND TRANSACTION_TYPE = 'DEBIT'";
	    
	    $stmt = $pdo->prepare($sql);
	    return $stmt->execute([
	        ':supplier_id' => $supplier_id,
	        ':credit_used' => $credit_used
	    ]);
	}
/*=========================================
 التعامل مع الفواتير 
=========================================*/
	
	// تسجيل الفاتورة
	public function Add_invoice($referenceId, $referenceType, $totalPrice, $returnPrice, $amountPaid, $paymentSuccess){
		// تحقق من القيم المدخلة
		
		if (empty($referenceId) || empty($referenceType) || empty($totalPrice) || !isset($returnPrice) || !isset($amountPaid) || !isset($paymentSuccess)) {
			return false;
		}

		// إعداد الاستعلام
		$stmt = $this->conn->prepare('INSERT INTO invoices (REFERENCE_ID, REFERENCE_TYPE, TOTAL_PRICE, RETURN_PRICE, AMOUNT_PAID, PAYMENT_SUCCESS) VALUES (?, ?, ?, ?, ?, ?)');

		// ربط القيم مع التحقق
		$stmt->bindValue(1, $referenceId, 	PDO::PARAM_INT);
		$stmt->bindValue(2, $referenceType, PDO::PARAM_STR);
		$stmt->bindValue(3, $totalPrice, 	PDO::PARAM_STR);
		$stmt->bindValue(4, $returnPrice, 	PDO::PARAM_STR);
		$stmt->bindValue(5, $amountPaid, 	PDO::PARAM_STR);
		$stmt->bindValue(6, $paymentSuccess,PDO::PARAM_INT);

		// تنفيذ الاستعلام
		if (!$stmt->execute()) {
			return false;
		}

		// الحصول على آخر ID
		$invoiceId = $this->conn->lastInsertId();
		return $invoiceId ?: false;
	}
    
    // تحديث فاتورة المبيعات
    public function Update_invoice($referenceId, $paymentSuccess){
		// تحقق من القيم المدخلة
		
		if (empty($referenceId) || !isset($paymentSuccess)) {
			return false;
		}
		
		//  جلب الـ invoice id 
	    $stmtGet = $this->conn->prepare("
	        SELECT INVO_ID 
	        FROM invoices 
	        WHERE REFERENCE_ID = ? 
	        AND REFERENCE_TYPE = 'SALES'
	        LIMIT 1
	    ");
	
	    $stmtGet->execute([$referenceId]);
	    $invoice = $stmtGet->fetch(PDO::FETCH_ASSOC);
	
	    if (!$invoice) {
	        return false;
	    }
	
	    $invoiceId = $invoice['INVO_ID'];

    
		// إعداد الاستعلام
		$stmt = $this->conn->prepare("
			UPDATE invoices I
			LEFT JOIN (
				SELECT  
					ORDER_ID,
					Sum(QTY * ( PRICE - DISCOUNT )) AS TOTAL_PRICE,
					Sum(CASE WHEN PRO_STATUSE IN('rejected','partial') THEN ( ( QTY - DELIVERED_QTY ) * ( PRICE - DISCOUNT ) ) ELSE 0 END)   AS RETURN_PRICE,
					Sum(CASE WHEN PRO_STATUSE IN('refund','partial_refund') THEN ( RETURNED_QTY * ( PRICE - DISCOUNT ) ) ELSE 0 END)   AS REFUND_PRICE
				FROM   order_product
				GROUP BY ORDER_ID
			) AS OP ON I.REFERENCE_ID = OP.ORDER_ID
			SET I.TOTAL_PRICE = OP.TOTAL_PRICE, 
				I.RETURN_PRICE = OP.RETURN_PRICE,
				I.REFUND_PRICE = OP.REFUND_PRICE,
				I.AMOUNT_PAID = ((OP.TOTAL_PRICE - OP.RETURN_PRICE) - OP.REFUND_PRICE), 
				I.PAYMENT_SUCCESS = ?
			WHERE I.REFERENCE_ID  = ?
			AND I.REFERENCE_TYPE  = 'SALES'
		");

		// ربط القيم مع التحقق
		$stmt->bindValue(1, $paymentSuccess,PDO::PARAM_INT);
		$stmt->bindValue(2, $referenceId, 	PDO::PARAM_INT);
		// تنفيذ الاستعلام
		if (!$stmt->execute() || $stmt->rowCount() == 0) {
			return false;
		}

		
		return $invoiceId;
	}
	
    //تسجيل المعامله فى ايرادات الخزنه
	public function Add_transaction($invoice_id, $entity_type, $entity_id, $amount, $payment_method_id, $category_id, $status, $note = ''){
	    // تحقق من القيم المدخلة
	    if (empty($entity_type) || empty($entity_id) || empty($amount) || empty($payment_method_id) || empty($category_id) || empty($status)) {
	        return false;
	    }
	
	    // إعداد الاستعلام
	    $stmt = $this->conn->prepare("INSERT INTO financial_transactions (branch_id, transaction_user_id,invoice_id, entity_type, entity_id, amount, payment_method_id, category_id, status, note) VALUES (?,?,?,?,?,?,?,?,?,?)");
	
	    // ربط القيم
	    $userId = $this->transaction_user_id;
	    $stmt->bindValue(1 , $_SESSION['BRANCH_ID'], PDO::PARAM_INT);
	    $stmt->bindValue(2 , $userId, PDO::PARAM_INT);
	    $stmt->bindValue(3 , $invoice_id, PDO::PARAM_INT);
	    $stmt->bindValue(4 , $entity_type, PDO::PARAM_STR);
	    $stmt->bindValue(5 , $entity_id, PDO::PARAM_INT);
	    $stmt->bindValue(6 , $amount, PDO::PARAM_STR);
	    $stmt->bindValue(7 , $payment_method_id, PDO::PARAM_INT);
	    $stmt->bindValue(8 , $category_id, PDO::PARAM_INT);
	    $stmt->bindValue(9 , $status, PDO::PARAM_STR);
	    $stmt->bindValue(10, $note, PDO::PARAM_STR);
	
	    // تنفيذ الاستعلام
	    if (!$stmt->execute()) {
	        return false;
	    }
		
	    // تحقق من إدخال البيانات
	    return (int) $this->conn->lastInsertId();
	}
    
    //تسجيل ارصدة العملاء والموردين والمنسقين والمندوبين
	public function Add_partners_balance($partner_type , $partner_id, $reference_id, $transaction_type, $balance, $note='',$transaction_category_id =null){
		$stmt = $this->conn->prepare('INSERT INTO partners_balance(PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, transaction_category_id) VALUES (?,?,?,?,?,?,?)');
		$stmt->bindValue(1 , $partner_type, 	PDO::PARAM_STR);
	    $stmt->bindValue(2 , $partner_id, 		PDO::PARAM_INT);
	    $stmt->bindValue(3 , $reference_id, 	PDO::PARAM_INT);
	    $stmt->bindValue(4 , $transaction_type, PDO::PARAM_STR);
	    $stmt->bindValue(5 , $balance, 			PDO::PARAM_STR);
	    $stmt->bindValue(6 , $note,				PDO::PARAM_STR);
	    $stmt->bindValue(7 , $transaction_category_id, PDO::PARAM_STR);
		$stmt->execute();
		if(!$stmt->rowCount()){
			 return false;
		}
		return true;
	}
    
    //خذف العهدة من حساب المندوب
	private function delete_custody($orderid, $deliveryid, $custody_return = false) {
		if(!$custody_return){
			
			$query = "DELETE FROM custody_orders WHERE ORDER_ID = :oid AND USER_ID = :delid ";
			$stCust0 = $this->conn->prepare($query);
			$stCust0->bindValue(':oid', $orderid, PDO::PARAM_INT);
			$stCust0->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
			if ($stCust0->execute() && $stCust0->rowCount()) {
				return true;
			}
		}else{
			$query = "SELECT COUNT(ORDER_ID) FROM custody_return_orders WHERE ORDER_ID = :oid AND USER_ID = :delid AND RETURNS = 1 ";
			$stCust1 = $this->conn->prepare($query);
			$stCust1->bindValue(':oid', $orderid, PDO::PARAM_INT);
			$stCust1->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
			if ($stCust1->execute() && $stCust1->rowCount() == 0) return true;
			
			$query = "DELETE FROM custody_return_orders WHERE ORDER_ID = :oid AND USER_ID = :delid ";
			$stCust2 = $this->conn->prepare($query);
			$stCust2->bindValue(':oid', $orderid, PDO::PARAM_INT);
			$stCust2->bindValue(':delid', $deliveryid, PDO::PARAM_INT);
			if ($stCust2->execute()) {
				
				return true;
			}
			
		}
		return false;
	}

/*=========================================
  اذن استلام نقدية
=========================================*/

    //التحصيل من المندوب
	public function registerCollection($delivery_id, $orderIds, $totalAmount) {
		
		try{
			$error = array();
			$errorlastupdate=0;
			$errorlastupdatemsg = array();
			// بداية المعاملة
			$query = "SELECT 
						CO.ORDER_ID,
						O.SUB_ORDER_ID,
						O.ORDER_TYPE_ID,
						O.ORDER_BY,
						CO.ORDER_STATUSE,
						CO.CUST_STAT,
						CO.RETURNS,
						O.ORDER_STATUS,
						O.ORDER_SUCCESS,
						O.LAST_UPDATE,
						TE.CLIENT_ID,
						CO.USER_ID AS DELIVERY_ID,
						ROUND((if(CO.CUST_STAT = 'FREEZE' ,0,O.SHIPPING_VAL) * UD.PERCENT / 100),2) AS DELIVERY_CIMMISSION,
						UA.AFFILIATEED_WITH AS LEADER_ID,
						Round(SUM(CP.DELIVERED_QTY)*UB.TEAM_COMMISSION,2) LEADER_COMMISSION,
						UA.USER_ID AS MARKETER_ID,
						Round(SUM(CP.DELIVERED_QTY)*UA.COMMISSION_SALES,2) AS MARKETER_COMMISSION,
						Round(Sum(CP.QTY * CP.PRICE), 2)                                       AS TOTAL_PRICE,
						Round(Sum(CP.QTY * CP.DISCOUNT), 2)                                    AS TOTAL_DISCOUNT,
						
						Round(Sum( if( CO.ORDER_STATUSE in(4,5) AND CO.RETURNS <> 1 , 0 ,  (CP.QTY - CP.DELIVERED_QTY) * (CP.PRICE - CP.DISCOUNT))) , 2) AS TOTAL_REJECTED_PRICE,
						
						Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * (CP.PRICE - CP.DISCOUNT) ), 2) AS TOTAL_DELIVERED_PRICE,
						Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * CP.DISCOUNT), 2) AS TOTAL_DELIVERED_DISCOUNT,
						
						Round(Sum(( CASE WHEN CO.ORDER_STATUSE <> 3 AND CO.RETURNS <> 1 THEN CP.QTY ELSE CP.DELIVERED_QTY END ) * ( CP.PRICE - CP.DISCOUNT )), 2) AS ACTUAL_TOTAL,
						
						Round(if(CO.CUST_STAT = 'FREEZE' ,0,O.SHIPPING_VAL), 2) AS SHIPPING_VAL,
						Round(Sum(if(CO.RETURNS = 0 ,CP.QTY ,CP.DELIVERED_QTY) * ( CP.PRICE - CP.DISCOUNT )) + if(CO.CUST_STAT = 'FREEZE',0,O.SHIPPING_VAL), 2) AS REQUIRED_PAYMENT,
						Json_arrayagg(
							Json_object(
								'PRODUCT_CODE', VOP.PRO_CODE, 
								'PRODUCT_NAME', VOP.PRO_NAME, 
								'PRODUCT_ATTRIBUTES', Ifnull(VOP.OPT_JSON, ''), 
								'ORDER_QTY', Format(CP.QTY, 2), 
								'RETURNED_QTY', Format(IF(CO.RETURNS = 0, 0, CP.QTY - CP.DELIVERED_QTY), 2),
								'DELIVERED_QTY', Format(IF(CO.RETURNS = 0, CP.QTY, CP.DELIVERED_QTY), 2), 
								'PRICE', Format(CP.PRICE, 2), 
								'DISCOUNT', Format(CP.DISCOUNT, 2), 
								'REQUIRED_PAYMENT', Format(IF(CO.RETURNS = 0, CP.QTY, CP.DELIVERED_QTY) * (CP.PRICE - CP.DISCOUNT), 2)
							)
						) AS PRODUCTS_DATA
					FROM custody_orders AS CO 
					LEFT JOIN orders AS O ON CO.ORDER_ID = O.ORDER_ID
					LEFT JOIN custody_product AS CP ON CO.ORDER_ID = CP.ORDER_ID
					LEFT JOIN users_affiliate AS UA ON O.ORDER_BY = UA.USER_ID
					LEFT JOIN users_affiliate AS UB ON UA.AFFILIATEED_WITH = UB.USER_ID
					LEFT JOIN users_delivery  AS UD ON CO.USER_ID = UD.USER_ID
					LEFT JOIN telephones      AS TE ON TE.TEL_ID = O.TEL_ID
					LEFT JOIN 
					(   SELECT  
						 CP.ORDER_ID,
						 CP.USER_ID,
						 CP.PRO_CODE,
						 PR.PRO_NAME,
						 GROUP_CONCAT(DISTINCT CONCAT(E.OPTION_NAME, ' : ', D.VALUE)) AS OPT_JSON
						FROM custody_product AS CP
						 LEFT JOIN product_codes AS PC ON CP.PRO_CODE = PC.PRO_CODE
						 LEFT JOIN products AS PR ON PC.PRODUCT_ID = PR.PRO_ID
						 LEFT JOIN product_code_join_option AS C ON PC.PRO_CODE = C.PRODUCT_CODE
						 LEFT JOIN product_option AS D ON D.ID = C.OPTION_VALUE_ID
						 LEFT JOIN product_option_group AS E ON E.OPTION_ID = D.OPTION_ID
						GROUP BY CP.ORDER_ID, CP.USER_ID, CP.PRO_CODE, PR.PRO_NAME
					) AS VOP ON CP.ORDER_ID = VOP.ORDER_ID AND CP.PRO_CODE = VOP.PRO_CODE AND CP.USER_ID = VOP.USER_ID
					WHERE FIND_IN_SET (CO.ORDER_ID ,:oids)
				GROUP BY CO.ORDER_ID, O.SUB_ORDER_ID, O.ORDER_TYPE_ID, O.ORDER_BY, O.SHIPPING_VAL, O.ORDER_STATUS, O.ORDER_SUCCESS, O.LAST_UPDATE";
			$oIds = htmlspecialchars(strip_tags(trim(implode(',',array_keys($orderIds)))));
			$this->conn->beginTransaction();
			
			// الحصول على قفل عالمي لمنع التعارض مع حركات أخرى
			$this->acquireGlobalFinanceLock();
			
			$stmt = $this->conn->prepare($query);
			$stmt->bindValue(':oids', $oIds, PDO::PARAM_STR);
			$stmt->execute();
			$orders = array();
			while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$orders[$record['ORDER_ID']]=$record;
			}
			$totalCHECK = 0;
			$totatShipp = 0;
			$shippCommission = 0;
			$orderData ;
			$err = 0;
			
			// التكرار عبر أوامر البيع
			foreach (array_keys($orderIds) as $orderId) {
					$err = 0;
					// التحقق من وجود أمر البيع
				if (!isset($orders[$orderId])) {
						$err ++;
						$error[] = "أمر البيع رقم {$orderId} غير موجود!";
				}else{
						$lastUpdateNaw = $orders[$orderId]['LAST_UPDATE'];
						$lastUpdateOld = $orderIds[$orderId][6];
						if($lastUpdateNaw != $lastUpdateOld){
							$err ++;
							$error[] = 'خطاء فى تنفيذ الاجراء بسبب قيام موظف اخر بتحديث البيانات';
							$errorlastupdatemsg[] =  $orders[$orderId]['SUB_ORDER_ID'].' تم تحديث بيانات من قبل مستخدم اخر!';
							$errorlastupdate++;
						}
						if($orders[$orderId]['CUST_STAT'] != 'FREEZE' && //اذاكان امر البيع مجمد على عهدة المندوب
							(
								($orders[$orderId]['ORDER_STATUSE'] == 4 && $orders[$orderId]['RETURNS'] == 0) || // اذا كان امر البيع تسليم جزئى ولم يقم المندوب بتسليم المرتجعات
								 $orders[$orderId]['ORDER_STATUSE'] == 5 || 
								 $orders[$orderId]['ORDER_STATUSE'] <  3 // اذا كان حالة امر البيع قيد الشحن او اقل
							)
						){
							$err ++;
							$error[] = 'حالة امر البيع لا تسمح بتنفيذ هذا الاجراء';
							$errorlastupdatemsg[] =  $orders[$orderId]['SUB_ORDER_ID'].' حالة امر البيع لا تسمح بتنفيذ هذا الاجراء!';
							$errorlastupdate++;
						}
						$orderData = $orders[$orderId];
						if($err > 0 ){
							continue; // الانتقال إلى أمر البيع التالى
						}
				}
					//===============================================
				if($err === 0){
						// تسجيل فاتورة مبيعات المنتجات
						$invoice_id1 = $this->Add_invoice($orderData['ORDER_ID'], 'SALES', $orderData['TOTAL_DELIVERED_PRICE'], $orderData['TOTAL_REJECTED_PRICE'], $orderData['ACTUAL_TOTAL'], 1);

						if(!$invoice_id1){
							$error[] = "Invoice Record Number is null";
							continue;
						}
						// اضافة المعامله
						$transactionRecorded1 = $this->Add_transaction($invoice_id1, 'CLIENT',$orderData['CLIENT_ID'], $orderData['ACTUAL_TOTAL'],1, 9 ,'COMPLETED','ORDER_PRODUCTS');
						if(!$transactionRecorded1){
							$error[] = "Unable to record financial1 transaction";
							continue;
						}
						// ترحيل التحصيل إلى دفتر الأستاذ وتخصيصه على مديونية أمر البيع.
						$this->Accounting->directSalePayment((int)$transactionRecorded1);
						// تسجيل مبلغ السداد فى رصيد العميل
						$resultFinal = $this->addClientCredit($orderData['CLIENT_ID'], $orderId,  $orderData['ACTUAL_TOTAL']+$orderData['SHIPPING_VAL']);
						if(!$resultFinal['statuse']){
							$error[] = "An error occurred while add ORDERS_LOG.";
							continue;
						}
						//===============================================
						
						//تسجيل فاتورة خدمة الشحن
						if($orderData['SHIPPING_VAL']>0){
							// اضافة المعامله
							$transactionRecorded2 = $this->Add_transaction($invoice_id1, 'CLIENT', $orderData['CLIENT_ID'], $orderData['SHIPPING_VAL'],1, 9 ,'COMPLETED','ORDER_SHIPPING');
							if(!$transactionRecorded2){
								$error[] = "Unable to record financial2 transaction";
								continue;
							}
							// رسوم الشحن حركة تحصيل مستقلة لكنها تخص نفس مديونية أمر البيع.
							$this->Accounting->directSalePayment((int)$transactionRecorded2);
						}
						// تحديث رصيد المندوب اذا كان الامر تم تسليمه عن طريق الشحن
						if($orderData['DELIVERY_ID']){
							$update_delevery_balance = $this->Add_partners_balance( 'DELIVERY', $orderData['DELIVERY_ID'], $orderData['ORDER_ID'], 'CREDIT', $orderData['DELIVERY_CIMMISSION'],'عمولة مستحقة للمندوب من خدمة الشحن','12');
							if(!$update_delevery_balance){
								$error[] = "An error occurred while updating the delegate's balance.";
								continue;
							}
						}
						// تحديث رصيد المسوق ان كان نوع الامر من المسوق
						if($orderData['ORDER_TYPE_ID'] == 2  && $orderData['MARKETER_ID'] && $orderData['CUST_STAT'] !='FREEZE'){
							$update_marketer_balance = $this->Add_partners_balance( 'MARKETER', $orderData['MARKETER_ID'], $orderData['ORDER_ID'], 'CREDIT', $orderData['MARKETER_COMMISSION'],'عمولة مستحقة للمسوق من امر البيع','12' );
							if(!$update_marketer_balance){
								$error[] = "An error occurred while updating the MARKETER's balance.";
								continue;
							}
							// تحديث رصيد قائد المسوق ان وجد قائد للمسوق
							if($update_marketer_balance && $orderData['ORDER_TYPE_ID'] == 2 && $orderData['LEADER_ID'] && $orderData['CUST_STAT'] !='FREEZE'){
								$update_mleader_balance = $this->Add_partners_balance( 'MARKETER', $orderData['LEADER_ID'], $orderData['ORDER_ID'], 'CREDIT', $orderData['LEADER_COMMISSION'], 'عمولة مستحقة للمسوق من الفريق' ,'12');
								if(!$update_mleader_balance){
									$error[] = "An error occurred while updating the leader's balance.";
									continue;
								}
							}
						}
						// تحديث حالة أمر البيع
						$stmt = $this->conn->prepare("UPDATE orders SET ORDER_SUCCESS = 1 ,LAST_UPDATE = NOW(6) WHERE ORDER_ID = ?");
						$stmt->execute([$orderData['ORDER_ID']]);
						if(!$stmt->rowCount()){
							$error[] = "An error occurred while updating the statuse order balance.";
							continue;
						}
						// حذف العهده المخزنيه للمندوب
						$delcustOrder = $this->delete_custody($orderData['ORDER_ID'], $orderData['DELIVERY_ID'],);
						if(!$delcustOrder){
							$error[] = "An error occurred while delete the custody order's balance.";
							continue;
						}
						//=================insert ORDERS_LOG===================
						$stratlogtext = " تم تحديث بيانات امر البيع من ";
						$textRes = "تم استلام مبالغ التحصيل من المندوب بنجاح وتم اغلاق امر البيع ";
						$logtext = $stratlogtext. $this->user_group."*". $textRes;
						$stOLog = $this->conn->prepare("INSERT INTO ORDERS_LOG (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) VALUES (:oid , :uauction, :log) ");
						$stOLog->bindValue(':oid', $orderData['ORDER_ID'], PDO::PARAM_INT);
						$stOLog->bindValue(':uauction', $this->transaction_user_id, PDO::PARAM_INT);
						$stOLog->bindValue(':log', $logtext, PDO::PARAM_STR);
						$stOLog->execute();
						if (!$stOLog->rowCount()) {
							$error[] = "An error occurred while add ORDERS_LOG.";
							continue;
						}
						$totalCHECK += $orderData['ACTUAL_TOTAL'];
						$totatShipp += $orderData['SHIPPING_VAL'];
						$shippCommission += $orderData['DELIVERY_CIMMISSION'];

						//=============insert shipping_movement================
								/*
									0 = start Shipping 
									1 = freeze
									2 = Returned order and product
									3 = Delivered
									4 = Partial
									5 = Rejected 
									6 = Returned From Rejected or Partial
									7 = تم السداد
									8 = Return after sale
								*/
						$shtype = 7;
						$shipquery = 'INSERT INTO `shipping_movement`( `movement_type`, `delivery_id`, `user_id`, `shipment_orders`) VALUES (:type, :deliveryid, :userid, :shipmentdata)';
						
						$stShMov = $this->conn->prepare($shipquery);
						$stShMov->bindValue(':type', $shtype, PDO::PARAM_INT);
						$stShMov->bindValue(':deliveryid', $orderData['DELIVERY_ID'], PDO::PARAM_INT);
						$stShMov->bindValue(':userid', $this->transaction_user_id, PDO::PARAM_INT);
						$stShMov->bindValue(':shipmentdata', $orderData['ORDER_ID'], PDO::PARAM_STR);
						$stShMov->execute();
						if (!$stShMov->rowCount()) {
							$error[] = "An error occurred while add ORDERS_LOG.";
							continue;
						}
				}
			}
			// إذا تم تنفيذ كل شيء بنجاح، نقوم بتأكيد المعاملة
			if(($totalCHECK + $totatShipp) == $totalAmount && count($error) <= 0){
					$this->conn->commit();
					//$this->conn->rollBack();
					return array('success',
						'نجح',
						"تم التحصيل بنجاح!",
						[],
						$error
					);
			}else{
				$this->conn->rollBack();
				if($errorlastupdate==0){
						return array('error',
							'خطاء',
							"حدث خطاء اثناء التنفيذ!",
							[],
							$error
						);
				}else{
					$msg='';
					foreach($errorlastupdatemsg as $line){
						$msg .="<li>{$line}</li>";
					}
					return array('error',
						'خطاء',
						$msg,
						[],
						$error
					);
				}
			}
		} catch (Exception $e) {
            error_log("InventoryManager Error: " . $e->getMessage());
            throw new Exception($e->getMessage());
		} finally {
            // فك القفل بعد الانتهاء
            $this->releaseGlobalFinanceLock();
        }
	}

/*=========================================
  اذن صرف نقدية
=========================================*/

	// صرف عمولة المندوب
	public function PaymentOfDeliveryDues(int $deliveryid, string $total_balance, string $total_amount): array {
		if ($deliveryid <= 0) throw new Exception("لم يتم الحفظ - من فضلك قم بإختيار المندوب اولا");
	    if ((float)$total_amount <= 0) throw new Exception("لم يتم الحفظ - من فضلك ادخل مبلغ صحيح لحفظ اذن الصرف ");
		if ((float)$total_balance <= 0) throw new Exception("لم يتم الحفظ - رصيد المندوب صفر");
	    if ((float)$total_balance < (float)$total_amount) throw new Exception("رصيد المندوب لا يكفى لصرف المبلغ الحالى");
	    try {
	        $this->conn->beginTransaction();
			// الحصول على قفل عالمي لمنع التعارض مع حركات أخرى
			$this->acquireGlobalFinanceLock();
			
	        // اضافة المعامله
			$transactionRecorded = $this->Add_transaction(NULL, 'DELIVERY', $deliveryid, $total_amount,1, '17' ,'COMPLETED','صرف مستحقات مندوب');
			if(!$transactionRecorded) throw new Exception("غير قادر على اضافة الحركه المالية");
			
			// تسجيل مبلغ المرتجع فى رصيد العميل
			$resultFinal = $this->Add_partners_balance( 'DELIVERY', $deliveryid, NULL, 'DEBIT', $total_amount,'صرف مستحقات مندوب','17' );
			if(!$resultFinal) throw new Exception("غير قادر على تحديث رصيد المندوب");
			
	        $this->conn->commit();
	
	        return [
	            'success',
	            'ناجح',
	            'تم صرف دفعه للمندوب بنجاح'
	        ];
	
	    } catch (PDOException $e) {
	
	        $this->conn->rollBack();
	
	        return [
	            'success',
	            'حدث خطأ أثناء حفظ السداد',
	            $e->getMessage()
	        ];
	
	    } finally {
            // فك القفل بعد الانتهاء
            $this->releaseGlobalFinanceLock();
        }

	}

	// صرف مستحقات مسوق
	public function PaymentOfMarketerDues(int $marketerId, string $total_balance, string $total_amount): array {
		if ($marketerId <= 0) throw new Exception("لم يتم الحفظ - من فضلك قم بإختيار المسوق اولا");
	    if ((float)$total_amount <= 0) throw new Exception("لم يتم الحفظ - من فضلك ادخل مبلغ صحيح لحفظ اذن الصرف ");
		if ((float)$total_balance <= 0) throw new Exception("لم يتم الحفظ - رصيد المسوق صفر");
	    if ((float)$total_balance < (float)$total_amount) throw new Exception("رصيد المسوق لا يكفى لصرف المبلغ الحالى");
	    try {
	        $this->conn->beginTransaction();
			
			// الحصول على قفل عالمي لمنع التعارض مع حركات أخرى
			$this->acquireGlobalFinanceLock();
			
	        // اضافة المعامله
			$transactionRecorded = $this->Add_transaction(NULL, 'MARKETER', $marketerId, $total_amount,1, '17' ,'COMPLETED','صرف مستحقات مسوق');
			if(!$transactionRecorded) throw new Exception("غير قادر على اضافة الحركه المالية");
			
			// تسجيل مبلغ المرتجع فى رصيد العميل
			$resultFinal = $this->Add_partners_balance( 'MARKETER', $marketerId, NULL, 'DEBIT', $total_amount,'صرف مستحقات مسوق','17' );
			if(!$resultFinal) throw new Exception("غير قادر على تحديث رصيد المسوق");
			
	        $this->conn->commit();
	
	        return [
	            'success',
	            'ناجح',
	            'تم صرف دفعه للمسوق بنجاح'
	        ];
	
	    } catch (PDOException $e) {
	
	        $this->conn->rollBack();
	
	        return [
	            'success',
	            'حدث خطأ أثناء حفظ السداد',
	            $e->getMessage()
	        ];
	
	    } finally {
            // فك القفل بعد الانتهاء
            $this->releaseGlobalFinanceLock();
        }
	}

	// صرف مستحقات المورد: الترحيل الجديد في الأستاذ العام دون تعديل الأرصدة التاريخية.
	public function PaymentOfSupplierDues(int $supplier_id, string $total_amount, array $orders = [], int $payment_method_id = 1, string $request_key = ''): array {
		$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
		$userId = (int)($_SESSION['USER_ID'] ?? 0);
		$note = trim((string)($_POST['note'] ?? ''));
		return (new SupplierAccountingManager($this->conn))->pay(
			$supplier_id, (float)$total_amount, $orders, $payment_method_id,
			$branchId, $userId, $request_key, $note
		);
	}


    public function ApplySupplierAdvances(int $supplier_id, array $orders, string $mode, string $request_key): array {
        $branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
        $userId = (int)($_SESSION['USER_ID'] ?? 0);
        return (new SupplierAccountingManager($this->conn))->applyAdvances(
            $supplier_id, $orders, $mode, $branchId, $userId, $request_key
        );
    }

    public function ReverseSupplierPayment(int $payment_id, string $reason = ''): array {
        return (new SupplierAccountingManager($this->conn))->reversePayment(
            $payment_id, (int)($_SESSION['USER_ID'] ?? 0), $reason
        );
    }

    public function ReverseSupplierAdvanceApplication(int $journal_id, string $reason = ''): array {
        return (new SupplierAccountingManager($this->conn))->reverseAdvanceApplication(
            $journal_id, (int)($_SESSION['USER_ID'] ?? 0), $reason
        );
    }

	// صرف مستحقات عميل (مرتجع مبيعات)
	public function PaymentOfCustomerDues($oid){
		if(!$oid) throw new Exception("من فضلك قم بإختيار امر البيع اولا"); 
		$query = "SELECT 
				    O.ORDER_ID, 
				    O.SUB_ORDER_ID, 
				    O.ORDER_TYPE_ID, 
				    O.ORDER_BY, 
				    CRO.ORDER_STATUSE, 
				    CRO.CUST_STAT, 
				    CRO.RETURNS, 
				    O.ORDER_STATUS, 
				    O.ORDER_SUCCESS,
				    O.LAST_UPDATE, 
				    TE.CLIENT_ID, 
				    CRO.USER_ID AS DELIVERY_ID, 
				    UA.AFFILIATEED_WITH AS LEADER_ID,
				    Round(SUM(OP.RETURNED_QTY)*UB.TEAM_COMMISSION,2) LEADER_COMMISSION,
                    UA.USER_ID AS MARKETER_ID,
				    Round(SUM(OP.RETURNED_QTY)*UA.COMMISSION_SALES,2) AS MARKETER_COMMISSION,
				    Round(PB.BALANCE,2) AS ACTUAL_TOTAL
				FROM  (
			 	    SELECT 
				     REFERENCE_ID,
				     CASE WHEN TRANSACTION_CATEGORY_ID = 14 THEN 1 ELSE 0 END AS DEFERRED_PAYMENT,
				     CASE WHEN TRANSACTION_CATEGORY_ID = 18 THEN 1 ELSE 0 END AS PAID,
				     BALANCE
				    FROM  partners_balance
				    WHERE PARTNER_TYPE ='CLIENT' AND TRANSACTION_CATEGORY_ID IN(14,18) 
				    GROUP BY REFERENCE_ID,PARTNER_ID
			    ) PB
				LEFT JOIN orders AS O ON PB.REFERENCE_ID = O.ORDER_ID 
				LEFT JOIN order_product AS OP ON O.ORDER_ID = OP.ORDER_ID
				LEFT JOIN users_affiliate AS UA ON O.ORDER_BY = UA.USER_ID
				LEFT JOIN users_affiliate AS UB ON UA.AFFILIATEED_WITH = UB.USER_ID
				LEFT JOIN telephones      AS TE ON TE.TEL_ID = O.TEL_ID
			    LEFT JOIN custody_return_orders CRO ON CRO.ORDER_ID = PB.REFERENCE_ID
                LEFT JOIN users_delivery  AS UD ON CRO.USER_ID = UD.USER_ID
                WHERE PB.PAID = 0 AND PB.REFERENCE_ID = :oid
			    GROUP BY O.ORDER_ID, O.SUB_ORDER_ID, O.ORDER_TYPE_ID, O.ORDER_BY, CRO.ORDER_STATUSE, CRO.CUST_STAT, 
			      CRO.RETURNS, O.ORDER_STATUS, O.ORDER_SUCCESS, O.LAST_UPDATE, TE.CLIENT_ID, CRO.USER_ID, UA.AFFILIATEED_WITH, UA.USER_ID ";
		try{     
			$oid = htmlspecialchars(strip_tags(trim($oid)));
			$this->conn->beginTransaction();
			
			// الحصول على قفل عالمي لمنع التعارض مع حركات أخرى
			$this->acquireGlobalFinanceLock();
			
			$stmt = $this->conn->prepare($query);
			$stmt->execute(['oid'=>$oid]);
			$record = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$order=isset($record[0]) ? $record[0]:[];
			if(!count($order)) throw new Exception("امر البيع الذى تم اختيارة لم يتوفر له بيانات فى الوقت الحالى");
			if(isset($order['ORDER_SUCCESS']) && $order['ORDER_SUCCESS'] == 2) throw new Exception("امر البيع الذى تم اختيارة ليس له مرتجعات فى الوقت الحالى");
			
			// لا يُسمح بصرف المرتجع المالي قبل ثبوت استلام الكميات المرتجعة مخزنيًا.
			$this->Accounting->assertSalesReturnStockReceived((int)$order['ORDER_ID']);
			
			//================= تسجيل فاتورة مبيعات المنتجات
			$invoice_id = $this->Update_invoice($order['ORDER_ID'], 'SALES', 1);
			if(!$invoice_id) throw new Exception("غير قادر على تحديث بيانات فاتورة المبيعات");
			
			//================= اضافة المعامله
			$transactionRecorded1 = $this->Add_transaction($invoice_id, 'CLIENT',$order['CLIENT_ID'], $order['ACTUAL_TOTAL'],1, 18 ,'COMPLETED','');
			if(!$transactionRecorded1) throw new Exception("غير قادر على اضافة الحركه المالية");
			
			//================= اضافة القيود المحاسبيه
			$accounting = $this->Accounting->salesReturnPayment($transactionRecorded1);
			if(!$accounting) throw new Exception("غير قادر على اضافة القيود المحاسبيه");
			
			//================= تسجيل مبلغ المرتجع فى رصيد العميل
			$resultFinal = $this->Add_partners_balance( 'CLIENT', $order['CLIENT_ID'], $order['ORDER_ID'], 'DEBIT', $order['ACTUAL_TOTAL'],'مرتجع مبيعات للعميل','18' );
			if(!$resultFinal) throw new Exception("غير قادر على تحديث رصيد العميل");
	
			//================= تحديث رصيد المسوق ان كان نوع الامر من المسوق
			if($order['ORDER_TYPE_ID'] == 2  && $order['MARKETER_ID']){
				// تحديث رصيد المسوق
				$update_marketer_balance = $this->Add_partners_balance( 'MARKETER', $order['MARKETER_ID'], $order['ORDER_ID'], 'DEBIT', $order['MARKETER_COMMISSION'],'خصم عمولة من مرتجع امر بيع','16' );
				if(!$update_marketer_balance) throw new Exception("فشل تحديث ارصدة المسوق");
				
				// تحديث رصيد قائد المسوق ان وجد قائد للمسوق
				if($update_marketer_balance && $order['ORDER_TYPE_ID'] == 2 && $order['LEADER_ID']){
					$update_mleader_balance = $this->Add_partners_balance( 'MARKETER', $order['LEADER_ID'], $order['ORDER_ID'], 'DEBIT', $order['LEADER_COMMISSION'], 'خصم عمولة مرتجع امر بيع من الفريق' ,'16');
					if(!$update_mleader_balance) throw new Exception("فشل تحديث ارصدة قائد المسوق");
				}
			}
			
			//================= تحديث حالة أمر البيع
			$stmt = $this->conn->prepare("UPDATE orders SET ORDER_SUCCESS = 2 ,LAST_UPDATE = NOW(6) WHERE ORDER_ID = ?");
			$stmt->execute([$order['ORDER_ID']]);
			if(!$stmt->rowCount()) throw new Exception("فشل فى تحديث حالة امر البيع");
			
			//================= حذف العهده المخزنيه للمندوب
			$delcustOrder = $this->delete_custody($order['ORDER_ID'], $order['DELIVERY_ID'],true);
			if(!$delcustOrder) throw new Exception("فشل فى حذف عهدة المندوب");
			
			//=================insert ORDERS_LOG===================
			$stratlogtext = " تم تحديث بيانات امر البيع من ";
			$textRes = "تم صرف قيمة مرتجعات امر البيع للعميل ";
			$logtext = $stratlogtext. $this->user_group."*". $textRes;
			$stOLog = $this->conn->prepare("INSERT INTO ORDERS_LOG (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`) VALUES (:oid , :uauction, :log) ");
			$stOLog->execute(['oid' =>  $order['ORDER_ID'], 'uauction' => $this->transaction_user_id, 'log' => $logtext]);
			if (!$stOLog->rowCount()) throw new Exception("فشل فى اضافة سجلات اوامر البيع");
			
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
						8 = Retutn From Sell
						9 = Return partial from sell
						10= رفض العميل تسليم المرتجعات
						11= تم استلام مرتجع البيع
				*/
			$shtype = 11;
			if($order['DELIVERY_ID']){
				$shipquery = 'INSERT INTO `shipping_movement`( `movement_type`, `delivery_id`, `user_id`, `shipment_orders`) VALUES (:type, :deliveryid, :userid, :shipmentdata)';
				$stShMov = $this->conn->prepare($shipquery);
				$stShMov->execute(['type' => $shtype, 'deliveryid' => $order['DELIVERY_ID'], 'userid' => $this->transaction_user_id, 'shipmentdata' => $order['ORDER_ID']]);
				if (!$stShMov->rowCount()) throw new Exception("فشل تسجيل حركة الشحن");
			}
			
			$this->conn->commit();
			return array('success', 'ناجح', 'تم الحفظ بنجاح');
			
		} catch (PDOException $e) {
			$this->conn->rollBack();
			error_log("finance_BE Error: " . $e->getMessage());
			return array('error', 'خطاء',$e->getMessage());
		} catch (Exception $e) {
			$this->conn->rollBack();
            error_log("finance_BE Error: " . $e->getMessage());
            return array('error', 'خطاء',$e->getMessage());
        } finally {
            // فك القفل بعد الانتهاء
            $this->releaseGlobalFinanceLock();
        }
	}

}
?>


