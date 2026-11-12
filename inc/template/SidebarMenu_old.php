<?php
$lang = $language['Menu'];
$UserScript_menu = unserialize($_SESSION['USER_SCRIPT']);
$usm= isset($UserScript_menu["Admin"])?$UserScript_menu["Admin"]:[];

if ($loginType === 1 && $pageType > 0) {
    ?>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="/" class="brand-link">
            <img src="../../<?php echo $_SESSION["COMPANY_LOGO"]; ?>" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light"><?php echo $_SESSION["COMPANY_NAME"]; ?></span>
        </a>
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="../../<?php echo $_SESSION["USER_IMG_LINK"]; ?>" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="?Go=Profile" class="d-block"><?php echo $_SESSION["FNAME"] ; ?></a>
                </div>
            </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Add icons to the links using the .nav-icon class with font-awesome or any other icon font library -->
                <?php if($_SESSION["ADMIN_TYPE"] && $pageType  === 1){
						echo '<li class="nav-header">لوحة تحكم الادارة</li>';
					} else if($_SESSION["AFFILIATE_TYPE"] && $pageType  === 2) {
						echo '<li class="nav-header">لوحة تحكم المسوقين</li>';
					} else if($pageType  === 3) {
						echo '<li class="nav-header">لوحة تحكم موظف الشحن</li>';
					} else if($pageType  === 4) {
						echo '<li class="nav-header">لوحة تحكم وسطاء البيع</li>';
					} else if($pageType  === 5) {
						echo '<li class="nav-header">لوحة تحكم التجار</li>';
					}
				?>
					
                <li id="Dashboard" class="nav-item has-treeview">
                    <a href="?" class="nav-link active">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>
                            <?php echo $lang['Dashboard']; ?>
                        </p>
                    </a>
                </li>
				

            <?php if($_SESSION["ADMIN_TYPE"] && $pageType  === 1){ ?>
				<!--انشاء امر بيع-->
                <li class="nav-item">
                    <a href="?ty=1&Go=NewOrder" class="nav-link">
                        <i class="fa fa-ellipsis-v nav-icon "></i>
                        <P>
                            انشاء امر بيع 
                        </P>
                    </a>
                </li>

            	<!-- تتبع طلبات العملاء-->
            	<?php if($usm["orderlist_ex0"]["View_order_list"]  == "true"){
                <li class="nav-item">
                    <a href="?Go=Orderlist&ty=1&ex=0" class="nav-link">
                        <i class="fas fa-list-alt  nav-icon"></i>
                        <P>
                            تتبع طلبات العملاء
                        </P>
                    </a>
                </li>
                <?php } ?>
                
                <!-- المراجعه والتأكيد -->
            	<?php if($usm["orderlist_ex1"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex2"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex3"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex4"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex5"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex6"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex12"]["View_order_list"]  == "true" ||
                       $usm["orderlist_ex13"]["View_order_list"]  == "true"){ ?>
                        
		       	<li class="nav-item has-treeview">
		        	<a href="?Go=OrderFollowUpConfirmation" class="nav-link">
		            	<i class="fa fa-clipboard-check nav-icon"></i>
		                <P>
		                    المراجعه والتأكيد
		                </P>
		            </a>
		        </li>
                <?php } ?>
                
               	<!-- المبيعات -->
            	<?php if(isset($usm["orderlist_os1"]["View_order_list"]) && $usm["orderlist_os1"]["View_order_list"]  == "true" ||
                       isset($usm["orderlist_os2"]["View_order_list"]) && $usm["orderlist_os2"]["View_order_list"]  == "true" ||
                       isset($usm["selseReport"]["View"]) && $usm["selseReport"]["View"] = "true"){ ?>
                        
                <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="fa-solid fa-cart-shopping nav-icon "></i>
                                <p>
                                    المبيعات 
                                    <i class="right fas fa-angle-left right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview badge-gray60">
                			<?php // المراجعه والتأكيد
	                        if((isset($usm["orderlist_ex0"]["View_order_list"]) && $usm["orderlist_ex0"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex1"]["View_order_list"]) && $usm["orderlist_ex1"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex2"]["View_order_list"]) && $usm["orderlist_ex2"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex3"]["View_order_list"]) && $usm["orderlist_ex3"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex4"]["View_order_list"]) && $usm["orderlist_ex4"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex13"]["View_order_list"]) && $usm["orderlist_ex13"]["View_order_list"]  == "true") ||
	                           (isset($usm["orderlist_ex5"]["View_order_list"]) && $usm["orderlist_ex5"]["View_order_list"]  == "true") ||
	                           (isset($usm["selseReport"]["View"]) && $usm["selseReport"]["View"] = "true")){ ?>
                            <li class="nav-header boder text-center badge badge-red0 pt-1 pb-1 pl-2 pr-2 " style="font-size: 10px;">بيع مباشر</li> 
                            <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                            
                                                         
                            <!-- تقرير حركة المبيعات والتسليمات -->
                            <?php if($usm["selseReport"]["View"]  == "true"){ ?>
                            <li class="nav-item">
                                <a href="?Go=SalesMovementReport" class="nav-link">
                                	<i class="fa fa-ellipsis-v nav-icon "></i>
                                    <P>
                                        تقرير حركة المبيعات والتسليمات
                                    </P>
                                </a>
                            </li>
                            <?php } ?>

                        <?php } ?>
                        
                        <!-- خدمات المتابعة والدعم -->
                       	<?php // خدمات المابعة والدعم
	                         if((isset($usm["SupportAfterSales"]) && $usm["SupportAfterSales"]["View_order_list"] == "true") ||
	                            (isset($usm["SupportOrders"]) && $usm["SupportOrders"]["View_order_list"] == "true")){?>
                            	<li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">خدمات الدعم</li>
                            	<hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                				<?php if(isset($usm["SupportAfterSales"]) && $usm["SupportAfterSales"]["View_order_list"] == "true"){ ?>
                                <!-- طلبات تم شحنها-->
                        <?php if($usm["orderlist_ex12"]["View_order_list"]  == "true"){ ?>
                                <li class="nav-item">
                                    <a href="?Go=Orderlist&ty=1&ex=12" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           تتبع تأكيد الشحن من المندوبين  
                                        </p>
                                    </a>
                                </li> 
                                
                        <?php } ?>
                                <!-- طلبات تم شحنها-->
                                <li class="nav-item">
                                    <a href="?Go=SupportAfterSales&ty=1" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           خدمة ما بعد البيع  
                                        </p>
                                    </a>
                                </li> 
								<?php } ?>
                            	<?php if(isset($usm["SupportOrders"]) && $usm["SupportOrders"]["View_order_list"] == "true"){ ?>
                                <!-- طلبات الاستبدال -->
                                <li class="nav-item">
                                    <a href="?Go=SupportOrders&ty=1" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           طلبات المنسقين والعملاء
                                        </p>
                                    </a>
                                </li>
                				<?php } ?>
                        <?php } ?>
                            </ul>
                        </li>
					<?php } ?>
					<!-- المنتجات -->
				<?php if($usm["Product-Categories"]["View"] == "true" ||
	                 $usm["ProductOption"]["View"] == "true" ||
                     $usm["Products"]["View"] == "true") { ?>
                       	<!--قسم المنتجات-->
                    	<li class="nav-item has-treeview " >
                            <a href="#" class="nav-link">
                                <i class="fa-brands fa-shopify nav-icon"></i>
                                <p>
                                    المنتجات
                                    <i class="fas fa-angle-left right"></i>
                                </p>
                        	</a>
                            <ul class="nav nav-treeview badge-gray60">
                				<?php if($UserScript_menu["Admin"]["Product-Categories"]["View"] == "true"){ ?>
                                <!--الاقسام -->
                                <li class="nav-item">
                                    <a href="?Go=Product-Categories" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                        الاقسام
                                        </p>
                                    </a>
                                </li>
                				<?php } ?>
		                		<?php if($UserScript_menu["Admin"]["ProductOption"]["View"] == "true"){ ?>
		                         <!--خيارات المنتجات-->
                                <li class="nav-item">
                                    <a href="?Go=ProductOption" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                        خيارات المنتجات
                                        </p>
                                    </a>
                                </li>
                				<?php } ?>
		                		<?php if($UserScript_menu["Admin"]["Products"]["View"] == "true"){ ?>
		                        <!--عرض المنتجات-->
                                <li class="nav-item">
                                    <a href="?Go=Products" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                        عرض المنتجات
                                        </p>
                                    </a>
                                </li>
                				<?php } ?>
    						</ul>
                        </li>
            	<?php } ?>
					<?php if((isset($usm["orderlist_ex7"]["View_order_list"]) && $usm["orderlist_ex7"]["View_order_list"]  == "true") ||
                        (isset($usm["orderlist_ex8"]["View_order_list"]) && $usm["orderlist_ex8"]["View_order_list"]  == "true") ||
                        (isset($usm["orderlist_ex9"]["View_order_list"]) && $usm["orderlist_ex9"]["View_order_list"]  == "true") ||
                        (isset($usm["orderlist_ex10"]["View_order_list"]) && $usm["orderlist_ex10"]["View_order_list"]  == "true") ||
                        (isset($usm["StockReceipt"]['view']) && $usm["StockReceipt"]['view'] == "true") ||
                        (isset($usm["StockIssue"]['view']) && $usm["StockIssue"]['view'] == "true") ||
                        (isset($usm["InternalTransfer"]['view']) && $usm["InternalTransfer"]['view'])){ ?>
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="fa-solid fa-warehouse nav-icon"></i>
                                <p>
                                    المخازن
                                    <i class="fas fa-angle-left right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview badge-gray60">
                			<?php if((isset($usm["orderlist_ex7"]["View_order_list"]) && $usm["orderlist_ex7"]["View_order_list"]  == "true") ||
                                   (isset($usm["orderlist_ex8"]["View_order_list"]) && $usm["orderlist_ex8"]["View_order_list"]  == "true") ||
                                   (isset($usm["orderlist_ex9"]["View_order_list"]) && $usm["orderlist_ex9"]["View_order_list"]  == "true") ||
                                   (isset($usm["orderlist_ex10"]["View_order_list"]) && $usm["orderlist_ex10"]["View_order_list"] == "true") ){?>
                                    <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">التجهيز والشحن</li>
                                    <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                					<?php if(isset($usm["orderlist_ex7"]) && $usm["orderlist_ex7"]["View_order_list"] == "true"){ 
									?>
                                        <li class="nav-item">
                                            <a href="?Go=Orderlist&ty=1&ex=7" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                    طلبات قيد التجهيز
                                                </p>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <?php if(isset($usm["orderlist_ex8"]) && $usm["orderlist_ex8"]["View_order_list"] == "true"){ ?>
                                        <li class="nav-item">
                                            <a href="?Go=Orderlist&ty=1&ex=8" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                   طلبات تم التجهيز
                                                </p>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <?php if(isset($usm["orderlist_ex9"]) && $usm["orderlist_ex9"]["View_order_list"] == "true" && 1==2){ ?>      
                                        <li class="nav-item">
                                            <a href="?Go=Orderlist&ty=3&ex=9" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                طلبات قيد الشحن
                                                </p>
                                            </a>
                                        </li>
                                    <?php } ?>
		                            <?php if(isset($usm["OrdersCustody"]) && $usm["OrdersCustody"]["View_order_list"] == "true"){ ?>      
		                                <li class="nav-item">
		                                    <a href="?Go=OrdersCustody&ex=10" class="nav-link">
		                                        <i class="fa fa-ellipsis-v nav-icon "></i>
		                           				<p>
		                           			     تسوية عهدة المندوبين
		                            			</p>
		                            		</a>
		                            	</li>
		                            <?php } ?>
                            <?php } ?>
	            			<?php if((isset($usm["StockReceipt"]['view']) && $usm["StockReceipt"]['view']) == "true" ||
                            	(isset($usm["StockIssue"]['view']) && $usm["StockIssue"]['view'] == "true") ||
                            	(isset($usm["InternalTransfer"]['view']) && $usm["InternalTransfer"]['view'])){ ?>  
                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">المعاملات المخزنيه</li>
                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
          						<?php if(isset($usm["StockReceipt"]) && 
                            		 $usm["StockReceipt"]['view'] == "true" ){ ?>  
		                                <li class="nav-item">
		                                    <a href="?Go=AddStock" class="nav-link">
		                                        <i class="fa fa-ellipsis-v nav-icon"></i>
		                                        <p>
		                                            اذن استلام مخزنى 
		                                        </p>
		                                    </a>
		                                </li>
                                <?php } ?>
                                
                                <?php if(isset($usm["StockIssue"]) && 
                            		 $usm["StockIssue"]['view'] == "true" ){ ?>  
                                <li class="nav-item">
                                    <a href="?Go=StockIssue" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon"></i>
                                        <p>
                                            اذن صرف مخزنى 
                                        </p>
                                    </a>
                                </li>
                                <?php } ?>
                                
                                <?php if(isset($usm["StockTransfer"]) && 
                            		 $usm["StockTransfer"]['view'] == "true" ){ ?>  
		                                <li class="nav-item">
		                                    <a href="?Go=StockTransfer" class="nav-link">
		                                        <i class="fa fa-ellipsis-v nav-icon"></i>
		                                        <p>
		                                            اذن تحويل مخزنى 
		                                        </p>
		                                    </a>
		                                </li>
                                <?php } ?>
                            	
                        	<?php } ?>
                            
                            <?php if(isset($usm["StockAudit"]) && 
                            		 $usm["StockAudit"]['view'] == "true" ){ ?>    
	                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">جرد ومطابقة</li>
	                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
	                                <li class="nav-item">
	                                    <a href="?Go=StockAudit" class="nav-link">
	                                        <i class="fa fa-ellipsis-v nav-icon"></i>
	                                        <p>
	                                            جرد ومطابقة المخزون 
	                                        </p>
	                                    </a>
	                                </li>
                           	<?php } ?>
		                            <li class="nav-item">
		                                <a href="?Go=AddPurchaseOrder" class="nav-link">
		                                   <i class="fa fa-ellipsis-v nav-icon "></i>
		                                   <p>
		                                      طلب توريد
		                                   </p>
		                                </a>
	                                </li>
                            <?php if((isset($usm["CriticalItemsReport"]) && $usm["CriticalItemsReport"]['view'] == "true") || 
                            		 (isset($usm["DelegatesInventoryReport"]) && $usm["DelegatesInventoryReport"]['view'] == "true") ||
                            		 (isset($usm["suppliersQtyReport"]) && $usm["suppliersQtyReport"]['view'] == "true")
                            		 ){ ?>    
                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">تقارير</li>
                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                            	<?php if(isset($usm["CriticalItemsReport"]) && $usm["CriticalItemsReport"]["view"] == "true"){ ?>    
                                	<li class="nav-item">
	                                    <a href="?Go=stockShortageReport" class="nav-link">
	                                        <i class="far fa-circle nav-icon"></i>
	                                        <p>
	                                            تقرير الاصناف الحرجه 
	                                        </p>
	                                    </a>
	                                </li>
                            	<?php } ?>
                            	<?php if(isset($usm["DelegatesShipmentReport"]) && $usm["DelegatesInventoryReport"]["view"] == "true"){ ?>    
                                	<li class="nav-item">
	                                    <a href="?Go=DelegatesShipmentReport" class="nav-link">
	                                        <i class="far fa-circle nav-icon"></i>
	                                        <p>
	                                            تقرير حركة عهدة المندوبين 
	                                        </p>
	                                    </a>
	                                </li>
                            	<?php } ?>
                            	<?php if(isset($usm["DelegatesInventoryReport"]) && $usm["DelegatesInventoryReport"]["view"] == "true"){ ?>    
                                	<li class="nav-item">
	                                    <a href="?Go=DelegatesInventoryReport" class="nav-link">
	                                        <i class="far fa-circle nav-icon"></i>
	                                        <p>
	                                            تقرير حركة الاستلامات المخزنيه 
	                                        </p>
	                                    </a>
	                                </li>
                            	<?php } ?>
                            	<?php if(isset($usm["suppliersQtyReport"]) && $usm["suppliersQtyReport"]["view"] == "true"){ ?>    
                                	<li class="nav-item">
	                                    <a href="?Go=SuppliersQtyReport" class="nav-link">
	                                        <i class="far fa-circle nav-icon"></i>
	                                        <p>
	                                            تقرير استلامات الموردين 
	                                        </p>
	                                    </a>
	                                </li>
                            	<?php } ?>
                            <?php } ?>
                            </ul>
                        </li>
                    <?php } ?>
                    
					<?php if((isset($usm["PaymentsReceipt"]["view"]) && $usm["PaymentsReceipt"]["view"]  == "true") ||
							 (isset($usm["PaymentDisbursement"]["view"]) && $usm["PaymentDisbursement"]["view"]  == "true") ||
							 (isset($usm["CashReceiptVouchers"]["view"]) && $usm["CashReceiptVouchers"]["view"]  == "true") ||
							 (isset($usm["AccountStatementSupplier"]["view"]) && $usm["AccountStatementSupplier"]["view"]  == "true") ||
							 (isset($usm["AccountStatementClient"]["view"]) && $usm["AccountStatementClient"]["view"]  == "true") ||
							 (isset($usm["AccountStatementMarketer"]["view"]) && $usm["AccountStatementMarketer"]["view"]  == "true") ||
							 (isset($usm["AccountStatementDelivery"]["view"]) && $usm["AccountStatementDelivery"]["view"]  == "true")){
					?>
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="fa-solid fa-dollar-sign nav-icon"></i>
                                <p>
                                    الحسابات
                                    <i class="fas fa-angle-left right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview badge-gray60">
                                <?php if(isset($usm["PaymentsReceipt"]["view"]) && $usm["PaymentsReceipt"]["view"]  == "true" && 1==2){?>
                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">الموظفين والشركاء</li>
                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                                <li class="nav-item">
                                    <a href="?Go=ReceivePayments" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           استلام عهدة المندوبين 
                                        </p>
                                    </a>
                                </li>
                                <?php } ?>
								<?php if((isset($usm["PaymentDisbursement"]["view"]) && $usm["PaymentDisbursement"]["view"]  == "true") ||
										 (isset($usm["CashReceiptVouchers"]["view"]) && $usm["CashReceiptVouchers"]["view"]  == "true")){?>
                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">الشؤن الماليه</li>
                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                             	<?php if(isset($usm["PaymentDisbursement"]["view"]) && $usm["PaymentDisbursement"]["view"]  == "true"){?>
								<li class="nav-item">
                                    <a href="?Go=PaymentDisbursement" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           اذن صرف نقدية 
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
								<?php if(isset($usm["CashReceiptVouchers"]["view"]) && $usm["CashReceiptVouchers"]["view"]  == "true"){?>
                                <li class="nav-item">
                                    <a href="?Go=CashReceiptVouchers" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                          اذن استلام نقدية 
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
								<?php } ?>
							<?php if((isset($usm["AccountStatementSupplier"]["view"]) && $usm["AccountStatementSupplier"]["view"]  == "true") ||
									 (isset($usm["AccountStatementClient"]["view"]) && $usm["AccountStatementClient"]["view"]  == "true") ||
									 (isset($usm["AccountStatementMarketer"]["view"]) && $usm["AccountStatementMarketer"]["view"]  == "true") ||
									 (isset($usm["AccountStatementDelivery"]["view"]) && $usm["AccountStatementDelivery"]["view"]  == "true")){?>
                                <li class="badge badge-red0 pt-1 pb-1 pl-2 pr-2 nav-header" style="font-size: 10px;">تقارير</li>
                                <hr class="badge-red0 mb-3" style="margin: -11px 0px 5px 0px;">
                                <?php if(isset($usm["AccountStatementSupplier"]["view"]) && $usm["AccountStatementSupplier"]["view"]  == "true"){ ?>
								<li class="nav-item">
                                    <a href="?Go=AccountStatement&ptr=SUPPLIER" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           كشف حساب مورد
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
								<?php if(isset($usm["AccountStatementClient"]["view"]) && $usm["AccountStatementClient"]["view"]  == "true"){ ?>
                                <li class="nav-item">
                                    <a href="?Go=AccountStatement&ptr=CLIENT" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           كشف حساب عميل
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
								<?php if(isset($usm["AccountStatementMarketer"]["view"]) && $usm["AccountStatementMarketer"]["view"]  == "true"){ ?>
                      			<li class="nav-item">
                                    <a href="?Go=AccountStatement&ptr=MARKETER" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i> 
                                        <p>
                                           كشف حساب مسوق
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
								<?php if(isset($usm["AccountStatementDelivery"]["view"]) && $usm["AccountStatementDelivery"]["view"]  == "true"){ ?>
                      			<li class="nav-item">
                                    <a href="?Go=AccountStatement&ptr=DELIVERY" class="nav-link">
                                        <i class="fa fa-ellipsis-v nav-icon "></i>
                                        <p>
                                           كشف حساب موظف شحن
                                        </p>
                                    </a>
                                </li>
								<?php } ?>
							<?php } ?>
							</ul>
                        </li>
					<?php }?>
				<?php
   				    if($usm["Countries"]["View"]                 == "true" ||
                       $usm["Gov"]["View"]                       == "true" ||
                       $usm["City"]["View"]                      == "true" ||
                       $usm["Local-Shipping"]["View"]            == "true" ||
                       $usm["International-Shipping"]["View"]    == "true" ||
                       $usm["Enterprise"]["View"]                == "true" ||
                       $usm["Administrative-Structure"]["View"]  == "true" ||
                       $usm["Branches"]["View"]                  == "true" ||
                       $usm["Warehouse"]["View"]                 == "true" ||
                       $usm["Users"]["View"]                     == "true" || 
                       $usm["Clients"]["View"]                   == "true" ||
                       $usm["Suppliers"]["View"]                 == "true" || 
                       $usm["Reports"]["View"]                   == "true" ){
                ?>
                        <li id="AllSettings" class="nav-item">
                            <a href=" " class="nav-link">
                                <i class="fa-solid fa-gears nav-icon"></i>
                                <p>
                                    <?php echo "الإعدادات"; ?>
                                </p>
                                <i class="fas fa-angle-left right"></i>
                            </a>
                            <ul class="nav nav-treeview badge-gray60" name="Redir" value="1">
                <?php
                           if($usm["Countries"]["View"]                 == "true" ||
                              $usm["Gov"]["View"]                       == "true" ||
                              $usm["City"]["View"]                      == "true" ||
                              $usm["Local-Shipping"]["View"]            == "true" ||
                              $usm["International-Shipping"]["View"]    == "true" ){
                               
                ?>
                                <li id="Settings" class="nav-item">
                                <a href=" " class="nav-link">
                                    <i class="fa fa-map-marker nav-icon"></i>
                                    <p>
                                        <?php echo $lang["Settings"]; ?>
                                    </p>
                                    <i class="fas fa-angle-left right"></i>
                                </a>
                                <ul class="nav nav-treeview badge-gray60" name="Redir" value="1">
                                    <?php
                                    if ($UserScript_menu["Admin"]["Countries"]["View"] == "true") {
                                        ?>
                                        <li id="Countries" class="nav-item">
                                            <a href="?Go=Countries" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    <?php echo $lang["Countries"]; ?>
                                                </p>
                                            </a>
                                        </li>
                                    <?php
                                    }
                                    if ($UserScript_menu["Admin"]["Gov"]["View"] == "true") {
                                    ?>
                                        <li class="nav-item">
                                            <a id="Gov" href="?Go=Gov" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    <?php echo $lang["Gov"]; ?>
                                                </p>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    if ($UserScript_menu["Admin"]["City"]["View"] == "true") {
                                        ?>
                                        <li class="nav-item">
                                            <a id="Cities" href="?Go=City" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    <?php echo $lang["Cities"]; ?>
                                                </p>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    if ($UserScript_menu["Admin"]["City"]["View"] == "true") {
                                        ?>
                                        <li class="nav-item">
                                            <a href="?Go=LocalShipping" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    إعداد الشحن المحلي
                                                </p>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    if (1==2 && $UserScript_menu["Admin"]["City"]["View"] == "true") {
                                        ?>
                                    <!--    <li class="nav-item">
                                            <a href="?Go=International-Shipping" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    إعداد الشحن الدولى
                                                </p>
                                            </a>
                                        </li> -->
                                        <?php
                                    } ?>
                                </ul>
                            </li>
                <?php
                           }
                           
                           if($usm["Enterprise"]["View"]                == "true" ||
                              $usm["Administrative-Structure"]["View"]  == "true" ||
                              $usm["Branches"]["View"]                  == "true" ||
                              $usm["Warehouse"]["View"]                 == "true" ||
                              $usm["Users"]["View"]                     == "true" || 
                              $usm["Reports"]["View"]                   == "true" ||
                              $usm["Suppliers"]["View"] 				== "true" ||
                              $usm["Clients"]["View"] 					== "true"
                              ){
                ?>
                                <li class="nav-item has-treeview">
                                    <a href="" class="nav-link">
                                        <i class="nav-icon fas fa-city "></i>
                                        <p>
                                            اعدادات عامه
                                            <i class="fas fa-angle-left right " style=" padeng"></i>
                                            <!-- <span class="badge badge-info right">6</span>-->
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview badge-gray60" name="Redir" value="1">
            							<?php
            							if ($UserScript_menu["Admin"]["Enterprise"]["View"] == "true"){
            							?>
                                        <li class="nav-item">
                                            <a href="?Go=Enterprise" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                    المؤسسة
                                                </p>
                                            </a>
                                        </li>
            							<?php
            							}
            							if($UserScript_menu["Admin"]["Administrative-Structure"]["View"] == "true"){
            							?>
                                        <li class="nav-item">
                                            <a href="?Go=Administrative-Structure" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                    الهيكل الإداري
                                                </p>
                                            </a>
                                        </li>
            							<?php
            							}
            							if($UserScript_menu["Admin"]["Branches"]["View"] == "true"){
            							?>
                                        <li class="nav-item">
                                            <a href="?Go=Branches" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                    بيانات الفروع
                                                </p>
                                            </a>
                                        </li>
            							<?php
            							}
            							if($UserScript_menu["Admin"]["Warehouse"]["View"] == "true"){
            							?>
                                        <li class="nav-item">
                                            <a href="?Go=Warehouse" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon "></i>
                                                <p>
                                                    بيانات المخازن
                                                </p>
                                            </a>
                                        </li>
            							<?php
            							}
            							if($UserScript_menu["Admin"]["Users"]["View"] == "true"){
            							?>
                                        <li class="nav-item">
                                            <a href="?Go=Users" class="nav-link">
                                                <i class="fa fa-ellipsis-v nav-icon"></i>
                                                <p>
                                                    المستخدمين
                                                </p>
                                            </a>
                                        </li>
            							<?php
            							}
            							?>
            							<?php
					                    if($usm["Clients"]["View"] == "true"){
					            		?>
		    