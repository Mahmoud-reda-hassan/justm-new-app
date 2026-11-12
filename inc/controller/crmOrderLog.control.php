<?php
ob_start("ob_gzhandler");
require_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/crmOrderLog_BE.php');
session_start();
// تحقق من دعم Brotli في الـ Accept-Encoding
$http_RQM = $_SERVER['REQUEST_METHOD'];
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$crm      = new crmOrderLog($db);

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
		
		switch ($_POST['CONTROL']) {
			
		//------------- listOrders ----------------------
			case 'AddCRM':
				if(isset($_POST['order_id'], 
					$_POST['employee_type'], 
					$_POST['action_type'],
					$_POST['result'], 
					$_POST['call_duration_sec'], 
					$_POST['notes'], 
					$_POST['shipDate'])
				){
					ResponseData($crm->AddCRMResult(
						$_POST['order_id'], 
						$_SESSION['USER_ID'], 
						$_POST['action_type'], 
						$_POST['result'], 
						$_POST['call_duration_sec'], 
						$_POST['employee_type'], 
						$_POST['notes']));
				}
				break;
			
			default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
		}
	}
	
}else if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
		//------------- listOrders ----------------------
			case 'test':
				$order_tbl->rq_parm = $_GET;
			    $_GET['ex'] = 0;
				ResponseData($order_tbl->listOrders());
			    break;
			
			default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
		}
		
	}
	
}
?>