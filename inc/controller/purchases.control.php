<?php
ob_start("ob_gzhandler");
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/purchases_BE.php');
include_once(ABSPATH.'/inc/Classes/secure/TrafficGuard.php');

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

$backEnd     = new purchases_BE($db);
session_write_close();
function ResponseData($data, $ErrorNumber = 200){
	global $trafficGuard, $trafficContext;
	$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	$encoding 		=false; 
	
	header('Content-Type: application/json');
	
	if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
		header('Content-Encoding: br');
		echo brotli_compress(json_encode($data, JSON_UNESCAPED_UNICODE), 9, BROTLI_GENERIC);
	}else if($encoding){
		//header('Content-Encoding: gzip');
		echo gzencode(json_encode($data, JSON_UNESCAPED_UNICODE), 9);
	}else{
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		
	}
	$statusCode = http_response_code();
	$trafficGuard->finish(
	    $trafficContext,
	    $statusCode
	);
	
	http_response_code($ErrorNumber);
	$db = null;
	unset($order_tbl,$db,$http_RQM,$DBconn,$_GET,$_POST);
	
};
$http_RQM = $_SERVER['REQUEST_METHOD'];
if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
	        case 'listPurchasesDT':
	            try {
	                
	                ResponseData($backEnd->listPurchasesDT($_GET,['SUPPLIER_ID IS NOT NULL']));
	            } catch (Exception $e) {
	                ResponseData(['error' => $e->getMessage()],500);
	            } finally {$db = null;}
	            break;
	            
	        case 'listPurchasesWanted':
	            try {
	                ResponseData($backEnd->listPurchasesDT($_GET,['SUPPLIER_ID IS NULL']));
	            } catch (Exception $e) {
	                ResponseData(['error' => $e->getMessage()],500);
	            } finally {$db = null;}
	            break;
	            
	        default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
	    }
	} else {
	    
	    ResponseData(['error' => 'CONTROL is missing'],404);
	}
}else if($http_RQM === "POST") {
	if (isset($_POST['CONTROL'])) {
	    switch ($_POST['CONTROL']) {
	        case 'listPurchasesDT':
	            try {
	                ResponseData($backEnd->listPurchasesDT($_POST),200);
	            } catch (Exception $e) {
	                ResponseData(['error' => $e->getMessage()],400);
	            } finally {$db = null;}
	            break;
	        default:
	            ResponseData(['error' => 'CONTROL value is Not found'],400);
	            break;
	    }
	} else {
	    ResponseData(['error' => 'CONTROL is missing'],403);
	}
}else{
	
	ResponseData(['error' => 'request type'],404);
}
 


/*session_start();
require_once(__DIR__ . '/../../config.php');
require_once(ABSPATH . '/inc/autoload.php'); // تضمين مكتبة Eloquent
require_once(ABSPATH . '/inc/controller/BackEnd/purchases_BE.php');
require_once(ABSPATH . 'inc/Databases/database-master/Capsule/Manager.php');
// ضبط الاتصال باستخدام Eloquent
//use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'port'      => DB_PORT,
    'database'  => DB_NAME,
    'username'  => DB_USER,
    'password'  => DB_PASSWORD,
    'charset'   => DB_CHARSET,
    'collation' => 'utf8mb4_general_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$backEnd = new purchases_BE();
$http_RQM = $_SERVER['REQUEST_METHOD'];

if ($http_RQM === "GET") {
    if (isset($_GET) && isset($_GET['CONTROL'])) {
        switch ($_GET['CONTROL']) {
            case 'listPurchasesDT':
                try {
                    http_response_code(200);
                    echo json_encode($backEnd->listPurchasesDT($_GET));
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'CONTROL value is Not found']);
                break;
        }
    }
}*/

?>