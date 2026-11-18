<?php
/**
 * شركات الشحن الخارجية (إدارة البيانات الأساسية).
 * الشركة ليست مستخدمًا يدخل النظام: كل شركة مرتبطة بمستخدم مندوب داخلي لا يستطيع الدخول
 * (كلمة المرور المخزنة 64 حرفًا بينما الدخول يقارن md5 من 32 حرفًا، واسم الدخول عشوائي).
 * حالة المستخدم نشط (SUSPENDED=1) طالما الشركة نشطة ليظهر في قوائم اختيار المندوب، وتتبع إيقاف الشركة.
 * ليعمل صرف العهدة وتأكيد التسليم وطرف DELIVERY في الأستاذ بالكود القائم دون تكرار.
 * لا يحذف شيئًا: الإيقاف فقط.
 */
require_once __DIR__ . '/ShippingCompanyImport.php';

class ShippingCompanies {
    public const POLICIES = ['SINGLE', 'PARTIAL'];
    public const METHODS  = ['NET_OF_FEE', 'GROSS_THEN_FEE'];
    public const FEE_MODES = ['ORDER_SHIPPING', 'FIXED', 'ZONE_RATE'];
    public const SHORTAGE_BASES = ['COST', 'SALE_PRICE'];

    private $db;
    public function __construct($db) { $this->db = $db; }

    /** مزامنة حالة المندوب الداخلي مع حالة الشركة (نشط = يظهر في قوائم المندوب). */
    private function syncUsers(): void {
        $this->db->exec("UPDATE users u JOIN shipping_companies c ON c.user_id = u.USER_ID SET u.SUSPENDED = c.is_active
                         WHERE u.SUSPENDED <> c.is_active AND u.USER_LOGIN LIKE 'shipco\\_%'");
    }

    public function listAll(): array {
        $this->syncUsers();
        return $this->db->query(
            "SELECT c.id, c.name, c.user_id, c.receiving_policy, c.settlement_method, c.fee_mode, c.fixed_fee,
                    c.is_active, c.note, c.created_at,
                    CONCAT(u.FORENAME, ' ', u.LASTNAME) AS courier_name, (u.USER_LOGIN LIKE 'shipco\\_%') AS courier_internal,
                    (SELECT COUNT(*) FROM shipping_company_rates r WHERE r.company_id = c.id) AS rates_count,
                    (c.import_settings IS NOT NULL AND c.import_settings <> '') AS has_import
             FROM shipping_companies c LEFT JOIN users u ON u.USER_ID = c.user_id ORDER BY c.is_active DESC, c.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $id): array {
        $st = $this->db->prepare("SELECT c.*, CONCAT(u.FORENAME, ' ', u.LASTNAME) AS courier_name, (u.USER_LOGIN LIKE 'shipco\\_%') AS courier_internal
                                  FROM shipping_companies c LEFT JOIN users u ON u.USER_ID = c.user_id WHERE c.id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new Exception('شركة الشحن غير موجودة.'); }
        return $row;
    }

    /** إعدادات قراءة كشف الشركة (JSON): الورقة، صف العناوين، الأعمدة بأسماء عناوينها، المبلغ شامل الشحن، السماحية، تفسير الحالات */
    public function saveImportSettings(int $id, $settings): void {
        $this->get($id);
        if (!is_array($settings)) { throw new Exception('إعدادات الكشف غير صالحة.'); }
        $col = function ($c) {
            if (!is_array($c)) { return null; }
            return ['name' => mb_substr(trim((string)($c['name'] ?? '')), 0, 100), 'index' => max(0, (int)($c['index'] ?? 0))];
        };
        $map = [];
        foreach ((array)($settings['status_map'] ?? []) as $k => $v) {
            if (in_array($v, ShippingCompanyImport::ACTIONS, true)) { $map[mb_substr((string)$k, 0, 100)] = $v; }
        }
        $clean = [
            'sheet' => mb_substr(trim((string)($settings['sheet'] ?? '')), 0, 100),
            'header_row' => max(1, min(100, (int)($settings['header_row'] ?? 1))),
            'order_col' => $col($settings['order_col'] ?? null),
            'amount_col' => $col($settings['amount_col'] ?? null),
            'status_col' => $col($settings['status_col'] ?? null),
            'fee_col' => $col($settings['fee_col'] ?? null),
            'includes_shipping' => !empty($settings['includes_shipping']) ? 1 : 0,
            'tolerance' => max(0, min(50, (float)($settings['tolerance'] ?? 1))),
            'status_map' => $map,
        ];
        if (!$clean['order_col']) { throw new Exception('عمود رقم الأمر مطلوب.'); }
        if (!$clean['amount_col']) { throw new Exception('عمود المبلغ المحصل مطلوب.'); }
        $st = $this->db->prepare("UPDATE shipping_companies SET import_settings = ? WHERE id = ?");
        $st->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $id]);
    }

    /** إنشاء أو تعديل. عند الإنشاء يُنشأ مستخدم مندوب داخلي موقوف تلقائيًا. */
    public function save(array $d, int $userId, int $branchId): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) { throw new Exception('اسم الشركة مطلوب (حتى 100 حرف).'); }
        $policy = (string)($d['receiving_policy'] ?? 'SINGLE');
        $method = (string)($d['settlement_method'] ?? 'NET_OF_FEE');
        $mode   = (string)($d['fee_mode'] ?? 'ORDER_SHIPPING');
        if (!in_array($policy, self::POLICIES, true))  { throw new Exception('سياسة الاستلام غير صحيحة.'); }
        if (!in_array($method, self::METHODS, true))   { throw new Exception('طريقة التسوية غير صحيحة.'); }
        if (!in_array($mode, self::FEE_MODES, true))   { throw new Exception('طريقة الرسوم غير صحيحة.'); }
        $basis = (string)($d['shortage_basis'] ?? 'COST');
        if (!in_array($basis, self::SHORTAGE_BASES, true)) { throw new Exception('أساس تقييم العجز غير صحيح.'); }
        $fixed = round((float)($d['fixed_fee'] ?? 0), 2);
        if ($fixed < 0) { throw new Exception('الرسم الثابت لا يكون سالبًا.'); }
        if ($mode === 'FIXED' && $fixed <= 0) { throw new Exception('أدخل الرسم الثابت لكل شحنة.'); }
        $note = trim((string)($d['note'] ?? ''));
        $note = $note === '' ? null : mb_substr($note, 0, 255);
        $id = (int)($d['id'] ?? 0);

        $dup = $this->db->prepare("SELECT id FROM shipping_companies WHERE name = ? AND id <> ?");
        $dup->execute([$name, $id]);
        if ($dup->fetchColumn()) { throw new Exception('اسم الشركة مستخدم من قبل.'); }

        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            if ($id > 0) {
                $row = $this->get($id);
                $st = $this->db->prepare("UPDATE shipping_companies SET name=?, receiving_policy=?, settlement_method=?, fee_mode=?, fixed_fee=?, note=?, shortage_basis=? WHERE id=?");
                $st->execute([$name, $policy, $method, $mode, $fixed, $note, $basis, $id]);
                // تغيير المندوب المرتبط (مسموح فقط لو المندوب الحالي بلا عهدة ولا أوامر ولا قيود)
                $mode = (string)($d['courier_mode'] ?? 'KEEP');
                if ($mode === 'EXISTING' || $mode === 'NEW') {
                    $newUid = $mode === 'EXISTING' ? $this->checkExistingCourier((int)($d['courier_user_id'] ?? 0)) : 0;
                    if ($newUid !== (int)$row['user_id']) {
                        $this->assertCourierUnused((int)$row['user_id']);
                        if ($mode === 'NEW') { $newUid = $this->createInternalUser($name, $branchId); }
                        $lk = $this->db->prepare("UPDATE shipping_companies SET user_id=? WHERE id=?");
                        $lk->execute([$newUid, $id]);
                        if ((int)$row['courier_internal'] === 1) {
                            // المندوب الداخلي القديم يُخفى ولا يُحذف
                            $this->db->prepare("UPDATE users SET SUSPENDED=0 WHERE USER_ID=?")->execute([(int)$row['user_id']]);
                        }
                        $row['user_id'] = $newUid;
                    }
                }
                // اسم المندوب الداخلي يتبع اسم الشركة
                $u = $this->db->prepare("UPDATE users SET FORENAME=? WHERE USER_ID=? AND USER_LOGIN LIKE 'shipco\\_%'");
                $u->execute([mb_substr($name, 0, 50), (int)$row['user_id']]);
            } else {
                $uid = ((string)($d['courier_mode'] ?? 'NEW')) === 'EXISTING'
                    ? $this->checkExistingCourier((int)($d['courier_user_id'] ?? 0))
                    : $this->createInternalUser($name, $branchId);
                $st = $this->db->prepare("INSERT INTO shipping_companies (name, user_id, receiving_policy, settlement_method, fee_mode, fixed_fee, note, shortage_basis, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
                $st->execute([$name, $uid, $policy, $method, $mode, $fixed, $note, $basis, $userId]);
                $id = (int)$this->db->lastInsertId();
            }
            if ($own) { $this->db->commit(); }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
        return $id;
    }

    /** لا يُفك ربط مندوب له حركة: عهدة أو أوامر أو قيود محاسبية */
    private function assertCourierUnused(int $uid): void {
        $q = [
            "SELECT 1 FROM custody_orders WHERE USER_ID = ? LIMIT 1",
            "SELECT 1 FROM orders WHERE DELIVERY_ID = ? LIMIT 1",
            "SELECT 1 FROM journal_entry_details WHERE partner_type = 'DELIVERY' AND partner_id = ? LIMIT 1",
        ];
        foreach ($q as $sql) {
            $st = $this->db->prepare($sql); $st->execute([$uid]);
            if ($st->fetchColumn()) { throw new Exception('لا يمكن تغيير مندوب الشركة بعد وجود عهدة أو أوامر أو قيود عليه.'); }
        }
    }

    /** مناديب موجودون (نشطون) غير مرتبطين بأي شركة شحن */
    public function availableCouriers(): array {
        return $this->db->query(
            "SELECT u.USER_ID AS id, CONCAT(u.FORENAME, ' ', u.LASTNAME) AS name
             FROM users_delivery d JOIN users u ON u.USER_ID = d.USER_ID
             WHERE u.SUSPENDED = 1 AND u.USER_LOGIN NOT LIKE 'shipco\\_%'
               AND NOT EXISTS (SELECT 1 FROM shipping_companies c WHERE c.user_id = u.USER_ID)
             ORDER BY u.FORENAME, u.LASTNAME"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function checkExistingCourier(int $uid): int {
        if ($uid <= 0) { throw new Exception('اختر المندوب المراد ربطه بالشركة.'); }
        $st = $this->db->prepare("SELECT u.SUSPENDED FROM users u JOIN users_delivery d ON d.USER_ID = u.USER_ID WHERE u.USER_ID = ?");
        $st->execute([$uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new Exception('المندوب المختار غير موجود.'); }
        if ((int)$row['SUSPENDED'] !== 1) { throw new Exception('المندوب المختار غير نشط.'); }
        $used = $this->db->prepare("SELECT 1 FROM shipping_companies WHERE user_id = ?");
        $used->execute([$uid]);
        if ($used->fetchColumn()) { throw new Exception('هذا المندوب مرتبط بشركة شحن أخرى.'); }
        return $uid;
    }

    /** مندوب داخلي (لا دخول) يمثل الشركة في العهدة والأستاذ. */
    private function createInternalUser(string $name, int $branchId): int {
        if ($branchId <= 0) { throw new Exception('تعذر تحديد الفرع.'); }
        $login = 'shipco_' . bin2hex(random_bytes(6));
        $pass  = hash('sha256', random_bytes(32));
        $st = $this->db->prepare(
            "INSERT INTO users (USER_GRUOP_ID, BRANCH_ID, FORENAME, LASTNAME, GENDER, SUSPENDED, USER_LOGIN, USER_PASSWORD,
                                ADMIN_TYPE, MERCHANT_TYPE, AFFILIATE_TYPE, INTERMEDIARY_TYPE, DELIVERY_TYPE, BIRTHDAY)
             VALUES (NULL, ?, ?, 'شركة شحن', 'male', 1, ?, ?, 0, 0, 0, 0, 1, '1984-09-30')"
        );
        $st->execute([$branchId, mb_substr($name, 0, 50), $login, $pass]);
        $uid = (int)$this->db->lastInsertId();
        $d = $this->db->prepare("INSERT INTO users_delivery (USER_ID, ZONE_ID, PERCENT) VALUES (?, NULL, 0)");
        $d->execute([$uid]);
        return $uid;
    }

    public function setActive(int $id, bool $active): void {
        $this->get($id);
        $st = $this->db->prepare("UPDATE shipping_companies SET is_active=? WHERE id=?");
        $st->execute([$active ? 1 : 0, $id]);
        $this->syncUsers();
    }

    /** المناطق مع رسم الشركة (إن وُجد) */
    public function zonesWithRates(int $companyId): array {
        $this->get($companyId);
        $st = $this->db->prepare(
            "SELECT z.ZONE_ID AS zone_id, z.ZONE_NAME AS zone_name, z.SHIPPING_RATE AS customer_rate, r.fee
             FROM zones z LEFT JOIN shipping_company_rates r ON r.zone_id = z.ZONE_ID AND r.company_id = ?
             ORDER BY z.ZONE_NAME"
        );
        $st->execute([$companyId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** حفظ أسعار المناطق: قيمة فارغة = حذف سعر المنطقة (يعني غير مسعّرة). */
    public function saveRates(int $companyId, array $rates): int {
        $this->get($companyId);
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        $n = 0;
        try {
            $up  = $this->db->prepare("REPLACE INTO shipping_company_rates (company_id, zone_id, fee) VALUES (?,?,?)");
            $del = $this->db->prepare("DELETE FROM shipping_company_rates WHERE company_id=? AND zone_id=?");
            $zchk = $this->db->prepare("SELECT 1 FROM zones WHERE ZONE_ID=?");
            foreach ($rates as $zoneId => $fee) {
                $zoneId = (int)$zoneId;
                $zchk->execute([$zoneId]);
                if (!$zchk->fetchColumn()) { throw new Exception('منطقة غير موجودة: ' . $zoneId); }
                if ($fee === '' || $fee === null) { $del->execute([$companyId, $zoneId]); continue; }
                if (!is_numeric($fee) || (float)$fee < 0) { throw new Exception('رسم غير صحيح للمنطقة ' . $zoneId); }
                $up->execute([$companyId, $zoneId, round((float)$fee, 2)]);
                $n++;
            }
            if ($own) { $this->db->commit(); }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
        return $n;
    }
}
