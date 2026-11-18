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
        $clean = []; $seen = []; $idx = [];
        foreach ($rows as $i => $r) {
            $no = self::normalizeOrder($r['order'] ?? '');
            if ($no === '') { continue; }
            $item = [
                'row'    => (int)($r['row'] ?? ($i + 1)),
                'order'  => $no,
                'amount' => self::normalizeAmount($r['amount'] ?? null),
                'status' => trim((string)($r['status'] ?? '')),
                'fee'    => self::normalizeAmount($r['fee'] ?? null),
            ];
            if (isset($idx[$no])) {
                // أمر مكرر: بنفس البيانات يُدمج، وببيانات مختلفة يبقى خطأ ويُسجَّل اختلاف الصفوف
                $k = $idx[$no]; $f = $clean[$k];
                $same = (($f['amount'] === null && $item['amount'] === null) || ($f['amount'] !== null && $item['amount'] !== null && abs($f['amount'] - $item['amount']) < 0.005))
                    && $f['status'] === $item['status']
                    && (($f['fee'] === null && $item['fee'] === null) || ($f['fee'] !== null && $item['fee'] !== null && abs($f['fee'] - $item['fee']) < 0.005));
                if ($same) { $clean[$k]['dup_same'] = ($clean[$k]['dup_same'] ?? 0) + 1; }
                else { $clean[$k]['dup_conflict'][] = $item; }
                continue;
            }
            $idx[$no] = count($clean);
            $clean[] = $item;
            $seen[$no] = 1;
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
                'flag' => 'OK', 'notes' => [], 'candidates' => [], 'order_id' => null, 'processed' => false,
            ];
            $o = $orders[$r['order']] ?? null;
            $stAct = null;
            if ($r['status'] !== '') {
                $stAct = $statusMap[$r['status']] ?? self::defaultAction($r['status']);
                if (!in_array($stAct, self::ACTIONS, true)) { $stAct = 'IGNORE'; }
                $statuses[$r['status']] = ['action' => $stAct, 'count' => ($statuses[$r['status']]['count'] ?? 0) + 1];
            }
            $row['status_action'] = $stAct;

            if (!empty($r['dup_conflict'])) {
                $row['flag'] = 'ERROR';
                $fmt = fn($x) => 'صف ' . $x['row'] . ' (المحصل ' . ($x['amount'] === null ? '—' : number_format($x['amount'], 2, '.', '')) . '، الحالة ' . ($x['status'] === '' ? '—' : $x['status']) . ')';
                $row['notes'][] = 'مكرر في الملف ببيانات مختلفة: ' . implode(' / ', array_merge([$fmt($r)], array_map($fmt, $r['dup_conflict'])));
            } elseif (!empty($r['dup_same'])) {
                $row['notes'][] = 'تكرر ' . ($r['dup_same'] + 1) . ' مرات في الملف بنفس البيانات (دُمج في صف واحد)';
            }
            if (!$o) {
                $row['flag'] = 'ERROR'; $row['notes'][] = 'رقم الأمر غير موجود في النظام';
            } elseif ($o['CO_USER'] === null) {
                $row['flag'] = 'ERROR'; $row['notes'][] = 'الأمر ليس على عهدة هذه الشركة';
            } else {
                $row['order_id'] = (int)$o['ORDER_ID'];
                $ls = $lines[(int)$o['ORDER_ID']] ?? [];
                $value = 0.0; $done = false;
                foreach ($ls as $l) {
                    $value += ((float)$l['PRICE'] - (float)$l['DISCOUNT']) * (float)$l['QTY'];
                    if ($l['PRO_STATUSE'] !== 'waiting') { $done = true; }
                }
                if ($o['CUST_STAT'] !== 'WAITING') { $done = true; }
                if ($done) { $row['flag'] = 'ERROR'; $row['processed'] = true; $row['notes'][] = 'نتيجة هذا الأمر مسجلة من قبل'; }
                $shipping = (float)$o['SHIPPING_VAL'];
                // شحن النظام (على العميل) قد يختلف عن شحن الشركة المكتوب في الكشف: نقبل المبلغ مطابقًا لأي منهما
                $coShip = $r['fee'];
                $ships = [$shipping];
                if ($coShip !== null && abs($coShip - $shipping) > 0.004) { $ships[] = (float)$coShip; $row['shipping_diff'] = round($coShip - $shipping, 2); }
                $row['company_shipping'] = $coShip === null ? null : round($coShip, 2);
                $expected = $value + ($includesShipping ? $shipping : 0.0);
                $row['order_value'] = round($value, 2); $row['shipping'] = round($shipping, 2); $row['expected'] = round($expected, 2);
                $maxShip = max($ships);

                if ($r['amount'] !== null || $stAct === null) {
                    $a = (float)($r['amount'] ?? 0);
                    $fullHit = false; $shipHit = false; $partialTargets = [];
                    foreach ($ships as $sv) {
                        if (abs($a - ($value + ($includesShipping ? $sv : 0.0))) <= $tol) { $fullHit = true; }
                        if ($sv > 0 && abs($a - $sv) <= $tol) { $shipHit = true; }
                        $partialTargets[] = $includesShipping ? $a - $sv : $a;
                    }
                    if (abs($a) <= 0.004) { $row['derived'] = 'NO_COLLECTION'; }
                    elseif ($fullHit) { $row['derived'] = 'FULL'; }
                    elseif ($shipHit) { $row['derived'] = 'RETURN_SHIPPING_PAID'; }
                    elseif ($maxShip > 0 && $a <= $maxShip + $tol && in_array($stAct, ['RETURN', 'RETURN_SHIPPING_PAID'], true)) {
                        // الكشف يقول مرتجع والمحصل لا يتجاوز الشحن: العميل دفع جزءًا من الشحن (فرق شحن)
                        $row['derived'] = 'RETURN_SHIPPING_PAID';
                        $row['notes'][] = 'المحصل ' . number_format($a, 2, '.', '') . ' أقل من الشحن ' . number_format($maxShip, 2, '.', '') . ' (فرق شحن)';
                    }
                    elseif ($a > $value + ($includesShipping ? $maxShip : 0.0) + $tol) { $row['derived'] = 'OVER'; $row['notes'][] = 'المبلغ المحصل أكبر من قيمة الأمر'; }
                    else {
                        $found = [];
                        foreach (array_unique($partialTargets) as $t) {
                            if ($t > 0) { $found = array_values(array_unique(array_merge($found, $this->partialCombos($ls, $t, $tol)))); }
                        }
                        if (count($found) === 1) { $row['derived'] = 'PARTIAL'; $row['candidates'] = $found; }
                        elseif (count($found) > 1) { $row['derived'] = 'PARTIAL'; $row['candidates'] = array_slice($found, 0, 4); $row['notes'][] = 'أكثر من توزيع ممكن للكميات المسلمة: اختر عند التطبيق'; }
                        else { $row['derived'] = 'PARTIAL_NO_MATCH'; $row['notes'][] = 'المبلغ لا يطابق أي توزيع لكميات الأمر'; }
                    }
                }
                if (isset($row['shipping_diff'])) { $row['notes'][] = 'شحن الشركة ' . number_format((float)$coShip, 2, '.', '') . ' يختلف عن شحن النظام ' . number_format($shipping, 2, '.', '') . ' (فرق ' . number_format($row['shipping_diff'], 2, '.', '') . ')'; }

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

    /** يحلل الكشف ويحفظ صفوفه (آخر صف لكل أمر) ليُعرض تحت الأوامر في عهدة الشركة. لا يغيّر أي أمر أو عهدة. */
    public function saveStatement(int $companyId, array $rows, bool $includesShipping, array $statusMap, float $tol, string $fileName, string $fileHash, int $userId): array {
        $res = $this->preview($companyId, $rows, $includesShipping, $statusMap, $tol);
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $im = $this->db->prepare("INSERT INTO shipping_company_imports (company_id, file_name, file_hash, rows_total, summary, uploaded_by) VALUES (?,?,?,?,?,?)");
            $im->execute([$companyId, mb_substr($fileName, 0, 255), preg_match('/^[a-f0-9]{64}$/', $fileHash) ? $fileHash : null, count($res['rows']),
                json_encode($res['summary'], JSON_UNESCAPED_UNICODE), $userId ?: null]);
            $importId = (int)$this->db->lastInsertId();
            $up = $this->db->prepare(
                "INSERT INTO shipping_company_statement_rows
                    (company_id, import_id, order_id, sub_order_id, row_no, amount, fee, file_status, status_action, derived, proposed, flag,
                     order_value, shipping, company_shipping, shipping_diff, expected, notes, candidates)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE import_id=VALUES(import_id), order_id=VALUES(order_id), row_no=VALUES(row_no), amount=VALUES(amount), fee=VALUES(fee),
                     file_status=VALUES(file_status), status_action=VALUES(status_action), derived=VALUES(derived), proposed=VALUES(proposed), flag=VALUES(flag),
                     order_value=VALUES(order_value), shipping=VALUES(shipping), company_shipping=VALUES(company_shipping), shipping_diff=VALUES(shipping_diff),
                     expected=VALUES(expected), notes=VALUES(notes), candidates=VALUES(candidates), uploaded_at=NOW()"
            );
            // الصفوف المحفوظة سابقًا لنفس الشركة: لتفادي محو أثر كشف سابق ولتسجيل التغيير
            $prev = [];
            $keys = array_map(fn($r) => mb_substr((string)$r['order'], 0, 32), $res['rows']);
            foreach (array_chunk($keys, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $q = $this->db->prepare("SELECT r.*, i.file_name AS prev_file FROM shipping_company_statement_rows r JOIN shipping_company_imports i ON i.id = r.import_id WHERE r.company_id = ? AND r.sub_order_id IN ($ph)");
                $q->execute(array_merge([$companyId], $chunk));
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $p) { $prev[$p['sub_order_id']] = $p; }
            }
            $hist = $this->db->prepare("INSERT INTO shipping_company_statement_history (company_id, import_id, order_id, sub_order_id, row_no, amount, fee, file_status, flag, derived, proposed, uploaded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $note = $this->db->prepare("UPDATE shipping_company_statement_rows SET notes = ? WHERE id = ?");
            $num = fn($v) => $v === null ? '—' : number_format((float)$v, 2, '.', '');
            foreach ($res['rows'] as &$r) {
                $key = mb_substr((string)$r['order'], 0, 32);
                $old = $prev[$key] ?? null;
                if ($old) {
                    if (!empty($r['processed'])) {
                        // الأمر عولج بناءً على كشف سابق: يبقى صفه ولا يُستبدل، وتُضاف ملاحظة بما ورد في الكشف الأحدث
                        $n = json_decode((string)$old['notes'], true) ?: [];
                        $n[] = 'ورد مجددًا في كشف لاحق (' . $fileName . '): المحصل ' . $num($r['amount']) . '، الحالة ' . ($r['file_status'] === '' ? '—' : $r['file_status']);
                        $note->execute([json_encode($n, JSON_UNESCAPED_UNICODE), (int)$old['id']]);
                        $r['kept_old'] = true;
                        continue;
                    }
                    $changed = abs((float)$old['amount'] - (float)$r['amount']) >= 0.005 || ($old['amount'] === null) !== ($r['amount'] === null)
                        || (string)$old['file_status'] !== (string)$r['file_status']
                        || abs((float)$old['fee'] - (float)$r['fee']) >= 0.005 || ($old['fee'] === null) !== ($r['fee'] === null);
                    if ($changed) {
                        $hist->execute([$companyId, (int)$old['import_id'], $old['order_id'], $old['sub_order_id'], $old['row_no'], $old['amount'], $old['fee'], $old['file_status'], $old['flag'], $old['derived'], $old['proposed'], $old['uploaded_at']]);
                        $r['notes'][] = 'تغيّر عن الكشف السابق (' . $old['prev_file'] . '): المحصل ' . $num($old['amount']) . ' ← ' . $num($r['amount']) . '، الحالة «' . $old['file_status'] . '» ← «' . $r['file_status'] . '»';
                        if ($r['flag'] === 'OK') { $r['flag'] = 'REVIEW'; }
                        $r['changed'] = true;
                    }
                }
                $up->execute([$companyId, $importId, $r['order_id'], mb_substr((string)$r['order'], 0, 32), $r['row'], $r['amount'], $r['fee'],
                    mb_substr((string)$r['file_status'], 0, 100), $r['status_action'], $r['derived'], $r['proposed'], $r['flag'],
                    $r['order_value'], $r['shipping'], $r['company_shipping'] ?? null, $r['shipping_diff'] ?? null, $r['expected'],
                    json_encode($r['notes'], JSON_UNESCAPED_UNICODE), json_encode($r['candidates'], JSON_UNESCAPED_UNICODE)]);
            }
            unset($r);
            if ($own) { $this->db->commit(); }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
        $res['import_id'] = $importId;
        $res['kept_old'] = count(array_filter($res['rows'], fn($r) => !empty($r['kept_old'])));
        $res['changed'] = count(array_filter($res['rows'], fn($r) => !empty($r['changed'])));
        unset($res['rows']);
        return $res;
    }

    /** معلومات المندوب: هل هو مربوط بشركة شحن؟ (null لو مندوب عادي) */
    public function companyOfCourier(int $courierId): ?array {
        $st = $this->db->prepare("SELECT id, name, is_active, import_settings FROM shipping_companies WHERE user_id = ?");
        $st->execute([$courierId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) { return null; }
        $last = $this->db->prepare("SELECT file_name, uploaded_at, rows_total FROM shipping_company_imports WHERE company_id = ? ORDER BY id DESC LIMIT 1");
        $last->execute([(int)$c['id']]);
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM shipping_company_statement_rows WHERE company_id = ? AND order_id IS NOT NULL");
        $cnt->execute([(int)$c['id']]);
        return ['id' => (int)$c['id'], 'name' => $c['name'], 'is_active' => (int)$c['is_active'], 'import_settings' => $c['import_settings'],
                'last_import' => $last->fetch(PDO::FETCH_ASSOC) ?: null, 'linked_orders' => (int)$cnt->fetchColumn()];
    }

    /** صفوف الكشف المحفوظة لأوامر معينة (مفتاحها رقم الأمر) مع الحالة الحالية للعهدة */
    public function statementForOrders(int $companyId, array $orderIds): array {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) { return []; }
        $ph = implode(',', array_fill(0, count($orderIds), '?'));
        $st = $this->db->prepare(
            "SELECT r.*, i.file_name, co.CUST_STAT AS custody_state
             FROM shipping_company_statement_rows r
             JOIN shipping_company_imports i ON i.id = r.import_id
             JOIN shipping_companies c ON c.id = r.company_id
             LEFT JOIN custody_orders co ON co.ORDER_ID = r.order_id AND co.USER_ID = c.user_id
             WHERE r.company_id = ? AND r.order_id IN ($ph)"
        );
        $st->execute(array_merge([$companyId], $orderIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['notes'] = json_decode((string)$r['notes'], true) ?: [];
            $r['candidates'] = json_decode((string)$r['candidates'], true) ?: [];
            $out[(int)$r['order_id']] = $r;
        }
        return $out;
    }

    /** كل أوامر الكشف المرتبطة بالشركة (لفلتر "الواردة من الشركة") */
    public function statementOrderIds(int $companyId): array {
        $st = $this->db->prepare("SELECT order_id FROM shipping_company_statement_rows WHERE company_id = ? AND order_id IS NOT NULL");
        $st->execute([$companyId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
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
