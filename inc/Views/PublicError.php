<?php
require_once "inc/inc_functions.php";
$title= "حدث خطاء";
$css="all.min.css,ionicons.min.css,adminlte.min.css,bootstrap.min.css,custom.css";
$js = "jquery.min.js,bootstrap.bundle.min.js,adminlte.min.js";
include get_dir_init("header.php");
?>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<section class="content">
      <div class="error-page">
        <h2 class="headline text-danger col-4">Error</h2>

        <div class="error-content">
          <h3><i class="fas fa-exclamation-triangle text-danger "></i> حدث خطاء غير متوقع.</h3>

          <p>
            نأسف لحدوث هذا الخطاء وسوف يقوم فريق العمل بحل هذه المشكله فى وقت لاحق ويمكنك ايضا التواصل مع فريق العمل من خلال الاتصال برقم ١١٩١١
          </p>
           رسالة الخطاء : 
<a> <?php echo $error ?></a>
         <p>
        
        </div>
      </div>
      <!-- /.error-page -->

    </section>
   </div>
    <?php echo get_js($js); ?>
    </body>
    </html>