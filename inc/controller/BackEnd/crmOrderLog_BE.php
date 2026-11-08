<?php
class crmOrderLog {

    public const EMPLOYEE_TYPES = [
        '1' => 'admin',
        '2' => 'marketing',
        '3' => 'delivery',
    ];

    public const ACTION_TYPES = [
        'call' => true,
        'whatsapp' => true,
        'sms' => true,
    ];

    public const RESULTS = [
        'confirmed'    => true, //EXISTS_ID = 7
        'editaddress'  => true, 
        'postponement' => true, //EXISTS_ID = 12
        'cancelled'    => true, //EXISTS_ID = 5
        'no_answer'    => true, //EXISTS_ID = 12
        'phone_off'    => true, //EXISTS_ID = 12
        'recall'       => true, //EXISTS_ID = 6
        'wrong_number' => true, //EXISTS_ID = 12
        
    ];
    public const RESULTS_EX = [
    	'recall'       => 6,
        'confirmed'    => 7,
        'editaddress'  => 7, 
        'postponement' => 12,
        'cancelled'    => 5,
        'no_answer'    => 12,
        'phone_off'    => 12,
        'wrong_number' => 12
        
    ];
    /*public const MESSAGES = [
            'confirmed'     => 'تم التواصل مع العميل وجارى تجهيز امر البيع للشحن',
            'postponement'  => 'تم التواصل مع العميل وقد طلب تأجيل ميعاد التسليم الى : '.$_POST['shipDate'],
            'no_answer'     => 'حاولنا الاتصال بالعميل ولم يتم الرد',
            'phone_closed'  => 'تم الاتصال ولكن هاتف العميل مغلق',
            'recall'    	=> 'لم نتمكن من التواصل مع العميل وسنحاول مرة أخرى لاحقًا',
            'cancelled'     => 'تم التواصل مع العميل وطلب الغاء أمر البيع',
            'wrong_number'  => 'رقم الهاتف غير صحيح'
    ];*/

    private $conn;

    public function __construct($db){
        $this->conn = $db;
    }

    // ✅ Helper: الوقت الحالي
    private function now(): string {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Cairo')))
            ->format('Y-m-d H:i:s');
    }

    // ✅ Validation
    private function validateValue(string $value, array $allowed): bool {
        return isset($allowed[$value]);
    }

    // ✅ توليد نص اللوج
    private function generateLogText(string $result): string {

        $messages = [
        	'recall'    	=> 'لم نتمكن من التواصل مع العميل وسنحاول مرة أخرى لاحقًا',
            'confirmed'     => 'تم التواصل مع العميل وجارى تجهيز امر البيع للشحن',
            'editaddress'   => 'تم تغير العنوان وجارى تجهيز امر البيع للشحن', 
            'postponement'  => 'تم التواصل مع العميل وقد طلب تأجيل ميعاد التسليم الى : '.$_POST['shipDate'],
            'cancelled'     => 'تم التواصل مع العميل وطلب الغاء أمر البيع',
            'no_answer'     => 'حاولنا الاتصال بالعميل ولم يتم الرد',
            'phone_off'     => 'تم الاتصال ولكن هاتف العميل مغلق',
            'wrong_number'  => 'رقم الهاتف غير صحيح'
           
        ];

        return $messages[$result] ?? 'تم تحديث حالة الطلب';
    }

    // ✅ إضافة CRM Log
    public function AddCRMOrderLogs(int $oId, int $employeeId, string $actionType, string $result, int $duration, int $employeeType, ?string $nextFollowUp = null, ?string $notes = null): mixed {

        if(!$this->validateValue($employeeType, self::EMPLOYEE_TYPES)) throw new Exception("نوع المستخدم غير متوفر");
        if(!$this->validateValue($actionType, self::ACTION_TYPES)) throw new Exception("نوع الحركه غير متوفر");
        if(!$this->validateValue($result, self::RESULTS)) throw new Exception("نوع الاجراء غير متوفر");

        // ✅ auto follow-up
        if ($result === 'recall' && $nextFollowUp === null) {
            $nextFollowUp = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Cairo')))
                ->modify('+10 minutes')
                ->format('Y-m-d H:i:s');
        }

        $query = "INSERT INTO crm_order_logs (
            ORDER_ID,
            EMPLOYEE_ID,
            employee_type,
            ACTION_TYPE,
            RESULT,
            call_duration_sec,
            NEXT_FOLLOW_UP,
            NOTES,
            CREATED_AT
        ) VALUES (?,?,?,?,?,?,?,?,?)";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                $oId,
                $employeeId,
                self::EMPLOYEE_TYPES[$employeeType],
                $actionType,
                $result,
                $duration,
                $nextFollowUp,
                $notes,
                $this->now()
            ]);

            return $this->conn->lastInsertId();

        } catch (PDOException $e) {
            error_log("CRM Log Error: " . $e->getMessage());
            return false;
        }
    }

    // ✅ إضافة Order Log
    public function AddOrderLog(int $oid, int $employeeType, array $loginlist = []): mixed {

        if (!$oid) return false;
		if(self::EMPLOYEE_TYPES[$employeeType] == 'admin') $userType = $_SESSION["USER_GRUOP_NAME"];
        if(self::EMPLOYEE_TYPES[$employeeType] == 'delivery') $userType = 'موظف الشحن';
        if(self::EMPLOYEE_TYPES[$employeeType] == 'marketing') $userType = 'المسوق';
        $userAction = $_SESSION["USER_ID"] ?? null;

        if (!$userAction) return false;

        $logstring = !empty($loginlist) ? '*'.implode(' * ', $loginlist) : '';
        $logtext   = "تم تحديث بيانات امر البيع من {$userType}{$logstring}";

        $query = "INSERT INTO ORDERS_LOG (
            ORDER_ID,
            USER_ACTION,
            LOG_TEXT
        ) VALUES (?, ?, ?)";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                $oid,
                $userAction,
                $logtext
            ]);

            return $this->conn->lastInsertId();

        } catch (PDOException $e) {
            error_log("Order Log Error: " . $e->getMessage());
            return false;
        }
    }
	
	// تعين حالة امر البيع
	public function updateOrderStatuse(int $oId, string $result){
	    $map = [
	        'postponement' => [12, 0],
	        // EXISTS_ID=5 معرف في order_exists باسم «إلغاء أمر البيع».
	        // EXISTS_ID=12 مخصص لتتبع تأكيد الشحن وليس للإلغاء.
	        'cancelled'    => [5, 0],
	        'no_answer'    => [12, 0],
	        'phone_off'    => [12, 0],
	        'wrong_number' => [12, 0],
	        'confirmed'    => [7, 1],
	        'recall'       => [6, 0]
	    ];

	    if (!isset($map[$result])) {
	        throw new Exception('حالة أمر البيع المختارة لا تصلح لتنفيذ الإجراء');
	    }

	    // لا نسمح بإلغاء طلب عليه مبلغ محصل أو قيد بيع قبل تسوية المقدم/عكس القيد.
	    // التحقق هنا يغطي مسار إلغاء خدمة العملاء (CRM)، وليس زر إلغاء الطلب فقط.
	    if ($result === 'cancelled') {
	        $check = $this->conn->prepare("
	            SELECT o.SALE_ENTRY_ID IS NOT NULL AS HAS_SALE_ENTRY,
	                   o.RETURN_ENTRY_ID IS NOT NULL AS HAS_RETURN_ENTRY,
	                   EXISTS(SELECT 1 FROM journal_entries j
	                       WHERE j.reference_type IN ('DIRECT_SALE','ORDER_SHIPPING','SALES_RETURN')
	                         AND j.reference_id = o.ORDER_ID AND j.status = 'POSTED') AS HAS_GL_POSTING
	            FROM ORDERS o WHERE o.ORDER_ID = ? FOR UPDATE
	        ");
	        $check->execute([$oId]);
	        $financial = $check->fetch(PDO::FETCH_ASSOC);
	        if (!$financial) {
	            throw new Exception('أمر البيع غير موجود');
	        }
	        if (!empty($financial['HAS_SALE_ENTRY']) || !empty($financial['HAS_RETURN_ENTRY']) || !empty($financial['HAS_GL_POSTING'])) {
	            throw new Exception('لا يمكن إلغاء أمر البيع قبل تسوية المبلغ المحصل أو القيد المحاسبي. يلزم تنفيذ مسار رد المقدم/عكس القيد أولًا.');
	        }
	    }

	    [$exists_id, $status] = $map[$result];
	    $stmt = $this->conn->prepare("
	        UPDATE ORDERS
	        SET EXISTS_ID = ?, ORDER_STATUS = ?, LAST_UPDATE = NOW(3)
	        WHERE ORDER_ID = ? AND EXISTS_ID = 6
	    ");
	    $stmt->execute([$exists_id, $status, $oId]);
	    return $stmt->rowCount() === 1;
	}
	
    // ✅ الفنكشن الأساسية (Workflow كامل)
    public function AddCRMResult(
        int $oId,
        int $employeeId,
        string $actionType,
        string $result,
        int $duration,
        string $employeeType = 'sales',
        ?string $notes = null
    ) {
    	
        try {
            $this->conn->beginTransaction();

            // ====== ✅ تحديث حالة الأوردر
            $upOrder = $this->updateOrderStatuse($oId,$result);
            if (!$upOrder) {
                $this->conn->rollBack();
                return ['error',
                'خطأ',
                'غير قادر على تغير حالة امر البيع حاليا'
                ,[]];
            }

            // ====== ✅ CRM Log
            $crmId = $this->AddCRMOrderLogs(
                $oId,
                $employeeId,
                $actionType,
                $result,
                $duration,
                $employeeType,
                null,
                $notes
            );
            if (!$crmId) {
                $this->conn->rollBack();
                return ['error',
                'خطأ',
                'غير قادر على حفظ سجل المكالمات'
                ,[]];
            }

            // ====== ✅ Order Log
            $logText = $this->generateLogText($result);
            $orderLogId = $this->AddOrderLog($oId, $employeeType, [$logText]);
            if (!$orderLogId) {
                $this->conn->rollBack();
                return ['error',
                'خطأ',
               	'غير قادى على حفظ سجل اوامر البيع',
                []];
            }

            $this->conn->commit();

            return ['success', 
					'نجح',
					'تم الحفظ بنجاح',
					[]
			];

        } catch (Exception $e) {
        	//error_log("AddCRMResult Error: " . $e->getMessage());
            return ['error','خطأ',$e->getMessage(),[]];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("AddCRMResult Error: " . $e->getMessage());
            return ['error','خطأ',$e->getMessage(),[]];
        }
    }
}