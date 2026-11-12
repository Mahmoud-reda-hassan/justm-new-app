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
	if(empty($perm->title)){
	    $perm->title="منطقة المسوقين";
	}
	$pageType 		= 2;
	$loginType 		= 1;
	$perm->PageType = 2;
	$perm->app_request($loginType,$pageType);

	include_once(ABSPATH."/inc/template/header.php");
	ob_end_flush();
?>