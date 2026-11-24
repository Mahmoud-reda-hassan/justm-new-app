<?php
session_start();
/*
 * شركات الشحن الخارجية: بيانات أساسية (الشركة، سياسة الاستلام، طريقة التسوية، طريقة الرسوم، أسعار المناطق).
 * الصلاحيات: المجموعات المعتمدة (Administrator/Owner/المدير المالي) أو صلاحية ShippingCompanies (view/admin).
 */
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/Classes/secure/TrafficGuard.php');
include_once(ABSPATH.'/inc/Classes/ShippingCompanies.php');
include_once(ABSPATH.'/inc/Classes/ShippingCompanyImport.php');
include_once(ABSPATH.'/inc/Classes/ShippingCompanyReceipt.php');
include_once(ABSPATH.'/inc/controller/BackEnd/shipment_BE.php');
include_once(ABSPATH.'/inc/Classes/ShippingCompanyShortage.php');
include_once(ABSPATH.'/inc/Classes/ShippingCompanyFees.php');
include_once(ABSPATH.'/inc/Classes/Accounting/AccountingEvents.php');

$DBconn = new dbconn();
$db     = $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);

$trafficGuard   = new TrafficGuard($db);
$trafficContext = $trafficGuard->check($_SESSION['USER_ID'] ?? null);
if (!$trafficContext['allowed']) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    header('Retry-After: ' . $trafficContext['retry_after']);
    echo json_encode(['success' => false, 'error' => $trafficContext['reason'], 'message' => 'Too Many Requests', 'retry_after' => $trafficContext['retry_after']], JSON_UNESCAPED_UNICODE);
    exit;
}

function ResponseData($data){
    global $trafficGuard, $trafficContext;
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    $trafficGuard->finish($trafficContext, http_response_code());
    exit();
}

function scPerm(string $key): bool {
    $us = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
    $v = is_array($us) ? ($us['Admin']['ShippingCompanies'][$key] ?? null) : null;
    return $v === 'true' || $v === true;
}
$scGroup    = trim((string)($_SESSION['USER_GRUOP_NAME'] ?? ''));
$scApproved = in_array($scGroup, ['Administrator', 'Owner', 'المدير المالي'], true);
$scCanView  = $scApproved || scPerm('view') || scPerm('admin');
$scCanAdmin = $scApproved || scPerm('admin');
// شاشة عهدة المندوبين تستخدم كشف الشركة أيضًا: صلاحيتها تكفي للعرض والرفع
$scUs = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
$scCustody = is_array($scUs) && (($scUs['Admin']['OrdersCustody']['View_order_list'] ?? '') === 'true');
// شاشة الاستلام المالي تعرض تفصيل الشركة أيضًا
$scFinView = is_array($scUs) && ((($scUs['Admin']['PaymentsReceipt']['view'] ?? '') === 'true') || (($scUs['Admin']['CashReceiptVouchers']['view'] ?? '') === 'true'));
$scCanViewCustody = $scCanView || $scCustody || $scFinView;
$scCanUpload = $scCanAdmin || $scCustody;
$scCanReceive = is_array($scUs) && (($scUs['Admin']['OrdersCustody']['ShipmentApproved'] ?? '') === 'true');
$deny = function () { ResponseData(array('error', 'خطأ', 'ليس لديك صلاحية.', [])); };

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['CONTROL'])) {
    ResponseData(array('error', 'Error', 'The request type is wrong', []));
}
$userId   = (int)($_SESSION['USER_ID'] ?? 0);
$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
$sc = new ShippingCompanies($db);
$ok = function ($data, $msg = '') { ResponseData(array('success', 'ناجح', $msg, $data)); };

try {
    switch ($_POST['CONTROL']) {
        case 'scList':
            if (!$scCanView) { $deny(); }
            $ok($sc->listAll());
        case 'scCouriers':
            if (!$scCanAdmin) { $deny(); }
            $ok($sc->availableCouriers());
        case 'scGet':
            if (!$scCanView) { $deny(); }
            $ok($sc->get((int)($_POST['id'] ?? 0)));
        case 'scSave':
            if (!$scCanAdmin) { $deny(); }
            $id = $sc->save($_POST, $userId, $branchId);
            $ok(['id' => $id], 'تم حفظ شركة الشحن');
        case 'scSetActive':
            if (!$scCanAdmin) { $deny(); }
            $sc->setActive((int)($_POST['id'] ?? 0), ((string)($_POST['active'] ?? '0')) === '1');
            $ok([], 'تم تحديث حالة الشركة');
        case 'scZones':
            if (!$scCanView) { $deny(); }
            $ok($sc->zonesWithRates((int)($_POST['id'] ?? 0)));
        case 'scRatesSave':
            if (!$scCanAdmin) { $deny(); }
            $rates = isset($_POST['rates']) && is_array($_POST['rates']) ? $_POST['rates'] : [];
            $n = $sc->saveRates((int)($_POST['id'] ?? 0), $rates);
            $ok(['saved' => $n], 'تم حفظ أسعار المناطق');
        case 'scImportSave':
            if (!$scCanAdmin) { $deny(); }
            $sc->saveImportSettings((int)($_POST['id'] ?? 0), json_decode((string)($_POST['settings'] ?? ''), true));
            $ok([], 'تم حفظ إعدادات ملف الشركة');
        case 'scPendingReturns':
            // المرتجعات المعلّقة على أي مندوب (شركة أو عادي) مجمّعة على مستوى الصنف والمواصفات
            if (!$scCanViewCustody) { $deny(); }
            $cid = (int)($_POST['user_id'] ?? 0);
            $rc = new ShippingCompanyReceipt($db);
            $pend = $rc->pendingReturns($cid);
            $items = [];
            if ($pend) {
                $ids = array_map(fn($p) => (int)$p['order_id'], $pend);
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $db->prepare(
                    "SELECT cp.PRO_CODE AS pro_code, pov.PRO_NAME AS name, IFNULL(pov.OPT_JSON,'') AS options,
                            SUM(cp.QTY - cp.DELIVERED_QTY) AS qty, COUNT(DISTINCT cp.ORDER_ID) AS orders
                     FROM custody_product cp
                     LEFT JOIN product_options_view pov ON pov.PRO_CODE = cp.PRO_CODE
                     WHERE cp.USER_ID = ? AND cp.ORDER_ID IN ($in)
                     GROUP BY cp.PRO_CODE, pov.PRO_NAME, pov.OPT_JSON
                     HAVING qty > 0 ORDER BY name, cp.PRO_CODE"
                );
                $st->execute(array_merge([$cid], $ids));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $items[] = ['pro_code' => $r['pro_code'], 'name' => $r['name'] ?: $r['pro_code'], 'options' => $r['options'],
                                'qty' => (int)$r['qty'], 'orders' => (int)$r['orders']];
                }
            }
            $ok([
                'count' => count($pend),
                'sample' => array_slice(array_column($pend, 'sub_order_id'), 0, 15),
                'is_company' => (bool)$rc->companyOfCourier($cid),
                'items' => $items,
                'total_qty' => array_sum(array_column($items, 'qty')),
            ]);
        case 'scCustodyInfo':
            if (!$scCanViewCustody) { $deny(); }
            $info = (new ShippingCompanyImport($db))->companyOfCourier((int)($_POST['user_id'] ?? 0));
            if ($info) {
                $rc = new ShippingCompanyReceipt($db);
                $co = $rc->companyOfCourier((int)$_POST['user_id']);
                $pend = $rc->pendingReturns((int)$_POST['user_id']);
                $info['receiving_policy'] = $co['receiving_policy'];
                $info['pending_returns'] = count($pend);
                $info['pending_sample'] = array_slice(array_column($pend, 'sub_order_id'), 0, 15);
            }
            $ok($info);
        case 'scFinanceInfo':
            // تفصيل مالي لأوامر شركة الشحن في الاستلام المالي: الرسم المتوقع ومصدره وطريقة التسوية والعجز المفتوح
            if (!$scCanViewCustody) { $deny(); }
            $ids = json_decode((string)($_POST['order_ids'] ?? '[]'), true);
            if (!is_array($ids)) { $ids = []; }
            $fees = new ShippingCompanyFees($db);
            $co = $fees->companyOfCourier((int)($_POST['user_id'] ?? 0));
            if (!$co) { $ok(['company' => null, 'orders' => new stdClass()]); }
            $out = [];
            try { $sh = $db->prepare("SELECT IFNULL(SUM(value),0) FROM shipping_company_shortages WHERE order_id = ? AND status = 'OPEN'"); } catch (Throwable $e) { $sh = null; }
            foreach (array_slice(array_map('intval', $ids), 0, 300) as $oid) {
                $row = ['fee' => null, 'source' => null, 'note' => null, 'shortage' => 0.0];
                try { $f = $fees->feeForOrder($co, $oid); $row['fee'] = $f['fee']; $row['source'] = $f['source']; }
                catch (Exception $e) { $row['note'] = $e->getMessage(); }
                try { $sp = $db->prepare("SELECT IFNULL(SUM(value),0) FROM shipping_company_shortages WHERE order_id = ? AND status = 'OPEN' AND journal_id IS NULL"); $sp->execute([$oid]); $row['shortage_pending'] = round((float)$sp->fetchColumn(), 2); } catch (Throwable $e) { $row['shortage_pending'] = 0.0; }
                try { if ($sh) { $sh->execute([$oid]); } $row['shortage'] = $sh ? round((float)$sh->fetchColumn(), 2) : 0.0; } catch (Throwable $e) { $row['shortage'] = 0.0; } // الجدول قد لا يكون مُنشأ بعد
                $out[$oid] = $row;
            }
            $ok(['company' => ['id' => (int)$co['id'], 'name' => $co['name'], 'method' => $co['settlement_method']], 'orders' => $out]);
        case 'scNetPreview':
            // الخصم المتوقع من المحصَّل عند اختيار "خصم من المبلغ المحصَّل": عمولة المندوب أو رسم الشركة لكل أمر (للعرض فقط)
            if (!$scCanViewCustody) { $deny(); }
            $req = json_decode((string)($_POST['orders'] ?? '{}'), true);   // {order_id: المطلوب}
            if (!is_array($req)) { $req = []; }
            $courierId = (int)($_POST['user_id'] ?? 0);
            $fees = new ShippingCompanyFees($db);
            $co = $fees->companyOfCourier($courierId);
            $pc = $db->prepare("SELECT PERCENT FROM users_delivery WHERE USER_ID = ?");
            $pc->execute([$courierId]);
            $pct = (float)$pc->fetchColumn();
            $q = $db->prepare("SELECT o.SHIPPING_VAL, IFNULL(c.CUST_STAT,'') AS cust_stat FROM orders o LEFT JOIN custody_orders c ON c.ORDER_ID = o.ORDER_ID AND c.USER_ID = ? WHERE o.ORDER_ID = ?");
            $per = []; $total = 0.0;
            foreach (array_slice($req, 0, 500, true) as $oid => $required) {
                $oid = (int)$oid; $required = round((float)$required, 2); $d = 0.0; $note = null;
                if ($co) {
                    try { $f = $fees->feeForOrder($co, $oid); $d = min((float)$f['fee'], $required); } catch (Exception $e) { $note = $e->getMessage(); }
                } else {
                    $q->execute([$courierId, $oid]);
                    $r = $q->fetch(PDO::FETCH_ASSOC);
                    $base = ($r && $r['cust_stat'] !== 'FREEZE') ? (float)$r['SHIPPING_VAL'] : 0.0;
                    $ov = $fees->overrideFor($oid);
                    $d = min(($ov && $ov['kind'] === 'COURIER_COMMISSION') ? round((float)$ov['amount'], 2) : round($base * $pct / 100, 2), $required);
                }
                $d = round($d, 2); $per[$oid] = ['deduction' => $d, 'note' => $note]; $total += $d;
            }
            $ok(['kind' => $co ? 'COMPANY' : 'COURIER', 'total_deduction' => round($total, 2), 'orders' => $per]);
        case 'scOverrideSave':
        case 'scOverrideClear':
            // تعديل/إرجاع رسم الشركة أو عمولة المندوب لأمر لم يُسدَّد بعد (سبب مطلوب، ويُسجَّل في سجل الأمر)
            if (!($scCanAdmin || $scFinView)) { $deny(); }
            $oid = (int)($_POST['order_id'] ?? 0);
            $courierId = (int)($_POST['user_id'] ?? 0);
            $q = $db->prepare("SELECT o.SUB_ORDER_ID, o.ORDER_SUCCESS, o.SHIPPING_VAL FROM orders o JOIN custody_orders c ON c.ORDER_ID = o.ORDER_ID WHERE o.ORDER_ID = ? AND c.USER_ID = ? LIMIT 1");
            $q->execute([$oid, $courierId]);
            $o = $q->fetch(PDO::FETCH_ASSOC);
            if (!$o) { throw new Exception('الأمر غير موجود في عهدة هذا المندوب.'); }
            if ((int)$o['ORDER_SUCCESS'] === 1) { throw new Exception('تم سداد هذا الأمر؛ لا يمكن تعديل التسوية.'); }
            $fees = new ShippingCompanyFees($db);
            if ($_POST['CONTROL'] === 'scOverrideClear') {
                $db->prepare("DELETE FROM shipping_settlement_overrides WHERE order_id = ?")->execute([$oid]);
                $db->prepare("INSERT INTO orders_log (ORDER_ID, USER_ACTION, LOG_TEXT) VALUES (?, ?, ?)")->execute([$oid, $userId, 'إرجاع تسوية الشحن للحساب التلقائي']);
                $ok(['cleared' => true], 'تم الرجوع للحساب التلقائي');
            }
            $amount = round((float)($_POST['amount'] ?? -1), 2);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($amount < 0) { throw new Exception('المبلغ غير صحيح.'); }
            if ($reason === '') { throw new Exception('سبب التعديل مطلوب.'); }
            $co = $fees->companyOfCourier($courierId);
            if ($co) {
                $kind = 'COMPANY_FEE';
                try { $orig = $fees->feeForOrder($co, $oid, true)['fee']; } catch (Exception $e) { $orig = null; }
            } else {
                $kind = 'COURIER_COMMISSION';
                $pc = $db->prepare("SELECT PERCENT FROM users_delivery WHERE USER_ID = ?"); $pc->execute([$courierId]);
                $orig = round((float)$o['SHIPPING_VAL'] * (float)$pc->fetchColumn() / 100, 2);
            }
            $db->prepare("INSERT INTO shipping_settlement_overrides (order_id, amount, original_amount, kind, reason, created_by) VALUES (?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE amount = VALUES(amount), original_amount = VALUES(original_amount), kind = VALUES(kind), reason = VALUES(reason), created_by = VALUES(created_by), created_at = NOW()")
               ->execute([$oid, $amount, $orig, $kind, $reason, $userId]);
            $db->prepare("INSERT INTO orders_log (ORDER_ID, USER_ACTION, LOG_TEXT) VALUES (?, ?, ?)")->execute([$oid, $userId, 'تعديل تسوية الشحن (' . ($kind === 'COMPANY_FEE' ? 'رسم الشركة' : 'عمولة المندوب') . ') من ' . ($orig ?? '—') . ' إلى ' . $amount . ': ' . $reason]);
            $ok(['kind' => $kind, 'amount' => $amount, 'original' => $orig], 'تم حفظ التعديل');
        case 'scShippingCalc':
            // شرح طريقة حساب تسوية الشحن للأمر: حسب إعدادات الشركة أو نسبة المندوب (للعرض فقط)
            if (!$scCanViewCustody) { $deny(); }
            $oid = (int)($_POST['order_id'] ?? 0);
            $q = $db->prepare("SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.SHIPP_PAYMENT, o.SHIPPING_VAL, o.DELIVERY_ID, IFNULL(co.CUST_STAT,'') AS cust_stat FROM orders o LEFT JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID WHERE o.ORDER_ID = ? LIMIT 1");
            $q->execute([$oid]);
            $o = $q->fetch(PDO::FETCH_ASSOC);
            if (!$o) { throw new Exception('الأمر غير موجود.'); }
            $fees = new ShippingCompanyFees($db);
            $courierId = (int)($_POST['user_id'] ?? 0) ?: (int)$o['DELIVERY_ID'];
            $co = $fees->companyOfCourier($courierId);
            $rejected = (int)$o['ORDER_STATUS'] === 5;
            $paid = (int)$o['SHIPP_PAYMENT'] === 1;
            $shipVal = round((float)$o['SHIPPING_VAL'], 2);
            $out = ['cust_stat' => $o['cust_stat'], 'order_no' => $o['SUB_ORDER_ID'], 'status' => (int)$o['ORDER_STATUS'], 'rejected' => $rejected, 'customer_paid_shipping' => $paid, 'order_shipping' => $shipVal];
            if ($co) {
                $out['type'] = 'COMPANY';
                $out['company'] = ['name' => $co['name'], 'method' => $co['settlement_method'], 'fee_mode' => $co['fee_mode'], 'fixed_fee' => (float)$co['fixed_fee'], 'return_fee_mode' => $co['return_fee_mode'] ?? 'NONE', 'return_fixed_fee' => (float)($co['return_fixed_fee'] ?? 0)];
                try { $f = $fees->feeForOrder($co, $oid); $out['fee'] = $f['fee']; $out['source'] = $f['source']; }
                catch (Exception $e) { $out['fee'] = null; $out['note'] = $e->getMessage(); }
                $st = (new ShippingCompanyImport($db))->statementForOrders((int)$co['id'], [$oid])[$oid] ?? null;
                if ($st) { $out['statement'] = ['amount' => (float)$st['amount'], 'fee' => $st['fee'] !== null ? (float)$st['fee'] : null, 'status' => $st['file_status'] ?? null]; }
            } else {
                $pc = $db->prepare("SELECT PERCENT FROM users_delivery WHERE USER_ID = ?");
                $pc->execute([$courierId]);
                $pct = (float)$pc->fetchColumn();
                $base = $o['cust_stat'] === 'FREEZE' ? 0.0 : $shipVal;
                $out['type'] = 'COURIER';
                $out['percent'] = $pct; $out['commission_base'] = $base;
                $out['commission'] = round($base * $pct / 100, 2);
                $ovc = $fees->overrideFor($oid);
                if ($ovc && $ovc['kind'] === 'COURIER_COMMISSION') { $out['auto_commission'] = $out['commission']; $out['commission'] = round((float)$ovc['amount'], 2); $out['override'] = $ovc; }
            }
            if ($co) {
                $ovf = $fees->overrideFor($oid);
                if ($ovf && $ovf['kind'] === 'COMPANY_FEE') { $out['override'] = $ovf; try { $out['auto_fee'] = $fees->feeForOrder($co, $oid, true)['fee']; } catch (Exception $e) { $out['auto_fee'] = null; } }
            }
            $ok($out);
        case 'scShortageLines':
            if (!$scCanViewCustody) { $deny(); }
            $oid = (int)($_POST['order_id'] ?? 0);
            if (!$oid && !empty($_POST['sub_order_id'])) {
                $q = $db->prepare("SELECT o.ORDER_ID FROM orders o JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID WHERE o.SUB_ORDER_ID = ? AND co.USER_ID = ? LIMIT 1");
                $q->execute([trim((string)$_POST['sub_order_id']), (int)($_POST['user_id'] ?? 0)]);
                $oid = (int)$q->fetchColumn();
                if (!$oid) { throw new Exception('الأمر غير موجود في عهدة هذه الشركة.'); }
            }
            $ok(['order_id' => $oid, 'lines' => (new ShippingCompanyShortage($db, null))->remainingLines($oid)]);
        case 'scShortageRecord':
            // المخزن يثبّت واقعة العجز (يخرج من المخزون) ولا تُرحَّل قيوده إلا من المالية
            if (!$scCanReceive) { $deny(); }
            $lines = json_decode((string)($_POST['lines'] ?? '[]'), true);
            if (!is_array($lines)) { throw new Exception('بيانات العجز غير صالحة.'); }
            $r = (new ShippingCompanyShortage($db, new AccountingEvents($db)))->record((int)($_POST['order_id'] ?? 0), $lines, (int)($_SESSION['USER_ID'] ?? 0), (string)($_POST['note'] ?? ''));
            $ok($r, 'تم تثبيت العجز وهو بانتظار ترحيل القيد من المالية');
        case 'scShortagePost':
            // المالية ترحّل قيد العجز المثبَّت من المخزن
            if (!($scFinView || $scCanAdmin)) { $deny(); }
            $ok((new ShippingCompanyShortage($db, new AccountingEvents($db)))->post((int)($_POST['order_id'] ?? 0), (int)($_SESSION['USER_ID'] ?? 0)), 'تم ترحيل قيد العجز');
        case 'scShortagePending':
            if (!$scCanViewCustody) { $deny(); }
            $ok((new ShippingCompanyShortage($db, null))->pendingForOrder((int)($_POST['order_id'] ?? 0)));
        case 'scShortageList':
            if (!$scCanViewCustody) { $deny(); }
            $ok((new ShippingCompanyShortage($db, null))->listForCompany((int)($_POST['company_id'] ?? 0), (string)($_POST['status'] ?? 'OPEN')));
        case 'scStatementOrders':
            if (!$scCanViewCustody) { $deny(); }
            $imp = new ShippingCompanyImport($db);
            $ids = json_decode((string)($_POST['order_ids'] ?? '[]'), true);
            $ok($imp->statementForOrders((int)($_POST['company_id'] ?? 0), is_array($ids) ? $ids : []));
        case 'scStatementIds':
            if (!$scCanViewCustody) { $deny(); }
            $ok((new ShippingCompanyImport($db))->statementOrderIds((int)($_POST['company_id'] ?? 0)));
        case 'scStatementSave':
            if (!$scCanUpload) { $deny(); }
            $rows = json_decode((string)($_POST['rows'] ?? '[]'), true);
            $map  = json_decode((string)($_POST['status_map'] ?? '{}'), true);
            if (!is_array($rows)) { throw new Exception('بيانات الكشف غير صالحة.'); }
            $imp = new ShippingCompanyImport($db);
            $ok($imp->saveStatement((int)($_POST['company_id'] ?? 0), $rows, ((string)($_POST['includes_shipping'] ?? '0')) === '1', is_array($map) ? $map : [],
                (float)($_POST['tolerance'] ?? 1), (string)($_POST['file_name'] ?? ''), (string)($_POST['file_hash'] ?? ''), $userId), 'تم حفظ كشف الشركة وربطه بالأوامر');
        case 'scApplyStatement':
            // بعد رفع الكشف: الأوامر السليمة (OK) تُسجَّل نتيجتها تلقائيًا (تسليم كامل / مرفوض) بدون استلام مخزني،
            // وغير السليمة (REVIEW / ERROR) تُرجَع في جدول للمراجعة بملخص بيانات الكشف.
            if (!$scCanUpload) { $deny(); }
            $companyId = (int)($_POST['company_id'] ?? 0);
            $co = $db->prepare("SELECT id, name, user_id FROM shipping_companies WHERE id = ?");
            $co->execute([$companyId]);
            $company = $co->fetch(PDO::FETCH_ASSOC);
            if (!$company) { throw new Exception('شركة الشحن غير موجودة.'); }
            $courierId = (int)$company['user_id'];
            $actionLabel = ['FULL' => 'تسليم كامل'];   // الرفع يسجّل التسليم الكامل فقط؛ المرفوض يُسجَّل يدويًا

            $imp = $db->query("SELECT MAX(id) FROM shipping_company_imports WHERE company_id = " . $companyId)->fetchColumn();
            $rowsQ = $db->prepare(
                "SELECT r.*, co.CUST_STAT AS custody_state
                 FROM shipping_company_statement_rows r
                 JOIN shipping_companies c ON c.id = r.company_id
                 LEFT JOIN custody_orders co ON co.ORDER_ID = r.order_id AND co.USER_ID = c.user_id
                 WHERE r.company_id = ? AND r.import_id = ? ORDER BY r.row_no"
            );
            $rowsQ->execute([$companyId, (int)$imp]);
            $all = $rowsQ->fetchAll(PDO::FETCH_ASSOC);

            // سبب الرفض مطلوب في سجل أسباب الرفض: نستخدم سببًا ثابتًا للكشف (يُنشأ مرة واحدة)
            $rsn = 'مرفوض حسب كشف شركة الشحن';
            $db->prepare("INSERT IGNORE INTO reasons_data (REASON) VALUES (?)")->execute([$rsn]);
            $q = $db->prepare("SELECT ID FROM reasons_data WHERE REASON = ?"); $q->execute([$rsn]);
            $reasonId = (int)$q->fetchColumn();
            $_POST['oshipp'] = ['ptype' => 1];   // يقرؤه setConfirmShipment
            $be = new shipment_BE($db);
            $linesQ = $db->prepare("SELECT PRO_CODE, QTY FROM custody_product WHERE ORDER_ID = ? AND USER_ID = ?");
            $applied = []; $failed = []; $problems = [];
            foreach ($all as $r) {
                $note = json_decode((string)$r['notes'], true) ?: [];
                $base = ['order' => $r['sub_order_id'], 'row' => (int)$r['row_no'], 'file_status' => $r['file_status'], 'amount' => $r['amount'],
                         'company_shipping' => $r['company_shipping'], 'order_value' => $r['order_value'], 'shipping' => $r['shipping'],
                         'expected' => $r['expected'], 'flag' => $r['flag'], 'notes' => $note];
                $act = $r['proposed'];
                if ($r['flag'] !== 'OK' || !isset($actionLabel[$act]) || !$r['order_id']) {
                    if ($r['flag'] === 'OK' && in_array($act, ['RETURN', 'RETURN_SHIPPING_PAID'], true) && $r['custody_state'] === 'WAITING') {
                        $base['notes'] = array_merge(['مرفوض في الكشف — سجّله يدويًا من شاشة العهدة'], $base['notes']);
                        $base['flag'] = 'REVIEW';
                    } elseif ($r['flag'] === 'OK') { continue; }
                    $problems[] = $base + ['proposed' => $act];
                    continue;
                }
                if ($r['custody_state'] !== 'WAITING') { continue; }   // نتيجته مسجلة من قبل
                $linesQ->execute([(int)$r['order_id'], $courierId]);
                $ship = [];
                foreach ($linesQ->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    $q = (float)$l['QTY'];
                    $ship[] = ['ORDER_ID' => (int)$r['order_id'], 'PRO_CODE' => $l['PRO_CODE'], 'DELIVERY_ID' => $courierId,
                               'productData' => ['appQty' => $act === 'FULL' ? $q : 0, 'defqty' => $q, 'recqty' => null],
                               'REASON_ID' => $reasonId, 'COMMENT' => 'من كشف شركة الشحن ' . $company['name'], 'TYPE' => 'SELSE'];
                }
                if (!$ship) { $failed[] = $base + ['reason' => 'لا توجد منتجات على عهدة الشركة لهذا الأمر']; continue; }
                $be->orderobj       = [(int)$r['order_id'] => ['oid' => (int)$r['order_id'], 'shipUid' => $courierId, 'custstat' => 'WAITING']];
                $be->urlControl     = 'OrdersCustody';
                $be->ShipApproved   = false;                       // بدون استلام مخزني
                $be->RefundShipping = ($act === 'RETURN') ? 'true' : 'false';
                $be->shipmentobj    = ['ship' => $ship, 'ptype' => 1];
                $be->ShipExchange   = null;
                $be->delivery_id    = $courierId;
                $be->branch_id      = $_SESSION['BRANCH_ID'] ?? null;
                $be->user_action    = $userId;
                $be->user_group     = $_SESSION['USER_GRUOP_NAME'] ?? '';
                $res = $be->ShipmentDelivery('confirm');
                if (is_array($res) && ($res[0] ?? '') === 'success') {
                    $applied[] = ['order' => $r['sub_order_id'], 'action' => $actionLabel[$act]];
                } else {
                    $failed[] = $base + ['reason' => (string)($res[2] ?? 'تعذر تسجيل النتيجة')];
                }
            }
            $ok(['applied' => $applied, 'failed' => $failed, 'problems' => $problems], 'تم تطبيق الكشف');
        case 'scPreview':
            if (!$scCanAdmin) { $deny(); }
            $rows = json_decode((string)($_POST['rows'] ?? '[]'), true);
            $map  = json_decode((string)($_POST['status_map'] ?? '{}'), true);
            if (!is_array($rows)) { throw new Exception('بيانات الكشف غير صالحة.'); }
            $imp = new ShippingCompanyImport($db);
            $ok($imp->preview((int)($_POST['company_id'] ?? 0), $rows, ((string)($_POST['includes_shipping'] ?? '0')) === '1', is_array($map) ? $map : [], (float)($_POST['tolerance'] ?? 1)));
        default:
            ResponseData(array('error', 'Error', 'Your request is not available', []));
    }
} catch (Throwable $e) {
    ResponseData(array('error', 'خطأ', $e->getMessage(), []));
}
