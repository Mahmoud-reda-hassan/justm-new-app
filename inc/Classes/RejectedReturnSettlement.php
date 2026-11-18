<?php
/**
 * تسوية الأمر المرفوض غير المدفوع الشحن بعد استلام مرتجعه مخزنيًا.
 * الاستلام المخزني يحذف المرفوض من العهدة (لا يظهر في الاستلام المالي)، فتُسجَّل هنا:
 *  - مندوب عادي: عمولته من قيمة الشحن بالنظام × نسبته (قيد 5700/2210 + رصيد المندوب القديم).
 *  - شركة شحن: رسم المرتجع حسب إعدادها، أو رقم الكشف إن وُجد (قيد 5720/2240 + سجل الرسوم).
 * لا تكرار: قيد واحد لكل أمر. الأمر الذي دفع عميله الشحن لا يُسوّى هنا (له مساره المالي).
 */
require_once __DIR__ . '/ShippingCompanyFees.php';
require_once __DIR__ . '/Accounting/AccountingEvents.php';

class RejectedReturnSettlement {
    private $db;
    private $events;
    public function __construct($db, $events = null) { $this->db = $db; $this->events = $events ?: new AccountingEvents($db); }

    /** @return array|null بيانات التسوية، أو null إن لم يكن الأمر مستحقًا لها */
    public function settle(int $orderId, int $courierId, int $branchId): ?array {
        $st = $this->db->prepare("SELECT ORDER_STATUS, IFNULL(SHIPP_PAYMENT,0) AS SHIPP_PAYMENT, SHIPPING_VAL FROM orders WHERE ORDER_ID = ?");
        $st->execute([$orderId]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o || (int)$o['ORDER_STATUS'] !== 5 || (int)$o['SHIPP_PAYMENT'] === 1 || $courierId <= 0) { return null; }

        $fees = new ShippingCompanyFees($this->db);
        $co = $fees->companyOfCourier($courierId);
        if ($co) {
            $f = $fees->feeForOrder($co, $orderId);
            if ($f['fee'] <= 0) { return null; }
            $jid = $this->events->rejectedReturnSettlement($orderId, $courierId, 0.0, $f['fee'], $branchId);
            if (!$jid) { return null; }
            $this->db->prepare("INSERT INTO shipping_company_fees (company_id, order_id, fee, source, method, gross_collected, payable_amount, journal_id) VALUES (?,?,?,?,?,0,?,?)
                ON DUPLICATE KEY UPDATE fee=VALUES(fee), source=VALUES(source), payable_amount=VALUES(payable_amount), journal_id=VALUES(journal_id)")
                ->execute([(int)$co['id'], $orderId, $f['fee'], $f['source'], $co['settlement_method'], $f['fee'], $jid]);
            return ['type' => 'COMPANY_FEE', 'amount' => $f['fee'], 'journal_id' => $jid];
        }

        $p = $this->db->prepare("SELECT PERCENT FROM users_delivery WHERE USER_ID = ?");
        $p->execute([$courierId]);
        $pct = $p->fetchColumn();
        $commission = round((float)$o['SHIPPING_VAL'] * (float)($pct === false ? 0 : $pct) / 100, 2);
        if ($commission <= 0) { return null; }
        $jid = $this->events->rejectedReturnSettlement($orderId, $courierId, $commission, 0.0, $branchId);
        if (!$jid) { return null; }
        $this->db->prepare("INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, transaction_category_id) VALUES ('DELIVERY', ?, ?, 'CREDIT', ?, ?, '12')")
            ->execute([$courierId, $orderId, $commission, 'عمولة مستحقة للمندوب عن أمر مرفوض مستلم']);
        return ['type' => 'COURIER_COMMISSION', 'amount' => $commission, 'journal_id' => $jid];
    }
}
