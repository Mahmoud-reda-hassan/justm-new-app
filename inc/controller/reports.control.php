<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/reports_BE.php');

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