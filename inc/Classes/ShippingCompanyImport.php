<?php
/**
 * معاينة كشف شركة الشحن (قراءة فقط: لا تكتب أي شيء في قاعدة البيانات).
 * الكشف يُقرأ في المتصفح (xlsx / xls / csv) ويصل هنا كصفوف: رقم الأمر + المبلغ المحصل + (حالة نصية) + (رسوم الشركة).
 * كل أمر يُطابَق على عهدة الشركة ويُقارن المبلغ المحصل بقيمة الأمر في النظام لاستنتاج حالته.
 * التطبيق الفعلي للنتائج يتم في خطوة لاحقة عبر مسار تأكيد الشحن القائم.
 */
class ShippingCompanyImport {
    public const ACTIONS = ['FULL', 'PARTIAL', 'RETURN', 'RETURN_SHIPPING_PAID', 'IGNORE'];
    private const MAX_ROWS = 3000;
    private const SEARCH_NODES = 20000;

    private $db;
    public function __construct($db) { $this->db = $db; }

    /** أرقام عربية/فواصل/عملة ← رقم. فارغ = null */
    public static function normalizeAmount($v): ?float {
        if ($v === null) { return null; }
        $s = trim((string)$v);
        if ($s === '') { return null; }
        $s = strtr($s, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','٬'=>',']);
        $s = preg_replace('/[^\d.,\-]/u', '', $s);
        if ($s === '' || $s === '-') { return null; }
        if (substr_count($s, ',') > 0 && substr_count($s, '.') === 0 && preg_match('/,\d{1,2}$/', $s)) { $s = str_replace(',', '.', $s); }
        $s = str_replace(',', '', $s);
        return is_numeric($s) ? (float)$s : null;
    }

    /** رقم الأمر: إن جاء من Excel بصيغة علمية (6.9261113125989E+13) يُحوَّل لأرقام كاملة */
    public static function normalizeOrder($v): string {
        $s = trim((string)$v);
        $s = strtr($s, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if ($s !== '' && preg_match('/^\d+(\.0+)?$/', $s)) { return preg_replace('/\.0+$/', '', $s); }
        if ($s !== '' && preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $s)) { return sprintf('%.0f', (float)$s); }
        return $s;
    }

    /** الإجراء المقترح من النص الحر للحالة (يُعدَّل يدويًا من الشاشة) */
    public static function defaultAction(string $text): string {
        $t = trim($text);
        if ($t === '') { return 'IGNORE'; }
        $t = preg_replace('/[ًٌٍَُِّْ]/u', '', $t);
        if (preg_match('/دفع\s*الشحن|دفع\s*شحن/u', $t) && !preg_match('/لم\s*يدفع|ولم\s*يدفع|لا\s*يدفع/u', $t)) { return 'RETURN_SHIPPING_PAID'; }
        if (preg_match('/جزئ|جزئي/u', $t) && !preg_match('/مرتجع\s*جزئ/u', $t) || preg_match('/تم\s*التسليم\s*و/u', $t)) { return 'PARTIAL'; }
        if (preg_match('/استبدال/u', $t)) { return 'IGNORE'; }
        if (preg_match('/مرتجع|فشل|رفض|لاغي|الغاء|إلغاء|مرفوض/u', $t)) { return 'RETURN'; }
        if (preg_match('/استلم|تم\s*التسليم|تسليم|سلم/u', $t) && !preg_match('/مكرر|قبل/u', $t)) { return 'FULL'; }
        return 'IGNORE'; // تأجيل / عدم رد / ملاحظات أخرى: لا تغيير
    }

    public function preview(int $companyId, array $rows, bool $includesShipping, array $statusMap = [], float $tol = 1.0): array {
        $c = $this->db->prepare("SELECT id, name, user_id, is_active, fee_mode, fixed_fee FROM shipping_companies WHERE id = ?");
        $c->execute([$companyId]);
        $company = $c->fetch(PDO::FETCH_ASSOC);
        if (!$company) { throw new Exception('شركة الشحن غير موجودة.'); }
        if (count($rows) > self::MAX_ROWS) { throw new Exception('الكشف أكبر من ' . self::MAX_ROWS . ' صف. قسّمه على ملفين.'); }
        $courierId = (int)$company['user_id'];
        $tol = max(0.0, min($tol, 50.0));

        // تنظيف الصفوف + اكتشاف التكرار داخل الملف
        $clean = []; $seen = [];
        foreach ($rows as $i => $r) {
            $no = self::normalizeOrder($r['order'] ?? '');
            if ($no === '') { continue; }
            $clean[] = [
                'row'    => (int)($r['row'] ?? ($i + 1)),
                'order'  => $no,
                'amount' => self::normalizeAmount($r['amount'] ?? null),
                'status' => trim((string)($r['status'] ?? '')),
                'fee'    => self::normalizeAmount($r['fee'] ?? null),
            ];
            $seen[$no] = ($seen[$no] ?? 0) + 1;
        }
        if (!$clean) { throw new Exception('لا توجد صفوف بأرقام أوامر في الملف. تأكد من اختيار عمود رقم الأمر.'); }

        // الأوامر وعهدة الشركة
        $orders = []; $lines = [];
        $nos = array_keys($seen);
        foreach (array_chunk($nos, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->db->prepare(
                "SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.SHIPPING_VAL, o.SHIPP_PAYMENT,
                        co.USER_ID AS CO_USER, co.CUST_STAT
                 FROM orders o
                 LEFT JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID AND co.USER_ID = ?
                 WHERE o.SUB_ORDER_ID IN ($ph)"
            );
            $st->execute(array_merge([$courierId], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) { $orders[$o['SUB_ORDER_ID']] = $o; }
        }
        $ids = array_map(fn($o) => (int)$o['ORDER_ID'], array_filter($orders, fn($o) => $o['CO_USER'] !== null));
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->db->prepare(
                "SELECT cp.ORDER_ID, cp.PRO_CODE, cp.QTY, cp.PRICE, cp.DISCOUNT, cp.DELIVERED_QTY, cp.PRO_STATUSE
                 FROM custody_product cp WHERE cp.USER_ID = ? AND cp.ORDER_ID IN ($ph)"
            );
            $st->execute(array_merge([$courierId], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) { $lines[(int)$l['ORDER_ID']][] = $l; }
        }

        $out = []; $statuses = []; $sum = ['OK' => 0, 'REVIEW' => 0, 'ERROR' => 0];
        foreach ($clean as $r) {
            $row = [
                'row' => $r['row'], 'order' => $r['order'], 'amount' => $r['amount'], 'file_status' => $r['status'], 'fee' => $r['fee'],
                'expected' => null, 'order_value' => null, 'shipping' => null,
                'derived' => null, 'status_action' => null, 'proposed' => null,
                'flag' => 'OK', 'notes' => [], 'candidates' => [],
            ];
            $o = $orders[$r['order']] ?? null;
            $stAct = null;
            if ($r['status'] !== '') {
                $stAct = $statusMap[$r['status']] ?? self::defaultAction($r['status']);
                if (!in_array($stAct, self::ACTIONS, true)) { $stAct = 'IGNORE'; }
                $statuses[$r['status']] = ['action' => $stAct, 'count' => ($statuses[$r['status']]['count'] ?? 0) + 1];
            }
            $row['status_action'] = $stAct;

            if ($seen[$r['order']] > 1) { $row['flag'] = 'ERROR'; $row['notes'][] = 'رقم الأمر مكرر في الملف'; }
            if (!$o) {
                $row['flag'] = 'ERROR'; $row['notes'][] = 'رقم الأمر غير موجود في النظام';
            } elseif ($o['CO_USER'] === null) {
                $row['flag'] = 'ERROR'; $row['notes'][] = 'الأمر ليس على عهدة هذه الشركة';
            } else {
                $ls = $lines[(int)$o['ORDER_ID']] ?? [];
                $value = 0.0; $done = false;
                foreach ($ls as $l) {
                    $value += ((float)$l['PRICE'] - (float)$l['DISCOUNT']) * (float)$l['QTY'];
                    if ($l['PRO_STATUSE'] !== 'waiting') { $done = true; }
                }
                if ($o['CUST_STAT'] !== 'WAITING') { $done = true; }
                if ($done) { $row['flag'] = 'ERROR'; $row['notes'][] = 'نتيجة هذا الأمر مسجلة من قبل'; }
                $shipping = (float)$o['SHIPPING_VAL'];
                $expected = $value + ($includesShipping ? $shipping : 0.0);
                $row['order_value'] = round($value, 2); $row['shipping'] = round($shipping, 2); $row['expected'] = round($expected, 2);

                if ($r['amount'] !== null || $stAct === null) {
                    $a = (float)($r['amount'] ?? 0);
                    if (abs($a) <= 0.004) { $row['derived'] = 'NO_COLLECTION'; }
                    elseif (abs($a - $expected) <= $tol) { $row['derived'] = 'FULL'; }
                    elseif ($shipping > 0 && abs($a - $shipping) <= $tol) { $row['derived'] = 'RETURN_SHIPPING_PAID'; }
                    elseif ($a > $expected + $tol) { $row['derived'] = 'OVER'; $row['notes'][] = 'المبلغ المحصل أكبر من قيمة الأمر'; }
                    else {
                        $target = $includesShipping ? $a - $shipping : $a;
                        $found = $target > 0 ? $this->partialCombos($ls, $target, $tol) : [];
                        if (count($found) === 1) { $row['derived'] = 'PARTIAL'; $row['candidates'] = $found; }
                        elseif (count($found) > 1) { $row['derived'] = 'PARTIAL'; $row['candidates'] = $found; $row['notes'][] = 'أكثر من توزيع ممكن للكميات المسلمة: اختر عند التطبيق'; }
                        else { $row['derived'] = 'PARTIAL_NO_MATCH'; $row['notes'][] = 'المبلغ لا يطابق أي توزيع لكميات الأمر'; }
                    }
                }

                // الإجراء المقترح + علامة المراجعة
                $d = $row['derived'];
                if ($d === 'FULL') { $row['proposed'] = 'FULL'; }
                elseif ($d === 'RETURN_SHIPPING_PAID') { $row['proposed'] = 'RETURN_SHIPPING_PAID'; }
                elseif ($d === 'PARTIAL') { $row['proposed'] = 'PARTIAL'; }
                elseif ($d === 'NO_COLLECTION') { $row['proposed'] = in_array($stAct, ['RETURN', 'IGNORE'], true) ? $stAct : 'RETURN'; }
                elseif ($d === null && $stAct !== null) { $row['proposed'] = $stAct; }
                else { $row['proposed'] = null; }

                if ($row['flag'] !== 'ERROR') {
                    $needs = in_array($d, ['OVER', 'PARTIAL_NO_MATCH'], true) || $row['proposed'] === null
                        || ($d === 'PARTIAL') || $row['proposed'] === 'PARTIAL'
                        || ($d === 'NO_COLLECTION' && $stAct === null)
                        || ($row['proposed'] === 'IGNORE');
                    // تعارض الحالة النصية مع المبلغ
                    if ($stAct !== null && $d !== null) {
                        $ok = ($d === 'FULL' && $stAct === 'FULL')
                            || ($d === 'RETURN_SHIPPING_PAID' && in_array($stAct, ['RETURN_SHIPPING_PAID', 'RETURN'], true))
                            || ($d === 'NO_COLLECTION' && in_array($stAct, ['RETURN', 'IGNORE', 'RETURN_SHIPPING_PAID'], true))
                            || ($d === 'PARTIAL' && $stAct === 'PARTIAL');
                        if (!$ok) { $needs = true; $row['notes'][] = 'الحالة المكتوبة في الكشف تخالف المبلغ المحصل'; }
                    }
                    if ($row['proposed'] === 'IGNORE') { $row['notes'][] = 'لا تغيير (مؤجل/ملاحظة)'; }
                    if ($row['proposed'] === 'PARTIAL' && !$row['candidates']) { $row['notes'][] = 'تسليم جزئي يحتاج تحديد الكميات'; }
                    $row['flag'] = $needs ? 'REVIEW' : 'OK';
                }
            }
            $sum[$row['flag']]++;
            $out[] = $row;
        }

        // أوامر على عهدة الشركة ولم تظهر في الكشف
        $missing = $this->db->prepare(
            "SELECT o.SUB_ORDER_ID FROM custody_orders co JOIN orders o ON o.ORDER_ID = co.ORDER_ID
             WHERE co.USER_ID = ? AND co.CUST_STAT = 'WAITING' ORDER BY co.CREATE_AT DESC"
        );
        $missing->execute([$courierId]);
        $notInFile = array_values(array_filter($missing->fetchAll(PDO::FETCH_COLUMN), fn($n) => !isset($seen[$n])));

        return [
            'company' => ['id' => (int)$company['id'], 'name' => $company['name']],
            'includes_shipping' => $includesShipping, 'tolerance' => $tol,
            'summary' => $sum + ['total' => count($out)],
            'statuses' => $statuses, 'rows' => $out,
            'not_in_file' => ['count' => count($notInFile), 'sample' => array_slice($notInFile, 0, 50)],
        ];
    }

    /**
     * توزيعات الكميات المسلمة (لكل سطر 0..QTY) التي مجموع قيمتها = المبلغ المستهدف (± السماحية).
     * يرجع حتى 4 توزيعات كأوصاف نصية؛ البحث محدود العقد.
     */
    private function partialCombos(array $lines, float $target, float $tol): array {
        $items = [];
        foreach ($lines as $l) {
            $items[] = ['code' => $l['PRO_CODE'], 'qty' => (int)$l['QTY'], 'unit' => round((float)$l['PRICE'] - (float)$l['DISCOUNT'], 2)];
        }
        $n = count($items); $res = []; $nodes = 0; $cur = array_fill(0, $n, 0);
        $dfs = function (int $i, float $sum) use (&$dfs, &$res, &$nodes, &$cur, $items, $n, $target, $tol) {
            if (count($res) >= 4 || ++$nodes > self::SEARCH_NODES) { return; }
            if ($i === $n) {
                if (abs($sum - $target) <= $tol && array_sum($cur) > 0) {
                    $parts = [];
                    foreach ($items as $k => $it) { if ($cur[$k] > 0) { $parts[] = $it['code'] . ' × ' . $cur[$k]; } }
                    $res[] = implode(' + ', $parts);
                }
                return;
            }
            for ($q = 0; $q <= $items[$i]['qty']; $q++) {
                $s = $sum + $q * $items[$i]['unit'];
                if ($s > $target + $tol) { break; }
                $cur[$i] = $q; $dfs($i + 1, $s);
            }
            $cur[$i] = 0;
        };
        $dfs(0, 0.0);
        return $res;
    }
}
