<?php
//session_start();
	require_once 'config.php';
	include_once(ABSPATH.'/inc/Databases/dbconn.php');
	require_once(ABSPATH.'/inc/auth_system.php');
	//require_once(ABSPATH."/inc/router.php");
	require_once(ABSPATH."/inc/Classes/user_permission.php");
	require_once(ABSPATH."/Languages/AR/SidebarMenu.lang.php");
	ob_start();
	session_start();
	$Database = new dbconn();
	$db = $Database->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);

	// التأكد من تسجيل الدخول
	Auth::login($db);
	$login=Auth::check();
	
$perm = new user_permission();
$loginType=1;
$pageType =0;
//$perm = new user_permission();
/*$perm->Pagelink = "AdminZone";
if(empty($perm->title)){
    $perm->title = 'منطقة VIP';
}

//$perm->app_request($loginType,$pageType);
/*if(isset($_SESSION) && 
	isset($_SESSION["ADMIN_TYPE"]) &&
	isset($_SESSION["MERCHANT_TYPE"]) && 
	isset($_SESSION["AFFILIATE_TYPE"]) && 
	isset($_SESSION["INTERMEDIARY_TYPE"]) && 
	isset($_SESSION["DELIVERY_TYPE"])){
		
	if($loginType === 1 &&	
	    $_SESSION["ADMIN_TYPE"] &&
		!$_SESSION["MERCHANT_TYPE"] && 
		!$_SESSION["AFFILIATE_TYPE"] && 
		!$_SESSION["INTERMEDIARY_TYPE"] && 
		!$_SESSION["DELIVERY_TYPE"]){
	   
		header("location: /Admin");
		
	} else if($loginType === 1 &&
		!$_SESSION["ADMIN_TYPE"] &&
		$_SESSION["MERCHANT_TYPE"] && 
		!$_SESSION["AFFILIATE_TYPE"] && 
		!$_SESSION["INTERMEDIARY_TYPE"] && 
		!$_SESSION["DELIVERY_TYPE"]){
		
		header("location: /Merchant");
		
	} else if($loginType === 1 &&
		!$_SESSION["ADMIN_TYPE"] &&
		!$_SESSION["MERCHANT_TYPE"] && 
		$_SESSION["AFFILIATE_TYPE"] && 
		!$_SESSION["INTERMEDIARY_TYPE"] && 
		!$_SESSION["DELIVERY_TYPE"]){
		
		header("location: /Affiliate");
		
	} else if($loginType === 1 && 
		!$_SESSION["ADMIN_TYPE"] &&
		!$_SESSION["MERCHANT_TYPE"] && 
		!$_SESSION["AFFILIATE_TYPE"] && 
		$_SESSION["INTERMEDIARY_TYPE"] && 
		!$_SESSION["DELIVERY_TYPE"]){
		
		header("location: /Intermediary");
		
	} else if($loginType === 1 &&	
		!$_SESSION["ADMIN_TYPE"] &&
		!$_SESSION["MERCHANT_TYPE"] && 
		!$_SESSION["AFFILIATE_TYPE"] && 
		!$_SESSION["INTERMEDIARY_TYPE"] && 
		$_SESSION["DELIVERY_TYPE"]){
		
		header("location: /Shipping");
		
	} else if($loginType === 1 &&	
	    ($_SESSION["ADMIN_TYPE"] ||
		$_SESSION["MERCHANT_TYPE"] || 
		$_SESSION["AFFILIATE_TYPE"] || 
		$_SESSION["INTERMEDIARY_TYPE"] || 
		$_SESSION["DELIVERY_TYPE"])){
		
		include ABSPATH."/inc/Views/AdminZone.php";
		
	} else {
		include ABSPATH.'/inc/template/header.php';
	}
}else{
	include ABSPATH."/inc/template/header.php";
}*/


if (isset(
	$_SESSION,
	$_SESSION["ADMIN_TYPE"], 
    $_SESSION["MERCHANT_TYPE"], 
    $_SESSION["AFFILIATE_TYPE"], 
    $_SESSION["INTERMEDIARY_TYPE"], 
    $_SESSION["DELIVERY_TYPE"]
   )) {
    
    if ($loginType === 1) {
        // نحسب عدد الصلاحيات المتاحة
        $roles = [
            "ADMIN_TYPE" => "/Admin",
            "MERCHANT_TYPE" => "/Merchant",
            "AFFILIATE_TYPE" => "/Affiliate",
            "INTERMEDIARY_TYPE" => "/Intermediary",
            "DELIVERY_TYPE" => "/Shipping"
        ];

        $availableRoles = [];

        foreach ($roles as $role => $path) {
            if (!empty($_SESSION[$role])) {
                $availableRoles[$role] = $path;
            }
        }

        $count = count($availableRoles);

        if ($count === 1) {
            // توجيه تلقائي لصلاحيته الوحيدة
            header("Location: " . reset($availableRoles));
            exit;
        } elseif ($count > 1) {
     
            // عرض صفحة تحتوي على الصلاحيات المتعددة
            include ABSPATH . "/inc/Views/AdminZone.php";
        } else {
            // لا يمتلك أي صلاحية
            include ABSPATH . '/inc/template/header.php';
        }
    } else {
        include ABSPATH . '/inc/template/header.php';
    }
} else {
    include ABSPATH . '/inc/template/header.php';
}
ob_end_flush();
?>