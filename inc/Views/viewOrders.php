<?php

/* 
-----نوع الامر ---
1 = إدارى
2 = مسوق
3 = الشحن والتسليم
4 = وسيط بيع
5 = تاجر
6 = يع مباشر 
--------------
*/
?>
<!-- Floating Call Widget -->
<div id="callWidget" class="call-widget" style="margin-left:-20px; display:none;">

    <!-- الجزء الصغير -->
    <div class="call-main" id="callMain">
        <i class="fa fa-phone-alt"></i>
        <span id="callTimer">00:00:00</span>
    </div>

    <!-- التفاصيل -->
    <div class="call-details" id="callDetails">
        <div id="clientName">اسم العميل</div>
        <div id="clientPhone">01000000000</div>
		<div id="clientAddress"></div>
    </div>
    
    <!-- انهاء المكالمه -->
	<div class="call-result" id="endCall">
	    <button id="endCallBtn" class="call-end-btn">
	        <i class="fa fa-phone-slash"></i>
	        إنهاء المكالمة
	    </button>
	</div>

</div>

<!-- swel temp -->
<div id="swlSlectOrdersTmp" style="display:none;">
	
    <table class="table table-bordered table-striped text-nowrap" style="font-size:13px;">
        <thead>
            <tr>
                <th class="nowrap">#</th>
                <th class="nowrap">امربيع رقم</th>
                <th class="nowrap">خط السير</th>
                <th class="nowrap">ناجح</th>
            </tr>
        </thead>
        <tbody class="ordersSelectedList">
        </tbody>
    </table>
</div>
<!--dlivered-dialog-->
 
<!--/end-dlivered-dialog-->

<!--dirChat-templet-->
<div id="dirchat" style="display:none">
	<div  class="card direct-chat direct-chat-warning" style="margin-top: 30px;margin-bottom: 0px">
		<div class="card-header">
			<h3 class="card-title float-left">التحدث مع خدمة العملاء</h3>
			<div class="card-tools float-right">
				<button type="button" class="btn btn-tool" data-toggle="tooltip" title="Contacts" data-widget="chat-pane-toggle">
					<i class="fas fa-comments"></i>
				</button>
				
				<span title="3 New Messages" class="countmsg badge badge-info">3</span>
			</div>
	    </div>
	    
		<div class="card-body">
			<div id="msgdirscrol" class="direct-chat-messages overflow-auto msgdir " style="height:400px">
				
			</div>
			<div class="direct-chat-contacts" style="height:auto">
			    <ul class="contacts-list m-0 p-0">
			        
			    </ul>
			</div>
		</div>

		<div class="card-footer">
			<button id ="btn-scroll-new-msg" class="btn-scroll-new-msg"><i class="fa-sharp fa-solid fa-comment"></i></i></button>
			<form id="msgSend" class="msgForm" action="#" method="post">
				<div class="input-group">
					<input type="text" name="message" placeholder="Type Message ..." class="form-control inmsgtext">
					<span class="input-group-append">
						<button id="btnOSsendMSG" type="button" class="btn btn-primary ossmsg">Send</button>
					</span>
				</div>
			</form>
		</div>

	</div>
</div>
<!--to Message-Templet-->
<div id="msgtemp1" style="display:none">
	<div class="direct-chat-msg right " style="width:95%">
		<div class="direct-chat-infos clearfix" style="margin-bottom:-5px">
			<span class="direct-chat-name float-left msguser1">Sarah Bullock</span>
			<span class="direct-chat-timestamp float-right msgtime1">23 Jan 2:05 pm</span>
		</div>
		<div class="direct-chat-text text-left bg-primary ml-0 ">
			<small class="msgtext1" style="text-size:8px">
			You better believe it!
			</small>
		</div>
		<div class="direct-chat-infos clearfix">
		    <span class="direct-chat-timestamp float-right msgraed"></span>
		    <span class="direct-chat-timestamp float-left msgug"></span>
		</div>
	</div>
</div>
<!--from Message-Templet-->
<div id="msgtemp2" style="display:none">
	<div class="direct-chat-msg float-right mr-0" style="width:95%">
		<div class="direct-chat-infos clearfix" style="margin-bottom:-5px">
			<span id="msguser2" class="direct-chat-name float-right msguser2">Alexander Pierce</span>
			<span id ="msgtime2" class="direct-chat-timestamp float-left p-0 msgtime2">23 Jan 2:00 pm</span>
		</div>
		<div class="direct-chat-text text-right bg-info mr-0">
		    <small class="msgtext2" style="text-size:10px">
			Is this template really for free? That's unbelievable!
		    </small>
		</div>
		
	</div>
</div>
<!--/end-dirChat-templet-->

<!-- Contacts are loaded here -->
<ul id="contacts-list-temp" class="contacts-list-temp" style="display:none">
    <li class='selectcontact'>
            <img class="contacts-list-img" src="../../img/user1-128x128.jpg">
            <div class="contacts-list-info">
                <span class="contacts-list-name"></span>
                <span class="contacts-list-msg ">How have you been? I was...</span>
            </div>
        <!-- /.contacts-list-info -->
    </li>
    <!-- End Contact Item -->
</ul>

<!-- orderViewProducts-->
<table id="rowvop" style="display:none">
<tr class="item itemorder bg-light">
    <td class="text-nowrap text-left">
        <div class=" tdrest tdproName"></div>
        <a class="tdrest text-info tdproOption"></a>
    </td>
    <td class="tdrest text-center tdproQty"></td>
    <td class="tdrest col-sm-1 text-center tdproPrice"></td>
    <td class="tdrest text-center tdproDis"></td>
    <td class="tdrest col-sm-1 text-center tdproTotal"></td>
    <td class="tdrest col-sm-1 text-center tdstore"></td>
    <td class="tdrest col-sm-1 text-center hidecol text-warning tdprorejactedQty"></td>
    <td class="tdrest col-sm-1 text-center hidecol text-success tdprodeliveredQty"></td>
    <td class="tdrest col-sm-1 text-center hidecol text-danger tdproreturnedQty"></td>
    
</tr>
</table>

<!-- order-View-Data-temp-->
<div id="ovdt" style="display:none">
    <div class="card-body pt-3 pl-3" style="display: block;">
        <div class="invoice p-3 mb-3">
            <!-- title row -->
            <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i><span id="orderid"></span>
                    <small id="orderdate" class="float-right"></small>
                  </h4>
                </div>
                <!-- /.col -->
              </div>
            <!-- info row -->
            <div class="row invoice-info">
                <div class="col-sm-6 invoice-col">
                  <address>
                    <a id="obyuname"></a>
                    <br>
                    <a id="odate"></a>
                    <br>
                    <a id="oshipdate"></a>
                    <br>
                    <a id="ostatuse" class="text-danger"></a>
                    <br>
                    <a id="oexists" class="text-danger"></a>
                    </address>
                </div>
                <div class="col-sm-6 invoice-col">
                  <address>
                    <a id="ouname"></a>
                    <br>
                    <a id="ouphone"></a>
                    <br>
                    <a id="ouemail"></a>
                    <br>
                    <a id="ouaddress"></a>
                    <br>
                    <a id="oucomm"><strong> ملاحظات : </strong>. . . . . . . . . . . . . . . . . . . . . . . . .</a>
                  </address>
                </div>
                <!-- /.col -->
            </div>
            
           	<!--Sales order product display template-->
           	<div class="row border">
                <table class="table table-hover table-responsive table-bordered table-striped w-100 col-sm-12 p-0 m-0 ">
                    <thead class="bg-secondary text-center">
                        <tr>
                            <th class="col">اسم المنتج</th>
                            <th class="col">الكمية</th>
                            <th class="col">السعر</th>
                            <th class="col">الخصم</th>
                            <th class="col">الاجمالى</th>
                            <th class="bg-info col">المخزون الحالى</th>
                            <th class="bg-primary col hidecol">مرفوض</th>
                            <th class="bg-primary col hidecol">تسليم</th>
                            <th class="bg-primary col hidecol">مرتجع</th>
                            
                        </tr>
                    </thead>
                    <tbody class="bg-light4 productOrderView">
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="row bg-primary9 border">
                <div class="col-sm-6">
                </div>
                <!--subtotal-->
                <div class="col-sm-6">
                    <p class="text-left pt-2"><b>ملخص الحساب</b></p>
                    <div class="bg-light">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th class="w-50">قيمة الفاتورة:</th>
                                    <td colspan="2" class="te suptotal">100</td>
                                </tr>
                                <tr>
                                    <th>قيمة الشحن:</th>
                                    <td colspan="2" class="shipval">50</td>
                                </tr>
                                <tr>
                                    <th>اجمالى:</th>
                                    <td colspan="2" class="total">150</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <hr>
                <!--actionOrder-->
                <div class="row border border-primary col-sm-12 p-0 m-0">
                    <div class="text-center mb-0 badge-gray5 w-100"><b>سجل النظام</b></div>
                    <div class="text-nowrap border badge-gray2 m-0 pt-3 border-light bg-ligh text-center overflow-auto w-100" style="height:auto;">
                        <ul class="actionOrderloop ">
                            
                        </ul>
                    </div>
                </div><!--/actionOrder-->
            </div>
            <!--/Sales order product display template-->
        </div>
    </div>
</div>

<!--filter option-->
<div id="filterhtml" style="display:none">
    <div class="border mb-1" style="padding:10px 5px 10px 5px; margin:5px 5px 5px 5px">
        <div class="row ">
            <?php 
            //نوع الامر -----------------------
           
    		if($perm->PageType==1){
    		?>
    		<div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-id-card" aria-hidden="true"></i></span>
                </div>
                <select id="fiorderType" name="orderType" class="form-control select2ot fil">ا</select>
            </div>
            <?php
    		} 
    		// نوع الشحن -------------------------
    		if($perm->PageType!=3 && $perm->PageType!=5 && $perm->PageType!=6){
    		?>
            <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-truck" aria-hidden="true"></i></span>
                </div>
                <select id="fishippType" name="shippingType" class="form-control select2sht fil"></select>
            </div>
    		<?php
    		}
    		// خط السير  -------------------------
    		if($perm->PageType!=5 && $perm->PageType!=6){
    		?>
            <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-truck" aria-hidden="true"></i></span>
                </div>
                <select id="fizones" name="zones" class="form-control select2zon fil"></select>
            </div>
            <?php
    		} 
    		//نوع الشاشه المعروضه -------------------
    		if((isset($perm->PageType) && $perm->PageType==1 && isset($_GET['ex']) && $_GET['ex'] ==0) || (isset($perm->PageType) && $perm->PageType!=1 && $perm->PageType!=3 && $perm->PageType!=5 && $perm->PageType!=6)){
    		?>
           <!-- <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-cog" aria-hidden="true"></i></span>
                </div>
                <select id="fiexists" name="exists" class="form-control select2ex fil"></select>
            </div>-->
    		<?php
    		}
    		//حالة امر البيع -------------------
    		if($perm->PageType!=3){
    		?>
            <!--<div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-tags" aria-hidden="true"></i></span>
                </div>
                <select id="fistatuse" name="statuse" class="form-control select2st fil"></select>
            </div>-->
    		<?php
    		}
    		//تصنيف المنتجات-------------------
    		?>
            <!--<div class="input-group col-12 mb-3" style="display:none">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-filter" aria-hidden="true"></i></span>
                </div>
                <select id="fiproCat" name="category" class="form-control select2proc ficat "></select>
            </div>-->
    		<?php
    		//المنتجات-------------------
    		?>
            <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                </div>
                <select id="fiproducts" name="products" class="form-control product select2pro fil" multiple></select>
            </div>
    		
           <!-- <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-calendar-plus" aria-hidden="true"></i></span>
                </div>
                <select id="fidateType" name="dateType" class="form-control select2dt dateType fil"placeholder="اختار نوع التاريخ">
                    <option value="" selected="">إختار نوع التاريخ</option>
                    <option value="1">تاريخ الانشاء</option>
                    <option value="2">تاريخ الشحن</option>
                    <option value="4">تاريخ الخروج</option
                    <?php if(isset($_GET['ex']) && ($_GET['ex'] == 0 || $_GET['ex'] > 4)){ ?>
                    <option value="3">تاريخ التسليم</option>
                    <?php }?>
                </select>
            </div>
            <div class="input-group col-12 mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <i class="fa fa-calendar" aria-hidden="true"></i>
                    </span>
                </div>
                <input id="dateRange" name="dateRange" autocomplete="off" type="text" class="form-control fil fild" placeholder="تاريخ البداء - تاريخ الانتهاء">
            </div>-->
            
        </div>
    </div>
</div> 

<!--filter option-->
<div id="response_action" class="containerload">
 <!--/filter view-->
    <div id="fiinfo" class="callout callout-danger bg-light w-100" style="display:none">
        <h6>تم تصفية الاوامر بنٱ على الخيارات التى حددتها!</h6>
        <hr>
        <p class="fiinfo"></p>
    </div>
<?php
if($_GET['Go'] === 'OrdersCustody' || $_GET['Go'] === 'ListOrdersDelivery'){
?>
    
    <div class="card card-secondary DCU zindexselect2">  
        <?php
        	if($_GET['Go'] === 'OrdersCustody'){
		?>
        	<div class="card-header">
        		<div class="row">
        			<div class="col-6 pt-2">
        				<h6>عرض العهدة الخاصة بـ »</h6>
        			</div>
	        		<select class="col-6 form-control-border select2" id="SelectUserDelivery" style="width:50%;">
						
					</select>
				</div>
        	</div>
		<?php
        	}
        ?>
        	<div class="card-body custodyTotal bg-secondary pt-1 pb-1">
				<div class="row">
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-primary">
							<h5 class="description-header cut"></h5>
							<span class="description-text">إجمالى العهدة</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-info">
							<h5 class="description-header cu2"></h5>
							<span class="description-text">فى انتظار التسليم</span>
						</div>
					
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-success">
							<h5 class="description-header cu3_4"></h5>
							<span class="description-text">فى انتظار التسوية</span>
						</div>
					
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-danger">
							<h5 class="description-header cutqty"></h5>
							<span class="description-text">اجمالى القطع المرتجع</span>
						</div>
					
					</div>
					
				</div>
        </div>
    </div>
<?php
}
?>
    <!--view order data-->
    <div class="card DOV" style="display:none;">   
        
    </div>

    <!--list order-->
    <div class="card DOL">   
    <?php
       if(isset($_GET['Go']) && $_GET['Go']  === 'OrdersCustody'){
	?>
    	<div class="card-header p-2">
			<ul id="Olhbtn" class="nav nav-pills">
				<li class="nav-item"><a id="OLAll"			   class="OLF nav-link" href="#All" 			 data-toggle="tab">عرض الكل</a></li>
				<li class="nav-item"><a id="OLUnderDelivery"   class="OLF nav-link" href="#Under-delivery"   data-toggle="tab">قيد التسليم</a></li>
				<li class="nav-item"><a id="OLDelivered"       class="OLF nav-link"	href="#Delivered"		 data-toggle="tab">تم التسليم</a></li>
				<li class="nav-item"><a id="OLUnderSettlement" class="OLF nav-link" href="#Under-settlement" data-toggle="tab">قيد التسويه</a></li>
			</ul>
		</div>
	<?php 
       }
       if(isset($_GET['Go']) && $_GET['Go']  === 'ShippingConfirmation'){
    ?>
    	<div class="card-header p-2">
			<ul id="Olhbtn2" class="nav nav-pills">
				<li class="nav-item"><a id="UnderConfirmation"	class="OLF nav-link active" href="#UnderConfirmation" 	data-toggle="tab">قيد التأكيد</a></li>
				<!--<li class="nav-item"><a id="waitingResponse"    class="OLF nav-link" href="#waitingResponse"	data-toggle="tab">فى انتظار الرد</a></li>-->
				<li class="nav-item"><a id="finished"       	class="OLF nav-link" href="#finished"   	data-toggle="tab">منتهى</a></li>
			</ul>
		</div>
    <?php
       }
    ?>
        <div class="card-body table-wrapper  p-4" style="display: block;">
        	<div class="progress-container">
			    <div id="progressBar" class="progress-bar">0%</div>
			</div>
			<div class="table-overlay1 m-0 p-0" style="margin-top:160px ! important">
		        <span style="color: white; font-size: 1.2em;">جاري التحميل...</span>
		    </div>
    
	    	<table id="tbl" class="tbl table table-hover table-bordered table-striped m-0 w-100 text-table " style="width:100%;">
			    <thead>
				    <tr class="">
				        <th class="text-nowrap">رقم الطلب</th>
				        <th class="text-nowrap">حالة الطلب</th>
				        <th class="text-nowrap">معلومات العميل</th>
				        <th class="text-nowrap">التواريخ</th>
				        <th class="text-nowrap">تحرير</th>
				    </tr>
				    <tr id="searchRow">
				        <th>
				        	<div class="input-group input-group-sm mb-0" style="direction:rtl;">
				        		<input type="text" class="form-control column-search" id="searchOrderID" placeholder="بحث برقم الطلب">
				        	</div>
				        </th>
				        <th>
				        	<div class="input-group input-group-sm mb-0" style="direction:rtl;">
					            <select class="form-control column-search" id="searchStatus">
					                	
					            </select>
				        	</div>
				        	<div id="missingRejectionReasonFilter" class="text-right mt-1" style="display:none; white-space:nowrap;">
				        	    <label class="mb-0" style="font-weight:normal; cursor:pointer;">
				        	        <input type="checkbox" id="filterMissingRejectionReason"> طلبات بدون أسباب رفض
				        	    </label>
				        	</div>
				        </th>
				        <th>
				        	<div class="input-group input-group-sm mb-0" style="direction:rtl;">
				        		<input type="text" class="form-control column-search" id="searchClient" placeholder="بحث بالاسم أو الهاتف"></th>
				        	</div>
				        </th>
				        <th>
							<div class="input-group input-group-sm dropdown-container mb-0" style="direction:rtl; min-width: 210px;">
							    <div class="input-group-prepend dropup">
							        <button id="customDropdownButton" type="button" class="btn btn-secondary">
							            <i id="selectedIcon" class="fas fa-calendar-plus"></i> <!-- الأيقونة الافتراضية -->
							        </button>
							        <ul class="custom-dropdown-menu" style="display: none;">
							            <li class="custom-dropdown-item" onclick="changeIcon('fas fa-calendar-plus','الانشاء')">
							                <i class="fas fa-calendar-plus"></i> تاريخ الإنشاء
							            </li>
							            <li class="dropdown-divider"></li>
							            <li class="custom-dropdown-item" onclick="changeIcon('fas fa-truck-fast','الشحن')">
							                <i class="fas fa-truck-fast"></i> تاريخ الشحن
							            </li>
							            <li class="dropdown-divider"></li>
							            <li class="custom-dropdown-item" onclick="changeIcon('fas fa-right-from-bracket','الخروج')">
							                <i class="fas fa-right-from-bracket"></i> تاريخ الخروج
							            </li>
							            <li class="dropdown-divider"></li>
							            <li class="custom-dropdown-item" onclick="changeIcon('fas fa-handshake','التسليم')">
							                <i class="fas fa-handshake"></i> تاريخ التسليم
							            </li>
							        </ul>
							    </div>
							    <input type="text" readonly id="daterange" class="form-control" placeholder="اختر التاريخ">
							    <div class="input-group-append">
							        <span class="input-group-text clear-daterange">&times;</span>
							    </div>
							</div>
			                
				        </th>
				        <th></th>
				    </tr>
				</thead>		
			    <tbody class="tbody-relative">
			        <!-- سيتم ملء البيانات هنا -->
			    </tbody>
			</table>
			
			
        </div>
        

    </div>
    <!--qrscan-templet-->
    <div class="DIRC" style="display:none;">
        <div class="card-body p-4" style="display: block;">
            <div id="qrreader" class="w-100 h-50 DIRC" style="height:200px;display:none;"></div>
            <div>
            	<button class="btn btn-primary btn-lg btn-block btnStratBarcode notscan" style="display:none;">بداء مسح البار كود</button>
            </div>
            <div id="qrreader-results" class="mt-3">
            	
            </div>
        </div>
    </div>
    <!--orderdelivery  xxxxxxxxxxxxxxxxxxx-->
    <div class="DOD" style="display:none;">
        <div class="card-body p-4" style="display: block;">
            <div id="qrreader" class="w-100 h-50 DIRC" style="height:200px;display:none;"></div>
            <div>
            	<button class="btn btn-primary btn-lg btn-block btnStratBarcode notscan" style="display:none;">بداء مسح البار كود</button>
            </div>
            <div id="qrreader-results">
            	
            </div>
        </div> 
    </div>
</div>



<script>
							
$(document).ready(function() {
	// تهيئة الـ Date Range Picker
							    $('#daterange').daterangepicker({
							        autoUpdateInput: false,
							        locale: {
							            format: 'YYYY-MM-DD',
							            separator: ' - ',
							            applyLabel: "تأكيد",
							            cancelLabel: "إلغاء",
							            fromLabel: "من",
							            toLabel: "إلى",
							            customRangeLabel: "تاريخ مخصص",
							            weekLabel: "أسبوع",
							            daysOfWeek: ["أحد", "إثنين", "ثلاثاء", "أربعاء", "خميس", "جمعة", "سبت"],
							            monthNames: ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"],
							            firstDay: 6
							        },
							        minDate: moment().subtract(1, 'years'),
							        maxDate: moment().add(1, 'years'),
							        startDate: moment().startOf('month'),
							        endDate: moment().endOf('month'),
							        opens: "left",
							        drops: "down",
							        showDropdowns: true,
							        showWeekNumbers: true,
							        timePicker: false,
							        ranges: {
							            "اليوم": [moment(), moment()],
							            "أمس": [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
							            "آخر 7 أيام": [moment().subtract(6, 'days'), moment()],
							            "آخر 30 يوم": [moment().subtract(29, 'days'), moment()],
							            "هذا الشهر": [moment().startOf('month'), moment().endOf('month')],
							            "الشهر الماضي": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
							            
							        }
							    });
							
							    // عند اختيار تاريخ، يتم تحديث الإدخال وإظهار زر الإلغاء
							    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
							        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
							        $('.clear-daterange').show();
							    });
							
							    // عند الضغط على X يتم مسح الحقل
							    $('.clear-daterange').on('click', function() {
							        $('#daterange').val('').trigger('change'); // مسح القيمة
							        $('.clear-daterange').hide(); // إخفاء X
							    });
							
							    // إخفاء زر الإلغاء إذا كان الحقل فارغًا عند البداية
							    if (!$('#daterange').val()) {
							        $('.clear-daterange').hide();
							    }
							  



    let observerInterval; // لمراقبة حركة الزر أثناء فتح القائمة

    $('#customDropdownButton').on('click', function (e) {
        e.stopPropagation();
        var dropdownMenu = $(this).next('.custom-dropdown-menu');

        if (!dropdownMenu.is(':visible')) {
            $('.custom-dropdown-menu').hide(); // إغلاق أي قائمة مفتوحة
            updateDropdownPosition($(this), dropdownMenu);
            dropdownMenu.show();

            // تحديث الموضع عند التمرير أو تغيير الحجم
            $(window).on('scroll resize', function () {
                if (dropdownMenu.is(':visible')) {
                    updateDropdownPosition($('#customDropdownButton'), dropdownMenu);
                }
            });

            // مراقبة تغييرات موضع الزر باستمرار
            observerInterval = setInterval(function () {
                if (dropdownMenu.is(':visible')) {
                    updateDropdownPosition($('#customDropdownButton'), dropdownMenu);
                }
            }, 50); // تحديث الموضع كل 50 مللي ثانية

        } else {
            dropdownMenu.hide();
            $(window).off('scroll resize'); // تعطيل التحديث عند الإغلاق
            clearInterval(observerInterval); // إيقاف مراقبة الموضع
        }
    });

    // إغلاق القائمة عند النقر خارجها
    $(document).on('click', function () {
        $('.custom-dropdown-menu').hide();
        $(window).off('scroll resize'); // تعطيل التحديث عند الإغلاق
        clearInterval(observerInterval); // إيقاف مراقبة الموضع
    });

    // تحديث موضع القائمة بالنسبة للزر
    function updateDropdownPosition(button, menu) {
        var rect = button[0].getBoundingClientRect();
        menu.css({
            position: 'fixed',
            top: rect.bottom, // يظهر القائمة أسفل الزر
            left: rect.left,
            zIndex: 5
        });
    }

	
});
	// تغيير الأيقونة عند اختيار نوع التاريخ
	function changeIcon(iconClass, labelText) {
		$('#selectedIcon').attr('class', iconClass); // تغيير الأيقونة
		$('#daterange').attr('placeholder', "قم بإختيار تاريخ " + labelText); // تحديث الـ Placeholder
		
		$('.clear-daterange').click();		

	}
	
</script>