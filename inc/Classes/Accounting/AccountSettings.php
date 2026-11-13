<?php

class AccountSettings{

    // Database Connection
    protected $db;
    
    // Constructor
	public function __construct($db) {
	    $this->db = $db;
	}
	
	
	/*****************************************************
	 * الحصول على رقم حساب من الإعدادات
	 *****************************************************/
	public function get(string $key): int {
	    $stmt = $this->db->prepare("
	
	        SELECT account_id
	
	        FROM account_settings
	
	        WHERE setting_key = ?
	
	        LIMIT 1
	
	    ");
	
	    $stmt->execute([
	        strtoupper($key)
	    ]);
	
	    $accountId = $stmt->fetchColumn();
	
	    if (!$accountId)
	        throw new Exception("Account setting '{$key}' not found.");
	
	    return (int)$accountId;
	}
	
	
	/*****************************************************
	 * التحقق من وجود إعداد
	 *****************************************************/
	public function exists(string $key): bool {
	    $stmt = $this->db->prepare("
	
	        SELECT COUNT(*)
	
	        FROM account_settings
	
	        WHERE setting_key = ?
	
	    ");
	
	    $stmt->execute([
	        strtoupper($key)
	    ]);
	
	    return (bool)$stmt->fetchColumn();
	}
    
    /*****************************************************
	 * إضافة أو تعديل إعداد
	 *****************************************************/
	public function set( string $key, int $accountId ): bool {
	    $stmt = $this->db->prepare("
	
	        INSERT INTO account_settings
	        (
	            setting_key,
	            account_id
	        )
	
	        VALUES
	        (?,?)
	
	        ON DUPLICATE KEY UPDATE
	
	            account_id = VALUES(account_id)
	
	    ");
	
	    return $stmt->execute([
	
	        strtoupper($key),
	        $accountId
	
	    ]);
	}
	
	
	/*****************************************************
	 * حذف إعداد
	 *****************************************************/
	public function delete( string $key ): bool {
	    $stmt = $this->db->prepare("
	
	        DELETE
	
	        FROM account_settings
	
	        WHERE setting_key = ?
	
	    ");
	
	    return $stmt->execute([
	
	        strtoupper($key)
	
	    ]);
	}
	
	/*****************************************************
	 * جميع الإعدادات
	 *****************************************************/
	public function all(): array {
	    $stmt = $this->db->query("
	
	        SELECT
	
	            setting_key,
	            account_id
	
	        FROM account_settings
	
	        ORDER BY setting_key
	
	    ");
	
	    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
	}
    
    /*****************************************************
	 * حساب الصندوق
	 *****************************************************/
	public function cash($branchId): int {
	    $stmt = $this->db->prepare("
	
	        SELECT account_id 
	        FROM cash_boxes 
	        WHERE branch_id = ?
	        LIMIT 1
	
	    ");
	
	    $stmt->execute([
	        $branchId
	    ]);
	
	    $accountId = $stmt->fetchColumn();
	
	    if (!$accountId) throw new Exception("Cash account not found for branch {$branchId}.");
	
	    return (int)$accountId;
	}
	
	/*****************************************************
	 * حساب صندوق الفرع
	 *****************************************************/
	public function branchCash(int $branchId): int{
		 $stmt = $this->db->prepare("
	
	        SELECT account_id 
	        FROM cash_boxes 
	        WHERE branch_id = ?
	        LIMIT 1
	
	    ");
	
	    $stmt->execute([
	        $branchId
	    ]);
	
	    $accountId = $stmt->fetchColumn();
	
	    if (!$accountId) throw new Exception("Cash account not found for branch {$branchId}.");
	
	    return (int)$accountId;
	}
	
	/*****************************************************
	 * حساب البنك
	 *****************************************************/
	public function bank(): int {
	    return $this->get('BANK');
	}
	




	/*****************************************************
	 * حساب العملاء
	 *****************************************************/
	public function customers(): int{
	    return $this->get('CUSTOMERS');
	}
	
	/*****************************************************
	 * حساب الموردين
	 *****************************************************/
	public function suppliers(): int {
	    return $this->get('SUPPLIERS');
	}
	
	/*****************************************************
	 * حساب المخزون
	 *****************************************************/
	public function inventory(): int {
	    return $this->get('INVENTORY');
	}
	
	/*****************************************************
	 * حساب المبيعات
	 *****************************************************/
	public function sales(): int {
	    return $this->get('SALES');
	}
	
	/*****************************************************
	 * حساب مردودات المبيعات
	 *****************************************************/
	public function salesReturns(): int {
	    return $this->get('SALES_RETURNS');
	}
	
	/*****************************************************
	 * حساب المشتريات
	 *****************************************************/
	public function purchases(): int {
	    return $this->get('PURCHASES');
	}
	
	/*****************************************************
	 * حساب مردودات المشتريات
	 *****************************************************/
	public function purchaseReturns(): int {
	    return $this->get('PURCHASE_RETURNS');
	}
	
	/*****************************************************
	 * حساب تكلفة البضاعة المباعة
	 *****************************************************/
	public function cogs(): int {
	    return $this->get('COGS');
	}
	
	
	/*****************************************************
	 * حساب إيرادات الشحن
	 *****************************************************/
	public function shippingRevenue(): int {
	    return $this->get('SHIPPING_REVENUE');
	}
	
	/*****************************************************
	 * حساب مصروفات الشحن
	 *****************************************************/
	public function deliveryExpense(): int {
	    return $this->get('DELIVERY_EXPENSE');
	}
	
	/*****************************************************
	 * حساب عمولات المندوبين
	 *****************************************************/
	public function deliveryCommission(): int {
	    return $this->get('DELIVERY_COMMISSION');
	}
	
	/*****************************************************
	 * حساب المرتبات
	 *****************************************************/
	public function salaryExpense(): int {
	    return $this->get('SALARY_EXPENSE');
	}
	
	/*****************************************************
	 * حساب الهالك
	 *****************************************************/
	public function scrapLoss(): int {
	    return $this->get('SCRAP_LOSS');
	}
	
	/*****************************************************
	 * حساب مكاسب فروقات الجرد
	 *****************************************************/
	public function stockCountGain(): int{
	    return $this->get('STOCK_COUNT_GAIN');
	}
	/*****************************************************
	 * حساب الإيرادات الأخرى
	 *****************************************************/
	public function otherRevenue(): int {
	    return $this->get('OTHER_REVENUE');
	}
	
	/*****************************************************
	 * حساب المصروفات الأخرى
	 *****************************************************/
	public function otherExpense(): int {
	    return $this->get('OTHER_EXPENSE');
	}

	/**
	 * عهدة المندوبين: المبالغ المحصلة لدى المندوب قبل توريدها للشركة.
	 */
	public function deliveryCustody(): int {
	    return $this->get('DELIVERY_CUSTODY');
	}

	/**
	 * عمولات المندوبين المستحقة (التزام) حتى تُصرف أو تُخصم من العهدة.
	 */
	public function deliveryCommissionPayable(): int {
	    return $this->get('DELIVERY_COMMISSION_PAYABLE');
	}
}