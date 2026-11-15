<?php
session_start();
/*
 * الأستاذ العام: تقارير (ميزان مراجعة، كشف حساب، كشف طرف، دفتر اليومية) + أدوات (افتتاحي النقدية، تصحيح تحصيل، إقفال الفترات، مصدر الأرصدة).
 * الصلاحيات: المجموعات المعتمدة (Administrator/Owner/المدير المالي) أو صلاحية GlLedger (view/post/admin) من شاشة المجموعات.
 */
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/Classes/secure/TrafficGuard.php');
include_once(ABSPATH.'/inc/Classes/Accounting/Reports.php');
include_once(ABSPATH.'/inc/Classes/Accounting/SystemFlags.php');
include_once(ABSPATH.'/inc/Classes/Accounting/CashOpening.php');
include_once(ABSPATH.'/inc/Classes/Accounting/CollectionCorrection.php');

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

/** الصلاحيات */
function glPerm(string $key): bool {
    $us = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
    $v = is_array($us) ? ($us['Admin']['GlLedger'][$key] ?? null) : null;
    return $v === 'true' || $v === true;
}
$glGroup    = trim((string)($_SESSION['USER_GRUOP_NAME'] ?? ''));
$glApproved = in_array($glGroup, ['Administrator', 'Owner', 'المدير المالي'], true);
$glCanView  = $glApproved || glPerm('view') || glPerm('post') || glPerm('admin');
$glCanPost  = $glApproved || glPerm('post');
$glCanAdmin = in_array($glGroup, ['Administrator', 'Owner'], true) || glPerm('admin');
$deny = function () { ResponseData(array('error', 'خطأ', 'ليس لديك صلاحية.', [])); };

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['CONTROL'])) {
    ResponseData(array('error', 'Error', 'The request type is wrong', []));
}
$userId   = (int)($_SESSION['USER_ID'] ?? 0);
$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);
$rep = new GlReports($db);
$ok  = function ($data, $msg = '') { ResponseData(array('success', 'ناجح', $msg, $data)); };

try {
    switch ($_POST['CONTROL']) {
        // ---------- تقارير (view) ----------
        case 'glTrialBalance':
            if (!$glCanView) { $deny(); }
            $ok($rep->trialBalance($_POST['from'] ?? null, $_POST['to'] ?? null, (int)($_POST['branch'] ?? 0)));
        case 'glAccountsList':
            if (!$glCanView) { $deny(); }
            $ok($db->query("SELECT id, account_code, account_name FROM accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC));
        case 'glAccountStatement':
            if (!$glCanView) { $deny(); }
            $ok($rep->accountStatement((int)($_POST['account_id'] ?? 0), $_POST['from'] ?? null, $_POST['to'] ?? null, (int)($_POST['branch'] ?? 0)));
        case 'glPartnerSearch':
            if (!$glCanView) { $deny(); }
            $ok($rep->searchPartners((string)($_POST['type'] ?? ''), (string)($_POST['q'] ?? '')));
        case 'glPartnerStatement':
            if (!$glCanView) { $deny(); }
            $ok($rep->partnerStatement((string)($_POST['type'] ?? ''), (int)($_POST['partner_id'] ?? 0), $_POST['from'] ?? null, $_POST['to'] ?? null));
        case 'glJournal':
            if (!$glCanView) { $deny(); }
            $ok($rep->journalBook($_POST['from'] ?? null, $_POST['to'] ?? null, (int)($_POST['branch'] ?? 0), (string)($_POST['ref_type'] ?? '')));
        case 'glRefTypes':
            if (!$glCanView) { $deny(); }
            $ok($rep->referenceTypes());
        case 'glEntryLines':
            if (!$glCanView) { $deny(); }
            $ok($rep->entryLines((int)($_POST['entry_id'] ?? 0)));
        case 'glValuation':
            if (!$glCanView) { $deny(); }
            $ok($rep->inventoryValuation());
        // ---------- افتتاحي النقدية (post) ----------
        case 'glCashList':
            if (!$glCanPost) { $deny(); }
            $ok((new CashOpening($db))->listAccounts());
        case 'glCashSet':
            if (!$glCanPost) { $deny(); }
            $id = (new CashOpening($db))->set((int)($_POST['account_id'] ?? 0), (float)($_POST['amount'] ?? 0), (string)($_POST['date'] ?? ''), $branchId, $userId);
            $ok(['entry_id' => $id], $id ? 'تم تسجيل الرصيد الافتتاحي' : 'لا فرق عن المسجّل');
        // ---------- تصحيح تحصيل (post) ----------
        case 'glCorrectionFind':
            if (!$glCanPost) { $deny(); }
            $ok((new CollectionCorrection($db))->find((string)($_POST['ref'] ?? '')));
        case 'glCorrectionPost':
            if (!$glCanPost) { $deny(); }
            session_write_close();
            $id = (new CollectionCorrection($db))->reverse((int)($_POST['entry_id'] ?? 0), (string)($_POST['reason'] ?? ''), $userId);
            $ok(['entry_id' => $id], 'تم عكس قيد التحصيل');
        // ---------- إدارة (admin) ----------
        case 'glPeriods':
            if (!$glCanView) { $deny(); }
            $ok($db->query("SELECT id, date_from, date_to, note, is_closed, closed_at FROM accounting_periods ORDER BY date_from DESC")->fetchAll(PDO::FETCH_ASSOC));
        case 'glPeriodClose':
            if (!$glCanAdmin) { $deny(); }
            $f = (string)($_POST['from'] ?? ''); $t = (string)($_POST['to'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) || $f > $t) { throw new Exception('تواريخ غير صحيحة.'); }
            $st = $db->prepare("INSERT INTO accounting_periods (date_from, date_to, note, is_closed, closed_by) VALUES (?, ?, ?, 1, ?)");
            $st->execute([$f, $t, mb_substr((string)($_POST['note'] ?? ''), 0, 250), $userId]);
            $ok([], 'تم إقفال الفترة');
        case 'glPeriodOpen':
            if (!$glCanAdmin) { $deny(); }
            $st = $db->prepare("UPDATE accounting_periods SET is_closed = 0, closed_by = ?, closed_at = NOW() WHERE id = ?");
            $st->execute([$userId, (int)($_POST['id'] ?? 0)]);
            $ok([], 'تم فتح الفترة');
        case 'glFlagGet':
            if (!$glCanView) { $deny(); }
            $ok(['BALANCE_SOURCE' => SystemFlags::get($db, 'BALANCE_SOURCE', 'legacy')]);
        case 'glFlagSet':
            if (!$glCanAdmin) { $deny(); }
            SystemFlags::set($db, (string)($_POST['key'] ?? ''), (string)($_POST['value'] ?? ''), $userId);
            $ok([], 'تم حفظ الإعداد');
        default:
            ResponseData(array('error', 'Error', 'Your request is not available', []));
    }
} catch (Throwable $e) {
    ResponseData(array('error', 'خطأ', $e->getMessage(), []));
}
