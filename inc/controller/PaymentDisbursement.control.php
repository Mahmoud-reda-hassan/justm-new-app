<?php
ob_start("ob_gzhandler");
require_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/PaymentDisbursement_BE.php');
session_start();
// تحقق من دعم Brotli في الـ Accept-Encoding
$http_RQM = $_SERVER['REQUEST_METHOD'];
$DBconn   = new dbconn();
$db       = $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$BE       = new PaymentDisbursement($db);

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
			case 'GetSupplierOrderList':
				$BE->Requst = $_POST;
				$supplierId = $_POST['supplierId'];
				ResponseData($BE->GetSupplierOrderList($supplierId));
				break;
			default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
		}
	}
	
};

if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
		//------------- listOrders ----------------------
			case 'GetClientData':
				$BE->Requst = $_GET;
				ResponseData($BE->GetClientData());
			    break;
			
			case 'GetDeliveryData':
				$BE->Requst = $_GET;
				ResponseData($BE->GetDeliveryData());
			    break;
			
			case 'GetMasouqData':
				$BE->Requst = $_GET;
				ResponseData($BE->GetMasouqData());
			    break;
			   
			case 'GetMoradData':
				$BE->Requst = $_GET;
				ResponseData($BE->GetMoradData());
			    break;
			
			case 'GetPartnersBalance':
				$BE->Requst = $_GET;
				$oid = (isset($_GET['oid']) && !empty($_GET['oid'])) ? $_GET['oid'] : null;
				$pid = (isset($_GET['pid']) && !empty($_GET['pid'])) ? $_GET['pid'] : null;
				$clt = (isset($_GET['pt']) && !empty($_GET['pt'])) ? $_GET['pt'] : null;
				ResponseData($BE->GetPartnersBalance($clt,$pid,$oid));
				
			    break;
			
			case 'GetClientRetrnOrder':
				$BE->Requst = $_GET;
				ResponseData($BE->GetClientRetrnOrder());
			    break;
			    
			case 'GetRefundBalance':
				$BE->Requst = $_GET;
				$oid = (isset($_GET['oid']) && !empty($_GET['oid'])) ? $_GET['oid'] : null;
				ResponseData($BE->GetRefundBalance($oid));
			    break;
			     
			default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
		}
		
	}
	
};
?>