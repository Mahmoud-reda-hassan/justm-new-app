<?php
declare(strict_types=1);

/**
 * تحميل حسابات الأستاذ من account_settings وربط طرق الدفع بالحساب الفعلي.
 * لا توجد حسابات افتراضية مخمنة؛ أي إعداد ناقص أو غير صالح يوقف الترحيل.
 */
class AccountSettings
{
    private $db;
    private array $cache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function configuredAccount(string $key): int
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT a.id, a.is_active, a.allow_posting
             FROM account_settings s
             INNER JOIN accounts a ON a.id = s.account_id
             WHERE s.setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['is_active'] !== 1 || (int)$row['allow_posting'] !== 1) {
            throw new RuntimeException('إعداد الحساب المحاسبي غير موجود أو غير صالح: ' . $key);
        }

        return $this->cache[$key] = (int)$row['id'];
    }

    public function customers(): int { return $this->configuredAccount('CUSTOMERS'); }
    public function sales(): int { return $this->configuredAccount('SALES'); }
    public function shippingRevenue(): int { return $this->configuredAccount('SHIPPING_REVENUE'); }
    public function cogs(): int { return $this->configuredAccount('COGS'); }
    public function inventory(): int { return $this->configuredAccount('INVENTORY'); }

    /** حساب مصروف عمولات المسوقين وقادة الفرق، مستقل عن عمولات المندوبين. */
    public function marketerCommissionExpense(): int
    {
        return $this->configuredAccount('MARKETER_COMMISSION_EXPENSE');
    }

    /** حساب مستحقات عمولات المسوقين وقادة الفرق، مستقل عن مستحقات المندوبين. */
    public function marketerCommissionPayable(): int
    {
        return $this->configuredAccount('MARKETER_COMMISSION_PAYABLE');
    }

    /** حساب الموردين الدائنين وفق دليل الحسابات المعتمد (2111). */
    public function suppliers(): int
    {
        return $this->configuredAccount('SUPPLIERS');
    }

    /** حساب المخزون بالطريق للمشتريات التي لم تستلم مخزنيًا بعد (1162). */
    public function inventoryInTransit(): int
    {
        return $this->configuredAccount('INVENTORY_IN_TRANSIT');
    }

    /** حساب الدفعات المقدمة للموردين (1171). */
    public function supplierAdvances(): int
    {
        return $this->configuredAccount('SUPPLIER_ADVANCES');
    }

    public function salesReturns(): int
    {
        $returns = $this->configuredAccount('SALES_RETURNS');
        if ($returns === $this->sales()) {
            throw new RuntimeException(
                'لا يمكن ترحيل مردودات المبيعات: إعداد SALES_RETURNS يشير إلى نفس حساب SALES. '
                . 'يجب إعداد حساب مردودات مستقل ومعتمد أولًا.'
            );
        }
        return $returns;
    }

    /**
     * حساب الصندوق الفعلي للفرع عند وجود صندوق واحد فقط.
     * يمنع الاختيار العشوائي إذا تعددت الخزائن؛ يجب أن يحدد مسار الحركة الخزينة صراحةً.
     */
    public function cash(int $branchId): int
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('الفرع مطلوب لتحديد حساب الصندوق.');
        }

        $stmt = $this->db->prepare(
            'SELECT cb.id AS cash_box_id, a.id AS account_id
             FROM cash_boxes cb
             INNER JOIN accounts a ON a.id = cb.account_id
             WHERE cb.branch_id = ? AND cb.is_active = 1
               AND a.is_active = 1 AND a.allow_posting = 1
             ORDER BY cb.id'
        );
        $stmt->execute([$branchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) === 0) {
            throw new RuntimeException('لا يوجد صندوق نشط مربوط بحساب قابل للترحيل لهذا الفرع.');
        }
        if (count($rows) > 1) {
            throw new RuntimeException(
                'يوجد أكثر من صندوق نشط لهذا الفرع. يجب تحديد الخزينة المطلوبة صراحةً قبل الترحيل.'
            );
        }

        return (int)$rows[0]['account_id'];
    }

    /** يرجع الحساب المحدد صراحةً لطريقة الدفع والفرع؛ لا يوجد fallback للصندوق. */
    public function transactionAccount(int $paymentMethodId, int $branchId): int
    {
        if ($paymentMethodId <= 0 || $branchId <= 0) {
            throw new InvalidArgumentException('طريقة الدفع والفرع مطلوبان لتحديد حساب الحركة المالية.');
        }

        $stmt = $this->db->prepare(
            'SELECT a.id
             FROM payment_method_accounts pma
             INNER JOIN payment_methods pm ON pm.id = pma.payment_method_id AND pm.is_active = 1
             INNER JOIN accounts a ON a.id = pma.account_id
             WHERE pma.payment_method_id = ? AND pma.branch_id = ? AND pma.is_active = 1
               AND a.is_active = 1 AND a.allow_posting = 1
             LIMIT 1'
        );
        $stmt->execute([$paymentMethodId, $branchId]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException(
                'لا يوجد حساب محاسبي معتمد لطريقة الدفع رقم ' . $paymentMethodId
                . ' في الفرع رقم ' . $branchId . '. لم يتم إنشاء القيد.'
            );
        }
        return (int)$id;
    }
}
