<?php
/**
 * رسم شركة الشحن لكل أمر (ما نستحقه للشركة)، وهو غير شحن العميل (SHIPPING_VAL) الذي هو إيراد.
 * الترتيب: رسم الكشف المحفوظ للأمر ← ثم إعداد الشركة (نفس شحن الأمر / ثابت / سعر منطقة).
 * الأمر المرفوض (حالة 5) بلا شحن مدفوع من العميل لا يُحمَّل رسمًا إلا لو جاء رسمه في الكشف.
 */
class ShippingCompanyFees {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function companyOfCourier(int $courierId): ?array {
        $st = $this->db->prepare("SELECT * FROM shipping_companies WHERE user_id = ?");
        $st->execute([$courierId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        return $c ?: null;
    }

    /** تعديل يدوي مسجَّل للأمر (رسم شركة أو عمولة مندوب) أو null. يتسامح مع غياب الجدول قبل تحديث القاعدة. */
    public function overrideFor(int $orderId): ?array {
        try {
            $st = $this->db->prepare("SELECT amount, original_amount, kind, reason FROM shipping_settlement_overrides WHERE order_id = ?");
            $st->execute([$orderId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (Throwable $e) { return null; }
    }

    /** @return array{fee: float, source: string} */
    public function feeForOrder(array $company, int $orderId, bool $ignoreOverride = false): array {
        $ov = $ignoreOverride ? null : $this->overrideFor($orderId);
        if ($ov && $ov['kind'] === 'COMPANY_FEE') { return ['fee' => round((float)$ov['amount'], 2), 'source' => 'MANUAL']; }
        $st = $this->db->prepare("SELECT fee FROM shipping_company_statement_rows WHERE company_id = ? AND order_id = ? AND fee IS NOT NULL");
        $st->execute([(int)$company['id'], $orderId]);
        $f = $st->fetchColumn();
        if ($f !== false && $f !== null) { return ['fee' => round((float)$f, 2), 'source' => 'STATEMENT']; }

        $o = $this->db->prepare("SELECT o.SHIPPING_VAL, o.SHIPP_PAYMENT, o.ORDER_STATUS, c.ZONE_ID FROM orders o LEFT JOIN city c ON c.CI_ID = o.CITY_ID WHERE o.ORDER_ID = ?");
        $o->execute([$orderId]);
        $ord = $o->fetch(PDO::FETCH_ASSOC);
        if (!$ord) { throw new Exception('الأمر غير موجود لحساب رسم الشركة.'); }
        if ((int)$ord['ORDER_STATUS'] === 5 && (int)$ord['SHIPP_PAYMENT'] !== 1) {
            // مرفوض لم يدفع عميله الشحن: الرقم من الكشف (فوق)، وإلا حسب إعداد "رسم المرتجع" للشركة
            switch ($company['return_fee_mode'] ?? 'NONE') {
                case 'FIXED':            return ['fee' => round((float)($company['return_fixed_fee'] ?? 0), 2), 'source' => 'RETURN_FIXED'];
                case 'SAME_AS_SHIPPING': break; // يكمل للحساب العادي بالأسفل
                default:                 return ['fee' => 0.0, 'source' => 'NONE'];
            }
        }

        switch ($company['fee_mode']) {
            case 'FIXED':
                return ['fee' => round((float)$company['fixed_fee'], 2), 'source' => 'FIXED'];
            case 'ZONE_RATE':
                $r = $this->db->prepare("SELECT fee FROM shipping_company_rates WHERE company_id = ? AND zone_id = ?");
                $r->execute([(int)$company['id'], (int)$ord['ZONE_ID']]);
                $fee = $r->fetchColumn();
                if ($fee === false || $fee === null) {
                    throw new Exception('لا يوجد سعر لمنطقة الأمر في أسعار شركة «' . $company['name'] . '». أضف سعر المنطقة أو ارفع كشفًا فيه رسم الأمر.');
                }
                return ['fee' => round((float)$fee, 2), 'source' => 'ZONE_RATE'];
            default:
                return ['fee' => round((float)$ord['SHIPPING_VAL'], 2), 'source' => 'ORDER_SHIPPING'];
        }
    }
}
