<?php
/**
 * عجز مرتجعات شركة الشحن: قطعة كان يجب أن تعود (QTY − DELIVERED_QTY) واستُلم أمرها كاملًا
 * ثم تبيّن أنها لم تصل فعليًا. يُسجَّل بعد الاستلام المخزني وقبل الاستلام المالي للأمر:
 *  - المخزون: حركة خروج بالكمية (يعدّل الرصيد الذي زاد عند استلام الأمر كاملًا).
 *  - القيد: مستحق على الشركة (1520) مقابل المخزون بالتكلفة، وفرق التقييم لو التقييم بسعر البيع.
 *  - التقييم حسب إعداد الشركة shortage_basis (COST افتراضي أو SALE_PRICE).
 */
class ShippingCompanyShortage {
    private $db;
    private $events;
    public function __construct($db, $events) { $this->db = $db; $this->events = $events; }

    /** الكمية الممكن تسجيل عجز عليها لكل منتج في الأمر */
    public function remainingLines(int $orderId): array {
        $st = $this->db->prepare(
            "SELECT cp.PRO_CODE AS pro_code, (cp.QTY - cp.DELIVERED_QTY) AS returned_qty,
                    IFNULL((SELECT SUM(s.qty) FROM shipping_company_shortages s WHERE s.order_id = cp.ORDER_ID AND s.pro_code = cp.PRO_CODE), 0) AS shorted_qty,
                    cp.PRICE AS price, cp.DISCOUNT AS discount
             FROM custody_product cp WHERE cp.ORDER_ID = ?"
        );
        $st->execute([$orderId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rem = (int)$r['returned_qty'] - (int)$r['shorted_qty'];
            if ($rem > 0) { $r['remaining'] = $rem; $out[] = $r; }
        }
        return $out;
    }

    /**
     * @param array $lines [[pro_code, qty], ...]
     * @return array{ids: int[], total: float}
     */
    public function record(int $orderId, array $lines, int $userId, string $note = ''): array {
        if (!$lines) { throw new Exception('حدد القطع التي لم تصل.'); }
        $o = $this->db->prepare(
            "SELECT co.USER_ID AS courier_id, co.RETURNS, co.CUST_STAT, o.SUB_ORDER_ID, o.STO_ID, st.BRANCH_ID, sc.id AS company_id, sc.shortage_basis
             FROM custody_orders co
             JOIN orders o ON o.ORDER_ID = co.ORDER_ID
             JOIN shipping_companies sc ON sc.user_id = co.USER_ID
             LEFT JOIN stores st ON st.STO_ID = o.STO_ID
             WHERE co.ORDER_ID = ?"
        );
        $o->execute([$orderId]);
        $ord = $o->fetch(PDO::FETCH_ASSOC);
        if (!$ord) { throw new Exception('الأمر ليس على عهدة شركة شحن (أو أُغلق ماليًا قبل تسجيل العجز).'); }
        if ((int)$ord['RETURNS'] !== 1) { throw new Exception('لا يُسجَّل عجز قبل الاستلام المخزني للأمر: استلم المرتجع أولاً ثم سجّل ما لم يصل.'); }

        $remaining = [];
        foreach ($this->remainingLines($orderId) as $r) { $remaining[$r['pro_code']] = $r; }
        $cost = $this->db->prepare("SELECT COST_PRICE FROM order_product WHERE ORDER_ID = ? AND PRO_CODE = ? LIMIT 1");

        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $ids = []; $totalValue = 0.0; $totalCost = 0.0; $stock = [];
            $ins = $this->db->prepare("INSERT INTO shipping_company_shortages (company_id, order_id, pro_code, qty, unit_cost, unit_value, basis, value, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as $ln) {
                $code = (string)$ln[0]; $qty = (int)$ln[1];
                if ($qty <= 0) { throw new Exception('كمية العجز غير صحيحة.'); }
                if (!isset($remaining[$code])) { throw new Exception("المنتج $code ليس له مرتجع متبقٍ في هذا الأمر."); }
                if ($qty > $remaining[$code]['remaining']) { throw new Exception("كمية عجز $code أكبر من المتبقي ({$remaining[$code]['remaining']})."); }
                $cost->execute([$orderId, $code]);
                $unitCost = round((float)$cost->fetchColumn(), 2);
                $unitSale = round((float)$remaining[$code]['price'] - (float)$remaining[$code]['discount'], 2);
                $unitVal  = $ord['shortage_basis'] === 'SALE_PRICE' ? $unitSale : $unitCost;
                if ($unitCost <= 0) { throw new Exception("المنتج $code بلا تكلفة: حدّد تكلفته أولاً."); }
                $val = round($unitVal * $qty, 2);
                $ins->execute([(int)$ord['company_id'], $orderId, $code, $qty, $unitCost, $unitVal, $ord['shortage_basis'], $val, $note === '' ? null : mb_substr($note, 0, 255), $userId]);
                $ids[] = (int)$this->db->lastInsertId();
                $totalValue += $val; $totalCost += round($unitCost * $qty, 2);
                $stock[$code] = ($stock[$code] ?? 0) + $qty;
                $remaining[$code]['remaining'] -= $qty;
            }
            $this->stockOut((int)$ord['STO_ID'], (int)$ord['BRANCH_ID'], $userId, $stock, $orderId, $ord['SUB_ORDER_ID']);
            $jid = $this->events->shippingShortage((int)$ord['courier_id'], $orderId, round($totalValue, 2), round($totalCost, 2), (int)$ord['STO_ID'], (int)$ord['BRANCH_ID'], (string)$ord['SUB_ORDER_ID']);
            $this->db->prepare("UPDATE shipping_company_shortages SET journal_id = ? WHERE id IN (" . implode(',', $ids) . ")")->execute([$jid]);
            if ($own) { $this->db->commit(); }
            return ['ids' => $ids, 'total' => round($totalValue, 2)];
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    /** خروج المخزون بالكميات (يمر عبر الإجراء المعتمد update_stores لتحديث الأرصدة) */
    private function stockOut(int $storeId, int $branchId, int $userId, array $qtyByCode, int $orderId, $sub): void {
        $mv = $this->db->prepare("INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, UNIQUE_ID, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED) VALUES ('SETTLEMENT', ?, ?, ?, ?, ?, ?, 1, 1)");
        $mv->execute([$userId, $branchId, $storeId, 'SC-SHORTAGE:' . $orderId, uniqid('SCS_', true), 'عجز مرتجع شركة شحن أمر ' . $sub]);
        $imId = (int)$this->db->lastInsertId();
        $cur = $this->db->prepare("SELECT STO_QTY FROM stores_product WHERE STO_ID = ? AND PRO_CODE = ? FOR UPDATE");
        $ipm = $this->db->prepare("INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY) VALUES (?, 'OUT', ?, ?, ?)");
        foreach ($qtyByCode as $code => $qty) {
            $cur->execute([$storeId, $code]);
            $have = $cur->fetchColumn();
            if ($have === false) { throw new Exception("المنتج $code غير موجود في رصيد المخزن."); }
            $ipm->execute([$imId, $code, (int)$have, $qty]);
        }
        $this->db->exec("CALL update_stores(@p_sc_return)");
    }

    public function listForCompany(int $companyId, string $status = 'OPEN'): array {
        $st = $this->db->prepare("SELECT s.*, o.SUB_ORDER_ID AS sub_order_id FROM shipping_company_shortages s JOIN orders o ON o.ORDER_ID = s.order_id WHERE s.company_id = ? AND s.status = ? ORDER BY s.id DESC");
        $st->execute([$companyId, $status]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
