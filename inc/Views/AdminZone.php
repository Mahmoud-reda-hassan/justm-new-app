<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>منطقة المستخدمين المميزين</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <!-- Vendor CSS Files -->
  <?php
  //$dirRoot=$perm->furl();
  echo '
  <link href="../../libraries/aos/aos.css" rel="stylesheet">
  <link href="../../libraries/bootstrap/4.2.1/css/bootstrap-4.2.1.min.css" rel="stylesheet">
  <link href="../../libraries/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../../libraries/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../../libraries/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="../../libraries/remixicon/remixicon.css" rel="stylesheet">
  <link href="../../libraries/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="../../dist/css/style-vndor.css" rel="stylesheet">';
  ?>

  <!-- =======================================================
  * Template Name: Gp - v4.8.1
  * Template URL: https://bootstrapmade.com/gp-free-multipurpose-html-bootstrap-template/
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="fixed-top ">
    <div class="container d-flex align-items-center justify-content-lg-between">
         <h1 class="logo me-auto me-lg-0"><a href="/"><?php echo $company=$_SERVER["SERVER_NAME"]; ?><span>.</span></a></h1>
         <a onclick="logout1()" class="get-started-btn scrollto">تسجيل الخروج</a>
    </div>
  </header><!-- End Header -->

  <!-- ======= Hero Section ======= -->
  <section id="hero" class="d-flex align-items-center justify-content-center" style="high:100%">
    <div class="container" data-aos="fade-up">

      <div class="row justify-content-center" data-aos="fade-up" data-aos-delay="150">
        <div class="col-xl-6 col-lg-8">
          <h1><span>.</span>منطقة المستخدمين المميزين</h1>
          <h2>من فضلك اختار وظيفة من الواظائف التالية</h2>
        </div>
      </div>
	  <div class="row gy-4 mt-5 justify-content-center" data-aos="zoom-in" data-aos-delay="250">
      <?php if($loginType === 1 &&	$_SESSION["MERCHANT_TYPE"] == 1){ ?>
        <div class="col-xl-2 col-md-4">
          <div class="icon-box">
            <i class="ri-store-line"></i>
            <h3><a href="/CP_APP/Merchant">منطقة التجار</a></h3>
          </div>
        </div>
        <?php } if($loginType === 1 &&	$_SESSION["INTERMEDIARY_TYPE"] == 1){ ?>
        <div class="col-xl-2 col-md-4">
          <div class="icon-box">
            <i class="ri-bar-chart-box-line"></i>
            <h3><a href="/Intermediary/">منطقة وسطاء البيع</a></h3>
          </div>
        </div>
        <?php } if($loginType === 1 &&	$_SESSION["DELIVERY_TYPE"] == 1){ ?>
        <div class="col-xl-2 col-md-4">
          <div class="icon-box">
            <i class="ri-calendar-todo-line"></i>
            <h3><a href="/Shipping/">منطقة موظفي الشحن</a></h3>
          </div>
        </div>
        <?php } if($loginType === 1 &&	$_SESSION["AFFILIATE_TYPE"] == 1){ ?>
        <div class="col-xl-2 col-md-4">
          <div class="icon-box">
            <i class="ri-paint-brush-line"></i>
            <h3><a href="/Affiliate/">منطقة المسوقين</a></h3>
          </div>
        </div>
        <?php } if($loginType === 1 &&	$_SESSION["ADMIN_TYPE"] == 1){ ?>
        <div class="col-xl-2 col-md-4">
          <div class="icon-box">
            <i class="ri-database-2-line"></i>
            <h3><a href="/Admin/">منطقة الادارين</a></h3>
          </div>
        </div>
        <?php } ?>
      </div>

    </div>
  </section><!-- End Hero -->
    <!-- Vendor JS Files -->
 <?php
  echo 
  '<script src="../../libraries/aos/aos.js"></script>
  <script src="../../libraries/glightbox/js/glightbox.min.js"></script>
  <script src="../../libraries/swiper/swiper-bundle.min.js"></script>';
 ?>
  
  <!-- Template Main JS File -->
 <?php
  echo '<script src="../../libraries/jquery/jquery.min.js?v=1659480791"></script>';
 ?>
  <script>
	function logout1() {
        $.post( "../inc/Api.php", { logout: ""} );
		setTimeout(function(){
			location.reload();
		}, 1000);
		
    }
  </script>

<?php  
	echo '
	<script src="/dist/js/main.js"></script>
	<script src="/dist/js/custom.js?v='.filemtime($_SERVER["DOCUMENT_ROOT"]."/dist/js/custom.js").'"></script>
	<script src="//cdn.jsdelivr.net/npm/eruda"></script>';
	
?>
<script>
	

window.addEventListener("load", () => {
    // فحص كل السكربتات
    const scripts = document.querySelectorAll("script[src]");
    scripts.forEach(script => {
        const url = script.getAttribute("src");
        fetch(url, { method: "HEAD", cache: "no-store" })
            .then(res => {
                if (!res.ok) {
                    console.error(`Script failed to load: ${url} (Status: ${res.status})`);
                }
            })
            .catch(err => {
                console.error(`Script load error: ${url}`, err);
            });
    });

    // فحص كل ملفات CSS
    const links = document.querySelectorAll("link[rel='stylesheet'][href]");
    links.forEach(link => {
        const url = link.getAttribute("href");
        fetch(url, { method: "HEAD", cache: "no-store" })
            .then(res => {
                if (!res.ok) {
                    console.error(`CSS failed to load: ${url} (Status: ${res.status})`);
                }
            })
            .catch(err => {
                console.error(`CSS load error: ${url}`, err);
            });
    });
});
if (typeof eruda != "undefined") {
    eruda.init();
}
</script>

</body>

</html>