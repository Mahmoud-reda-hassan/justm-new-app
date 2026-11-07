<?php
/*
<br>
المطلوب :-
<br>
1- تحصيل كل اومر البيع او الاوامر المحدده الموجوده فى الحاله 3,4
<br>
2- تحصيل قيمة امر البيع وقيمة الشحن فى فى حالة قيد الشحن مع تغير حالة الامر الى تم التسليم
<br>
3- تصفية الاوامر فى حالة ايقاف التسليم يتم دفع ثمن المنتجات دون تحصيل قيمة الشحن مع حذف الامر من العهده على المندوب 
<br>
4- تحصيل قيمة الامر + الشحن فيى حالة مرفوض او جزئى ولم يتم تصفيتة مخزنيا مع تغير حالة الامر اى تم التسليم
<br>
<br>
التصميم :-

اختيار المندوب ثم عرض الاوامر ثم اختيار الاوامر ثم فتح فاتورة بيع بإجمالى المبلغ لحساب الاوامر
*/
?>
    	<div class="card card-secondary DCU zindexselect2">  
        	<div class="card-header ">
        		<div class="row ">
        			<div class="col-6 pt-2"><h6>عرض العهدة الخاصة بـ »</h2></div>
	        		<select class="col-6 form-control-border select2" id="SelectUserDelivery" style="width:50%;">
						
					</select>
				</div>
        	</div>
        	<div class="card-body custodyTotal bg-secondary pt-1 pb-1">
				<div class="row">
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-primary">
							<h5 class="description-header cut">0</h5>
							<span class="description-text">إجمالى العهدة</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-info">
							<h5 class="description-header cu2">0</h5>
							<span class="description-text">قيد التسليم</span>
						</div>
					
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-danger">
							<h5 class="description-header cu3_4">0</h5>
							<span class="description-text">قيد التسويه المخزنية</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-light">
						<div class="description-block border-right text-success">
							<h5 class="description-header cutqty">0</h5>
							<span class="description-text">قيد التسويه المالية</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-primary">
						<div class="description-block border-right text-light">
							<h5 class="description-header selse">0</h5>
							<span class="description-text">اجمالى الايرادات</span>
						</div>
					</div>
					<div class="col-sm-3 col-6 bg-primary">
						<div class="description-block border-right text-light">
							<h5 class="description-header ship">0</h5>
							<span class="description-text">اجمالى المصروفات</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-success">
						<div class="description-block border-right text-light">
							<h5 class="description-header cash">0</h5>
							<span class="description-text">الرصيدالحالى بـ الخزينه</span>
						</div>
					</div>
					
					<div class="col-sm-3 col-6 bg-info">
						<div class="description-block border-right text-light">
							<h5 class="description-header sup">0</h5>
							<span class="description-text">المديونية المفتوحة</span>
						</div>
					</div>
					
        		</div>
    		</div>
    	</div>

<div class="card card-outline card-secondary mb-3" id="customerCollectionMethodPanel">
  <div class="card-body py-2">
    <div class="form-row align-items-end">
      <div class="form-group col-md-4 mb-1">
        <label for="rcPaymentMethod">طريقة تحصيل المندوب من العميل</label>
        <select id="rcPaymentMethod" class="form-control"><option value="">اختر طريقة الدفع</option></select>
      </div>
      <div class="col-md-8 small text-muted mb-2">تسجيل التحصيل يثبت المبلغ في عهدة المندوب. استلام الشركة للمبلغ يتم من لوحة التسوية الجديدة أدناه.</div>
    </div>
  </div>
</div>

<!-- المرحلة 14: تسوية عهدة التحصيلات الجديدة؛ لا تستبدل المسار القديم قبل الاختبار -->
<div class="card card-outline card-primary" id="deliverySettlementPanel">
  <div class="card-header"><h3 class="card-title">تسوية تحصيلات المندوب مع الشركة (النظام المحاسبي الجديد)</h3></div>
  <div class="card-body">
    <div class="alert alert-info py-2">هذه الشاشة لتسوية مبالغ سبق أن حصّلها المندوب من العملاء وسُجلت في النظام الجديد. لا تسجل تحصيل العميل من هنا.</div>
    <div class="form-row align-items-end">
      <div class="form-group col-md-4">
        <label for="dsPolicyMode">سياسة العمولة العامة</label>
        <select id="dsPolicyMode" class="form-control">
          <option value="DEDUCT_FROM_CUSTODY">خصم العمولة من العهدة</option>
          <option value="SEPARATE">صرف العمولة بشكل منفصل</option>
          <option value="USER_CHOICE">السماح بالاختيار عند كل تسوية</option>
        </select>
      </div>
      <div class="form-group col-md-2"><button type="button" id="dsSavePolicy" class="btn btn-outline-primary">حفظ السياسة</button></div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="dsDelivery">المندوب</label>
        <select id="dsDelivery" class="form-control select2" style="width:100%"><option value="">اختر المندوب</option></select>
      </div>
      <div class="form-group col-md-4">
        <label for="dsPaymentMethod">طريقة استلام المبلغ</label>
        <select id="dsPaymentMethod" class="form-control"><option value="">اختر طريقة الدفع</option></select>
      </div>
      <div class="form-group col-md-4" id="dsModeWrap" style="display:none">
        <label for="dsMode">تسوية العمولة</label>
        <select id="dsMode" class="form-control">
          <option value="DEDUCT_FROM_CUSTODY">خصم العمولة من العهدة</option>
          <option value="SEPARATE">صرف العمولة بشكل منفصل</option>
        </select>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered table-striped" id="dsOrdersTable">
        <thead><tr><th><input type="checkbox" id="dsSelectAll"></th><th>رقم الأمر</th><th>التاريخ</th><th>المبلغ المفتوح</th><th>العمولة المفتوحة</th></tr></thead>
        <tbody><tr><td colspan="5" class="text-center text-muted">اختر المندوب لعرض الأوامر المفتوحة</td></tr></tbody>
        <tfoot><tr><th colspan="3" class="text-right">إجمالي الأوامر المحددة</th><th id="dsSelectedTotal">0.00</th><th id="dsSelectedCommission">0.00</th></tr></tfoot>
      </table>
    </div>
    <hr>
    <div class="form-row align-items-end">
      <div class="form-group col-md-3"><label for="dsAmount">مبلغ الاستلام (توزيع FIFO)</label><input id="dsAmount" type="number" min="0.01" step="0.01" class="form-control" placeholder="اتركه فارغًا لتسوية المحدد بالكامل"></div>
      <div class="form-group col-md-3"><label for="dsCashPreview">المبلغ النقدي المتوقع</label><input id="dsCashPreview" class="form-control" readonly value="0.00"></div>
      <div class="form-group col-md-6"><label for="dsNote">ملاحظات</label><input id="dsNote" class="form-control" maxlength="500" placeholder="اختياري"></div>
    </div>
    <div id="dsPreviewResult" class="small text-muted mb-2">حدد أوامر أو أدخل مبلغًا لمعاينة التوزيع.</div>
    <button type="button" id="dsRefresh" class="btn btn-outline-secondary"><i class="fas fa-sync"></i> تحديث الأوامر</button>
    <button type="button" id="dsPreview" class="btn btn-info"><i class="fas fa-calculator"></i> معاينة التوزيع</button>
    <button type="button" id="dsSettle" class="btn btn-primary" disabled><i class="fas fa-hand-holding-usd"></i> تسجيل استلام العهدة</button>
    <small class="d-block text-muted mt-2">إذا كانت التسوية مفروضة من إعدادات النظام فلن يمكن تغييرها من هذه الشاشة. صرف العمولة المنفصل يتم من إجراء مستقل بعد اعتماد الحسابات.</small>
  </div>
</div>

		<div class="card DOV" style="display:none;">   
    	</div>
    	<div class="card DOL">
		    	<div class="card-header p-2">
					<ul id="Olhbtn" class="nav nav-pills">
						<li class="nav-item"><a id="OLAll"			   class="OLF nav-link" href="#All" 			 data-toggle="tab">الكل</a></li>
						<li class="nav-item"><a id="OLUnderDelivery"   class="OLF nav-link" href="#Under-delivery"   data-toggle="tab">قيد التسليم</a></li>
						<li class="nav-item"><a id="OLDelivered"       class="OLF nav-link"	href="#Delivered"		 data-toggle="tab">قيد المخزن</a></li>
						<li class="nav-item"><a id="OLUnderSettlement" class="OLF nav-link" href="#Under-settlement" data-toggle="tab">قيد التسوية</a></li>
					</ul>
				</div>
		        <div class="card-body p-4" style="display: block;">
			    	<table id="tbl" class="table table-hover table-bordered table-striped text-nowrap w-100 col-sm-12 p-0 m-0">
					    <thead>
					        <tr>
					            <th>عرض</th>
					            <th>رقم الأمر</th>
					            <th>الحالة</th>
					            <th>بيانات المستخدمين</th>
					            <th>بيانات المندوب</th>
					            <th>تاريخ الخروج</th>
					            <th>إجمالي المبلغ</th>
					            <th>تسجيل تحصيل العميل</th>
					        </tr>
					        <tr>
					            <th></th>
					            <th>
					            	<input id="FiOrderNumber" type="text" class="form-control form-control-sm column-filter"  placeholder="بحث..." />
					            </th>
					            <th>
					            	<select  id="FiStatus" class="form-control form-control-sm column-filter" style="width:105px">
					                	<option value="">الكل</option>
					                	<option value="2">جارى الشحن</option>
						                <option value="3">تم التسليم</option>
						                <option value="4">تسليم جزئى</option>
					                	<option value="5">مرفوض</option>
					            	</select>
					            </th>
					            <th>
					            	<input id="FiClient" type="text" class="form-control form-control-sm column-filter" placeholder="بحث..." />
					            </th>
					            <th>
					            	<input id="FiDelivary" type="text" class="form-control form-control-sm column-filter" placeholder="بحث..." />
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
								    <input type="text" readonly id="daterange" class="form-control" placeholder="اختر اختار تاريخ الخروج">
								    <div class="input-group-append">
								        <span class="input-group-text clear-daterange">&times;</span>
								    </div>
								</div>

					            </th>
					            <th>
					            	<input id="FiPrice" type="text" class="form-control form-control-sm column-filter" placeholder="بحث..." />
					            </th>
					            <th>
					            	<button id="btnSelectAllPayment" type="button" data-id="all" class="btn btn-app-row btn-xs btnSelectAllPayment h-80 f-5 m-1 bg-1info" disabled="true">
					            		<i class="fa-regular fa-square"></i>
					            	</button>
					            	<button id="btnCancelAllPayment" type="button" data-id="all" class="btn btn-app-row btn-xs btnCancelAllPayment h-80 f-5 m-1 bg-1info" style="display:none;">
					            		<i class="fa-solid fa-square-check"></i>
					            	</button>
					            </th>
					        </tr>
					    </thead>
					    <tbody>
					        <!-- البيانات ستُعرض هنا بواسطة DataTables -->
					    </tbody>
					</table>
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
			minDate: moment().subtract(2, 'years'),
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
				"الشهر الماضي": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
			},
			//autoUpdateInput: true,
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
		
		$('.clear-daterange').on('click', function() {
		    $('#daterange').val('');
		    $('.clear-daterange').hide(); 
		    table.column(5).search('').draw(); // العمود رقم 5 هو تاريخ الخروج
		});
	
		// إخفاء زر الإلغاء إذا كان الحقل فارغًا عند البداية
		if (!$('#daterange').val()) {
			$('.clear-daterange').hide();
		}
								  
	}); 
	</script>
	    
    
    
    
    
    
