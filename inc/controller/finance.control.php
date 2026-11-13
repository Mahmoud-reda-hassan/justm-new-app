<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');

$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$CBE 	= new finance_BE($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];
/*===================================================
** $Database Shipment_tbl table 
**====================================================*/
if($http_RQM != 'POST'){
    echo json_encode(array('error', 'Error', 'The request type is wrong', []));
    exit();
}

if(!isset($_POST['CONTROL'])){
	echo json_encode(array('error', 'Error', 'The parameters sent in your request are wrong.', []));
	exit();
}

header('Content-Type: application/json');
// معالجة الطلبات
try {
	
	switch ($_POST['CONTROL']) {
		case 'CollectingFinancialPledge':
			$CBE->transaction_user_id = $_SESSION['USER_ID'];
			$CBE->user_group 		  = $_SESSION['USER_GRUOP_NAME'];
			echo json_encode($CBE->registerCollection($_POST['delivery_id'], $_POST['orderObj'], $_POST['totalAmount']));
		    break;
		case 'PaymentOfCustomerDues':
		    $CBE->transaction_user_id = $_SESSION['USER_ID'];
			$CBE->user_group 		  = $_SESSION['USER_GRUOP_NAME'];
		    $orderIds = json_decode($_POST['order_ids'] ?? '[]', true);
		    $clientId = (int)($_POST['client_id'] ?? 0);
		    echo json_encode($CBE->PaymentOfCustomerDues($orderIds, $clientId));
			break;
		case 'PaymentOfCustomerDirect':
		    $CBE->transaction_user_id = $_SESSION['USER_ID'];
			$CBE->user_group 		  = $_SESSION['USER_GRUOP_NAME'];
		    echo json_encode($CBE->PaymentOfCustomerDirect($_POST['oid']));
			break;
		case 'SaveSupplierPayment':
			$supplier_id= $_POST['supplier_id'];
			$total_amount = $_POST['total_amount'];
			$orders = isset($_POST['orders']) ? $_POST['orders']: [];
			echo json_encode($CBE->PaymentOfSupplierDues($supplier_id, $total_amount, $orders));
			break;
		case 'PaymentOfMarketerDues':
			$marketerId= $_POST['partner_id'];
			$total_amount = $_POST['amount'];
			$total_balance = isset($_POST['balance']) ? $_POST['balance']: 0;
			echo json_encode($CBE->PaymentOfMarketerDues($marketerId, $total_balance, $total_amount));
			break;
		case 'PaymentOfDeliveryDues':
			$deliveryid= $_POST['partner_id'];
			$total_amount = $_POST['amount'];
			$total_balance = isset($_POST['balance']) ? $_POST['balance']: 0;
			echo json_encode($CBE->PaymentOfDeliveryDues($deliveryid, $total_balance, $total_amount));
			break;

		default:
		    echo json_encode(array('error', 'Error', 'Your request is not available', []));
			break;
		}
}catch (Exception $e) {
	echo json_encode(array('error', 'خطاء', $e->getMessage()));
}finally{
	$db = null;
}
exit();

?>