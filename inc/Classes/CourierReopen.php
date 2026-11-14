<?php
/**
 * رد أمر شحن مندوب إلى المخزن للتعديل (بعد اعتماد أمين المخزن).
 *
 * - مسموح فقط للمدير العام / المالك / المدير المالي (انظر ALLOWED_GROUPS).
 * - يعكس قيد الأستاذ (قيد مضاد) وحركة استلام المرفوضات (حركة مضادة)؛ لا يحذف شيئاً من السجلات.
 * - يعيد الأمر إلى "عند المندوب" (ORDER_STATUS=2) وسجل العهدة، ليسجّل أمين المخزن التسليم من جديد
 *   في نفس شاشة تأكيد الشحنة.
 * - يمتنع إن كان قد حُصّل من المندوب: يجب عكس التحصيل أولاً.
 * لا يضيف أي شاشة؛ الزر (إن وُجد) يستدعي هذه الدالة فقط.
 */
require_once __DIR__ . '/Accounting/AccountingEvents.php';
require_once __DIR__ . '/InventoryManager.php';

class CourierReopen {

    /** أسماء مجموعات المستخدمين المسموح لها برد الأمر للمخزن */
    public const ALLOWED_GROUPS = ['Administrator', 'Owner', 'المدير المالي'];

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public static function isAllowedGroup(string $groupName): bool {
        return in_array(trim($groupName), self::ALLOWED_GROUPS, true);
    }

    /**
     * @return array ['order_id'=>int,'reverse_entry_id'=>int,'inventory_rows'=>int]
     * @throws Exception برسالة عربية/إنجليزية واضحة عند أي مخالفة
     */
    public function reopen(int $orderId, int $userId, int $branchId, string $userGroupName, string $reason): array {
        if (!self::isAllowedGroup($userGroupName)) {
            throw new Exception("ليس لديك صلاحية رد الأمر للمخزن.");
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new Exception("سبب رد الأمر للمخزن مطلوب.");
        }

        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }

        try {
            $st = $this->db->prepare("SELECT ORDER_ID, ORDER_STATUS, EXISTS_ID, DELIVERY_ID, ORDER_SUCCESS, SALE_ENTRY_ID, ADDRESS FROM orders WHERE ORDER_ID = ? FOR UPDATE");
            $st->execute([$orderId]);
            $o = $st->fetch(PDO::FETCH_ASSOC);
            if (!$o) { throw new Exception("الأمر غير موجود."); }
            if ((int)$o['EXISTS_ID'] !== 10 || !in_array((int)$o['ORDER_STATUS'], [3, 4, 5], true)) {
                throw new Exception("لا يمكن رد هذا الأمر: ليس في عهدة مندوب بحالة تسليم/رفض.");
            }
            if ((int)$o['ORDER_SUCCESS'] === 1) {
                throw new Exception("تم تحصيل هذا الأمر من المندوب؛ اعكس التحصيل أولاً.");
            }
            $deliveryId = (int)$o['DELIVERY_ID'];
            if ($deliveryId <= 0) { throw new Exception("الأمر غير مرتبط بمندوب."); }

            // 1) قيد الأستاذ
            $reverseId = 0;
            if ($o['SALE_ENTRY_ID'] !== null) {
                $acc = new AccountingEvents($this->db);
                $reverseId = $acc->reverseCourierDelivery($orderId, 'رد الأمر للمخزن: ' . $reason);
            }

            // 2) المخزون (استلام المرفوضات)
            $inv = new InventoryManager($this->db);
            $invRows = $inv->reverseReturnShipp($orderId, $userId, $branchId);

            // 3) إعادة الأمر عند المندوب
            $this->db->prepare("UPDATE order_product SET DELIVERED_QTY = 0, RETURNED_QTY = 0, PRO_STATUSE = 'waiting' WHERE ORDER_ID = ?")->execute([$orderId]);
            $this->db->prepare("UPDATE orders SET ORDER_STATUS = 2, SHIPP_PAYMENT = 1, DELIVERED_DATE = NULL, LAST_UPDATE = NOW(6) WHERE ORDER_ID = ?")->execute([$orderId]);

            // سجل العهدة: يُعاد بناؤه (قد يكون حُذف عند رفض كامل)
            $this->db->prepare("DELETE FROM custody_product WHERE ORDER_ID = ?")->execute([$orderId]);
            $this->db->prepare("DELETE FROM custody_orders WHERE ORDER_ID = ?")->execute([$orderId]);
            $this->db->prepare("INSERT INTO custody_orders (ORDER_ID, USER_ID, ORDER_STATUSE, SHIPPING_ADDRESS, CUST_STAT, RETURNS)
                                SELECT ORDER_ID, DELIVERY_ID, 2, ADDRESS, 'WAITING', 0 FROM orders WHERE ORDER_ID = ?")->execute([$orderId]);
            $this->db->prepare("INSERT INTO custody_product (ORDER_ID, USER_ID, PRO_CODE, QTY, PRICE, DISCOUNT, DELIVERED_QTY, RETURNED_QTY, PRO_STATUSE, LAST_UPDATE)
                                SELECT OP.ORDER_ID, CO.USER_ID, OP.PRO_CODE, OP.QTY, OP.PRICE, OP.DISCOUNT, 0, 0, 'waiting', NOW(6)
                                FROM order_product OP JOIN custody_orders CO ON CO.ORDER_ID = OP.ORDER_ID WHERE OP.ORDER_ID = ?")->execute([$orderId]);

            // 4) سجل الأمر
            $this->db->prepare("INSERT INTO orders_log (ORDER_ID, USER_ACTION, LOG_TEXT) VALUES (?, ?, ?)")
                     ->execute([$orderId, $userId, "رد الأمر للمخزن للتعديل ({$userGroupName}): " . $reason]);

            if ($own) { $this->db->commit(); }
            return ['order_id' => $orderId, 'reverse_entry_id' => $reverseId, 'inventory_rows' => $invRows];
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw ($e instanceof Exception) ? $e : new Exception($e->getMessage(), 0, $e);
        }
    }
}
