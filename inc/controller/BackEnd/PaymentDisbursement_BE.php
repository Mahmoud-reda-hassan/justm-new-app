<?php
class PaymentDisbursement {
   	public $Requst;
    private $conn;

    public function __construct($db){
        $this->conn = $db;
    }
	
	public function GetDeliveryData(){
	
	    $search = trim($this->Requst['search'] ?? '');
	
	    $query = "SELECT 
				U.USER_ID AS id,
				CONCAT(U.FORENAME ,' ',U.LASTNAME) AS name,
				T.TEL_NUMBER AS phone
			FROM  users_delivery D
			LEFT JOIN users U ON U.USER_ID = D.USER_ID
			LEFT JOIN telephones T ON U.USER_ID = T.USER_ID AND U.USER_ID IS NOT NULL ";
	
	    $params = [];
	
	    if ($search !== "") {
	        $query .= " WHERE (CONCAT(U.FORENAME ,' ',U.LASTNAME) LIKE :search OR T.TEL_NUMBER LIKE :search) ";
	        $params['search'] = "%$search%";
	    }
	
	    $query .= " LIMIT 1000";
	
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetMasouqData(){
	
	    $search = trim($this->Requst['search'] ?? '');
	
	    $query = " SELECT 
			 U.USER_ID AS id,
			concat(U.FORENAME ,' ',U.LASTNAME) AS name,
			T.TEL_NUMBER AS phone
			FROM  users_affiliate A
			 LEFT JOIN users U
			 ON U.USER_ID = A.USER_ID
			LEFT JOIN telephones T 
			ON U.USER_ID = T.USER_ID AND U.USER_ID IS NOT NULL ";
				
	    $params = [];
	
	    if ($search !== "") {
	        $query .= " WHERE (CONCAT(U.FORENAME ,' ',U.LASTNAME) LIKE :search OR T.TEL_NUMBER LIKE :search) ";
	        $params['search'] = "%$search%";
	    }
	
	    $query .= " LIMIT 1000";
	
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetMoradData(){
	
	    $search = trim($this->Requst['search'] ?? '');
	
	    $query = " SELECT 
			S.SUP_ID AS id,
			S.SUP_NAME AS name,
			T.TEL_NUMBER AS phone
			FROM  suppliers S
			LEFT JOIN telephones T 
			ON s.SUP_ID = T.SUPPLIER_ID AND T.SUPPLIER_ID IS NOT NULL ";
							
	    $params = [];
	
	    if ($search !== "") {
	        $query .= " WHERE (S.SUP_NAME LIKE :search OR T.TEL_NUMBER LIKE :search) ";
	        $params['search'] = "%$search%";
	    }
	
	    $query .= " LIMIT 1000";
	
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetClientData(){
		$search = trim($this->Requst['search'] ?? '');
		$query = "SELECT C.CLIENT_ID AS id, C.CLIENT_NAME AS name, MAX(T.TEL_NUMBER) AS phone
			FROM orders O
			INNER JOIN telephones T ON T.TEL_ID = O.TEL_ID
			INNER JOIN client C ON C.CLIENT_ID = T.CLIENT_ID
			INNER JOIN journal_entries H ON H.id = O.RETURN_ENTRY_ID AND H.status = 'POSTED'
			INNER JOIN (
				SELECT D.journal_entry_id, D.partner_id,
					SUM(D.credit - COALESCE(A.allocated_amount,0)) AS return_credit
				FROM journal_entry_details D
				INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
					AND ASET.setting_key IN ('CUSTOMERS','CUSTOMER_CREDITS')
				LEFT JOIN (
					SELECT credit_line_id, SUM(amount) AS allocated_amount
					FROM accounting_line_allocations WHERE status = 'ACTIVE' GROUP BY credit_line_id
				) A ON A.credit_line_id = D.id
				WHERE D.partner_type = 'CLIENT' AND D.credit > 0
				GROUP BY D.journal_entry_id, D.partner_id
				HAVING SUM(D.credit - COALESCE(A.allocated_amount,0)) > 0
			) RC ON RC.journal_entry_id = H.id AND RC.partner_id = C.CLIENT_ID
			LEFT JOIN (
				SELECT order_id, SUM(amount) AS refunded
				FROM customer_refund_payments WHERE status = 'POSTED' GROUP BY order_id
			) R ON R.order_id = O.ORDER_ID
			WHERE RC.return_credit > COALESCE(R.refunded,0)";
		$params = [];
		if ($search !== '') {
			$query .= " AND (C.CLIENT_NAME LIKE :search OR T.TEL_NUMBER LIKE :search)";
			$params['search'] = $search . '%';
		}
		$query .= " GROUP BY C.CLIENT_ID, C.CLIENT_NAME ORDER BY C.CLIENT_NAME LIMIT 100";
		$stmt = $this->conn->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function GetClientRetrnOrder(){
		$search = trim($this->Requst['search'] ?? '');
		$clientId = (int)($this->Requst['cid'] ?? 0);
		$query = "SELECT O.ORDER_ID AS id, O.SUB_ORDER_ID AS name
			FROM orders O
			INNER JOIN telephones T ON T.TEL_ID = O.TEL_ID
			INNER JOIN journal_entries H ON H.id = O.RETURN_ENTRY_ID AND H.status = 'POSTED'
			INNER JOIN (
				SELECT D.journal_entry_id, D.partner_id,
					SUM(D.credit - COALESCE(A.allocated_amount,0)) AS return_credit
				FROM journal_entry_details D
				INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
					AND ASET.setting_key IN ('CUSTOMERS','CUSTOMER_CREDITS')
				LEFT JOIN (
					SELECT credit_line_id, SUM(amount) AS allocated_amount
					FROM accounting_line_allocations WHERE status = 'ACTIVE' GROUP BY credit_line_id
				) A ON A.credit_line_id = D.id
				WHERE D.partner_type = 'CLIENT' AND D.credit > 0
				GROUP BY D.journal_entry_id, D.partner_id
				HAVING SUM(D.credit - COALESCE(A.allocated_amount,0)) > 0
			) RC ON RC.journal_entry_id = H.id AND RC.partner_id = T.CLIENT_ID
			LEFT JOIN (
				SELECT order_id, SUM(amount) AS refunded
				FROM customer_refund_payments WHERE status = 'POSTED' GROUP BY order_id
			) R ON R.order_id = O.ORDER_ID
			WHERE T.CLIENT_ID = :client_id AND RC.return_credit > COALESCE(R.refunded,0)";
		$params = ['client_id' => $clientId];
		if ($search !== '') {
			$query .= " AND O.SUB_ORDER_ID LIKE :search";
			$params['search'] = '%' . $search . '%';
		}
		$query .= " GROUP BY O.ORDER_ID, O.SUB_ORDER_ID ORDER BY O.ORDER_ID DESC LIMIT 1000";
		$stmt = $this->conn->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function GetRefundBalance($orderId){
		if (!$orderId) return [['total_balance' => 0]];
		$query = "SELECT GREATEST(
				COALESCE((SELECT SUM(D.credit - COALESCE(A.allocated_amount,0))
					FROM journal_entry_details D
					INNER JOIN journal_entries H ON H.id = D.journal_entry_id AND H.status = 'POSTED'
					INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
						AND ASET.setting_key IN ('CUSTOMERS','CUSTOMER_CREDITS')
					LEFT JOIN (
						SELECT credit_line_id, SUM(amount) AS allocated_amount
						FROM accounting_line_allocations WHERE status = 'ACTIVE' GROUP BY credit_line_id
					) A ON A.credit_line_id = D.id
					WHERE H.id = O.RETURN_ENTRY_ID AND D.partner_type = 'CLIENT'
						AND D.partner_id = T.CLIENT_ID AND D.credit > 0),0)
				- COALESCE((SELECT SUM(R.amount) FROM customer_refund_payments R
					WHERE R.order_id = O.ORDER_ID AND R.status = 'POSTED'),0), 0
			) AS total_balance
			FROM orders O
			INNER JOIN telephones T ON T.TEL_ID = O.TEL_ID
			WHERE O.ORDER_ID = ? AND O.RETURN_ENTRY_ID IS NOT NULL
				AND O.ORDER_STATUS IN (6,7) LIMIT 1";
		$stmt = $this->conn->prepare($query);
		$stmt->execute([(int)$orderId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return [$row ?: ['total_balance' => 0]];
	}

	public function GetPartnersBalance($partnerType,$partnerId,$order_id=null){
		$partnerType = strtoupper((string)$partnerType);
		if ($partnerType === 'SUPPLIER') {
			$accountKeys = $order_id ? "'SUPPLIERS'" : "'SUPPLIERS','SUPPLIER_ADVANCES'";
			$glSql = "SELECT COALESCE(SUM(d.credit-d.debit),0)
				FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id=d.journal_entry_id AND h.status='POSTED'
				INNER JOIN account_settings aset ON aset.account_id=d.account_id
					AND aset.setting_key IN ($accountKeys)
				WHERE d.partner_type='SUPPLIER' AND d.partner_id=?";
			$glParams = [(int)$partnerId];
			if ($order_id) {
				$glSql .= " AND (d.reference_id=? OR (h.reference_type IN ('PURCHASE_ORDER','PURCHASE_RETURN') AND h.reference_id=?))";
				$glParams[] = (int)$order_id;
				$glParams[] = (int)$order_id;
			}
			$glStmt = $this->conn->prepare($glSql);
			$glStmt->execute($glParams);
			$glBalance = round((float)$glStmt->fetchColumn(), 2);
			return [['total_balance' => $glBalance]];
		}
		if ($partnerType === 'DELIVERY') {
			if ($order_id) return [['total_balance' => 0]];
			$stmt = $this->conn->prepare("SELECT COALESCE(SUM(d.credit-d.debit),0) FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id=d.journal_entry_id AND h.status='POSTED'
				INNER JOIN delivery_accounting_settings das ON das.courier_commission_payable_account_id=d.account_id
				WHERE das.id=1 AND d.partner_type='DELIVERY' AND d.partner_id=?");
			$stmt->execute([(int)$partnerId]); return [['total_balance'=>round((float)$stmt->fetchColumn(),2)]];
		}
		if ($partnerType === 'MARKETER') {
			if ($order_id) return [['total_balance' => 0]];
			$stmt = $this->conn->prepare("SELECT COALESCE(SUM(d.credit-d.debit),0) FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id=d.journal_entry_id AND h.status='POSTED'
				INNER JOIN account_settings aset ON aset.account_id=d.account_id AND aset.setting_key='MARKETER_COMMISSION_PAYABLE'
				WHERE d.partner_type='MARKETER' AND d.partner_id=?");
			$stmt->execute([(int)$partnerId]); return [['total_balance'=>round((float)$stmt->fetchColumn(),2)]];
		}
		if ($partnerType === 'CLIENT') {
			$accountKeys = $order_id ? "'CUSTOMERS'" : "'CUSTOMERS','CUSTOMER_CREDITS'";
			$glSql = "SELECT COALESCE(SUM(d.credit-d.debit),0)
				FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id=d.journal_entry_id AND h.status='POSTED'
				INNER JOIN account_settings aset ON aset.account_id=d.account_id
					AND aset.setting_key IN ($accountKeys)
				WHERE d.partner_type='CLIENT' AND d.partner_id=?";
			$params = [(int)$partnerId];
			if ($order_id) {
				$glSql .= " AND h.reference_type IN ('DIRECT_SALE','ORDER_SHIPPING','SALES_RETURN') AND h.reference_id=?";
				$params[] = (int)$order_id;
			}
			$glStmt = $this->conn->prepare($glSql);
			$glStmt->execute($params);
			$glBalance = round((float)$glStmt->fetchColumn(), 2);
			return [['total_balance' => $glBalance, 'gl_balance' => $glBalance]];
		}
		throw new InvalidArgumentException('نوع الطرف غير مدعوم في دفتر الأستاذ العام.');
	}
	public function GetSupplierOrderList($supplierId){

    $records = [];

    $query = "
        SELECT
            PO.ORDER_ID,
            PO.CREATE_AT,
            PO.TOTAL_PRICE,
            COALESCE(PRO.RETURN_TOTAL,0) AS RETURN_TOTAL,
            PO.TOTAL_PAYMENTS,
            (PO.TOTAL_PRICE - COALESCE(PRO.RETURN_TOTAL,0) - PO.TOTAL_PAYMENTS
            ) AS REMAIN
        FROM purchases_orders PO
        LEFT JOIN (
            SELECT
                REFERANC,
                SUM(TOTAL_PRICE - TOTAL_PAYMENTS) AS RETURN_TOTAL
            FROM purchases_return_orders PR
            WHERE PR.STATUSE = 'DEFERRED'
              AND EXISTS (SELECT 1 FROM inventory_movement IM
                  WHERE IM.REFERENCE = PR.ORDER_ID AND LOWER(IM.IM_MOVE_TYPE) = 'refundpurchises')
            GROUP BY PR.REFERANC
        ) PRO ON PO.ORDER_ID = PRO.REFERANC
        WHERE
            PO.SUPPLIER_ID = ?
            AND (PO.TOTAL_PRICE - COALESCE(PRO.RETURN_TOTAL,0) - PO.TOTAL_PAYMENTS) > 0

        ORDER BY PO.ORDER_ID ASC
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$supplierId]);

    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $records[] = $record;

    }

    $displayRecords = count($records);

    if(!isset($_POST["draw"])){

        $_POST["draw"] = 0;

    }

    return [
        "draw"                 => intval($_POST["draw"]),
        "iTotalRecords"        => $displayRecords,
        "iTotalDisplayRecords" => $displayRecords,
        "data"                 => $records
    ];

}

    
    

    public function GetSupplierAdvanceBalance($supplierId): array
    {
        $stmt = $this->conn->prepare("SELECT ROUND(COALESCE(SUM(A.amount),0)-COALESCE((
                SELECT SUM(X.amount) FROM supplier_gl_advance_applications X
                INNER JOIN journal_entries J ON J.id=X.journal_entry_id AND J.status='POSTED'
                WHERE X.supplier_id=A.supplier_id
            ),0),2) AS AVAILABLE
            FROM supplier_gl_payment_allocations A
            INNER JOIN supplier_gl_payments P ON P.id=A.payment_id AND P.reversal_journal_entry_id IS NULL
            WHERE A.supplier_id=? AND A.allocation_type='ADVANCE'
            GROUP BY A.supplier_id");
        $stmt->execute([(int)$supplierId]);
        $amount = $stmt->fetchColumn();
        return ['supplier_id'=>(int)$supplierId,'available_advance'=>round((float)($amount ?: 0),2)];
    }


}