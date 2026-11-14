<div class="container-fluid mt-4 px-3 px-md-5">
    <!-- Metro Tiles -->
    <div id="metro-container" class="row g-4 justify-content-center mb-5">

        <!-- تأكيد طلبات العملاء -->
        <?php if($usm["orderlist_ex1"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=1"
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-primary">
                <i class="tile-icon fas fa-clipboard-check"></i>
                <div class="tile-title fw-bold">
                    تأكيد طلبات العملاء
                </div>
            </div>
        </a>
	  	<?php } ?>
	  	
        <!-- الطلبات المؤجلة -->
        <?php if($usm["orderlist_ex2"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=2"
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-warning">
                <i class="tile-icon fas fa-clock"></i>
                <div class="tile-title fw-bold">
                    الطلبات المؤجلة
                </div>
            </div>
        </a>
	  	<?php } ?>
	  
        <!-- الطلبات غير المتوفرة -->
        <?php if($usm["orderlist_ex3"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=3"
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-secondary">
                <i class="tile-icon fas fa-box-open"></i>
                <div class="tile-title fw-bold">
                    الطلبات غير المتوفرة
                </div>
            </div>
        </a>
	  	<?php } ?>
	  
        <!-- العملاء غير المستجيبين -->
        <?php if($usm["orderlist_ex4"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=4"
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-dark">
                <i class="tile-icon fas fa-phone-slash"></i>
                <div class="tile-title fw-bold">
                    العملاء غير المستجيبين
                </div>
            </div>
        </a>
	  	<?php } ?>
	  
        <!-- ملغاة لعدم الرد -->
        <?php if($usm["orderlist_ex13"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=13"
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-danger">
                <i class="tile-icon fas fa-user-times"></i>
                <div class="tile-title fw-bold">
                    ملغاة لعدم الرد
                </div>
            </div>
        </a>
	  	<?php } ?>
	  
        <!-- ملغاة بطلب العميل -->
        <?php if($usm["orderlist_ex5"]["View_order_list"]  == "true"){ ?>
        <a href="?Go=Orderlist&ty=1&ex=5" 
           class="col-md-6 col-lg-4 col-xl-2 text-decoration-none">
            <div class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-danger">
                <i class="tile-icon fas fa-user-times"></i>
                <div class="tile-title fw-bold">
                    ملغاة بطلب العميل
                </div>
            </div>
        </a>
	  	<?php } ?>
	  	
    </div>
</div>