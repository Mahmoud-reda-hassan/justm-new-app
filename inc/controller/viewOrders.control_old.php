<?php
 session_start();
 header("Content-type:application/json");
 header('Content-Encoding: gzip');
 
/*
 * DataTables example server-side processing script.
 *
 * Please note that this script is intentionally extremely simple to show how
 * server-side processing can be implemented, and probably shouldn't be used as
 * the basis for a large complex system. It is suitable for simple use cases as
 * for learning.
 *
 * See https://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - https://datatables.net/license_mit
 */
 
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Easy set variables
 */
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/ordersList_BE.php');
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$order_tbl      = new ordersList_BE($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];
if($http_RQM === "POST") {
	if(isset($_POST) && isset($_POST['CONTROL'])){
		
		switch ($_POST['CONTROL']) {
		//------------- filterOrder ----------------------
		/*	case 'filterOrder':
			    $order_tbl          = new Order_tbl($db);
				$data=array();
				$data[]=$order_tbl->filterOrder();
				$data[]=$order_tbl->radioOrderExists();
				if(isset($_SESSION["USER_ID"])){
		    		$arr  =array(
		        	    "ui" => $_SESSION["USER_ID"],
		        	    "country" => $_SESSION["COUNTRY_ID"],
		        	    "gov" => $_SESSION["GOV_ID"],
		        	    "city" => $_SESSION["CITY_ID"],
		        	    "adminComId" => $_SESSION["COMPANY_ID"],
		        	    "adminBranchId" => $_SESSION["BRANCH_ID"],
		        	    "admin" => $_SESSION["ADMIN_TYPE"],
		        	    "merchat" => $_SESSION["MERCHANT_TYPE"],
		        	    "affiliate" => $_SESSION["AFFILIATE_TYPE"],
		        	    "intermediary" => $_SESSION["INTERMEDIARY_TYPE"],
		        	    "delivery" => $_SESSION["DELIVERY_TYPE"],
		    	        "uScript" => $UserScript
		    	    );
		    		$data[]=$arr;
				}
				http_response_code(200);
				echo json_encode($data);
			    break;
		//------------- orderView ------------------------
			case 'orderView':
			    if(!empty($_POST["order_id"])){
					$order_tbl          = new Order_tbl($db);
					$order_tbl->order_id = $_GET['order_id'];
					echo json_encode($order_tbl->orderView());
				}else{
					echo json_encode('Failure_params');
				}
			    break;
		//------------ radioOrderExists ------------------
			case 'radioOrderExists':
				$order_tbl          = new Order_tbl($db);
				echo json_encode($order_tbl->radioOrderExists());
		        exit;
			    break;
			    
		*/   
			    
		//------------- listOrders ----------------------
			case 'AllOrderlist':
			   // $order_tbl          = new ordersList_BE($db);
			    $_POST['ex'] = 0;
			    
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'ListOrdersDelivery':
			   // $order_tbl          = new ordersList_BE($db);
			    $_POST['ex'] = 0;
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'ListOrdersDelivered':
			  //  $order_tbl          = new ordersList_BE($db);
				$_POST['ex'] = 0;
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'ListOrdersMarketer':
			  //  $order_tbl          = new ordersList_BE($db);
				$_POST['ex'] = 0;
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    //echo json_encode($order_tbl->listOrders());$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'Orderlist':
			   // $order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'ClientOrderlist':
				
				// $order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'OrdersCustody':
				
			   // $order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			//------------- default --------------------------
			case 'DirectSale':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'SalesReturn':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'SupportAfterSales':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'SupportOrders':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_POST;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case '':
		}

	}
}else if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		
		switch ($_GET['CONTROL']) {
		//------------- filterOrder ----------------------
		/*	case 'filterOrder':
			    $order_tbl          = new Order_tbl($db);
				$data=array();
				$data[]=$order_tbl->filterOrder();
				$data[]=$order_tbl->radioOrderExists();
				if(isset($_SESSION["USER_ID"])){
		    		$arr  =array(
		        	    "ui" => $_SESSION["USER_ID"],
		        	    "country" => $_SESSION["COUNTRY_ID"],
		        	    "gov" => $_SESSION["GOV_ID"],
		        	    "city" => $_SESSION["CITY_ID"],
		        	    "adminComId" => $_SESSION["COMPANY_ID"],
		        	    "adminBranchId" => $_SESSION["BRANCH_ID"],
		        	    "admin" => $_SESSION["ADMIN_TYPE"],
		        	    "merchat" => $_SESSION["MERCHANT_TYPE"],
		        	    "affiliate" => $_SESSION["AFFILIATE_TYPE"],
		        	    "intermediary" => $_SESSION["INTERMEDIARY_TYPE"],
		        	    "delivery" => $_SESSION["DELIVERY_TYPE"],
		    	        "uScript" => $UserScript
		    	    );
		    		$data[]=$arr;
				}
				http_response_code(200);
				echo json_encode($data);
			    break;
		//------------- orderView ------------------------
			case 'orderView':
			    if(!empty($_POST["order_id"])){
					$order_tbl          = new Order_tbl($db);
					$order_tbl->order_id = $_GET['order_id'];
					echo json_encode($order_tbl->orderView());
				}else{
					echo json_encode('Failure_params');
				}
			    break;
		//------------ radioOrderExists ------------------
			case 'radioOrderExists':
				$order_tbl          = new Order_tbl($db);
				echo json_encode($order_tbl->radioOrderExists());
		        exit;
			    break;
			    
		*/   
			    
		//------------- listOrders ----------------------
			case 'AllOrderlist':
			   // $order_tbl          = new ordersList_BE($db);
			    $_GET['ex'] = 0;
			    
				$order_tbl->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersDelivery':
			   // $order_tbl          = new ordersList_BE($db);
			    $_GET['ex'] = 0;
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersDelivered':
			  //  $order_tbl          = new ordersList_BE($db);
				$_GET['ex'] = 0;
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersMarketer':
			  //  $order_tbl          = new ordersList_BE($db);
				$_GET['ex'] = 0;
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'Orderlist':
				
			   // $order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'OrdersCustody':
				
			   // $order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			//------------- default --------------------------
			case 'DirectSale':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'SupportAfterSales':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'SupportOrders':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$order_tbl          = new ordersList_BE($db);
				$order_tbl->rq_parm = $_GET;
				http_response_code(200);
				echo gzencode(json_encode($order_tbl->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($order_tbl,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case '':
		}
		exit;

	}
}
?>