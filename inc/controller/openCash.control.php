<?php
session_start();
/* الصرف/الاستلام المفتوح: قائمة الأسباب، إضافة بند، حفظ الحركة. يتطلب تسجيل الدخول؛ شاشاتها محكومة بصلاحيات الصفحات. */
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/Classes/Accounting/OpenCash.php');

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['CONTROL'])) {
    echo json_encode(array('error', 'Error', 'The request type is wrong', [])); exit();
}
$userId = (int)($_SESSION['USER_ID'] ?? 0);
if (!$userId) { echo json_encode(array('error', 'خطأ', 'يجب تسجيل الدخول.', [])); exit(); }
$us = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
$group = trim((string)($_SESSION['USER_GRUOP_NAME'] ?? ''));
$kind = ($_POST['kind'] ?? '') === 'RECEIPT' ? 'RECEIPT' : 'PAYOUT';
$page = $kind === 'RECEIPT' ? 'CashReceiptVouchers' : 'PaymentDisbursement';
$can = in_array($group, ['Administrator', 'Owner', 'المدير المالي'], true)
    || (is_array($us) && (($us['Admin'][$page]['view'] ?? null) === 'true' || ($us['Admin'][$page]['view'] ?? null) === true));
if (!$can) { echo json_encode(array('error', 'خطأ', 'ليس لديك صلاحية.', [])); exit(); }

$DBconn = new dbconn();
$db = $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$oc = new OpenCash($db);
try {
    switch ($_POST['CONTROL']) {
        case 'OpenCashAccounts':
            echo json_encode(array('success', 'ناجح', '', $oc->listAccounts($kind)), JSON_UNESCAPED_UNICODE); break;
        case 'OpenCashAddAccount':
            echo json_encode(array('success', 'ناجح', 'تمت إضافة البند', $oc->addAccount($kind, (string)($_POST['name'] ?? ''))), JSON_UNESCAPED_UNICODE); break;
        case 'OpenCashSave':
            $id = $oc->post($kind, (int)($_POST['account_id'] ?? 0), (float)($_POST['amount'] ?? 0), trim((string)($_POST['note'] ?? '')), (int)($_SESSION['BRANCH_ID'] ?? 0), $userId);
            echo json_encode(array('success', 'ناجح', $kind === 'PAYOUT' ? 'تم تسجيل الصرف' : 'تم تسجيل الاستلام', ['entry_id' => $id]), JSON_UNESCAPED_UNICODE); break;
        default:
            echo json_encode(array('error', 'Error', 'CONTROL value is Not found', []));
    }
} catch (Throwable $e) {
    echo json_encode(array('error', 'خطأ', $e->getMessage(), []), JSON_UNESCAPED_UNICODE);
}
