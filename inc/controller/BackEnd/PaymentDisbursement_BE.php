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
			INNER JOIN journal_entry_details D ON D.journal_entry_id = H.id
			INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
				AND ASET.setting_key = 'CUSTOMERS'
			LEFT JOIN (
				SELECT order_id, SUM(amount) AS refunded
				FROM customer_refund_payments WHERE status = 'POSTED' GROUP BY order_id
			) R ON R.order_id = O.ORDER_ID
			LEFT JOIN (
				SELECT I.reference_id AS order_id, SUM(D.debit) AS refunded
				FROM journal_entries H
				INNER JOIN financial_transactions FT ON H.reference_type = 'SALES_RETURN_PAYMENT' AND FT.id = H.reference_id
				INNER JOIN invoices I ON I.INVO_ID = FT.invoice_id
				INNER JOIN journal_entry_details D ON D.journal_entry_id = H.id AND D.partner_type = 'CLIENT' AND D.debit > 0
				INNER JOIN account_settings A ON A.account_id = D.account_id AND A.setting_key = 'CUSTOMERS'
				WHERE H.status = 'POSTED'
				GROUP BY I.reference_id
			) LR ON LR.order_id = O.ORDER_ID
			WHERE D.partner_type = 'CLIENT' AND D.partner_id = C.CLIENT_ID AND D.credit > 0
				AND D.credit > COALESCE(R.refunded,0) + COALESCE(LR.refunded,0)";
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
			INNER JOIN journal_entry_details D ON D.journal_entry_id = H.id
			INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
				AND ASET.setting_key = 'CUSTOMERS'
			LEFT JOIN (
				SELECT order_id, SUM(amount) AS refunded
				FROM customer_refund_payments WHERE status = 'POSTED' GROUP BY order_id
			) R ON R.order_id = O.ORDER_ID
			LEFT JOIN (
				SELECT I.reference_id AS order_id, SUM(D.debit) AS refunded
				FROM journal_entries H
				INNER JOIN financial_transactions FT ON H.reference_type = 'SALES_RETURN_PAYMENT' AND FT.id = H.reference_id
				INNER JOIN invoices I ON I.INVO_ID = FT.invoice_id
				INNER JOIN journal_entry_details D ON D.journal_entry_id = H.id AND D.partner_type = 'CLIENT' AND D.debit > 0
				INNER JOIN account_settings A ON A.account_id = D.account_id AND A.setting_key = 'CUSTOMERS'
				WHERE H.status = 'POSTED'
				GROUP BY I.reference_id
			) LR ON LR.order_id = O.ORDER_ID
			WHERE T.CLIENT_ID = :client_id AND D.partner_type = 'CLIENT'
				AND D.partner_id = T.CLIENT_ID AND D.credit > COALESCE(R.refunded,0) + COALESCE(LR.refunded,0)";
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
				COALESCE((SELECT SUM(D.credit)
					FROM journal_entry_details D
					INNER JOIN journal_entries H ON H.id = D.journal_entry_id AND H.status = 'POSTED'
					INNER JOIN account_settings ASET ON ASET.account_id = D.account_id
						AND ASET.setting_key = 'CUSTOMERS'
					WHERE H.id = O.RETURN_ENTRY_ID AND D.partner_type = 'CLIENT'
						AND D.partner_id = T.CLIENT_ID AND D.credit > 0),0)
				- COALESCE((SELECT SUM(R.amount) FROM customer_refund_payments R
					WHERE R.order_id = O.ORDER_ID AND R.status = 'POSTED'),0)
				- COALESCE((SELECT SUM(D.debit)
					FROM journal_entries H
					INNER JOIN financial_transactions FT ON H.reference_type = 'SALES_RETURN_PAYMENT' AND FT.id = H.reference_id
					INNER JOIN invoices I ON I.INVO_ID = FT.invoice_id AND I.reference_id = O.ORDER_ID
					INNER JOIN journal_entry_details D ON D.journal_entry_id = H.id
					INNER JOIN account_settings ASET ON ASET.account_id = D.account_id AND ASET.setting_key = 'CUSTOMERS'
					WHERE H.status = 'POSTED' AND D.partner_type = 'CLIENT'
						AND D.partner_id = T.CLIENT_ID AND D.debit > 0),0), 0
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
			$legacySql = "SELECT COALESCE(SUM(CASE WHEN TRANSACTION_TYPE='CREDIT' THEN BALANCE ELSE -BALANCE END),0)
				FROM partners_balance WHERE PARTNER_TYPE='SUPPLIER' AND PARTNER_ID=?";
			$legacyParams = [(int)$partnerId];
			if ($order_id) { $legacySql .= " AND REFERENCE_ID=?"; $legacyParams[] = (int)$order_id; }
			$legacyStmt = $this->conn->prepare($legacySql);
			$legacyStmt->execute($legacyParams);
			$legacyBalance = (float)$legacyStmt->fetchColumn();

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
				$glParams[] = (int)$order_id; $glParams[] = (int)$order_id;
			}
			$glStmt = $this->conn->prepare($glSql);
			$glStmt->execute($glParams);
			return [['total_balance' => round($legacyBalance + (float)$glStmt->fetchColumn(), 2)]];
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
		$query = "SELECT COALESCE(SUM(CASE WHEN TRANSACTION_TYPE='CREDIT' THEN BALANCE ELSE 0 END)-SUM(CASE WHEN TRANSACTION_TYPE='DEBIT' THEN BALANCE ELSE 0 END),0) AS total_balance
			FROM partners_balance pb WHERE PARTNER_TYPE=:ptype AND PARTNER_ID=:pid";
		$params=['ptype'=>$partnerType,'pid'=>$partnerId];
		if($order_id){$query.=" AND pb.REFERENCE_ID=:oid";$params['oid']=$order_id;}
		$stmt=$this->conn->prepare($query);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    
    
}