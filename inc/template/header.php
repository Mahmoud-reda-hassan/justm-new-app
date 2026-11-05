<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<title>
<?php 
$company = (!empty($_SESSION["COMPANY_NAME"])) ? $_SESSION["COMPANY_NAME"] : $_SERVER["SERVER_NAME"];
$company .= !empty($perm->title) ? " | " . $perm->title : "";
echo $company;
?>
</title>

<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="image/logo.png">
<!-- ======================
   JavaScript Libraries
====================== -->
<?php 

$perm->header_CSS();
$perm->array_CSS();
if(DEBUG === true || isset($_GET["debug"])){
	echo'
			<!-- dubg mood -->
			<script src="//cdn.jsdelivr.net/npm/eruda"></script>
			<script>
				if (typeof eruda != "undefined") {
				    eruda.init();
				}
			</script>
		';
}

?>
<!-- FingerprintJS -->
<script src="/libraries/fingerprint/fp.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js"></script>-->

<script>

/* ======================
   Browser Fingerprint
====================== */

window.fpid = null;

// قراءة الكوكي
function getCookie(name){
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

// إنشاء fingerprint
async function getBrowserFingerprint(){

    if(typeof FingerprintJS === "undefined"){
        return null;
    }

    const fp = await FingerprintJS.load();
    const result = await fp.get();

    return result.visitorId;
}


// التنفيذ
(async function(){

    let fpId = await getBrowserFingerprint();

    if(fpId){
        document.cookie = "fpId=" + fpId + "; path=/; max-age=" + (10*24*60*60) + "; true;true";
    }

    window.fpid = fpId;

})();


/* ======================
   APP CONFIG
====================== */

window.APP_CONFIG = {
    datatablesServerSide:    <?= DATATABLES_SERVER_SIDE ? "true" : "false" ?>,
    datatablesServerSideEx0: <?= DATATABLES_SERVER_SIDE_EX0 ? "true" : "false" ?>,
    datatablesServerSideEx1: <?= DATATABLES_SERVER_SIDE_EX1 ? "true" : "false" ?>,
    datatablesServerSideEx2: <?= DATATABLES_SERVER_SIDE_EX2 ? "true" : "false" ?>,
    datatablesServerSideEx3: <?= DATATABLES_SERVER_SIDE_EX3 ? "true" : "false" ?>,
    datatablesServerSideEx4: <?= DATATABLES_SERVER_SIDE_EX4 ? "true" : "false" ?>,
    datatablesServerSideEx5: <?= DATATABLES_SERVER_SIDE_EX5 ? "true" : "false" ?>,
    datatablesServerSideEx7: <?= DATATABLES_SERVER_SIDE_EX7 ? "true" : "false" ?>,
    datatablesServerSideEx8: <?= DATATABLES_SERVER_SIDE_EX8 ? "true" : "false" ?>,
    datatablesServerSideEx9: <?= DATATABLES_SERVER_SIDE_EX9 ? "true" : "false" ?>,
    datatablesServerSideEx10: <?= DATATABLES_SERVER_SIDE_EX10 ? "true" : "false" ?>,
    datatablesRowCount: <?= (int) DATATABLES_ROW_COUNT_IN_PAGE ?>

};
</script>

<!-- App 
<script src="/dest/asassets/js/app.js"></script>-->

<!-- jQuery -->
<script src="/libraries/jQuery/jquery-3.6.1.min.js"></script>
<!-- Cookies -->
<script src="/libraries/jquery-cookie/jquery.cookie.min.js"></script>

<script src="/libraries/inputmask/jquery.inputmask.min.js"></script>
<script src="/libraries/ping-js/ping.min.js"></script>

<!-- Raphael -->
<script src="/libraries/raphael/raphael.min.js"></script>

<!-- Mapael -->
<script src="/libraries/jquery-mapael/jquery.mapael.min.js"></script>

<!-- خرائط Mapael -->
<script src="/libraries/jquery-mapael/maps/world_countries.min.js"></script>
<!-- Select2 Library -->
<!--<script src="/libraries/select2-4.1.0/select2.min.js"></script>-->
<!--<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>-->
<!--<script src="/libraries/select2/js/i18n/ar.js"></script>-->
<!--<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/ar.js"></script>-->

<?php 

//$perm->header_CSS();
//$perm->array_CSS();

$perm->header_JS();
$perm->array_headr_js();


if($login == false && ($loginType === 1 || $loginType === 0)){
    include ABSPATH."/inc/template/login.php";
}else if(
    $login == true
    && $loginType === 1
    && !(
        ($_SESSION["ADMIN_TYPE"] == 1 && $pageType == 1) ||
        ($_SESSION["AFFILIATE_TYPE"] == 1 && $pageType == 2) ||
        ($_SESSION["DELIVERY_TYPE"] == 1 && $pageType == 3) ||
        ($_SESSION["INTERMEDIARY_TYPE"] == 1 && $pageType == 4) ||
        ($_SESSION["MERCHANT_TYPE"] == 1 && $pageType == 5)
    )
){

    include ABSPATH."/inc/Views/404.php";
    exit;

}
else{

    if(isset($_SESSION["SUSPENDED"]) && $_SESSION["SUSPENDED"] == 1){

        include ABSPATH."/inc/template/body.php";

    }else{

        include ABSPATH."/inc/Views/suspended_login.php";

    }

    exit;
}

?>

</head>
</html>
