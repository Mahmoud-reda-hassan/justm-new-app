<?php
session_start();

include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/accounts_BE.php');
include_once(ABSPATH.'/inc/Classes/secure/TrafficGuard.php');

header("Content-type:application/json");
header('Content-Encoding: gzip');
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);

$trafficGuard = new TrafficGuard($db);
$trafficContext = $trafficGuard->check(
    $_SESSION['USER_ID'] ?? null
);
if (!$trafficContext['allowed']) {

    http_response_code(429);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Retry-After: ' .
        $trafficContext['retry_after']
    );


    echo json_encode(
        [
            'success' => false,

            'error' =>
                $trafficContext['reason'],

            'message' =>
                'Too Many Requests',

            'retry_after' =>
                $trafficContext['retry_after']
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}
session_write_close();
$backEnd     = new accounts_BE($db);

$http_RQM = $_SERVER['REQUEST_METHOD'];
if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
			case 'ReceivePayments':
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listAccount(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersDelivery':
			   // $backEnd          = new ordersList_BE($db);
			    $_GET['ex'] = 0;
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersDelivered':
			  //  $backEnd          = new ordersList_BE($db);
				$_GET['ex'] = 0;
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'ListOrdersMarketer':
			  //  $backEnd          = new ordersList_BE($db);
				$_GET['ex'] = 0;
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listOrders(),JSON_UNESCAPED_UNICODE),9);
			    $db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			case 'Orderlist':
			   // $backEnd          = new ordersList_BE($db);
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			    
			//------------- default --------------------------
			case 'OrdersCustody':
			    //if(isset($_SESSION["USER_ID"])){
			    //	$backEnd          = new ordersList_BE($db);
				$backEnd->rq_parm = $_GET;
				//http_response_code(200);
				echo gzencode(json_encode($backEnd->listOrders(),JSON_UNESCAPED_UNICODE),9);
				$db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			  //  }
		}

	}
}
$statusCode = http_response_code();
	$trafficGuard->finish(
	    $trafficContext,
	    $statusCode
	);
	
?>