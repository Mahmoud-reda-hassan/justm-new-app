<?php
ob_start("ob_gzhandler");
require_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/ordersList_BE.php');
session_start();
// تحقق من دعم Brotli في الـ Accept-Encoding
$http_RQM = $_SERVER['REQUEST_METHOD'];
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$order_tbl      = new ordersList_BE($db);

function ResponseData($data){
	$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	$encoding 		=false; 
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

if($http_RQM === "POST") {
	if(isset($_POST) && isset($_POST['CONTROL'])){
		$order_tbl->rq_parm = $_POST;
		switch ($_POST['CONTROL']) {
		//------------- listOrders ----------------------
			case 'AllOrderlist':
				$_POST['ex'] = 0;
				ResponseData($order_tbl->listOrders());
				break;
			case 'ListOrdersDelivery':
				$_POST['ex'] = 0;
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'ListOrdersDelivered':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'ListOrdersMarketer':
			//  $order_tbl          = new ordersList_BE($db);
				$_POST['ex'] = 0;
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'Orderlist':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'OrdersCustody':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'DirectSale':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'SalesReturn':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'SupportAfterSales':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'SupportOrders':
			    ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'ClientOrderlist':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'ShippingConfirmation':
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
			case 'ShippingConfirmationAction':
				print_r($_POST);
				ResponseData($order_tbl->listOrders_dt_server_side());
				break;
		}
	}
	
}else if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
		//------------- listOrders ----------------------
			case 'AllOrderlist':
				$order_tbl->rq_parm = $_GET;
			    $_GET['ex'] = 0;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'ListOrdersDelivery':
				$order_tbl->rq_parm = $_GET;
			    $_GET['ex'] = 0;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'ListOrdersDelivered':
				$order_tbl->rq_parm = $_GET;
				$_GET['ex'] = 0;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'ListOrdersMarketer':
				$order_tbl->rq_parm = $_GET;
				$_GET['ex'] = 0;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'Orderlist':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'OrdersCustody':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders());
			    break;
			case 'DirectSale':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders());
				break;
			case 'SupportAfterSales':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders());
				break;
			case 'SupportOrders':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders());
				break;
			case 'ClientOrderlist':
				$order_tbl->rq_parm = $_GET;
				ResponseData($order_tbl->listOrders_NewOrder(true));
				break;
			case '':
				break;
			//------------- default --------------------------
		}
		exit;
	}
	
}
?>