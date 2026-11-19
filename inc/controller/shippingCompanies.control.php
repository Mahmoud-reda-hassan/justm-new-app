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
                try { if ($sh) { $sh->execute([$oid]); } $row['shortage'] = $sh ? round((float)$sh->fetchColumn(), 2) : 0.0; } catch (Throwable $e) { $row['shortage'] = 0.0; } // الجدول قد لا يكون مُنشأ بعد
                $out[$oid] = $row;
            }
            $ok(['company' => ['id' => (int)$co['id'], 'name' => $co['name'], 'method' => $co['settlement_method']], 'orders' => $out]);
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
            $out = ['order_no' => $o['SUB_ORDER_ID'], 'status' => (int)$o['ORDER_STATUS'], 'rejected' => $rejected, 'customer_paid_shipping' => $paid, 'order_shipping' => $shipVal];
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
            if (!$scCanReceive && !$scFinView && !$scApproved) { $deny(); }
            $lines = json_decode((string)($_POST['lines'] ?? '[]'), true);
            if (!is_array($lines)) { throw new Exception('بيانات العجز غير صالحة.'); }
            $r = (new ShippingCompanyShortage($db, new AccountingEvents($db)))->record((int)($_POST['order_id'] ?? 0), $lines, (int)($_SESSION['USER_ID'] ?? 0), (string)($_POST['note'] ?? ''));
            $ok($r, 'تم تسجيل العجز على الشركة');
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
