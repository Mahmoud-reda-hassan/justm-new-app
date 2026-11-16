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
	
	/** مستخدمون (مندوب/مسوق) مستحق لهم مبلغ على الشركة فقط (الرصيد الصافي موجب) — لشاشة صرف المستحقات. */
	private function GetUsersWithDue(string $partnerType, string $roleTable){
	    $search = trim($this->Requst['search'] ?? '');
	    include_once(__DIR__ . '/../../Classes/Accounting/SystemFlags.php');
	    if ($partnerType === 'BROKER' || SystemFlags::get($this->conn, 'BALANCE_SOURCE', 'legacy') === 'gl') {
	        $inner = "SELECT d.partner_id AS pid, SUM(d.credit - d.debit) AS net
	            FROM journal_entry_details d JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
	            WHERE d.partner_type = :ptype GROUP BY d.partner_id";
	    } else {
	        $inner = "SELECT PARTNER_ID AS pid, SUM(CASE WHEN TRANSACTION_TYPE='CREDIT' THEN BALANCE ELSE -BALANCE END) AS net
	            FROM partners_balance WHERE PARTNER_TYPE = :ptype GROUP BY PARTNER_ID";
	    }
	    $query = "SELECT U.USER_ID AS id, CONCAT(U.FORENAME,' ',U.LASTNAME) AS name,
	            (SELECT T.TEL_NUMBER FROM telephones T WHERE T.USER_ID = U.USER_ID LIMIT 1) AS phone,
	            ROUND(R.net,2) AS due
	        FROM $roleTable A
	        JOIN users U ON U.USER_ID = A.USER_ID
	        JOIN ($inner) R ON R.pid = U.USER_ID
	        WHERE R.net > 0.004";
	    $params = ['ptype' => $partnerType];
	    if ($search !== "") {
	        $query .= " AND (CONCAT(U.FORENAME,' ',U.LASTNAME) LIKE :search OR EXISTS (SELECT 1 FROM telephones T2 WHERE T2.USER_ID = U.USER_ID AND T2.TEL_NUMBER LIKE :search2))";
	        $params['search']  = "%$search%";
	        $params['search2'] = "%$search%";
	    }
	    $query .= " ORDER BY name LIMIT 1000";
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function GetDeliveryDueData(){ return $this->GetUsersWithDue('DELIVERY', 'users_delivery'); }
	public function GetMasouqDueData(){ return $this->GetUsersWithDue('MARKETER', 'users_affiliate'); }
	public function GetBrokerDueData(){ return $this->GetUsersWithDue('BROKER', '(SELECT USER_ID FROM users WHERE INTERMEDIARY_TYPE = 1)'); }

	/** الموردون الذين عليهم مديونية للشركة فقط (الرصيد الصافي سالب): للاستلام النقدي من المورد. */
	public function GetMoradRefundData(){
	    $search = trim($this->Requst['search'] ?? '');
	    include_once(__DIR__ . '/../../Classes/Accounting/SystemFlags.php');
	    if (SystemFlags::get($this->conn, 'BALANCE_SOURCE', 'legacy') === 'gl') {
	        $inner = "SELECT d.partner_id AS sid, SUM(d.credit - d.debit) AS net
	            FROM journal_entry_details d JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
	            WHERE d.partner_type = 'SUPPLIER' GROUP BY d.partner_id";
	    } else {
	        $inner = "SELECT PARTNER_ID AS sid, SUM(CASE WHEN TRANSACTION_TYPE='CREDIT' THEN BALANCE ELSE -BALANCE END) AS net
	            FROM partners_balance WHERE PARTNER_TYPE='SUPPLIER' GROUP BY PARTNER_ID";
	    }
	    $query = "SELECT S.SUP_ID AS id, S.SUP_NAME AS name,
	            (SELECT T.TEL_NUMBER FROM telephones T WHERE T.SUPPLIER_ID = S.SUP_ID LIMIT 1) AS phone,
	            -R.net AS refundable
	        FROM suppliers S JOIN ($inner) R ON R.sid = S.SUP_ID
	        WHERE R.net < -0.004";
	    $params = [];
	    if ($search !== "") {
	        $query .= " AND (S.SUP_NAME LIKE :search OR EXISTS (SELECT 1 FROM telephones T2 WHERE T2.SUPPLIER_ID = S.SUP_ID AND T2.TEL_NUMBER LIKE :search2)) ";
	        $params['search'] = "%$search%";
	        $params['search2'] = "%$search%";
	    }
	    $query .= " ORDER BY S.SUP_NAME LIMIT 1000";
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
	
	public function GetClientData($inout = false){
	
	    $search = trim($this->Requst['search'] ?? '');
	    $wherecat = $inout === true ? 14 : 8;
	    $joincat = $inout === true ? 18 : 9;
	    $order_type = $inout === true ? 1 : 2;
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
			  AND B.TRANSACTION_CATEGORY_ID = ".$joincat."
			WHERE A.PARTNER_TYPE = 'CLIENT'
			  AND A.TRANSACTION_CATEGORY_ID = ".$wherecat."
			  AND B.TRANSACTION_CATEGORY_ID IS NULL 
			  AND O.ORDER_TYPE_ID = ".$order_type." ";
	
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
	
	/** العملاء الذين لهم مرتجعات مستحقة الصرف فعلًا (نفس شروط جدول الأوامر). */
	public function GetClientReturnDueData(){
	    $search = trim($this->Requst['search'] ?? '');
	    $query = "SELECT A.PARTNER_ID AS id, C.CLIENT_NAME AS name,
	            (SELECT T.TEL_NUMBER FROM telephones T WHERE T.CLIENT_ID = A.PARTNER_ID LIMIT 1) AS phone,
	            ROUND(SUM(A.BALANCE),2) AS due
	        FROM partners_balance A
	        LEFT JOIN orders O ON A.REFERENCE_ID = O.ORDER_ID
	        LEFT JOIN client C ON C.CLIENT_ID = A.PARTNER_ID
	        WHERE A.PARTNER_TYPE = 'CLIENT' AND A.TRANSACTION_CATEGORY_ID = 14
	         AND NOT EXISTS (SELECT 1 FROM partners_balance B WHERE B.REFERENCE_ID = A.REFERENCE_ID AND B.PARTNER_ID = A.PARTNER_ID
	           AND B.PARTNER_TYPE = 'CLIENT' AND B.TRANSACTION_CATEGORY_ID = 18)
	         AND (O.ORDER_SUCCESS IS NULL OR O.ORDER_SUCCESS <> 2)";
	    $params = [];
	    if ($search !== "") {
	        $query .= " AND (C.CLIENT_NAME LIKE :search OR EXISTS (SELECT 1 FROM telephones T2 WHERE T2.CLIENT_ID = A.PARTNER_ID AND T2.TEL_NUMBER LIKE :search2))";
	        $params['search']  = "%$search%";
	        $params['search2'] = "$search%";
	    }
	    $query .= " GROUP BY A.PARTNER_ID, C.CLIENT_NAME HAVING SUM(A.BALANCE) > 0 ORDER BY C.CLIENT_NAME LIMIT 200";
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute($params);
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/** أوامر البيع التي لها مرتجع مؤجل لم يُصرف للعميل بعد (الأقدم أولًا). */
	public function GetClientReturnOrdersDue($clientId){
	    $query = "SELECT A.REFERENCE_ID AS id, O.SUB_ORDER_ID AS name, O.LAST_UPDATE AS last_update, ROUND(MAX(A.BALANCE),2) AS amount
			FROM partners_balance A
			LEFT JOIN orders O ON A.REFERENCE_ID = O.ORDER_ID
			WHERE A.PARTNER_TYPE = 'CLIENT' AND A.TRANSACTION_CATEGORY_ID = 14 AND A.PARTNER_ID = :cid
			 AND NOT EXISTS (SELECT 1 FROM partners_balance B WHERE B.REFERENCE_ID = A.REFERENCE_ID AND B.PARTNER_ID = A.PARTNER_ID
			   AND B.PARTNER_TYPE = 'CLIENT' AND B.TRANSACTION_CATEGORY_ID = 18)
			 AND (O.ORDER_SUCCESS IS NULL OR O.ORDER_SUCCESS <> 2)
			GROUP BY A.REFERENCE_ID, O.SUB_ORDER_ID, O.LAST_UPDATE
			ORDER BY A.REFERENCE_ID ASC LIMIT 1000";
	    $stmt = $this->conn->prepare($query);
	    $stmt->execute(['cid' => (int)$clientId]);
	    return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function GetClientdirctOrder(){
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
			 AND B.TRANSACTION_CATEGORY_ID = 9
			WHERE A.PARTNER_TYPE = 'CLIENT'
			 AND A.TRANSACTION_CATEGORY_ID = 8
			 AND B.TRANSACTION_CATEGORY_ID IS NULL
			 AND O.ORDER_TYPE_ID = 1
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
	
	public function GetDirectBalance($orderId){
		$query = "
			SELECT COALESCE(BALANCE,0) AS total_balance
			 FROM  (
				SELECT 
				 REFERENCE_ID,
				 CASE WHEN TRANSACTION_CATEGORY_ID = 8 THEN 1 ELSE 0 END AS DEFERRED_PAYMENT,
				 CASE WHEN TRANSACTION_CATEGORY_ID = 9 THEN 1 ELSE 0 END AS PAID,
				 BALANCE
				FROM  partners_balance
      			LEFT JOIN orders o on o.ORDER_ID = REFERENCE_ID
				WHERE PARTNER_TYPE ='CLIENT' AND TRANSACTION_CATEGORY_ID IN(8,9) AND ORDER_TYPE_ID = 1
				GROUP BY REFERENCE_ID,PARTNER_ID
			) PB
			WHERE DEFERRED_PAYMENT = 1 AND PAID = 0 AND REFERENCE_ID = :oid";
				 
		if(!$orderId) return [['total_balance'=>0]];
		$stmt = $this->conn->prepare($query);
	    $stmt->execute(['oid'=>$orderId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function GetPartnersBalance($partnerType,$partnerId,$order_id=null){
		// مفتاح مصدر الأرصدة (الأستاذ العام > الفترات والإعدادات): الافتراضي legacy = السلوك القديم بلا تغيير
		if (!$order_id && in_array($partnerType, ['CLIENT','SUPPLIER','DELIVERY','MARKETER','BROKER'], true)) {
			include_once(__DIR__ . '/../../Classes/Accounting/SystemFlags.php');
			if ($partnerType === 'BROKER' || SystemFlags::get($this->conn, 'BALANCE_SOURCE', 'legacy') === 'gl') {
				return [['total_balance' => PartnerBalanceSource::net($this->conn, $partnerType, (int)$partnerId)]];
			}
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
				FROM  partners_balance pb 
				WHERE PARTNER_TYPE = :ptype
				 AND PARTNER_ID = :pid ";
				 $parms = ['ptype' =>$partnerType, 'pid' => $partnerId];
				 if($order_id) {
				 	$query .= " AND pb.REFERENCE_ID =:oid";
				 	$parms['oid']=$order_id;
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
                SUM(TOTAL_PAYMENTS) AS RETURN_TOTAL
            FROM purchases_return_orders
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