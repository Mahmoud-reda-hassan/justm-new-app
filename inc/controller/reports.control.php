<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/reports_BE.php');
include_once(ABSPATH.'/inc/Classes/Accounting/Reports.php');

$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$CBE 	= new reports_BE($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];

function ResponseData($data){
	$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	$encoding 		=true; 
	header('Content-Type: application/json');
	if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
		header('Content-Encoding: br');
		echo brotli_compress(json_encode($data, JSON_UNESCAPED_UNICODE), 9, BROTLI_GENERIC);
	}else if($encoding){
		header('Content-Encoding: gzip');
		echo gzencode(json_encode($data, JSON_UNESCAPED_UNICODE), 9);
	}else{
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
	}
	http_response_code(200);
	$db = null;
	unset($order_tbl,$db,$http_RQM,$DBconn);
};
/*===================================================
** $Database Shipment_tbl table 
**====================================================*/

if($http_RQM != 'POST'){
    ResponseData(array('error', 'Error', 'The request type is wrong', []));
    exit();
}

if(!isset($_POST['CONTROL'])){
	ResponseData(array('error', 'Error', 'The parameters sent in your request are wrong.', []));
	exit();
}

// معالجة الطلبات
try {
	switch ($_POST['CONTROL']) {
		case 'stockShortageReport':
			ResponseData($CBE->stockShortageReport());
		    break;
		case 'SalesMovementReport':
			ResponseData($CBE->SalesMovementReport());
		    break;
		case 'SalesMovementReportExport':
			ResponseData($CBE->SalesMovementReport(true));
		    break;
		case 'SuppliersQtyReport':
			ResponseData($CBE->suppliersQtyReportIN());
		    break;
		case 'AccountStatement':
			ResponseData($CBE->AccountStatement());
		    break;
		case 'DelegatesReportTotal':
			ResponseData($CBE->getDelegatesReportTotal());
		    break;
		case 'DelegatesReportDaily':
			ResponseData($CBE->getDelegatesDailyReport());
		    break;
		case 'DelegatesReportDetails':
			ResponseData($CBE->getDelegatesDetailsReport());
		    break;
		case 'ShippingReport':
			ResponseData($CBE->getShippingReport());
		    break;
		case 'ShippingCustodyReport':
			ResponseData($CBE->getShippingCustodyReport());
		    break;
		case 'ShippingUnsettledWarehouse':
			ResponseData($CBE->getShippingCustodyReport('warehouse'));
		    break;
		case 'ShippingUnsettledFinancial':
			ResponseData($CBE->getShippingCustodyReport('financial'));
		    break;
		case 'inventoryProDailyReport':
			$storeId       = $_POST['store_id'];
			$dateFrom      =  $_POST['date_from'];
			$dateTo        = $_POST['date_to'];
			ResponseData($CBE->inventoryProDailyReport($storeId, $dateFrom, $dateTo));
		    break;
		case 'inventoryDailyReport':
			$storeId       = $_POST['store_id'];
			$dateFrom      =  $_POST['date_from'];
			$dateTo        = $_POST['date_to'];
			ResponseData($CBE->inventoryDailyReport($storeId, $dateFrom, $dateTo));
		    break;
		case 'inventoryDailyDetails':
			$storeId       = $_POST['store_id'];
			$reportDate    = $_POST['report_date'];
			ResponseData($CBE->inventoryDailyDetails($storeId, $reportDate));
		    break;
		case 'inventoryMovementProducts':
			$imid          = $_POST['im_id'];
			$dirmov        = $_POST['dir'];
			ResponseData($CBE->inventoryDailyProduct($imid,$dirmov));
		    break;
		case 'marketerDailyReport':
			$dateFrom      = $_POST['date_from'];
			$dateTo        = $_POST['date_to'];
			$partnerId     = $_SESSION['USER_ID'] == '' ? 0 : $_SESSION['USER_ID'];
			ResponseData($CBE->marketerDailyReport($partnerId, $dateFrom, $dateTo));
		    break;
		case 'marketerDailyDetails':
			$dateFrom      = $_POST['date_from'];
			$dateTo        = $_POST['date_to'];
			$partnerId     = $_SESSION['USER_ID'] == '' ? 0 : $_SESSION['USER_ID'];
			$order_statuse = $_POST['order_statuse'];
			ResponseData($CBE->marketerDailyDetails($partnerId, $order_statuse, $dateFrom, $dateTo));
		    break;
		case 'PublicReport':
			// التقرير العام: ملخص مالي من دفتر الأستاذ (أرباح وخسائر عن الفترة + أرصدة آخر الفترة)
			$us = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
			$okPerm = is_array($us) && (($us['Admin']['Reports']['View'] ?? '') === 'true');
			if (!$okPerm) { ResponseData(array('error', 'خطاء', 'ليس لديك صلاحية عرض التقرير العام', [])); break; }
			$from = $_POST['date_from'] ?? null; $to = $_POST['date_to'] ?? null;
			$tb = (new GlReports($db))->trialBalance($from, $to);
			$sum = function ($rows, $pred, $fn) { $t = 0; foreach ($rows as $r) { if ($pred($r)) { $t += $fn($r); } } return round($t, 2); };
			$net = function ($r) { return $r['closing_debit'] - $r['closing_credit']; };
			$byCode = function ($prefix) use ($tb, $sum, $net) { return $sum($tb, function ($r) use ($prefix) { return strpos($r['account_code'], $prefix) === 0; }, $net); };
			$revenue = array(); $expense = array();
			foreach ($tb as $r) {
				if ($r['account_type'] === 'REVENUE') { $v = round($r['period_credit'] - $r['period_debit'], 2); if ($v != 0) { $revenue[] = array('account_code' => $r['account_code'], 'account_name' => $r['account_name'], 'amount' => $v); } }
				if ($r['account_type'] === 'EXPENSE') { $v = round($r['period_debit'] - $r['period_credit'], 2); if ($v != 0) { $expense[] = array('account_code' => $r['account_code'], 'account_name' => $r['account_name'], 'amount' => $v); } }
			}
			$totRev = round(array_sum(array_column($revenue, 'amount')), 2);
			$totExp = round(array_sum(array_column($expense, 'amount')), 2);
			ResponseData(array('s', 'ok', array(
				'revenue' => $revenue, 'expense' => $expense,
				'total_revenue' => $totRev, 'total_expense' => $totExp, 'net_profit' => round($totRev - $totExp, 2),
				'balances' => array(
					'cash' => $byCode('11'), 'customers_owe' => $byCode('1200'), 'inventory' => $byCode('1300'),
					'suppliers_owed' => -$byCode('2100'), 'courier_payable' => -$byCode('2210'), 'marketer_payable' => -$byCode('2220'),
				),
			)));
		    break;
		default:
		    ResponseData(array('error', 'Error', 'Your request is not available', []));
			break;
		} 
}catch (Exception $e) {
	ResponseData(array('error', 'خطاء','حدث خطاء استثنائى غير معروف', [$e->getMessage()]));
}finally{
	$db = null;
}

exit();

?>