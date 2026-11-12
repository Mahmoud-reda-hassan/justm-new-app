<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/purchases_BE.php');
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$backEnd     = new purchases_BE($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];
if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
	        case 'listPurchasesDT':
	            try {
	                http_response_code(200);
	                echo json_encode($backEnd->listPurchasesDT($_GET,['SUPPLIER_ID IS NOT NULL']));
	            } catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
	            break;
	            
	        case 'listPurchasesWanted':
	            try {
	                http_response_code(200);
	                echo json_encode($backEnd->listPurchasesDT($_GET,['SUPPLIER_ID IS NULL']));
	            } catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
	            break;
	            
	        default:
	            http_response_code(400);
	            echo json_encode(['error' => 'CONTROL value is Not found']);
	            break;
	    }
	} else {
	    http_response_code(402);
	    //echo json_encode(['error' => 'CONTROL is missing']);
	}
}else if($http_RQM === "POST") {
	if (isset($_POST['CONTROL'])) {
	    switch ($_POST['CONTROL']) {
	        case 'listPurchasesDT':
	            try {
	                http_response_code(200);
	                echo json_encode($backEnd->listPurchasesDT($_POST));
	            } catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
	            break;
	        default:
	            http_response_code(404);
	            echo json_encode(['error' => 'CONTROL value is Not found']);
	            break;
	    }
	} else {
	    http_response_code(400);
	    echo json_encode(['error' => 'CONTROL is missing']);
	}
}else{
	http_response_code(400);
	echo json_encode(['error' => 'request type']);
}
 
?>