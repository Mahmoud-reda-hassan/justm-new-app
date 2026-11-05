<?php
//ob_start("ob_gzhandler");
 session_start();
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
include_once(ABSPATH.'/inc/controller/BackEnd/shipment_BE.php');

$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$shipment_tbl 	= new shipment_BE($db);
$DirectSale 	= new DirectSale_BE ($db);
$RetrnSale 		= new RetrnSale_BE ($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];

function ResponseData($data){
	$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	$encoding 		=false; 
	http_response_code(200);
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
	
	$db = null;
	unset($order_tbl,$db,$http_RQM,$DBconn);
	exit();
				
};
/*===================================================
** $Database Shipment_tbl table 
**====================================================*/
    if($http_RQM == 'POST'){
    	if(isset($_POST['CONTROL'])){
	    	header("Content-type:text");
	    	switch ($_POST['CONTROL']) {
	    		case 'shipmentOut':
	    			
	    			$shipment_tbl->orderobj      = $_POST['order_id'];
					$shipment_tbl->shiping_date  = $_POST['shipDate']; 
					$shipment_tbl->delivery_id   = $_POST['shipUser'];
					$shipment_tbl->delivery_name = $_POST['shipUName'];
					$shipment_tbl->last_update   = $_POST['lastUpdate'];
					$shipment_tbl->branch_id     = $_SESSION["BRANCH_ID"];
			        $shipment_tbl->user_action   = $_SESSION["USER_ID"];
			        $shipment_tbl->user_group    = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($shipment_tbl->regEmployeeCustody());
					//$db = null;
					//unset($shipment_tbl,$db,$http_RQM,$_POST,$DBconn);
					break;
	//&&&&&&&&&&&&&&&&&&&&
				case 'shipmentRef':
					$shipment_tbl->orderobj    = $_POST['order_id'];
					$shipment_tbl->order_id    = $_POST['order_id'];
					$shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
					$shipment_tbl->user_action = $_SESSION["USER_ID"];
					$shipment_tbl->last_update   = $_POST['lastUpdate'];
					$shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($shipment_tbl->refEmployeeCustody($_POST['type']));
					break;
	//&&&&&&&&&&&&&&&&&&&&
				case 'approvedShip':
					$shipment_tbl->orderobj    = array( $_POST['odata']['oid'] => $_POST['odata']);
					$shipment_tbl->delivery_id = $_POST["delivery_id"];
					$shipment_tbl->last_update = $_POST['lastUpdate'];
					$shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
					$shipment_tbl->user_action = $_SESSION["USER_ID"];
					$shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($shipment_tbl->ShipmentRefund());
					break;

				case 'shipDelivery':
					$shipment_tbl->orderobj    = $_POST['order_id'];
					$shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
					$shipment_tbl->user_action = $_SESSION["USER_ID"];
					$shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($shipment_tbl->ShipmentDelivery());
					break;

				case 'shipDelivered':
					/*
		    			return {
	                        CONTROL: "shipDelivered",
	                        odata: result.value.odata,
	                        oshipp: {
	                            oapp: result.value.oapp,
	                            orej: result.value.orej,
	                            reapp: result.value.reapp,
	                            ptype: result.value.utype
	                        },
	                        exchange: result.value.exchange,
	                        ShipApproved: false,
	                        urlControl: Go
	                    };
                    */
                    //print_r($_POST);
                    if($_POST['oshipp']['ptype'] != 1 && $_POST['oshipp']['ptype'] != 3) ResponseData(array('error', 'خطاء', 'ليس لديك صلاحية لتنفيذ هذا الاجراء', []));
					//if($_POST['oshipp']['ptype'] == 1 && (!isset($_POST['deliveryid']) || !$_POST['deliveryid']<=0)) ResponseData(array('error', 'خطاء', 'بيانات المندوب غير متوفرة', []));
					if($_POST['oshipp']['ptype'] != 1 && ($_POST['ShipApproved'] == 'true')) ResponseData(array('error', 'خطاء', 'ليس لديك صلاحية لتنفيذ هذا الاجراء', []));
				
					$shipment_tbl->orderobj      = $_POST['odata'];
					$shipment_tbl->urlControl    = $_POST['urlControl'];
					$shipment_tbl->ShipApproved  = $_POST['ShipApproved'];
					$shipment_tbl->shipmentobj   = $_POST['oshipp'];
					$shipment_tbl->shipmentobj['request_key'] = $_POST['request_key'] ?? '';
					$shipment_tbl->ShipExchange  = $_POST['exchange'] ?? null;
					$shipment_tbl->delivery_id   = isset($_POST['deliveryid']) && $_POST['deliveryid'] > 0 ? $_POST['deliveryid'] : $_SESSION["USER_ID"];
					$shipment_tbl->branch_id     = $_SESSION["BRANCH_ID"];
					$shipment_tbl->user_action   = $_SESSION["USER_ID"];
					$shipment_tbl->user_group    = $_POST['oshipp']['ptype'] == 1 ? $_SESSION["USER_GRUOP_NAME"] : 'موظف الشحن';
					ResponseData($shipment_tbl->ShipmentDelivery('confirm'));
					break;
				case 'returnedOrderSales':
					/*
		    			return {
	                        CONTROL: "shipDelivered",
	                        odata: result.value.odata,
	                        oshipp: {
	                            oapp: result.value.oapp,
	                            orej: result.value.orej,
	                            reapp: result.value.reapp,
	                            ptype: result.value.utype
	                        },
	                        exchange: result.value.exchange,
	                        ShipApproved: false,
	                        urlControl: Go
	                    };
                    */
                    
                    if($_POST['oshipp']['ptype'] != 1 && $_POST['oshipp']['ptype'] != 3) ResponseData(array('error', 'خطاء', 'ليس لديك صلاحية لتنفيذ هذا الاجراء', []));
					//if($_POST['oshipp']['ptype'] == 1 && (!isset($_POST['deliveryid']) || !$_POST['deliveryid']<=0)) ResponseData(array('error', 'خطاء', 'بيانات المندوب غير متوفرة', []));
					if($_POST['oshipp']['ptype'] != 1 && ($_POST['ShipApproved'] == 'true')) ResponseData(array('error', 'خطاء', 'ليس لديك صلاحية لتنفيذ هذا الاجراء', []));
				
					
					$RetrnSale->urlControl    = $_POST['urlControl'];
					$RetrnSale->ShipApproved  = $_POST['ShipApproved'];
					$RetrnSale->shipmentobj   = $_POST['oshipp'];
					$RetrnSale->ShipExchange  = $_POST['exchange'];
					$RetrnSale->delivery_id   = isset($_POST['deliveryid']) && $_POST['deliveryid'] > 0 ? $_POST['deliveryid'] : $_SESSION["USER_ID"];
					$RetrnSale->branch_id     = $_SESSION["BRANCH_ID"];
					$RetrnSale->user_action   = $_SESSION["USER_ID"];
					$RetrnSale->user_group    = $_POST['oshipp']['ptype'] == 1 ? $_SESSION["USER_GRUOP_NAME"] : 'موظف الشحن';
					ResponseData($RetrnSale->CustomerReturnOfProducts($_POST['odata'],'confirm'));
					break;
					
	    		case 'countProducts':
	    			$shipment_tbl->orderobj    = $_POST['order_id'];
	   				ResponseData($shipment_tbl->countProducts());
	    			break;
	    		case 'confirmDirectOrderSales':
			        $DirectSale->branch_id   = $_SESSION["BRANCH_ID"];
			        $DirectSale->user_action = $_SESSION["USER_ID"];
			        $DirectSale->user_group  = $_SESSION["USER_GRUOP_NAME"];
					$DirectSale->last_update = $_POST['olup'];
					ResponseData($DirectSale->confirmOrderSales($_POST['oid']));
	    			break;	
				case 'custodySettlement':
					$shipment_tbl->orderobj    = $_POST["orderobj"];
	    			$shipment_tbl->delivery_id = $_POST["delivery_id"];
	   				ResponseData($shipment_tbl->custodySettlement());
	    			break;
	    			
	    		default:
	    			ResponseData(array('error', 'Error', 'Your request is not available', []));
	    			break;
	    	}
	    	exit();
    	}else{
    		
    		ResponseData(array('error', 'Error', 'The parameters sent in your request are wrong.', []));
    		exit();
    	}
    
    	
    
    }else if($http_RQM == 'GET'){
    	if(isset($_GET['CONTROL'])){
	    	header("Content-type:text");
	    	switch ($_GET['CONTROL']) {
	    		case 'shipDelivery':
	    			$shipment_tbl->orderobj    = $_GET['order_id'];
			        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
			        $shipment_tbl->user_action = $_SESSION["USER_ID"];
			        $shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($shipment_tbl->ShipmentDelivery());
	    			//$db = null;
	    			//unset($shipment_tbl,$db,$http_RQM,$_POST,$DBconn);
	    			break;
				case 'DirectSaleDelivery':
					$order_id = $_GET['order_id'];
			        $DirectSale->branch_id   = $_SESSION["BRANCH_ID"];
			        $DirectSale->user_action = $_SESSION["USER_ID"];
			        $DirectSale->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($DirectSale->CustomerDeliveryOfProducts($order_id));
	    			break;
				case 'ReturnOfProducts':
					$order_id = $_GET['order_id'];
			        $RetrnSale->branch_id   = $_SESSION["BRANCH_ID"];
			        $RetrnSale->user_action = $_SESSION["USER_ID"];
			        $RetrnSale->user_group  = $_SESSION["USER_GRUOP_NAME"];
					ResponseData($RetrnSale->CustomerReturnOfProducts($order_id));
	    			break;
	    		default:
	    			ResponseData(array(type=>'error', title=>'Error', message=>'Your request is not available'));
	    			//$db = null;
	    			//unset($shipment_tbl,$db,$http_RQM,$_POST,$DBconn);
	    			break;
	    	}
    	}else{
    		ResponseData(array('error', 'Error', 'The parameters sent in your request are wrong.', []));
    		exit();
    	}
    	
    }else{
    	ResponseData(array('error', 'Error', 'The request type is wrong', []));
    	exit();
    }
$DBconn->enableAutoLock();
$DBconn->disableAutoLock();
$DBconn->addProtectedTable('new_table');
?>