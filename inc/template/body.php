<?php

?>
    <body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed" style="margin-top: 73px">
        <!-- wrapper -->
        
		<div id="contentbody" class="wrapper overlay-wrapper">
				
            <!-- navbar -->
            <?php include ABSPATH. "/inc/template/navbar.php"; ?>
            <!-- /.navbar -->
            <!-- sidebar -->
            <?php include ABSPATH. "/inc/template/SidebarMenu.php"; ?>
            <!-- /.sidebar -->
            <!-- content-wrapper -->
            <div id="overlay2" class="content-wrapper content">
		        <div class="spinner"></div>
		    </div>
		    
			<div id ="ovarlay" class=" content-wrapper content overlay">
						
						<i class="fas fa-3x fa-sync-alt fa-spin"></i>
						<p></p>
						<div class="text-bold pt-2 ">  جارى عرض البيانات ....  </div>
						
			</div>
		    <div id="hid" class="content<?php if($perm->Pagelink !=="404" || file_exists(ABSPATH. "/inc/Views/".$perm->Pagelink . '.php') ){echo "-wrapper";} ?>" style="display:none;">
				<section class="content-header">
                    <div class="container-fluid" style="margin-bottom:-20px">
                        <div class="row mb-0">
                            <!-- <div class="col-5">
                            <h5><?php echo $perm->title ?></h5> 
                            </div>-->
                            
                        </div>
                        <div class="row mb-0">
                            <div class="col-6">
                                <div class="ml-2 mr-2">
                                    <ol class="breadcrumb text-left text-primary">
                                        <li class="breadcrumb-item">الرئيسية</li>
                                        <li class="breadcrumb-item active"><?php echo $perm->title ?></li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-6 btn-app-pade text-right mb-0" style="margin:0px 0px 0px 0px; padding: 0 0 0 0;" >
                                <?php if (file_exists(ABSPATH. "/inc/Views/".$perm->Pagelink . '.php')){
								     include ABSPATH. "/inc/Forms/ButtonsApp.php";
							     } ?>
                            </div>
                        </div>
                    </div>
                   <!-- /.container-fluid -->
                </section>
				<section class="content">
							<hr>
							<!-- content -->
							<p id="pagelink" style="display: none">
							<?php echo $perm->Pagelink;
										
							?>
							<p>
							<?php
								if (!$perm->Pagelink || empty($perm->Pagelink) || !file_exists(ABSPATH. "/inc/Views/".$perm->Pagelink . '.php')){
									/*ob_start();
									header("Location:PageNotFound");
									ob_end_flush(); */
									include ABSPATH. "/inc/Views/404.php";
								} else {
									include ABSPATH. "/inc/Views/".$perm->Pagelink . '.php';
								} 
							?>
							<!-- /.content -->
							<hr>
						</section>
	        </div>
		    <!-- /.content-wrapper -->
            <!-- footer -->
            <?php include ABSPATH."inc/template/footer.php";  ?>
            
        <!-- /.footer -->
        
		</div>
        <!-- /.wrapper -->
        <div id="btnCartAddNewOrder" class="cart" style="display:none">
	        <i class="fas fa-shopping-cart"></i>
	    </div>
<?php
	$perm->array_body_JS(); 
?>
<!-- Custom Scripts -->
<script>
	$(".mobile").inputmask('999-99999999');
	$(".fax").inputmask('9999999999');
	$(".phone").inputmask('9999999999');
	
	<?php 
		if($perm->privetjs !==''){
			echo $perm->privetjs; 
		}
	?>
	
	if(typeof $.fn.dataTable != 'undefined'){
	     $.extend(true, $.fn.dataTable.defaults, {
				dom: `<"row"<"col-sm-12 col-md-3 text-left"l><"col-sm-12 col-md-5 text-center"f><"col-sm-12 text-right col-md-4"B>>
					  <"row"<"col-sm-12 col-md-12"i>>
					  <"row"<"w-100"tr>>
					  <"row"<"col-sm-12 col-md-7"p>>`,
				language: {
					url: '../../Languages/AR/Datatable.lang.json'
				},
				scrollY: "70vh",
				scrollX: true,
				processing: true,
				serverSide: true,
				//serverMethod: "post",
				select: {
					style: "multi" // يسمح بتحديد عدة صفوف
				},
				/*order: [[2, "desc"]], // ترتيب افتراضي للعمود الثاني
				search: {
					return: false // تنفيذ البحث عند الضغط على Enter
				},
				paging: {
			    	pageLength: 50,
			    },*/
				pageLength: 50, // عدد الصفوف الافتراضية لكل صفحة
				searching: true,
				ordering: true,
				lengthMenu: [[10, 25, 50, 100,-1],["10", "25", "50", "100","الكل"]], // خيارات طول الصفحات
				pagingType: "simple_numbers", // نظام ترقيم الصفحات
				responsive: false, // دعم التوافق مع الشاشات الصغيرة
				buttons: [
					"copy", "excel", "pdf", "print" // أزرار التصدير
				]
		});  
     }
     
</script>
</body>