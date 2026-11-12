<?php
ob_start("ob_gzhandler");
include_once(__dir__.'/../config.php');
include_once(ABSPATH.'/inc/Classes/user_permission.php');
include_once(ABSPATH.'/inc/auth_system.php'); // تحميل ملف التشفير
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/Classes/secure/TrafficGuard.php');

$Database 	= new dbconn();
$db        	= $Database->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);

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


function ResponseData($data){
	global $trafficGuard, $trafficContext;
	$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	$encoding 		=false; 
	
	http_response_code();
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
	$statusCode = http_response_code();
	$trafficGuard->finish(
	    $trafficContext,
	    $statusCode
	);
	//$db = null;
	//unset($order_tbl,$db,$http_RQM,$DBconn,$_GET,$_POST);
				
};

function dateTimeNaw(){
   $datetime = date_create_from_format('Y-m-d h:i:s', $datetime, 'Africa/Cairo');
    // Get the current date.
    $date = date('Y-m-d');
    // Check if the current date is between the last Friday of April and the last Thursday of October.
    if (in_array(date('w', strtotime($date)), [5, 6])) {
      // Add 60 minutes to the current time.
      $datetime = date('Y-m-d H:i:s', time() + 3600);
    } else {
      // The current date is not in the summer time period.
      $datetime = date('H:i:s');
    }
    // Display the current time after adjusting it.
    return $datetime;
}

function load_class($class) {
    include_once ABSPATH.'/inc/Classes/'.$class . '.php';
}

function injectionSession2($data, $fingerprint){
	$_SESSION["FINGERPRINT"] = $fingerprint;  // 🔥 إضافة بصمة المتصفح لحماية الجلسة
    $_SESSION["LOGIN_TIME"] = time();
    $_SESSION["USER_ID"] = $data["USER_ID"];
    $_SESSION["FNAME"] = $data["FORENAME"];
    $_SESSION["LNAME"] = $data["LASTNAME"];
    $_SESSION["GENDER"] = $data["GENDER"];
    $_SESSION["SUSPENDED"] = $data["SUSPENDED"];
    $_SESSION["USER_GRUOP_NAME"] = $data["USER_GRUOP_NAME"];
    $_SESSION["USER_IMG_LINK"] = empty($data["USER_IMG_LINK"]) ? 
                                 ($data["GENDER"] == "male" || $data["GENDER"] == "ذكر" ? "img/usericon-male.png" : 
                                 ($data["GENDER"] == "female" || $data["GENDER"] == "أنثى" ? "img/usericon-female.png" : "img/usericon.png")) 
                                 : $data["USER_IMG_LINK"];
    $_SESSION["COMPANY_LOGO"] = empty($data["COMPANY_LOGO"]) ? "img/logo.png" : $data["COMPANY_LOGO"];
    $_SESSION["COMPANY_NAME"] = $data["COMPANY_NAME"];
    $_SESSION["ADMIN_TYPE"] = $data["ADMIN_TYPE"];
    $_SESSION["MERCHANT_TYPE"] = $data["MERCHANT_TYPE"];
    $_SESSION["AFFILIATE_TYPE"] = $data["AFFILIATE_TYPE"];
    $_SESSION["INTERMEDIARY_TYPE"] = $data["INTERMEDIARY_TYPE"];
    $_SESSION["DELIVERY_TYPE"] = $data["DELIVERY_TYPE"];
    $_SESSION["USER_SCRIPT"] = $data["USER_SCRIPT"];
    $_SESSION["COMPANY_ID"] = $data["COMPANY_ID"];
    $_SESSION["BRANCH_ID"] = $data["BRANCH_ID"];
    $_SESSION["BRANCH_NAME"] = $data["BRANCH_NAME"];
    $_SESSION["CITY_ID"] = $data["CITY_ID"];
    $_SESSION["GOV_ID"] = $data["GOV_ID"];
    $_SESSION["COUNTRY_ID"] = $data["COUNTRY_ID"];
}




spl_autoload_register('load_class');

$chlogin = false;
$UserScript  = array();
$uTAdmin    = false;
$uTAffil    = false;
$uTDelivery = false;
if(isset($_SESSION) && count($_SESSION)>0 && isset($_SESSION['USER_ID']) && !empty($_SESSION['USER_ID'])) {
    $chlogin     = true;
    if(isset($_SESSION['ADMIN_TYPE'])){
        $uTAdmin = $_SESSION['ADMIN_TYPE'];
        $UserScript  = unserialize($_SESSION['USER_SCRIPT']);
    }
    if(isset($_SESSION['AFFILIATE_TYPE'])){
        $uTAffil    = $_SESSION['AFFILIATE_TYPE'];
    }else if(isset($_SESSION['DELIVERY_TYPE'])){
    	
    	$uTDelivery = $_SESSION['DELIVERY_TYPE'];
    }
}
$http_RQM = $_SERVER['REQUEST_METHOD'];
/*=======================================
	$Database => USERS TABLE
========================================*/
    if($http_RQM === 'POST' && isset($_POST["logout"])){
		session_unset();
		session_destroy();
		setcookie("USER_AUTH", "", time() - 604800, "/", "", true, true);
		setcookie("fpId", "", time() - 604800, "/");
        echo count($_SESSION);
        exit();
    } //end logout 
    
    
    
    
    /*if($http_RQM === 'POST' && isset($_POST['CONTROL'], $_POST['email'], $_POST['pass']) && $_POST['CONTROL'] == 'login') {
	    $Users_tbl = new Users_tbl($db);
	    $result = $Users_tbl->Userlogin();
	
	    if ($result->rowCount() !== NULL && $result->rowCount() > 0) {
	        $row = $result->fetch(PDO::FETCH_ASSOC);
	        // تخزين بيانات المستخدم في الجلسة
	        injectionSession($row);	
	        // 🔥 تخزين وقت بدء الجلسة
	        $_SESSION["LOGIN_TIME"] = time();
	        http_response_code(200);
	        ResponseData(count($_SESSION));
	        exit();
	    } else {
	        http_response_code(200);
	        ResponseData(0);
	        exit();
	    }
	} */// End login API
	/*
	if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['CONTROL'], $_POST['email'], $_POST['pass'], $_COOKIE['fpId']) && $_POST['CONTROL'] == 'login') {
	    
	    $Users_tbl = new Users_tbl($db);
	    
	    $result = $Users_tbl->Userlogin();
	    if ($result->rowCount() > 0) {
	        $row = $result->fetch(PDO::FETCH_ASSOC);

	        // 🔐 التحقق من كلمة المرور
	        if (!count($row)) {
	            http_response_code(200);
		        ResponseData(0);
		        exit();
	        }
	        
	        // ✅ تخزين بيانات المستخدم في الجلسة
	     	injectionSession($row, $_COOKIE['fpId']);
	        $remember = $_POST['remember'] ?? false;
			setcookie(
				'rem',
				$_POST['remember'],
				time() + (86400 * 365),
				"/",
				"",
				true,  // Secure (HTTPS بس)
				false  // HttpOnly (مايتقراش من JS)
			);
			$Security = new Security();
	        $Security->saveUserCookie($row, $_COOKIE['fpId'], $_COOKIE['rem']);
	        
	        http_response_code(200);
	        ResponseData(count($_SESSION));
	        exit();
	    } else {
	    	
	        http_response_code(200);
	        ResponseData(0);
	        exit();
	    }
	} 
	*/
	if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['CONTROL'], $_POST['email'], $_POST['pass'], $_COOKIE['fpId']) && $_POST['CONTROL'] == 'login') {
	    $Users_tbl = new Users_tbl($db);
	    $result = $Users_tbl->Userlogin();
	
	    if ($result->rowCount() > 0) {
	        $row = $result->fetch(PDO::FETCH_ASSOC);
	
	        if (!count($row)) {
	            http_response_code(200);
	            ResponseData(0);
	            exit();
	        }
	
	        injectionSession($row, $_COOKIE['fpId']); // 🔥 تخزين السيشن وربطه بالـ fingerprint
	        $remember = $_POST['remember'] ?? false;
	
	        setcookie(
	            'rem',
	            $_POST['remember'],
	            time() + (86400 * 365),
	            "/",
	            "",
	            true,  // Secure
	            true  // HttpOnly
	        );
	
	        $Security = new Security();
	        $Security->saveUserCookie($row, $_COOKIE['fpId'], $_COOKIE['rem']); // 🔐 تشفير البيانات وتخزينها بالكوكي
	
	        http_response_code(200);
	        ResponseData(count($_SESSION));
	        exit();
	    } else {
	        http_response_code(200);
	        ResponseData(0);
	        exit();
	    }
	}
    if($http_RQM === 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL']  == 'valSess'){
	    $arr  =array('','','');
	    if(isset($_SESSION) && isset($_SESSION["USER_ID"])){
	        $Users_tbl = new Users_tbl($db);
	        $Users_tbl->USER_ID = $_SESSION["USER_ID"]; //password_verify($_SESSION["USER_ID"]);
	        $res = $Users_tbl->getScript();
			if(
			    !($res['ADMIN_TYPE'] == $_SESSION["ADMIN_TYPE"] && 
			    $res['MERCHANT_TYPE'] == $_SESSION["MERCHANT_TYPE"] &&
			    $res['AFFILIATE_TYPE'] == $_SESSION["AFFILIATE_TYPE"] &&
			    $res['INTERMEDIARY_TYPE'] == $_SESSION["INTERMEDIARY_TYPE"] &&
			    $res['DELIVERY_TYPE'] == $_SESSION["DELIVERY_TYPE"] &&
			    $res["USER_SCRIPT"] == $UserScript)
			){
			    $_SESSION["ADMIN_TYPE"]        =$res["ADMIN_TYPE"];
			    $_SESSION["MERCHANT_TYPE"]     =$res["MERCHANT_TYPE"];
    			$_SESSION["AFFILIATE_TYPE"]    =$res["AFFILIATE_TYPE"];
			    $_SESSION["INTERMEDIARY_TYPE"] =$res["INTERMEDIARY_TYPE"];
    			$_SESSION["DELIVERY_TYPE"]     =$res["DELIVERY_TYPE"];
		        $_SESSION["USER_SCRIPT"]       =$res["USER_SCRIPT"];
			    $UserScript                    = unserialize($res["USER_SCRIPT"]);
			    $arr["msg"]                    = array('w','قام المسؤولين بالإدارة بتغير صلاحيات الدخول الخاصه بك');
			}
			$arr["ui"]            = $_SESSION["USER_ID"];
			$arr["country"]       = $_SESSION["COUNTRY_ID"];
			$arr["gov"]           = $_SESSION["GOV_ID"];
			$arr["city"]          = $_SESSION["CITY_ID"];
			$arr["adminComId"]    = $_SESSION["COMPANY_ID"];
			$arr["adminBranchId"] = $_SESSION["BRANCH_ID"];
			$arr["admin"]         = $_SESSION["ADMIN_TYPE"];
			$arr["merchat"]       = $_SESSION["MERCHANT_TYPE"];
			$arr["affiliate"]     = $_SESSION["AFFILIATE_TYPE"];
			$arr["intermediary"]  = $_SESSION["INTERMEDIARY_TYPE"];
			$arr["delivery"]      = $_SESSION["DELIVERY_TYPE"];
			$arr["uScript"]       = $UserScript;
			http_response_code(200);
			ResponseData($arr);
		} else {
		//	http_response_code(500);
			ResponseData($arr);
		}
	    exit();
    }
    if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'UserList') {
	    if($UserScript['Admin']['Users']['View'] == "true"){
	        $Users_tbl = new Users_tbl($db);
	        if(isset($_GET['start']) ){
		        ResponseData($Users_tbl->UserList());
	        }else{
		        ResponseData(['e','Failure_params']);
	        }
	    }
    }
    
    
    if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getDeliveryList') {
	   // if($UserScript['Admin']['Users']['View'] == "true"){
	        $Users_tbl = new Users_tbl($db);
	       // if(isset($_GET['start']) ){
		        ResponseData($Users_tbl->getDeliveryList());
	       // }else{
		   //     ResponseData(['e','Failure_params']);
	       // }
	   // }
    }
    
    if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getMarketerList') {
	    //if($UserScript['Admin']['Users']['View'] == "true"){
	        $Users_tbl = new Users_tbl($db);
	        //if(isset($_GET['start']) ){
		        ResponseData($Users_tbl->getMarketerList());
	       // }else{
		   //     ResponseData(['e','Failure_params']);
	       // }
	   // }
    }
   
    if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'UserListForType') {
	    if($UserScript['Admin']['Users']['View'] == "true" || 
	       $UserScript['Admin']['orderlist_ex9']['View_order_list'] == "true"){
	        $Users_tbl = new Users_tbl($db);
	        if(isset($_GET['utype'])){
    		    $rq = $Users_tbl->UserList();
    		    $rq = $rq['udata'][$_GET['utype']];
    		    
    		    ResponseData($rq);
	        }else{
	            ResponseData($Users_tbl->UserList());
	        }
	    }
    }
	if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'UserData') {
	    if($UserScript['Admin']['Users']['Update'] == "true"){
	        if(isset($_GET['user_id'])){
				$Users_tbl = new Users_tbl($db);
		        $Users_tbl->USER_ID =$_GET['user_id'];
		        ResponseData($Users_tbl->UserList());
	        }else{
		        ResponseData(['e','Failure_params']);
	        }
	    }
    }
	if($uTAdmin == 1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'insertUser'){
	    if($UserScript['Admin']['Users']['Addnew'] == "true"){
	        $Users_tbl = new Users_tbl($db);
			$x = $_POST['data'];
			if(!isset($x['USER_IMG_LINK'])){
				$x['USER_IMG_LINK'] = "";
			}
	        //$Users_tbl->USER_ID            = '';
		    $Users_tbl->USER_GRUOP_ID      = $x['USER_GRUOP_ID'];
		    $Users_tbl->BRANCH_ID          = $x['BRANCH_ID'];
		    $Users_tbl->FORENAME           = $x['FORENAME'];
		    $Users_tbl->LASTNAME           = $x['LASTNAME'];
		    $Users_tbl->GENDER             = $x['GENDER'];
		    $Users_tbl->SUSPENDED          = $x['SUSPENDED'];
		    $Users_tbl->USER_LOGIN         = $x['USER_LOGIN'];
		    $Users_tbl->USER_PASSWORD      = $x['USER_PASSWORD'];
		    //$Users_tbl->RE_PASSCODE        = $x['RE_PASSCODE'];
		    //$Users_tbl->RE_PASS_EXPIRE     = $x['RE_PASS_EXPIRE'];
		    $Users_tbl->ADMIN_TYPE         = $x['ADMIN_TYPE'];
		    $Users_tbl->MERCHANT_TYPE      = $x['MERCHANT_TYPE'];
		    $Users_tbl->AFFILIATE_TYPE     = $x['AFFILIATE_TYPE'];
		    $Users_tbl->INTERMEDIARY_TYPE  = $x['INTERMEDIARY_TYPE'];
		    $Users_tbl->DELIVERY_TYPE      = $x['DELIVERY_TYPE'];
		    $Users_tbl->USER_IMG_LINK      = $x['USER_IMG_LINK'];
		    //---------telphone-------------
		    $Users_tbl->DEFAULTTELL        = 1;
            $Users_tbl->WHATSAPP           = $x['whatsapp'];
            $Users_tbl->TELEGRAM           = $x['telegram'];
            $Users_tbl->TEL_TYPE           = $x['telType'];
            $Users_tbl->TEL_NUMBER         = $x['clitel'];
            //---------address-------------
            $Users_tbl->CITY_ID            = $x['addressCity'];
            $Users_tbl->DEFAULTADD         = 1;
            $Users_tbl->ADDRESS_TITLE      = $x['addressType'];
            $Users_tbl->ADDRESS            = $x['cliaddress'];
		    //---------affilate-------------
		    $Users_tbl->AFFILIATEED_WITH   = $x['AFFILIATEED_WITH'];
		    $Users_tbl->LIVEL              = 0;
		    $Users_tbl->COMMISSION_SALES   = $x['COMMISSION_SALES'];
		    $Users_tbl->TEAM_COMMISSION    = $x['TEAM_COMMISSION'];
		    //---------Delivery-------------
		    $Users_tbl->ZONE_ID            = $x['ZONE_ID'];
		    $Users_tbl->PERCENT            = $x['SHIPP_PERCENT'];
		    $Users_tbl->insertUser();
	    }
	}
    if($uTAdmin == 1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'uppass'){
	    if($UserScript['Admin']['Users']['Update'] == "true"){
		    if(isset($_POST['user_id'],$_POST['uppass'])){
		    	$Users_tbl = new Users_tbl($db);
		        ResponseData($Users_tbl->updatePassword($_POST['user_id'], $_POST['uppass']));
	        }else{
		        ResponseData(['e','Failure_params']);
	        }
	        
	    }else{
	    	ResponseData(['w','نأسف . انت لا تملك صلاحية لتنفيذ هذا الاجراء']);
	    }
    }
    if($uTAdmin == 1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateUser') {
	    if($UserScript['Admin']['Users']['Update'] == "true"){
	        $Users_tbl = new Users_tbl($db);
			
			$x = $_POST['data'];
			
			if(!isset($x['ADDRESS_ID'])){
				$x['ADDRESS_ID'] = "";
			}
			if(!isset($x['USER_IMG_LINK'])){
				$x['USER_IMG_LINK'] = "";
			}
	        $Users_tbl->USER_ID            = $x['USER_ID'];
	        $Users_tbl->TEL_ID             = $x['TEL_ID'];
	        $Users_tbl->ADDRESS_ID         = $x['ADDRESS_ID'];
		    $Users_tbl->USER_GRUOP_ID      = $x['USER_GRUOP_ID'];
		    $Users_tbl->BRANCH_ID          = $x['BRANCH_ID'];
		    $Users_tbl->FORENAME           = $x['FORENAME'];
		    $Users_tbl->LASTNAME           = $x['LASTNAME'];
		    $Users_tbl->GENDER             = $x['GENDER'];
		    $Users_tbl->SUSPENDED          = $x['SUSPENDED'];
		    $Users_tbl->USER_LOGIN         = $x['USER_LOGIN'];
		    $Users_tbl->USER_PASSWORD      = $x['USER_PASSWORD'];
		    $Users_tbl->ADMIN_TYPE         = $x['ADMIN_TYPE'];
		    $Users_tbl->MERCHANT_TYPE      = $x['MERCHANT_TYPE'];
		    $Users_tbl->AFFILIATE_TYPE     = $x['AFFILIATE_TYPE'];
		    $Users_tbl->INTERMEDIARY_TYPE  = $x['INTERMEDIARY_TYPE'];
		    $Users_tbl->DELIVERY_TYPE      = $x['DELIVERY_TYPE'];
		    $Users_tbl->USER_IMG_LINK      = $x['USER_IMG_LINK'];
		    //---------telphone-------------
		    $Users_tbl->DEFAULTTELL        = 1;
            $Users_tbl->WHATSAPP           = $x['whatsapp'];
            $Users_tbl->TELEGRAM           = $x['telegram'];
            $Users_tbl->TEL_TYPE           = $x['telType'];
            $Users_tbl->TEL_NUMBER         = $x['clitel'];
            //---------address-------------
            $Users_tbl->CITY_ID            = $x['addressCity'];
            $Users_tbl->DEFAULTADD         = 1;
            $Users_tbl->ADDRESS_TITLE      = $x['addressType'];
            $Users_tbl->ADDRESS            = $x['cliaddress'];
		    //---------affilate-------------
		    $Users_tbl->AFFILIATEED_WITH   = $x['AFFILIATEED_WITH'];
		    $Users_tbl->COMMISSION_SALES   = $x['COMMISSION_SALES'];
		    $Users_tbl->TEAM_COMMISSION    = $x['TEAM_COMMISSION'];
		    //---------Delivery-------------
		    $Users_tbl->ZONE_ID            = $x['ZONE_ID'];
		    $Users_tbl->PERCENT            = $x['SHIPP_PERCENT'];
		    $Users_tbl->updateUser();
	    }
    }
/*===================================================
** $Database => USER_GROUP table 
**==================================================*/
	if($http_RQM == 'GET'  && $uTAdmin == 1 && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getlistUserGroup') {
	    if($UserScript['Admin']['Administrative-Structure']['View'] == "true"){
		    $UserGroup_tbl = new User_group_tbl($db);
			$UserGroup_tbl->listUserGroup();
	    } else {
	        ResponseData('not_permission_view');
	    }
	    exit();
	}
	if($http_RQM == 'GET'  && $uTAdmin == 1 && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getdropUserGroup') {
	    if($UserScript['Admin']['Administrative-Structure']['View'] == "true"){
		    $UserGroup_tbl = new User_group_tbl($db);
			$UserGroup_tbl->Select_user_group();
	    } else {ResponseData(['e','not_permission_add_new']);
	    	
	    }
	    exit;
	}
	if($http_RQM == 'POST' && $uTAdmin == 1 && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'permissionArray') {
	    if($UserScript['Admin']['Administrative-Structure']['Addnew'] == "true" || $UserScript['Admin']['Administrative-Structure']['Update'] == "true"){
			$UserGroup_tbl = new User_group_tbl($db);
			$UserGroup_tbl->user_group_id = $_POST['data_id'];
			$UserGroup_tbl->user_permission_array();
	    } else {
			ResponseData('not_permission_view');
		}
	    exit();
	}
	if($http_RQM == 'POST' && $uTAdmin == 1 && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'AddUserGroup') {
	    if($UserScript['Admin']['Administrative-Structure']['Addnew'] == "true"){
		    if(
			!empty($_POST['data-id']) && 
			!empty($_POST['uname'])   && 
			!empty($_POST['script'])
			){
		        $UserGroup_tbl = new User_group_tbl($db);
				$UserGroup_tbl->user_group_name = $_POST['uname'];
		        $UserGroup_tbl->user_script     = $_POST['script'];
		        $UserGroup_tbl->AddUserGroup();
		    } else {ResponseData(['e','Failure_params']);}
	    } else {ResponseData(['e','not_permission_add_new']);}
	    exit;
	}
	if($http_RQM == 'POST' && $uTAdmin == 1 && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateUserGroup') {
	    if($UserScript['Admin']['Administrative-Structure']['Update'] == "true"){
		    if(
			!empty($_POST['data-id']) && 
			!empty($_POST['uname'])   && 
			!empty($_POST['script'])
			){
		        $UserGroup_tbl = new User_group_tbl($db);
				$UserGroup_tbl->user_group_id   = $_POST['data-id'];
		        $UserGroup_tbl->user_group_name = $_POST['uname'];
		        $UserGroup_tbl->user_script     = $_POST['script'];
		        $UserGroup_tbl->updateUserGroup();
		    } else {
		        ResponseData(['e','Failure_params']);
		    }
	    } else {
	        ResponseData(['e','not_permission_view']);
	    }
	    exit;
	}
	if($http_RQM == 'POST' && $uTAdmin == 1 && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteUserGroup') {
	    if($UserScript['Admin']['Administrative-Structure']['Delete'] == "true"){
		    if(!empty($_POST['data-id'])){
				$UserGroup_tbl = new User_group_tbl($db);
				$UserGroup_tbl->user_group_id = $_POST['data-id'];
		        $UserGroup_tbl->deleteUserGroup();
		    } else {ResponseData(['e','Failure_params']);}
	    } else {ResponseData(['e','not_permission_add_new']);}
	    exit;
	}
/*===================================================
** $Database => Countries table 
**====================================================*/
	if($http_RQM == 'GET' && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getSelectOptionCountries') {	
		$Countries_tbl	= new Countries_tbl($db);
		$Countries_tbl->getSelectOptionCountries(); 									
		exit();																
	}
	if($uTAdmin ==1 && $http_RQM == 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL']  == 'getlistCountries') {
	    if($UserScript['Admin']['Countries']["View"] == "true"){
		    $Countries_tbl = new Countries_tbl($db);
			$Countries_tbl->listCountries();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addCountries') {
	    if($UserScript['Admin']['Countries']["Addnew"] == "true"){
	        if(!empty($_POST["CO_NAME"]) && !empty($_POST["CO_NAME_EN"])){
		        $Countries_tbl = new Countries_tbl($db);
				$Countries_tbl->co_name         = $_POST["CO_NAME"];
			    $Countries_tbl->co_name_en      = $_POST["CO_NAME_EN"];
			    $Countries_tbl->addCountries();
	        } else {
	            ResponseData(array('Failure_params'));
	        }
    	} else {
            ResponseData(array('not_permission_add'));
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateCountries') {
	    if($UserScript['Admin']['Countries']["Update"] == "true"){
			if(!empty($_POST['data_id']) && !empty($_POST['CO_NAME']) && !empty($_POST['CO_NAME_EN'])){
				$Countries_tbl = new Countries_tbl($db);
				$Countries_tbl->co_id         = $_POST['data_id'];
				$Countries_tbl->co_name       = $_POST['CO_NAME'];
				$Countries_tbl->co_name_en    = $_POST['CO_NAME_EN'];
				$Countries_tbl->updateCountries();
			} else {
				ResponseData(array('Failure_params'));
			}
		} else {
		    ResponseData(array('not_permission_updste'));
		}
		exit;
	}
    if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteCountries') {
		if($UserScript['Admin']['Countries']['Delete'] == "true"){
            if(!empty($_POST["Countries_id"])){
                $Countries_tbl = new Countries_tbl($db);
				$Countries_tbl->co_id = $_POST["Countries_id"];
			    $Countries_tbl->deleteCountries();
            } else {
                ResponseData(array('Failure_params',$_POST["Countries_id"]));
            }    
		} else {
		    ResponseData(array('not_permission_delete'));
		}
		exit;
	}	
/*===================================================
** $Database => Gov table 
**====================================================*/
	if($uTAdmin ==1 && $http_RQM == 'GET' &&  !empty($_GET['CONTROL'])  && $_GET['CONTROL']   == 'getlistGov') {
	    if($UserScript['Admin']['Gov']["View"] == "true"){
		    $Gov_tbl = new Gov_tbl($db);
			$Gov_tbl->listGov();
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'updateGov'){
	    if($UserScript['Admin']['Gov']["Update"] == "true"){
			if(!empty($_POST['data_id']) && 
			   !empty($_POST['GOV_NAME']) && 
			   !empty($_POST['GOV_NAME_EN']) && 
			   !empty($_POST['COUNTRY_ID'])){
				$Gov_tbl = new Gov_tbl($db);
				$Gov_tbl->gov_id         = $_POST['data_id'];
				$Gov_tbl->gov_name       = $_POST['GOV_NAME'];
				$Gov_tbl->gov_name_en    = $_POST['GOV_NAME_EN'];
				$Gov_tbl->country_id     = $_POST['COUNTRY_ID'];
				$Gov_tbl->updateGov();
			    
			} else {
				echo 'Failure_params';
			}
		} else {
		    echo 'not_permission_updste';
		}
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL']  == 'addGov') {
	    if($UserScript['Admin']['Gov']["Addnew"] == "true"){
	        if(!empty($_POST["GOV_NAME"]) && 
	           !empty($_POST["GOV_NAME_EN"]) && 
	           !empty($_POST["COUNTRY_ID"])){
	            $Gov_tbl = new Gov_tbl($db);  
			    $Gov_tbl->gov_name        = $_POST["GOV_NAME"];
			    $Gov_tbl->gov_name_en     = $_POST["GOV_NAME_EN"];
                $Gov_tbl->country_id      = $_POST["COUNTRY_ID"];
                
			    $Gov_tbl->addGov();
			    
	        } else {
	            echo 'Failure_params';
	        }
    	} else {
            echo 'not_permission_add';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL']  == 'deleteGov') {
	    if($UserScript['Admin']['Gov']['Delete'] == "true"){
		    if(!empty($_POST["Gov_id"])){     
			    $Gov_tbl = new Gov_tbl($db);
				$Gov_tbl->gov_id     = $_POST["Gov_id"];
			    $Gov_tbl->deleteGov();
			    exit;
		    } else {
	            echo 'Failure_params';
	        }
	    } else {
            echo 'not_permission_add';
	    }
	    exit;
	}
/*===================================================
** $Database => City table 
**====================================================*/
	if( $http_RQM == 'GET' &&  !empty($_GET['CONTROL']) && $_GET['CONTROL']  == 'getAddressDataUse') {
		
		    $City_tbl = new City_tbl($db);
			$City_tbl->getAddressDataUse();
		    exit;
	   
    }	
	if($uTAdmin ==1 && $http_RQM == 'GET' &&  !empty($_GET['CONTROL']) && $_GET['CONTROL']  == 'getlistCity') {
		if($UserScript['Admin']['City']["View"] == "true"){
		    $City_tbl = new City_tbl($db);
			$City_tbl->listCity();
		    exit;
	    }
    }	
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL']  == 'addCity') {
	    if($UserScript['Admin']['City']["Addnew"] == "true"){
	        if(!empty($_POST["CI_NAME"]) && 
	           !empty($_POST["CI_NAME_EN"]) && 
	           !empty($_POST["GOV_ID"])){
			    $City_tbl = new City_tbl($db);
				$City_tbl->ci_name        = $_POST["CI_NAME"];
			    $City_tbl->ci_name_en     = $_POST["CI_NAME_EN"];
                $City_tbl->gov_id         = $_POST["GOV_ID"];
			    $City_tbl->addCity();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'updateCity') {
	    if($UserScript['Admin']['City']["Update"] == "true"){
			if(!empty($_POST['data_id']) && !empty($_POST['CI_NAME']) && !empty($_POST['CI_NAME_EN']) && !empty($_POST['GOV_ID'])){
				$City_tbl = new City_tbl($db);
				$City_tbl->ci_id         = $_POST['data_id'];
				$City_tbl->ci_name       = $_POST['CI_NAME'];
				$City_tbl->ci_name_en    = $_POST['CI_NAME_EN'];
				$City_tbl->gov_id        = $_POST['GOV_ID'];
				$City_tbl->updateCity();
			} else {
			    ResponseData('Failure_params');
			}
		} else {
		    ResponseData('not_permission_updste');
		}
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL']  == 'deleteCity') {
	    if($UserScript['Admin']['City']['Delete'] == "true"){
		    if(!empty($_POST["City_id"])){     
			    $City_tbl = new City_tbl($db);
				$City_tbl->ci_id = $_POST["City_id"];
			    $City_tbl->deleteCity();
			    exit;
		    } else {
	            ResponseData('Failure_params');
	        }
	    } else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
/*===================================================
** $Database Zones (LocalShipping) table 
**====================================================*/
	if($uTAdmin ==1 && $http_RQM == 'GET'  && !empty($_GET['CONTROL']) && $_GET['CONTROL']   == 'getlistLocalShipping') {
	    if($UserScript['Admin']['Local-Shipping']["View"] == "true"){
		    $Zones_tbl = new Zones_tbl($db);
			$Zones_tbl->listLocalShipping();
	    } else { 
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addLocalShipping') {
	    if($UserScript['Admin']['Local-Shipping']["Addnew"] == "true"){
	        if(!empty($_POST['ZONE_NAME']) &&
			!empty($_POST['ZONE_NAME_EN']) &&
	        !empty($_POST["SHIPPING_RATE"]) &&
	        //!empty($_POST["SHIPPING_LOWEST"]) &&
	        !empty($_POST["SHIPPING_TIME_MAX"]) &&
	        !empty($_POST["SHIPPING_T_ID"]) &&
			!empty($_POST["STO_ID"]) &&
	        !empty($_POST["CI_ZONE_ID"])){
				$Zones_tbl = new Zones_tbl($db);
				$Zones_tbl->zone_name              = $_POST['ZONE_NAME'];
				$Zones_tbl->zone_name_en           = $_POST['ZONE_NAME_EN'];
				$Zones_tbl->shipping_rate          = $_POST['SHIPPING_RATE'];
				$Zones_tbl->shipping_lowest        = $_POST['SHIPPING_LOWEST'];
				$Zones_tbl->shipping_time_max      = $_POST['SHIPPING_TIME_MAX'];
                $Zones_tbl->ci_zone_id             = $_POST['CI_ZONE_ID'];
                $Zones_tbl->shipping_type          = $_POST['SHIPPING_T_ID'];
                $Zones_tbl->store_id               = $_POST['STO_ID'];
                $Zones_tbl->addLocalShipping();
	        } else {
	            ResponseData(['e','حدث خطاء عير معروف','Failure_params']);
	        }
    	} else {
            ResponseData(['e','حدث خطاء غير معروف','not_permission_add']);
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'updateLocalShipping') {
	    if($UserScript['Admin']['Local-Shipping']["Update"] == "true"){
			if(!empty($_POST['data_id']) && 
			!empty($_POST['ZONE_NAME']) && 
			!empty($_POST['ZONE_NAME_EN']) && 
	        !empty($_POST["SHIPPING_RATE"]) &&
	        $_POST["SHIPPING_LOWEST"]>=0 &&
	        !empty($_POST["SHIPPING_TIME_MAX"]) &&
	        !empty($_POST["SHIPPING_T_ID"]) &&
			!empty($_POST["STO_ID"]) &&
	        !empty($_POST["CI_ZONE_ID"])){
				$Zones_tbl = new Zones_tbl($db);
				$Zones_tbl->zone_id                = $_POST['data_id'];
				$Zones_tbl->zone_name              = $_POST['ZONE_NAME'];
				$Zones_tbl->zone_name_en           = $_POST['ZONE_NAME_EN'];
				$Zones_tbl->shipping_rate          = $_POST['SHIPPING_RATE'];
				$Zones_tbl->shipping_lowest        = $_POST['SHIPPING_LOWEST'];
				$Zones_tbl->shipping_time_max      = $_POST['SHIPPING_TIME_MAX'];
                $Zones_tbl->ci_zone_id             = $_POST['CI_ZONE_ID'];
                $Zones_tbl->shipping_type          = $_POST['SHIPPING_T_ID'];
                $Zones_tbl->store_id               = $_POST['STO_ID'];
				$Zones_tbl->updateZones();
			} else {
			    ResponseData('Failure_params');
			}
		} else {
		    ResponseData('not_permission_updste');
		}
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteLocalShipping') {
	    if($UserScript['Admin']['Local-Shipping']['Delete'] == "true"){
		    if(!empty($_POST["LocalShipping_id"])){     
			    $Zones_tbl = new Zones_tbl($db);
				$Zones_tbl->zone_id     = $_POST["LocalShipping_id"];
			    $Zones_tbl->deletezones();
			    exit;
		    } else {
	            ResponseData('Failure_params');
	        }
	    } else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
/*===================================================
** $Database Company (Enterprise) table 
**====================================================*/
    if($uTAdmin ==1 && $http_RQM == 'GET'   && !empty($_GET['CONTROL']) && $_GET['CONTROL'] == 'getEnterprise') {
	    if($UserScript['Admin']['Enterprise']["View"] == "true"){
		    $Company_tbl = new Companies_tbl($db);
			$Company_tbl->getCompany();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateEnterprise') {
	    if($UserScript['Admin']['Enterprise']["Update"] == "true"){
		    $Company_tbl = new Companies_tbl($db);
			$Company_tbl->com_name		= $_POST['COM_NAME'];
			$Company_tbl->com_tel		= $_POST['COM_TEL'];
			$Company_tbl->com_fax		= $_POST['COM_FAX'];
			$Company_tbl->com_mobile	= $_POST['COM_MOBILE'];
			$Company_tbl->com_email		= $_POST['COM_EMAIL'];
			$Company_tbl->city_id		= $_POST['CITY_ID'];
			$Company_tbl->com_address	= $_POST['COM_ADDRESS'];
			$Company_tbl->com_logobrand	= '';
			$Company_tbl->updateCompany();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
/*===================================================
** $Database Branches table 
**====================================================*/
	if($uTAdmin ==1 && $http_RQM == 'GET'  && !empty($_GET['CONTROL']) && $_GET['CONTROL'] == 'getlistBranches') {
	    if($UserScript['Admin']['Branches']["View"] == "true"){
		    $Branches_tbl = new Branches_tbl($db);
			$Branches_tbl->listBranches();
	    } else { 
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addBranches') {
	    if($UserScript['Admin']['Branches']["Addnew"] == "true"){
	        if(!empty($_POST["COMPANIE_ID"])     && 
	           !empty($_POST["addressCity"])  && 
	           !empty($_POST["BR_NAME"]) &&
	           !empty($_POST["BR_ADDRESS"])){
			    $Branches_tbl = new Branches_tbl($db);
				$Branches_tbl->companie_id     = $_POST["COMPANIE_ID"];
			    $Branches_tbl->city_id         = $_POST["addressCity"];
                $Branches_tbl->br_name         = $_POST["BR_NAME"];
                $Branches_tbl->br_main         = $_POST["BR_MAIN"];
			    $Branches_tbl->br_address      = $_POST["BR_ADDRESS"];
                $Branches_tbl->addBranches();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}	
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateBranches') {
	   if($UserScript['Admin']['Branches']["Update"] == "true"){
		   if( !empty($_POST["data_id"]) 		&&
			   !empty($_POST["COMPANIE_ID"])   	&& 
	           !empty($_POST["addressCity"])  	&& 
	           !empty($_POST["BR_NAME"]) 	  	&&
	           !empty($_POST["BR_ADDRESS"])){
					$Branches_tbl = new Branches_tbl($db);
					$Branches_tbl->br_id           = $_POST["data_id"];
					$Branches_tbl->companie_id     = $_POST["COMPANIE_ID"];
					$Branches_tbl->city_id         = $_POST["addressCity"];
					$Branches_tbl->br_name         = $_POST["BR_NAME"];
					$Branches_tbl->br_main         = $_POST["BR_MAIN"];
					$Branches_tbl->br_address      = $_POST["BR_ADDRESS"];
					$Branches_tbl->updateBranches();
			} else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteBranches') {
	    if($UserScript['Admin']['Branches']['Delete'] == "true"){
		    if(!empty($_POST["Branches_id"])){     
			    $Branches_tbl = new Branches_tbl($db);
				$Branches_tbl->br_id     = $_POST["Branches_id"];
			    $Branches_tbl->deleteBranches();
			    exit;
		    } else {
	            ResponseData('Failure_params');
	        }
	    } else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
/*===================================================
** $Database Stores (Warehouse) table 
**====================================================*/
    if($uTAdmin ==1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL'] == 'getlistWarehouse') {
	    if($UserScript['Admin']['Warehouse']["View"] == "true"){
		    $Stores_tbl = new Stores_tbl($db);
			$Stores_tbl->listWarehouse();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addWarehouse') {
	    if($UserScript['Admin']['Warehouse']["Addnew"] == "true"){
	        if(!empty($_POST["STO_NAME"])     && 
	           !empty($_POST["BRANCH_ID"])  && 
	           !empty($_POST["STTY_ID"])){
			    $Stores_tbl = new Stores_tbl($db);
				$Stores_tbl->sto_name     = $_POST["STO_NAME"];
			    $Stores_tbl->branch_id    = $_POST["BRANCH_ID"];
                $Stores_tbl->stty_id      = $_POST["STTY_ID"];
                echo $Stores_tbl->addWarehouse();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateWarehouse'){
	    if($UserScript['Admin']['Warehouse']["Update"] == "true"){
	        if( !empty($_POST['data_id'])     &&
		        !empty($_POST['STO_NAME']) 	&& 
		    	!empty($_POST['BRANCH_ID']) 	&& 
		    	!empty($_POST['STTY_ID'])) {
				$Stores_tbl = new Stores_tbl($db);
		    	$Stores_tbl->sto_id     = $_POST['data_id'];
		        $Stores_tbl->sto_name   = $_POST['STO_NAME'];
		    	$Stores_tbl->branch_id  = $_POST['BRANCH_ID'];
		    	$Stores_tbl->stty_id    = $_POST['STTY_ID'];
		    	$Stores_tbl->updateWarehouse();
		    } else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteWarehouse') {
	    if($UserScript['Admin']['Warehouse']['Delete'] == "true"){
		    if(!empty($_POST["Warehouse_id"])){     
				$Stores_tbl	= new Stores_tbl($db);
				$Stores_tbl->sto_id     = $_POST['Warehouse_id'];
				$Stores_tbl->deleteWarehouse();
			} else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
		exit;
	}
/*===================================================
** $Database Product_Categories table 
**====================================================*/
    if($http_RQM == 'GET' && isset($_GET['CONTROL']) && $_GET['CONTROL'] == 'getdropProduct-Categories') {
			$ProcutCAT_tbl = new Product_category_tbl($db);
			if(!isset($_GET['ID'])){
				$_GET['ID']="";
			}
			$ProcutCAT_tbl->cat_id = $_GET['ID'];
			$ProcutCAT_tbl->getdropProductCategories();
			
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getlistProduct-Categories') {
	    if($UserScript['Admin']['Product-Categories']["View"] == "true"){
			$ProcutCAT_tbl = new Product_category_tbl($db);
			if(!isset($_GET['CAT_ID'])){
				$_GET['CAT_ID']="";
			}
			$ProcutCAT_tbl->cat_id = $_GET['CAT_ID'];
		    $ProcutCAT_tbl->listProductCategories();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addProduct-Categories') {
	    if($UserScript['Admin']['Product-Categories']["Addnew"] == "true"){
	        if(!empty($_POST["CAT_NAME"])){
	            $Procut_tbl      	= new Product_category_tbl($db);
	            $Procut_tbl->cat_parent  = $_POST["CAT_PARENT"];
			    $Procut_tbl->cat_name    = $_POST["CAT_NAME"];
			    //$Procut_tbl->cat_id      = $_POST["CAT_ID"];
                $Procut_tbl->addProductCategories();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateProduct-Categories'){
	    if($UserScript['Admin']['Product-Categories']["Update"] == "true"){
		if( !empty($_POST['CAT_ID'])     &&
		    !empty($_POST['CAT_NAME']) 	&& 
		    !empty($_POST['CAT_PARENT'])) {
		    	$Procut_tbl = new Product_category_tbl($db);
				$Procut_tbl->cat_id      = $_POST['CAT_ID'];
		        $Procut_tbl->cat_name    = $_POST['CAT_NAME'];
		    	$Procut_tbl->cat_parent  = $_POST['CAT_PARENT'];
		    	$Procut_tbl->updateProductCategories();
		    } else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteProduct-Categories') {
	    if($UserScript['Admin']['Product-Categories']['Delete'] == "true"){
		    if(!empty($_POST["Product-Categories_id"])){     
				$Procut_tbl = new Product_category_tbl($db);
				$Procut_tbl->cat_id     = $_POST['Product-Categories_id'];
				$Procut_tbl->deleteProductCategories();
			} else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
		exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'getdropProCat') {
		    $Procut_tbl = new Product_category_tbl($db);
			$Procut_tbl->cat_id = $_POST['ID'];
			$Procut_tbl->getdropProCat();
	    exit;
	}
	
/*===================================================
** $Database ProductOption table 
**====================================================*/
    if($uTAdmin ==1 && $http_RQM == 'GET'  && !empty($_GET['CONTROL'])  && $_GET['CONTROL']  == 'getlistProductOption') {
	   
	    if($UserScript['Admin']['ProductOption']["View"] == "true"){
			$ProductOption_tbl	= new ProductOption_tbl($db);
			//$Procut_tbl->cat_id = $_POST['CAT_ID'];
		    $ProductOption_tbl->listProductOption();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addProductOptionGroup') {
	    if($UserScript['Admin']['ProductOption']["Addnew"] == "true"){
	        if(!empty($_POST["OPTION_NAME"])     && 
	           !empty($_POST["OPTION_TYPE"])){
			    $ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_name    = $_POST["OPTION_NAME"];
			    $ProductOption_tbl->option_type    = $_POST["OPTION_TYPE"];
                $ProductOption_tbl->addProductOptionGroup();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addProductOptionValue') {
	    if($UserScript['Admin']['ProductOption']["Addnew"] == "true"){
	        if(!empty($_POST["OPTION_ID"])     && 
	           !empty($_POST["OPTION_VALUE"])){
			    $ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_id    	= $_POST["OPTION_ID"];
			    $ProductOption_tbl->option_value    = $_POST["OPTION_VALUE"];
                $ProductOption_tbl->addProductOptionValue();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateProductOptionGroup') {
	    if($UserScript['Admin']['ProductOption']["Update"] == "true"){
	        if(!empty($_POST["data_id"])     &&
			   !empty($_POST["OPTION_NAME"])     && 
	           !empty($_POST["OPTION_TYPE"])){
				$ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_id      = $_POST["data_id"];
			    $ProductOption_tbl->option_name    = $_POST["OPTION_NAME"];
			    $ProductOption_tbl->option_type    = $_POST["OPTION_TYPE"];
                $ProductOption_tbl->updateProductOptionGroup();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateProductOptionValue') {
	    if($UserScript['Admin']['ProductOption']["Update"] == "true"){
	        if(!empty($_POST["OPTION_VALUE_ID"])     && 
	           !empty($_POST["OPTION_VALUE"])){
			    $ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_value_id    	= $_POST["OPTION_VALUE_ID"];
			    $ProductOption_tbl->option_value    = $_POST["OPTION_VALUE"];
                $ProductOption_tbl->updateProductOptionValue();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteProductOptionGroup') {
	    if($UserScript['Admin']['ProductOption']["Delete"] == "true"){
	        if(!empty($_POST["ProductOption_id"])){
			    $ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_id    	= $_POST["ProductOption_id"];
                $ProductOption_tbl->deleteProductOptionGroup();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteProductOptionValue') {
	    if($UserScript['Admin']['ProductOption']["Delete"] == "true"){
	        if(!empty($_POST["ProductOption_id"])){
			    $ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_value_id    	= $_POST["ProductOption_id"];
                $ProductOption_tbl->deleteProductOptionValue();
	        } else {
	            ResponseData('Failure_params');
	        }
    	} else {
            ResponseData('not_permission_add');
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteProductOption') {
	    if($UserScript['Admin']['ProductOption']['Delete'] == "true"){
		    if(!empty($_POST["ProductOption_id"])){     
				$ProductOption_tbl	= new ProductOption_tbl($db);
				$ProductOption_tbl->option_id     = $_POST['ProductOption_id'];
				$ProductOption_tbl->deleteProductOption();
			} else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
		exit;
	}
/*===================================================
** $Database Products table 
**====================================================*/
	if($uTAdmin ==1 && $http_RQM == 'POST'  && isset($_GET['CONTROL'])  && $_GET['CONTROL'] == 'getlistProducts') {
	    if($UserScript['Admin']['Products']["View"] == "true"){
			$Products_tbl		= new Products_tbl($db);
			//$Procut_tbl->cat_id = $_GET['CAT_ID'];
		    $Products_tbl->listProducts();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	
	if($uTAdmin ==1 && $http_RQM == 'POST'  && isset($_POST['CONTROL'])  && $_POST['CONTROL'] == 'getNewProCodes') {
	    
		$Products_tbl		= new Products_tbl($db);
		$count = isset($_POST['count']) ? $_POST['count']:1;
		ResponseData($Products_tbl->generateMultipleCodes($count));
	    exit;
	}
	
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'addProduct') {
	    if($UserScript['Admin']['Products']["Addnew"] == "true"){
	        if(!empty($_POST['ProductName']) &&
	           !empty($_POST['ProductCat']) &&
	           !empty($_POST['ProductItems'])){
				if(!isset($_POST['ProductImg'])){
					$_POST['ProductImg']="";
				}
			   $Products_tbl		= new Products_tbl($db);
			   $Products_tbl->pro_name          = $_POST['ProductName'];
	           $Products_tbl->cat_id            = $_POST['ProductCat'];
			   $Products_tbl->pro_visble        = $_POST['PRO_VISBLE'];
	           $Products_tbl->per_booking       = $_POST['PER_BOOKING'];
			   $Products_tbl->pro_description   = $_POST['ProductDescription'];
	           $Products_tbl->length            = $_POST['ProductLength'];
	           $Products_tbl->width             = $_POST['ProductWidth'];
	           $Products_tbl->height            = $_POST['ProductHeight'];
	           $Products_tbl->weight            = $_POST['ProductWeight'];
	           $Products_tbl->pro_item          = $_POST['ProNewItems'];
			   $Products_tbl->pro_option        = $_POST['ProductOption'];
	           $Products_tbl->pro_img_link      = $_POST['ProductImg'];
			   $Products_tbl->addProduct();
	        } else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateProduct') {
	    if($UserScript['Admin']['Products']["Update"] == "true"){
	        if(!empty($_POST['ProductName']) &&
	           !empty($_POST['ProductCat']) &&
	           !empty($_POST['ProductItems'])){
				if(!isset($_POST['ProductImg'])){
					$_POST['ProductImg']="";
				}
	           $Products_tbl		= new Products_tbl($db);
			   $Products_tbl->pro_id            = $_POST['Pro_id'];
	           $Products_tbl->pro_name          = $_POST['ProductName'];
	           $Products_tbl->cat_id            = $_POST['ProductCat'];
			   $Products_tbl->pro_visble        = $_POST['PRO_VISBLE'];
	           $Products_tbl->per_booking       = $_POST['PER_BOOKING'];
			   $Products_tbl->pro_description   = $_POST['ProductDescription'];
	           $Products_tbl->length            = $_POST['ProductLength'];
	           $Products_tbl->width             = $_POST['ProductWidth'];
	           $Products_tbl->height            = $_POST['ProductHeight'];
	           $Products_tbl->weight            = $_POST['ProductWeight'];
	           $Products_tbl->pro_item          = $_POST['ProductItems'];
			   $Products_tbl->pro_new_item      = $_POST['ProNewItems'];
			   $Products_tbl->pro_option        = $_POST['ProductOption'];
	           $Products_tbl->pro_img_link      = $_POST['ProductImg'];
			   $Products_tbl->updateProduct();

	               
	           } else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteProducts') {
	    if($UserScript['Admin']['Products']["Delete"] == "true"){
	        if(isset($_POST['Products_id'])){
	            $Products_tbl		= new Products_tbl($db);
				$Products_tbl->pro_id = $_POST['Products_id'];
	            $Products_tbl->deleteProducts();
	        } else {
	            ResponseData('Failure_params');
	        }
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL'],$_POST['Pro_code']) && $_POST['CONTROL'] == 'chDelOptionRow') {
	    if($UserScript['Admin']['Products']["Delete"] == "true"){
	        if(!empty($_POST['Pro_code'])){
	            $Products_tbl		= new Products_tbl($db);
				$Products_tbl->pro_code = $_POST['Pro_code'];
	            ResponseData($Products_tbl->chDelOptionRow());
	        } else {
	            ResponseData(array('e','Error','Failure_params'));
	        }
	    } else {
	        ResponseData(array('e','نأسف','انت لاتملك صلاحية الحذف'));
	    }
	    exit;
	}
	if($uTAdmin ==1 && $http_RQM == 'POST' && isset($_POST['CONTROL'],$_POST['Pro_code']) && $_POST['CONTROL'] == 'DelOptionRow') {
	    if($UserScript['Admin']['Products']["Delete"] == "true"){
	        if(!empty($_POST['Pro_cid']) && !empty($_POST['Pro_code'])){
	            $Products_tbl		= new Products_tbl($db);
				$Products_tbl->pro_code = $_POST['Pro_code'];
				$Products_tbl->product_code_id = $_POST['Pro_cid'];
	            ResponseData($Products_tbl->DelOptionRow());
	        } else {
	            ResponseData(array('e','Error','Failure_params'));
	        }
	    } else {
	        ResponseData(array('e','نأسف','انت لاتملك صلاحية الحذف'));
	    }
	    exit;
	}
	if($http_RQM == 'GET'  && !empty($_GET['CONTROL'])  && $_GET['CONTROL']  === 'getProductCodes') {
	    if($UserScript['Admin']['Products']["View"] == "true"){
		    $Products_tbl		= new Products_tbl($db);
			if(!isset($_GET['CODE'])){
				$_GET['CODE']="";
			}
			if(!isset($_GET['CODE_ID'])){
				$_GET['CODE_ID']="";
			}
			$Products_tbl->pro_code =$_GET['CODE'];
			$Products_tbl->product_code_id =$_GET['CODE_ID'];
		    $Products_tbl->getProductCodes();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($http_RQM == 'POST' && !empty($_POST['CONTROL']) && $_POST['CONTROL'] === 'viewProduct') {
	    if($UserScript['Admin']['Products']["View"] == "true"){
				$Products_tbl		= new Products_tbl($db);
				if(!isset($_POST['Pro_id'])){
					$_POST['Pro_id']="";
				}
				if(!isset($_POST['Sto_id'])){
					$_POST['Sto_id']="";
				}
	            $Products_tbl->pro_id = $_POST['Pro_id'];
	            $Products_tbl->sto_id = $_POST['Sto_id'];
	            $Products_tbl->viewProduct();
	    } else {
	        echo 'not_permission_view';
	    }
	    exit;
	}
	if($http_RQM == 'GET'  && !empty($_GET['CONTROL'])  && $_GET['CONTROL']  === 'viewProductName') {
	    if($UserScript['Admin']['Products']["View"] == "true"){
	        if(!empty($_POST['ProductName'])){
				$Products_tbl		= new Products_tbl($db);
				$Products_tbl->pro_id = $_POST['Pro_id'];
	            $Products_tbl->pro_name = $_POST['ProductName'];
	            $Products_tbl->viewProductName();
	        } else {
	            ResponseData('Failure_params');
	            exit;
	        }
	    } else {
	        echo 'not_permission_view';
	        exit;
	    }
	    exit;
	}
	if($http_RQM == 'GET'  && !empty($_GET['CONTROL'])  && $_GET['CONTROL']  === 'selectProduct') {
		$Products_tbl		= new Products_tbl($db);
		$Products_tbl->selectProduct();
	    exit;
	}
	if($http_RQM == 'GET'  && !empty($_GET['CONTROL'])  && $_GET['CONTROL']  === 'ProductsData' ) {	
		$Products_tbl		= new Products_tbl($db);
		$Products_tbl->ProductsData();
		
		exit();
	}
	if($http_RQM == "GET"  && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'ProAttributes'){
		$Products_tbl		= new Products_tbl($db);
		http_response_code(200);
		ResponseData($Products_tbl->ProAttributes($_GET['pid']));
		$db = null;
		exit();
	}
	if($http_RQM == "GET" && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'Productlist'){
		$Products_tbl		= new Products_tbl($db);
		http_response_code(200);
		ResponseData($Products_tbl->Productlist());
		$db = null;
		exit();
	}
	//===============file link=================
    if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'flink'){
		$link = get_dir_init($_GET['fn']);						
		http_response_code(200);
		ResponseData($link);
		exit;																
	}
	//==============Products Data==============

/*===================================================
** $Database ORDES table 
**====================================================*/
    if($http_RQM == 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'filterOrder') {
	    if(isset($_SESSION["USER_ID"])){
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
			ResponseData($data);
		    exit();
	    }else{
	    	ResponseData(array('','',''));
	    }
    }
    if(($uTAdmin || $uTAffil) && $http_RQM == 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'listOrders') {//11111
		$order_tbl          = new Order_tbl($db);
		$order_tbl->rq_parm = $_GET;
		http_response_code(200);
		ResponseData($order_tbl->listOrders());
		
        exit;
    }
   	if( $http_RQM == 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'orderView') {//111111
		if(!empty($_GET["order_id"])){
			$order_tbl          = new Order_tbl($db);
			$order_tbl->order_id = $_GET['order_id'];
			ResponseData($order_tbl->orderView());
		}else{
			ResponseData('Failure_params');
		}
        exit;
    }
    if(($uTAdmin || $uTAffil) && $http_RQM == 'GET'  && isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'radioOrderExists') {//1111
		$order_tbl          = new Order_tbl($db);
		ResponseData($order_tbl->radioOrderExists());
        exit;
    }
    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'resetOrder') {//1111
		$order_tbl          = new Order_tbl($db);
		$params  = $_POST['orderData'];
		$orderid = array_key_first($params);
		$lastUp  = $params[$orderid]['olup'];
		ResponseData($order_tbl->resetOrder($orderid, $lastUp));
        exit;
    }
    if(($uTAdmin || $uTAffil) && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'getInventory') {
		$order_tbl          = new Order_tbl($db);
		$order_tbl->rq_parm = $_POST;
	
		ResponseData($order_tbl->getInventory(array() ,$_POST['stoid']));
        exit;
    }
/*===================================================
**   login user Aria 
**====================================================*/
	if(isset($_SESSION) && count($_SESSION)>0){
	/*===================================================
	** $Database ORDER_SERVEICE table 
	**====================================================*/
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addOrderServeiceOS') {
			$OS_tbl = new Order_Service_tbl($db);
			$OS_tbl->ORDER_ID 	= $_POST['orderid'];
			$OS_tbl->USER_ID	= $_SESSION["USER_ID"];
			$OS_tbl->USER_TYPE  = $_POST['ptype'];
			$OS_tbl->last_update= $_POST['lu'];
			$OS_tbl->OST_ID		= $_POST['ostid'];
			$OS_tbl->OSTX_MSG	= $_POST['ostxmsg'];
			ResponseData($OS_tbl->OSinsert('os'));
			exit;
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'OrderServeiceSENDMSG') {
			if($_POST['ptype'] == 0){
				ResponseData(['e','غير قادر على تحديث حالة الرسائل']);
			} 
			$OS_tbl = new Order_Service_tbl($db);
			$OS_tbl->OS_ID	    	= $_POST['osid'];
			$OS_tbl->OSTX_MSG		= $_POST['ostxmsg'];
			$OS_tbl->USER_ID		= $_SESSION['USER_ID'];
			$OS_tbl->USER_TYPE      = $_POST['ptype'];
			ResponseData($OS_tbl->OSinsert('msg'));
			exit;
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addOrderServeiceTYPE') {
			$OS_tbl = new Order_Service_tbl($db);
			ResponseData($OS_tbl->OSinsert('type'));
			exit;
		}
		if(($uTAdmin || $uTAffil) && !empty($_GET['CONTROL'])  &&  $_GET['CONTROL'] == 'OrderServeiceCHAT') {
			$OS_tbl = new Order_Service_tbl($db);
			$OS_tbl->OS_ID		= $_GET['osid'];
			$OS_tbl->USER_TYPE  = $_GET['ptype'];
			ResponseData($OS_tbl->OSview('osch'));
			exit;
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'OrderServeiceREADMSG') {
			$OS_tbl = new Order_Service_tbl($db);
			$OS_tbl->OSTX_ID		= $_POST['ostxid'];
			$OS_tbl->USER_TYPE      = $_POST['ptype'];
			ResponseData($OS_tbl->OSupdate('readmsg'));
			exit;
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'OrderServeiceClose') {
			$OS_tbl = new Order_Service_tbl($db);
			$OS_tbl->OS_ID		= $_POST['osid'];
			$OS_tbl->USER_ID	= $_SESSION["USER_ID"];
			$OS_tbl->USER_TYPE  = $_POST['ptype'];
			ResponseData($OS_tbl->OSupdate('osClose'));
			exit;
		}
	/*===================================================
	** $Database Shipment_tbl table 
	**====================================================*/
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'shipmentOut') {
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['order_id'];
			$shipment_tbl->shiping_date= $_POST['shipDate']; 
	        $shipment_tbl->delivery_id = $_POST['shipUser'];
	        $shipment_tbl->delivery_name=$_POST['shipUName'];
	        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
	        $shipment_tbl->user_action = $_SESSION["USER_ID"];
	        $shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
			ResponseData($shipment_tbl->regEmployeeCustody());
	        exit;
	    }
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'shipmentRef') {
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['order_id'];
	        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
	        $shipment_tbl->user_action = $_SESSION["USER_ID"];
	        $shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
			ResponseData($shipment_tbl->refEmployeeCustody($_POST['type']));
	        exit;
	    }
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'shipDelivery') {
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['order_id'];
	        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
	        $shipment_tbl->user_action = $_SESSION["USER_ID"];
	        $shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
			ResponseData($shipment_tbl->ShipmentDelivery());
	        exit;
	    }
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'approvedShip') {
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['order_id'];
	        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
	        $shipment_tbl->user_action = $_SESSION["USER_ID"];
	        $shipment_tbl->user_group  = $_SESSION["USER_GRUOP_NAME"];
			ResponseData($shipment_tbl->ShipmentDelivery());
	        exit;
	    }
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'shipDelivered') {
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['odata'];
			$shipment_tbl->shipmentobj = $_POST['oshipp'];
	        $shipment_tbl->branch_id   = $_SESSION["BRANCH_ID"];
	        $shipment_tbl->delivery_id = $_SESSION["USER_ID"];
	        if($_POST['oshipp']['ptype'] == 3){
	        	$shipment_tbl->user_group = 'موظف الشحن';
	        }else if($_POST['oshipp']['ptype'] == 2){
	        	$shipment_tbl->user_group = 'المسوق';
	        }else if($_POST['oshipp']['ptype'] == 1){
	        	$shipment_tbl->user_group = $_SESSION["USER_GRUOP_NAME"];
	        }
			ResponseData($shipment_tbl->ShipmentDelivery('confirm'));
	        exit;
	    }
	    if($uTAdmin && $http_RQM == 'POST'  && isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'countProducts') {
			 
			$shipment_tbl              = new Shipment_tbl($db);
			$shipment_tbl->orderobj    = $_POST['order_id'];
	   		ResponseData($shipment_tbl->countProducts());
	        exit;
	    }
	/*===================================================
	** $Database client table 
	**====================================================*/
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateClientName') {
			if(!empty($_POST['cli_name']) && !empty($_POST['cli_id'])){
				$Client_tbl = new Client_tbl($db);
				$Client_tbl->client_name     = $_POST['cli_name'];
				$Client_tbl->client_id       = $_POST['cli_id'];
				
				ResponseData($Client_tbl->updateClientName());
			}else{
				ResponseData(["e","حدث خطاء - لم يتم الحفظ هناك بيانات مفقوده"]);
			}
		}
		if(($uTAdmin || $uTAffil) && !empty($_GET['CONTROL']) && $_GET['CONTROL'] == 'getClientList') {
				$Client_tbl = new Client_tbl($db);
				ResponseData($Client_tbl->getClientList());
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'listClient') {
				$Client_tbl = new Client_tbl($db);
				ResponseData($Client_tbl->listClient());
		}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addClient') {
				if(!empty($_POST['cli_name']) && 
				   !empty($_POST['addressCity']) &&
				   !empty($_POST['address_type']) &&
				   !empty($_POST['cli_address']) &&
				   !empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel'])){
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->client_name     = $_POST['cli_name'];
					$Client_tbl->client_email    = $_POST['cli_email'];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->address_title   = $_POST['address_type'];
					$Client_tbl->defaultadd      = $_POST['defultAdd'];
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->addClient();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteClients') {
				$Client_tbl = new Client_tbl($db);
				$Client_tbl->client_id       = $_POST["Clients_id"];
				ResponseData($Client_tbl->deleteClients());
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addClientAdd') {
				if(!empty($_POST['cli_id']) &&
				   !empty($_POST['addressCity']) &&
				   !empty($_POST['address_type']) &&
				   !empty($_POST['cli_address'])){
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->client_id       = $_POST["cli_id"];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->address_title   = $_POST['address_type'];
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->addClientAdd();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addClientTel') {
				if(!empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel']) &&
				   !empty($_POST['cli_id'])){
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->client_id       = $_POST["cli_id"];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->addClientTel();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteClientTel') {
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->tel_id    = $_POST['tel_id'];
					$Client_tbl->client_id = $_POST["cli_id"];
					$Client_tbl->deleteClientTel();
					exit;
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteClientAdd') {
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->address_id    = $_POST['add_id'];
					$Client_tbl->client_id = $_POST["cli_id"];
					$Client_tbl->deleteClientAdd();
					exit;
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateClientTel') {
				if(!empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel']) &&
				   !empty($_POST['tel_id'])){
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->tel_id          = $_POST["tel_id"];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->updateClientTel();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateClientAdd') {
				if(!empty($_POST['add_id']) &&
				   !empty($_POST['addressCity']) &&
				   !empty($_POST['cli_address'])){
				   	
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->address_id      = $_POST["add_id"];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->defaultadd      = $_POST['defultAdd'];
					$Client_tbl->address_title   = isset($_POST['address_type']) ? $_POST['address_type'] : '' ;
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->updateClientAdd();
					exit;
				} else {
					print_r($_POST);
					ResponseData('Failure_params');
					exit;
				}
			}
				if(($uTAdmin || $uTAffil) && !empty($_GET['CONTROL'])  &&  $_GET['CONTROL'] == 'searchClient') {
					$Client_tbl		    = new Client_tbl($db);
					$Client_tbl->tel_namber  = $_GET['cli_search'];
					$Client_tbl->client_id   = $_GET['cli_id'];
					$Client_tbl->tel_id      = $_GET['tel_id'];
					$Client_tbl->address_id  = $_GET['add_id'];
					$Client_tbl->searchClient();
					exit;
		}
	/*===================================================
	** $Database client table 
	**====================================================*/
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'listSuppliers') {
				$Client_tbl = new Suppliers_tbl($db);
				ResponseData($Client_tbl->listSuppliers());
		}
		if($uTAdmin && $http_RQM == 'GET' && isset($_GET['CONTROL']) && $_GET['CONTROL'] == 'supplierslist') {
				$Client_tbl = new Suppliers_tbl($db);
				ResponseData($Client_tbl->supplierslist());
		}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addSuppliers') {
				if(!empty($_POST['cli_name']) && 
				   !empty($_POST['addressCity']) &&
				   
				   !empty($_POST['cli_address']) &&
				   !empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel'])){
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->client_name     = $_POST['cli_name'];
					$Client_tbl->client_email    = $_POST['cli_email'];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->address_title   = $_POST['address_type'] ?? 'المنزل';
					$Client_tbl->defaultadd      = $_POST['defultAdd'];
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->addClient();
					exit;
				} else {
					ResponseData(['e','Failure_params']);
					exit;
				}
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteSuppliers') {
				$Client_tbl = new Suppliers_tbl($db);
				$Client_tbl->client_id       = $_POST["Suppliers_id"];
				ResponseData($Client_tbl->deleteClients());
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addSupplierAdd') {
				if(!empty($_POST['cli_id']) &&
				   !empty($_POST['addressCity']) &&
				   !empty($_POST['address_type']) &&
				   !empty($_POST['cli_address'])){
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->client_id       = $_POST["cli_id"];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->address_title   = $_POST['address_type'];
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->addClientAdd();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addSupplierTel') {
				if(!empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel']) &&
				   !empty($_POST['cli_id'])){
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->client_id       = $_POST["cli_id"];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->addSupplierTel();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteSupplierTel') {
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->tel_id    = $_POST['tel_id'];
					$Client_tbl->client_id = $_POST["cli_id"];
					$Client_tbl->deleteSupplierTel();
					exit;
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'deleteSupplierAdd') {
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->address_id    = $_POST['add_id'];
					$Client_tbl->client_id = $_POST["cli_id"];
					$Client_tbl->deleteClientAdd();
					exit;
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateSupplierTel') {
				if(!empty($_POST['tel_type']) &&
				   !empty($_POST['cli_tel']) &&
				   !empty($_POST['tel_id'])){
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->tel_id          = $_POST["tel_id"];
					$Client_tbl->defaulttel      = $_POST['defultTel'];
					$Client_tbl->whatsapp        = $_POST['whatsapp'];
					$Client_tbl->telegeam        = $_POST['telegram'];
					$Client_tbl->tel_type        = $_POST['tel_type'];
					$Client_tbl->tel_namber      = $_POST['cli_tel'];
					$Client_tbl->updateSupplierTel();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if($uTAdmin && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateSupplierAdd') {
				if(!empty($_POST['add_id']) &&
				   !empty($_POST['addressCity']) &&
				   !empty($_POST['address_type']) &&
				   !empty($_POST['cli_address'])){
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->address_id      = $_POST["add_id"];
					$Client_tbl->city_id         = $_POST['addressCity'];
					$Client_tbl->defaultadd      = $_POST['defultAdd'];
					$Client_tbl->address_title   = $_POST['address_type'];
					$Client_tbl->address         = $_POST['cli_address'];
					$Client_tbl->updateClientAdd();
					exit;
				} else {
					ResponseData('Failure_params');
					exit;
				}
			}
		if($uTAdmin && !empty($_GET['CONTROL'])  &&  $_GET['CONTROL'] == 'searchSuppliers') {
					$Client_tbl		    = new Suppliers_tbl($db);
					$Client_tbl->tel_id      = $_GET['tel_id'];
					$Client_tbl->searchSuppliers($_GET['cli_id'] ,$_GET['cli_search']);
					exit;
		}
	/*===================================================
	**  AFFILIATE_product  
	**====================================================*/
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'selectProductsOrder') {
				$Products_tbl		= new Products_tbl($db);
				$Products_tbl->selectProductsOrder();
				exit;
			}
	/*===================================================
	** $Database orders table 
	**====================================================*/
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'addOrder') {
				$order_tbl          = new Order_tbl($db);
				$order_tbl->orderObj				= $_POST['order'];
				$order_tbl->orderObj['orderby']		= $_SESSION['USER_ID'];
				$order_tbl->orderObj['coupon_code'] = null ;
				if(isset($_POST['item'])){
					$order_tbl->itemObj	    			= $_POST['item'];
				}else{
					$order_tbl->itemObj	    			= '';
				}
				$order_tbl->logObj	    		    = $_POST['olog'];
				$order_tbl->addOrders();
				exit;
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'updateOrder') {
				$order_tbl          = new Order_tbl($db);
				$order_tbl->orderObj				= $_POST['order'];
				$order_tbl->orderObj['orderby']		= $_SESSION['USER_ID'];
				$order_tbl->orderObj['coupon_code'] = null ;
				$order_tbl->itemObj	    			= $_POST['item'];
				$order_tbl->logObj	    		    = $_POST['olog'];
				$order_tbl->updateOrder();
				exit;
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'Redirect_Orders') {
        		$order_tbl              = new Order_tbl($db);
        		$order_tbl->orderObj	= $_POST['ordersData'];
        		$order_tbl->exists_id   = $_POST['ex_int'];
        		$order_tbl->logStr      = $_POST['ex_str'];
        		ResponseData($order_tbl->RedirectToEX());
                exit;
            }
		if(($uTAdmin || $uTAffil) && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'delOrder'){
			    if(isset($_POST['oid'],$_POST['lup'])) {
    				$order_tbl          = new Order_tbl($db);
    				$order_tbl->order_id    = htmlspecialchars(strip_tags($_POST['oid']));
                    $order_tbl->last_update = htmlspecialchars(strip_tags($_POST['lup']));
    				$order_tbl->deleteOrder();
    				exit;
			    } else {
			        ResponseData(['error','حدث خطاء','نعتذر حدث خطاء تقنى من فضلك قم بإبلاغ المطور بتفاصيل الخطاء (oid or lup is not set)']);
			    }
			}
		if(($uTAdmin || $uTAffil) && isset($_POST['CONTROL']) && $_POST['CONTROL'] == 'unDelOrder'){
			    if(isset($_POST['oid'],$_POST['lup'])) {
    				$order_tbl          = new Order_tbl($db);
    				$order_tbl->order_id    = htmlspecialchars(strip_tags($_POST['oid']));
                    $order_tbl->last_update = htmlspecialchars(strip_tags($_POST['lup']));
    				$order_tbl->unDeleteOrder();
    				exit;
			    } else {
			        ResponseData(['error','حدث خطاء','نعتذر حدث خطاء تقنى من فضلك قم بإبلاغ المطور بتفاصيل الخطاء (oid or lup is not set)']);
			    }
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'listOrders') {
				$order_tbl          = new Order_tbl($db);
				$order_tbl->listOrders();
				exit;
			}
		if( !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'orderView') {
				if(!empty($_POST["order_id"])){
					$order_tbl          = new Order_tbl($db);
					$order_tbl->order_id = $_POST['order_id'];
					$order_tbl->orderView();
				}else{
					ResponseData('Failure_params');
				}
				exit;
			}
		if(($uTAdmin || $uTAffil) && !empty($_POST['CONTROL']) && $_POST['CONTROL'] == 'radioOrderExists') {
				$order_tbl          = new Order_tbl($db);
				$order_tbl->radioOrderExists();
				exit;
			}
		
	}
	/*===========================Country page dropdown==============================**/
 	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropGov'){	
	   	$Countries_tbl = new Countries_tbl($db);
        $Countries_tbl->getdropCountries();									
    	exit;																
	}																		
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropCity'){	
		$Gov_tbl = new Gov_tbl($db);
        $Gov_tbl->getdropGov();												
    	exit;																
	}																		
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropCity'){	
		$Countries_tbl = new Countries_tbl($db);
        $Countries_tbl->getdropGov();										
		exit;																
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropEnterpris'){
		$Company_tbl     	 = new Companies_tbl($db);
		if(isset($_GET['ID']) && $_GET['ID'] != ''){
		    $fg=$_GET['ID'];
		}else{
		    $fg="";
		}
		$Company_tbl->com_id = $fg;
		$Company_tbl->getdropCompany();
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropLocalShipping'){
		    $Zones_tbl = new Zones_tbl($db);
			$Zones_tbl->getdropShipping($_GET['ID']);
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropLocalShipping2'){
		    $Zones_tbl = new Zones_tbl($db);
			$Zones_tbl->getdropLocalShipping();
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropBranches'){
		    $Branches_tbl = new Branches_tbl($db);
			if(isset($_GET['ID']) && $_GET['ID'] != ''){
    		    $fg=$_GET['ID'];
    		}else{
    		    $fg="";
    		}
			$Branches_tbl->br_id = $fg;
			$Branches_tbl->getdropBranches();
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropStty'){
			$Stores_tbl = new Stores_tbl($db);
			$Stores_tbl->stty_id = $_GET['ID'];
			$Stores_tbl->getdropStoresType();
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropWarehouse'){
			$Stores_tbl = new Stores_tbl($db); 
			$Stores_tbl->stty_id = isset($_GET['ID']) ? $_GET['ID'] : null ;
			$Stores_tbl->filtertype = isset($_GET['type']) ? $_GET['type'] : null;
			$asscitiv = isset($_GET['ass']) ? $_GET['ass']:false;
			$Stores_tbl->getdropWarehouse($asscitiv); 
	    exit;
	}
	if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getdropShippingType'){
			$Shipping_type_tbl  = new Shipping_type_tbl($db);
			$Shipping_type_tbl->getdropShippingType();
	    exit;
	}
    if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getCountryList'){
			$Countries_tbl = new Countries_tbl($db);
			$Countries_tbl->getdropCountries();
		exit;
    }
    if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getGovList'){
			$Gov_tbl = new Gov_tbl($db);
			$Gov_tbl->country_id     = $_GET['ID'];
			$Gov_tbl->getdropGov();
		exit;
    }
    if(isset($_GET['CONTROL']) && $_GET['CONTROL'] === 'getCityList'){
        $City_tbl        	= new City_tbl($db);
		$City_tbl->gov_id     = $_GET['ID'];
        $City_tbl->getdropCity();
		exit;
    }
	
	if(isset($_GET['CONTROL'])  && $_GET['CONTROL']  === 'getdropProductOptionGroup') {
		    $ProductOption_tbl	= new ProductOption_tbl($db);
			if(!isset($_GET['ID'])){
				$_GET['ID']="";
			}
			$ProductOption_tbl->option_id = $_GET['ID'];
			$ProductOption_tbl->getdropProductOptionGroup();
	    exit;
	}
	if(isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'getdropProductOptionValue') {
		    $ProductOption_tbl	= new ProductOption_tbl($db);
			if(!isset($_POST['ID'])){
				$_POST['ID']="";
			}
			if(!isset($_POST['PRO_ID'])){
				$_POST['PRO_ID']="";
			}
			$ProductOption_tbl->option_id = $_POST['ID'];
		    $ProductOption_tbl->product_id= $_POST['PRO_ID'];
			$ProductOption_tbl->getdropProductOptionValue();
	    exit;
	}

	if(isset($_GET['CONTROL'])  && $_GET['CONTROL']  === 'getCAPTCHA'){
        // PHP CAPTCHA image
        // توليد صورة CAPTCHA بلغة PHP
    
        // تحديد الأبعاد وحجم الخط ومتغيرات الأحرف وغيرها من القيم المستخدمة في إنشاء الصورة
        $width = 130;
        $height = 30;
        $font_size = 20;
        $font = "../dist/css/font/verdana.ttf";
        $font = realpath($font);
        $chars_length = 4;
    
        // مجموعة الأحرف المتاحة للاختيار منها عشوائياً لإنشاء الرمز
        $captcha_characters = '0123456789';
    
        // إنشاء صورة فارغة بالأبعاد المحددة وتعيين لون الخلفية الأحمر لها
        $image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($image, 255, 0, 0);
        $font_color = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);
    
        // رسم مستطيلات بشكل عشوائي باللون البنفسجي على الصورة الحمراء
        $d = $width/10;
        $color = imagecolorallocate($image, 188, 165, 201);
        for ($i = 0; $i < $d; $i++) { 
            imagerectangle($image, rand(0,$width), rand(0,$height), rand(0,$width), rand(0,$height), $color); 
        }
    
        // اختيار حرف عشوائي من قائمة الأحرف المحددة وإضافته إلى الصورة مع تحديد موقعه وزاويته بشكل عشوائي
        $xw = ($width/$chars_length);
        $x = 0;
        $font_gap = $xw/2-$font_size/2;
        $token = '';
        for($i = 0; $i < $chars_length; $i++) {
            $letter = $captcha_characters[rand(0, strlen($captcha_characters)-1)];
            $token .= $letter;
            $x =($i == 0 ? 0 : $xw * $i);
            imagettftext($image, $font_size, rand(-20,20), $x+$font_gap, rand(20, $height-5), $font_color, $font, $letter);
        }
    
        // حفظ الحرف العشوائي الذي تم اختياره في متغير الرمز الذي يتم تخزينه في متغير الجلسة
        $_SESSION['captcha_token'] = strtolower($token);
    
        // عرض الصورة الناتجة على شكل صورة PNG
        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
        exit;
	}
	if(isset($_POST['CONTROL']) && $_POST['CONTROL']  == 'checkCAPTCHA'){
	    if(isset($_POST['inputCaptcha']) && $_POST['inputCaptcha'] === $_SESSION['captcha_token'] ){
	       // header('Content-Type: text/plain');
	        ResponseData('ok');
	        
	    }else{
	       // header('Content-Type: text/plain');
	        ResponseData('no');
	    }
	    
	}
	if(isset($_POST['CONTROL']) && $_POST['CONTROL'] === 'tcpdf'){
	    
	    // إنشاء كائن TCPDF
        $pdf = new TCPDF();
        
        // إضافة صفحة جديدة
        $pdf->AddPage();
        
        // إضافة باركود
        //$barcodeText = '1234567890'; // النص الذي سيتم ترميزه في الباركود
        //$barcode = new TCPDFBarcode($barcodeText, 'C128');
        //$pdf->write1DBarcode($barcodeText, 'C128', '', '', '', 18, 0.4, $barcode, 'N');
        
        // إضافة رمز QR
        //$qrText = 'https://www.example.com'; // النص الذي سيتم ترميزه في رمز QR
        //$pdf->write2DBarcode($qrText, 'QRCODE,H', '', '', 30, 30);
        
        // حفظ الملف PDF
        $pdf->Output('file.pdf', 'D');
	    
	    
	    if(isset($_POST['inputCaptcha']) && $_POST['inputCaptcha'] === $_SESSION['captcha_token'] ){
	        //header('Content-Type: text/plain');
	        echo( json_encode('ok'));
	        
	    }else{
	        //header('Content-Type: text/plain');
	        echo( json_encode('no'));
	    }

	}


	if($uTAdmin == 1 && $http_RQM == 'GET'  && isset($_GET['CONTROL'])  && $_GET['CONTROL']  == 'ResonseData') {
		if($UserScript['Admin']['SupportAfterSales']['View_order_list'] == "true"){
			$Order_tbl = new Order_tbl($db);
	    	ResponseData($Order_tbl->getProductRejectionReason((int)($_GET['ORDER_ID'] ?? 0), (string)($_GET['PRO_CODE'] ?? '')));
			exit;
		}
	}
	if($uTAdmin == 1 && $http_RQM == 'POST'  && isset($_POST['CONTROL'])  && $_POST['CONTROL']  == 'saveResonseProData') {
		if($UserScript['Admin']['SupportAfterSales']['View_order_list'] == "true"){
			$Order_tbl = new Order_tbl($db);
	    	ResponseData(
	    		$Order_tbl->saveProductRejectionReason($_POST['DATA'])
	    	);
			exit;
		}
	}

session_write_close();
	/*===============================================================================**/
	
	