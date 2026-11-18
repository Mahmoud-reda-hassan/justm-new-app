<?php
/**
 * استلام المخزن لمرتجعات شركة الشحن (عهدة الشركة).
 * لا يغيّر مسار الاستلام القائم (ShipmentRefund) بل يضع حدودًا قبله لمندوب الشركة فقط:
 *  - منع الاستلام المزدوج: لا يُستلم إلا أمر عليه مرتجع معلّق في عهدة الشركة.
 *  - سياسة الشركة: SINGLE = كل المرتجعات المعلّقة بإذن واحد، PARTIAL = دفعات بأذون متعددة.
 *  - ما لم يُستلم يبقى على عهدة الشركة (عجز عليها) ويظهر كمعلّق.
 */
class ShippingCompanyReceipt {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function companyOfCourier(int $courierId): ?array {
        $st = $this->db->prepare("SELECT id, name, receiving_policy FROM shipping_companies WHERE user_id = ?");
        $st->execute([$courierId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        return $c ?: null;
    }

    /** أوامر عليها مرتجع لم يستلمه المخزن من الشركة بعد */
    public function pendingReturns(int $courierId): array {
        $st = $this->db->prepare(
            "SELECT co.ORDER_ID AS order_id, o.SUB_ORDER_ID AS sub_order_id
             FROM custody_orders co JOIN orders o ON o.ORDER_ID = co.ORDER_ID
             WHERE co.USER_ID = ? AND (co.RETURNS IS NULL OR co.RETURNS = 0)
               AND ((co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN (4,5)) OR co.CUST_STAT = 'FREEZE')
             ORDER BY co.ORDER_ID"
        );
        $st->execute([$courierId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * يتحقق من طلب استلام أوامر محددة. يرجع null لو المندوب ليس شركة (فلا قيود)، وإلا يرمي استثناءً برسالة عربية.
     * @param bool $bulk true لزر "استلام كل مرتجعات الشركة" (يسمح به في السياستين)
     */
    public function validate(int $courierId, array $orderIds, bool $bulk = false): ?array {
        $company = $this->companyOfCourier($courierId);
        if (!$company) { return null; }
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) { throw new Exception('لا توجد أوامر للاستلام.'); }
        $pending = $this->pendingReturns($courierId);
        $pendingIds = array_map(fn($p) => (int)$p['order_id'], $pending);
        $bad = array_values(array_diff($orderIds, $pendingIds));
        if ($bad) {
            $q = $this->db->prepare("SELECT SUB_ORDER_ID FROM orders WHERE ORDER_ID IN (" . implode(',', array_fill(0, count($bad), '?')) . ")");
            $q->execute($bad);
            $names = array_slice($q->fetchAll(PDO::FETCH_COLUMN), 0, 10);
            throw new Exception('لا يوجد مرتجع معلّق على الشركة لهذه الأوامر (استُلمت من قبل أو ليست مرتجعة): ' . implode('، ', $names));
        }
        if ($company['receiving_policy'] === 'SINGLE') {
            $missing = array_diff($pendingIds, $orderIds);
            if ($missing) {
                throw new Exception('سياسة الشركة «' . $company['name'] . '» هي استلام كامل بإذن واحد: يجب استلام كل مرتجعاتها المعلّقة (' . count($pendingIds) . ' أمر) معًا. المحدد ' . count($orderIds) . ' فقط. استخدم زر "استلام كل مرتجعات الشركة".');
            }
        }
        return $company;
    }
}
