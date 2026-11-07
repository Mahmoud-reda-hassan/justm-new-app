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
	
	    $query = "SELECT A.PARTNER_ID AS id,
			  C.CLIENT_NAME AS name,
		      T.TEL_NUMBER AS phone
			FROM partners_balance A
			LEFT JOIN orders O ON A.REFERENCE_ID = O.ORDER_ID
		 	LEFT JOIN client C ON C.CLIENT_ID = A.PARTNER_ID
			LEFT JOIN telephones T ON C.CLIENT_ID = T.CLIENT_ID 
			LEFT JOIN partners_balance B
			  ON B.REFERENCE_ID = A.REFERENCE_ID
			  AND B.PARTNER_ID = A.PARTNER_ID
			  AND B.PARTNER_TYPE = 'CLIENT'
			  AND B.TRANSACTION_CATEGORY_ID = 18
			WHERE A.PARTNER_TYPE = 'CLIENT'
			  AND A.TRANSACTION_CATEGORY_ID = 14 
			  AND B.TRANSACTION_CATEGORY_ID IS NULL ";
	
	    $params = [];
	
	    if ($search !== "") {
	        $query .= " AND (C.CLIENT_NAME LIKE :search OR T.TEL_NUMBER LIKE :search) ";
	        $params['search'] = "$search%";
	    }
	
	    $query .= "GROUP BY A.PARTNER_ID,C.CLIENT_NAME LIMIT 100";
	
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetClientRetrnOrder(){
	    $search = trim($this->Requst['search'] ?? '');
		$Client_id = trim($this->Requst['cid'] ?? '');
	    $query = "SELECT A.REFERENCE_ID AS id,
			 O.SUB_ORDER_ID AS name
			FROM partners_balance A
			 LEFT JOIN orders O ON A.REFERENCE_ID = O.ORDER_ID
			 LEFT JOIN partners_balance B
			 ON B.REFERENCE_ID = A.REFERENCE_ID
			 AND B.PARTNER_ID = A.PARTNER_ID
			 AND B.PARTNER_TYPE = 'CLIENT'
			 AND B.TRANSACTION_CATEGORY_ID = 18
			WHERE A.PARTNER_TYPE = 'CLIENT'
			 AND A.TRANSACTION_CATEGORY_ID = 14
			 AND B.TRANSACTION_CATEGORY_ID IS NULL
			 AND A.PARTNER_ID = :CID ";
	
	    $params = [];
		$params['CID'] = $Client_id;
	    if ($search !== "") {
	        $query .= " AND (O.SUB_ORDER_ID LIKE :search) ";
	        
	        $params['search'] = "%$search%";
	    }
	    
	    $query .= " LIMIT 1000";
	
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetRefundBalance($orderId){
		$query = "
			SELECT COALESCE(BALANCE,0) AS total_balance
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
			WHERE DEFERRED_PAYMENT = 1 AND PAID = 0 AND REFERENCE_ID = :oid";
				 
		if(!$orderId) return [['total_balance'=>0]];
		
		$stmt = $this->conn->prepare($query);
	    $stmt->execute(['oid'=>$orderId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetPartnersBalance($partnerType,$partnerId,$order_id=null){
		// مستحقات المندوبين مصدرها حساب مستحقات العمولة في الأستاذ العام فقط.
		if (strtoupper((string)$partnerType) === 'DELIVERY') {
			if ($order_id) return [['total_balance' => 0]];
			$stmt = $this->conn->prepare("SELECT COALESCE(SUM(d.credit - d.debit),0) AS total_balance
				FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
				INNER JOIN delivery_accounting_settings das ON das.courier_commission_payable_account_id = d.account_id
				WHERE das.id = 1 AND d.partner_type = 'DELIVERY' AND d.partner_id = ?");
			$stmt->execute([(int)$partnerId]);
			return [['total_balance' => round((float)$stmt->fetchColumn(), 2)]];
		}

		// مستحقات المسوقين وقادة الفرق مصدرها حساب المستحقات في الأستاذ العام فقط.
		if (strtoupper((string)$partnerType) === 'MARKETER') {
			if ($order_id) return [['total_balance' => 0]];
			$stmt = $this->conn->prepare("SELECT COALESCE(SUM(d.credit - d.debit),0) AS total_balance
				FROM journal_entry_details d
				INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
				INNER JOIN account_settings aset ON aset.account_id = d.account_id
					AND aset.setting_key = 'MARKETER_COMMISSION_PAYABLE'
				WHERE d.partner_type = 'MARKETER' AND d.partner_id = ?");
			$stmt->execute([(int)$partnerId]);
			return [['total_balance' => round((float)$stmt->fetchColumn(), 2)]];
		}

		$query = "SELECT COALESCE(Sum(CASE
				    WHEN TRANSACTION_TYPE = 'CREDIT'
				    THEN BALANCE
				    ELSE 0
				   END) - Sum(CASE
				   WHEN TRANSACTION_TYPE = 'DEBIT'
				   THEN BALANCE
				   ELSE 0
				     END),0) AS total_balance
				FROM partners_balance pb
				WHERE PARTNER_TYPE = :ptype AND PARTNER_ID = :pid ";
		$parms = ['ptype' => $partnerType, 'pid' => $partnerId];
		if ($order_id) {
			$query .= " AND pb.REFERENCE_ID = :oid";
			$parms['oid'] = $order_id;
		}
		$stmt = $this->conn->prepare($query);
		$stmt->execute($parms);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            FROM purchases_return_orders
            WHERE STATUSE = 'DEFERRED'
            GROUP BY REFERANC
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