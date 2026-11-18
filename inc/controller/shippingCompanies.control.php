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
$scCanViewCustody = $scCanView || $scCustody;
$scCanUpload = $scCanAdmin || $scCustody;
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
