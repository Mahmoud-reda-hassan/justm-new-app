<?php
session_start();

?>


    <!-- Filters -->
    <div class="card shadow rounded mb-3">

        <!--<div class="card-header">
            <h5 class="mb-0">تقرير حركة المخزون اليومية</h5>
        </div>-->

        <div class="card-body ">

            <div class="row cpselect2">

                <div class="col-md-4 zindexselect2">

                    <label>المخزن</label>

                    <select id="store_id"
                            class="form-control">
                    </select>

                </div>

                <div class="col-md-4">

                    <label>الفترة</label>

                    <input type="text"
                           id="date_range"
                           class="form-control">

                </div>

                <div class="col-md-2">

                    <label>&nbsp;</label>

                    <button id="btnLoadReport"
                            class="btn btn-primary btn-block">
                        عرض التقرير
                    </button>

                </div>

                <div class="col-md-2">

                    <label>&nbsp;</label>

                    <button id="btnBack"
                            class="btn btn-secondary btn-block"
                            style="display:none;">
                        رجوع
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- SUMMARY REPORT -->
    <div id="summarySection">

        <div class="card shadow rounded">

            <div class="card-header">
               
                <ul id="Olhbtn" class="nav nav-pills">
						<li class="nav-item"><a id="defaultReport"			   class="OLF nav-link" href="#All" 			 data-toggle="tab">يومية حركة المخزون</a></li>
						<li class="nav-item"><a id="productMovmentReport"      class="OLF nav-link" href="#Under-delivery"   data-toggle="tab">حركة المنتجات</a></li>
				</ul>
            </div>

            <div class="card-body table-responsive p-2">
				<div class="mb-2" id="dynamicFilters1"></div>
				
                <table id="tblSummary" class="table table-bordered table-hover table-striped text-nowarp">
                    <thead>
                    <tr>
            			<th rowspan="2">عرض</th>
                        <th class="text-nowarp" rowspan="2">التاريخ</th>
                        <th class="badge-green10 text-nowarp" rowspan="2">رصيد الفتح</th>

                        <th class="badge-green10 text-center" colspan="7">الوارد</th>

                        <th class="badge-red10 text-center" colspan="5">المنصرف</th>

                        <th class="badge-red10 text-nowarp" rowspan="2">رصيد الإغلاق</th>
                    </tr>
                    <tr>
                        <th class="badge-green5  text-center text-nowarp">استلام مورد</th>
                        <th class="badge-green5  text-center text-nowarp">استرداد مندوب</th>
                        <th class="badge-green5  text-center text-nowarp"> راجع مندوب</th>
                        <th class="badge-green5  text-center text-nowarp"> راجع استبدال</th>
                        <th class="badge-green5  text-center text-nowarp"> راجع بيع مباشر</th>
                        <th class="badge-green5  text-center text-nowarp">تسوية وارد</th>
						<th class="badge-green5  text-center text-nowarp">تحويل وارد</th>

                        <th class="badge-red5  text-center text-nowarp">مرتجع مورد</th>
                        <th class="badge-red5  text-center text-nowarp">عهدة مندوب</th>
                        <th class="badge-red5  text-center text-nowarp">بيع مباشر</th>
                        <th class="badge-red5  text-center text-nowarp">تسوية صادر</th>
						<th class="badge-red5  text-center text-nowarp">تحويل صادر</th>
                    </tr>
                    </thead>
                </table>
                
                <table id="tblProDetails" class="table table-bordered table-hover table-striped table-sm w-100">
				    <thead>
				        <tr>
				        	
				            <th>رقم الطلب</th>
				            <th>التاريخ</th>
				            <th>اسم الشريك</th>
				            <th>الحركة</th>
				            <th>المنتج</th>
				            <th>الوصف</th>
				            <th>الكمية الوارده</th>
				            <th>الكميه المنصرفه</th>
				        </tr>
				    </thead>
					<tfoot>
						<tr>
							<th colspan="6">الإجمالي</th>
							<th></th>
							<th></th>
						</tr>
					</tfoot>
				</table>
				
								
            </div>

        </div>

    </div>

    <!-- DETAILS REPORT -->
    <div id="detailsSection" style="display:none;">

        <div class="card shadow rounded">

            <div class="card-header">

                <h6 class="mb-0" id="selectedDateTitle">
                </h6>

            </div>

            <div class="card-body table-responsive p-2">
                <div class="mb-2" id="dynamicFiltersDetails"></div>

                <table id="tblDetails" class="table table-bordered table-hover table-striped table-sm w-100">
				    <thead>
				        <tr>
				        	<th>عرض</th>
				            <th>رقم الطلب</th>
				            <th>التاريخ</th>
				            <th>اسم الشريك</th>
				            <th>الحركة</th>
				            <th>ملاحظات</th>
				            <th>الكمية الوارده</th>
							<th>الكميه المنصرفه</th>
					    </tr>
				        <!--<tr class="filters">
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						    <th class="resetfilter"></th>
						</tr>-->
				    </thead>
					<tfoot>
					<tr>
						<th colspan="6">الإجمالي</th>
						<th></th>
						<th></th>
					</tr>
					</tfoot>
				</table>
				
            </div>

        </div>
    </div>
	
	
    

<script>

let summaryTable = null;
let detailsTable = null;
let summaryProTable = null;
$(document).ready(function () {
    
/* ==========================
   DATERANGEPICKER
========================== */

 $(async function() {
	    $('#date_range').daterangepicker({
	      	autoUpdateInput: true,
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
	    });
	
	    $('#date_range').on('apply.daterangepicker', function(ev, picker) {
	      $(this).val(picker.startDate.format('YYYY-MM-DD') + ' إلى ' + picker.endDate.format('YYYY-MM-DD'));
	    });
	
	    $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
	      $(this).val('');
	    });
 });

/* ==========================
   SELECT2
========================== */

$('#store_id').select2({
        placeholder: `من فضلك اختار المخزن`,
        allowClear: true,
        language: 'ar', // دعم اللغة
        selectionCssClass: 'form-control',
	    width: '100%',
	    dir: 'rtl',
	    dropdownParent: $('#store_id').parent(),
        ajax: {
            url: '/inc/Api.php?CONTROL=getdropWarehouse&ass=true',
            method: 'GET',
            dataType: 'json',
            delay: 250, // تأخير الطلب لتقليل التحميل
            
            data: function(params) {
                return {
                    query: params.term // مصطلح البحث
                };
            },
            processResults: function(data) {
                // معالجة البيانات وإرجاع النتائج إلى Select2
                return {
                    results: data.map(function(sto) {
                        return {
                            id: sto.ID, // القيمة
                            text: sto.NAME // النص المعروض
                        };
                    })
                };
            },
            cache: true
        }
    });

/* ==========================
   LOAD REPORT
========================== */

$('#btnLoadReport').on('click', function () {

    const storeId = $('#store_id').val();

    if (!storeId) {

        alert('اختر المخزن');

        return;
    }

    const picker = $('#date_range').data('daterangepicker');

    const dateFrom = picker.startDate.format('YYYY-MM-DD');
    const dateTo   = picker.endDate.format('YYYY-MM-DD');

    loadSummaryReport(
        storeId,
        dateFrom,
        dateTo
    );

});

/* ==========================
   SUMMARY DATATABLE
========================== */

function loadSummaryReport(storeId, dateFrom, dateTo) {
	if ($.fn.DataTable.isDataTable('#tblProDetails')) {
		$('#tblProDetails').DataTable().destroy();
	}
	
	if ($.fn.DataTable.isDataTable('#tblSummary')) {
		$('#tblSummary').DataTable().destroy();
	}
	
    //$('#detailsSection').hide();
    //$('#summarySection').show();
    //$('#tblProDetails').hide();
    //$('#tblSummary').hide();
    $('#btnBack').hide();
    
	if ($('#tblSummary').is(':visible')) {
    	//$('#tblSummary').show();
	    summaryTable = $('#tblSummary').DataTable({
			
	        processing: true,
	        serverSide: false,
	        //responsive: true,
	        pageLength: 25,
	
	        ajax: {
	
	            url: '/inc/controller/reports.control.php',
	
	            type: 'POST',
	
	            data: {
					CONTROL: 'inventoryDailyReport', 
	                store_id: storeId,
	                date_from: dateFrom,
	                date_to: dateTo
	
	            },
	
	            dataSrc: ''
	
	        },
	
	        columns: [
			    {data: '', 
		         title: '',
		         className: 'text-center',
		         render: (data, type, row) => {
		              return `
		                  <button id="btnOview" type="button" class="dt-controlerA btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-5">
			    			<i class="fas fa-eye"></i>
			    		  </button>`;
		         }
		        },
	            {data: 'report_date'},
	            {data: 'opening_balance'},
	
	            {data: 'supplier_receiving'}, //وارد مورد
	            {data: 'refund_from_courier'},//استرداد من مندوب
	            {data: 'return_shipp'}, // وارد مرتجعات مندوب
	            {data: 'return_change'}, //وارد مرتجعات استبدال
	            {data: 'return_sales'}, //وارد مرتجعات بيع مباشر
	            {data: 'settlement_in'},//وارد تسوية مخزنيه
				{data: 'transfer_in'},//وارد تحويلات مخزنيه
	            
				{data: 'supplier_return'}, // صرف مرتجع لمورد
	            {data: 'shipping_to_courier'},// صرف لمندوب
	            {data: 'direct_sales'}, //صرف بيع مباشر
	            {data: 'settlement_out'},// صرف تسوية مخزنيه
				{data: 'transfer_out'},// صرف تحويلات مخزنيه
				
	            {data: 'closing_balance'}
	        ],
	        columnDefs: [
			    {
			        targets: [3,4,5,6,7,8,9], // أعمدة الوارد
			        className: 'badge-green5 text-center'
			    },
			    {
			        targets: [9,10,11,12,13,14], // أعمدة المنصرف
			        className: 'badge-red5 text-center'
			    },
			    {
			        targets: [2,15], // أرصدة الفتح والإغلاق
			        className: 'font-weight-bold text-center'
			    },
			    {
			        targets: [2], // أرصدة الفتح 
			        className: 'badge-green10 text-center'
			    },
			    {
			        targets: [15], // أرصدة الإغلاق
			        className: 'badge-red10 text-center'
			    },
			    {
			        targets: [1], // التاريخ
			        className: 'font-weight-bold badge-red10 text-center text-nowrap '
			    }
			],
	
	        order: [[1,'asc']]
	
	    });
	}else if($('#tblProDetails').is(':visible')){
        const filterValues = {};
	    summaryProTable = $('#tblProDetails').DataTable({
	
	        processing: true,
	        serverSide: false,
	        //responsive: true,
	        pageLength: 25,
	
	        ajax: {
	
	            url: '/inc/controller/reports.control.php',
	
	            type: 'POST',
	
	            data: {
					CONTROL: 'inventoryProDailyReport', 
	                store_id: storeId,
	                date_from: dateFrom,
	                date_to: dateTo
	            },
	
	            dataSrc: ''
	
	        },
	
	        columns: [
	            {data: 'ORDER_ID'},
			    {data: 'CREATED_DATE'},
	            {data: 'PARTNER_NAME'}, 
	            {data: 'IM_MOVE_TYPE'},
	            {data: 'PRO_NAME'},
	            {data: 'OPT_JSON'}, 
	            {data: 'QTY_IN'}, 
	            {data: 'QTY_OUT'}
	        ],
	        columnDefs: [
			    {
			        targets: [0,1,2,3,4,5,6,7], // أعمدة الوارد
			        className: ' text-center text-nowrap '
			    },
			    {
			        targets: [6], // أرصدة الفتح والإغلاق
			        className: 'badge-green5 font-weight-bold text-center'
			    },
			    {
			        targets: [7], // أرصدة الفتح 
			        className: 'badge-red5 font-weight-bold text-center'
			    }
			],
	
	        order: [[1,'asc']],
	        // استخدم initComplete بدلاً من drawCallback
		    initComplete: function () {
		            const api = this.api();
		            const allData = api.data().toArray();
		
		            // إذا لم توجد بيانات، نعرض رسالة ونخرج
		            if (!allData.length) {
		                $('#dynamicFilters').html('<div class="alert alert-warning">لا توجد بيانات لعرضها</div>');
		                return;
		            }
		
		            // بناء الفلاتر (مرة واحدة فقط)
		            buildFilters(allData, api);
		   },
		    footerCallback: function (row, data, start, end, display) {
	
				var api = this.api();
	
				function intVal(i) {
					return typeof i === 'string'
						? Number(i.replace(/[^0-9.-]/g, ''))
						: typeof i === 'number'
							? i
							: 0;
				}
	
				// إجمالي العمود قبل الأخير
				var total1 = api.column(6, { search: 'applied' }).data().reduce(function (a, b) {
					return intVal(a) + intVal(b);
				}, 0);
	
				// إجمالي آخر عمود
				var total2 = api.column(7, { search: 'applied' }).data().reduce(function (a, b) {
					return intVal(a) + intVal(b);
				}, 0);
	
				$(api.column(6).footer()).html(total1.toLocaleString());
				$(api.column(7).footer()).html(total2.toLocaleString());
	
			}
		
	    });
	    
	    // دالة بناء الفلاتر
		    function buildFilters(allData, api) {
		        // 1. استخراج أسماء المنتجات الفريدة
		        const proNames = [...new Set(allData.map(row => row.PRO_NAME))];
				// 2. استخراج أنواع الحركه الفريده
		        const movMent = [...new Set(allData.map(row => row.IM_MOVE_TYPE))];
		        // 1. استخراج أسماء الشركاء الفريده
		        const partneNams = [...new Set(allData.map(row => row.PARTNER_NAME))];

		        // 2. تحليل OPT_JSON وجمع المفاتيح والقيم
		        const optKeysMap = new Map();
		        allData.forEach(row => {
		            const opt = row.OPT_JSON || '';
		            const pairs = opt.split('|').map(p => p.trim());
		            pairs.forEach(pair => {
		                if (!pair) return;
		                const parts = pair.split(':');
		                if (parts.length === 2) {
		                    const key = parts[0].trim();
		                    const val = parts[1].trim();
		                    if (!optKeysMap.has(key)) {
		                        optKeysMap.set(key, new Set());
		                    }
		                    optKeysMap.get(key).add(val);
		                }
		            });
		        });
		
				// 3. بناء HTML الفلاتر بتصميم متجانس ومنتظم
				let filtersHtml = `
				    <div class="card shadow-sm mb-3">
				        <div class="card-body">
				            <div class="row  align-items-end">
				            	<!-- فلتر الحركة -->
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">نوع الحركة</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="MOV_NAME">
				                        <option value="">الكل</option>
				                        ${movMent.map(name => `<option value="${name}">${name}</option>`).join('')}
				                    </select>
				                </div>
				                <!-- فلتر الشريك -->
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">الشريك</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="PRT_NAME">
				                        <option value="">الكل</option>
				                        ${partneNams.map(name => `<option value="${name}">${name}</option>`).join('')}
				                    </select>
				                </div>
				                <!-- فلتر المنتج -->
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">المنتج</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="PRO_NAME">
				                        <option value="">الكل</option>
				                        ${proNames.map(name => `<option value="${name}">${name}</option>`).join('')}
				                    </select>
				                </div>
				`;
				
				// إضافة باقي الفلاتر (OPT_JSON)
				optKeysMap.forEach((valuesSet, key) => {
				    const values = Array.from(valuesSet).sort();
				    filtersHtml += `
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">${key}</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="${key}">
				                        <option value="">الكل</option>
				                        ${values.map(v => `<option value="${v}">${v}</option>`).join('')}
				                    </select>
				                </div>
				    `;
				});
				
				filtersHtml += `
				            </div>
				        </div>
				    </div>
				`;
				
		        $('#dynamicFilters1').html(filtersHtml);
		
		        // 4. تنظيف وإضافة الفلتر المخصص الجديد
		        if (customFilter) {
		            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(f => f !== customFilter);
		        }
		
		        function customFilter (settings, data, dataIndex) {
		            const rowData = api.row(dataIndex).data();
		            if (!rowData) return true;
		
		            for (const [filterKey, selectedVal] of Object.entries(filterValues)) {
		                if (!selectedVal) continue;
		
		                if (filterKey === 'PRO_NAME') {
		                    if (rowData.PRO_NAME !== selectedVal) return false;
		                } else if (filterKey === 'MOV_NAME') {
		                    if (rowData.IM_MOVE_TYPE !== selectedVal) return false;
		                } else if (filterKey === 'PRT_NAME') {
		                    if (rowData.PARTNER_NAME !== selectedVal) return false;
		                } else {
		                    const opt = rowData.OPT_JSON || '';
		                    const pairs = opt.split('|').map(p => p.trim());
		                    let found = false;
		                    for (const p of pairs) {
		                        const parts = p.split(':');
		                        if (parts.length === 2) {
		                            const k = parts[0].trim();
		                            const v = parts[1].trim();
		                            if (k === filterKey && v === selectedVal) {
		                                found = true;
		                                break;
		                            }
		                        }
		                    }
		                    if (!found) return false;
		                }
		            }
		            return true;
		        };
		        $.fn.dataTable.ext.search.push(customFilter);
		
		        // 5. ربط أحداث التغيير (مع إزالة المستمعات القديمة)
		        $('.filter-select').off('change').on('change', function () {
		            const filterKey = $(this).data('filter');
		            const val = $(this).val();
		            filterValues[filterKey] = val;
		            api.draw(); // إعادة التصفية
		        });
		
		        // **لا نستدعي api.draw() هنا**، لأن البيانات معروضة بالفعل
		        // والفلتر الافتراضي يسمح بكل البيانات (لأن filterValues فارغة)
		    }
		    
		    
	}
}

/* ==========================
   CLICK DAILY ROW
========================== */

$('#tblSummary').on('click', 'tbody tr', function () {

    const row = summaryTable.row(this).data();

    if (!row) return;

    loadDetails(
        $('#store_id').val(),
        row.report_date
    );

});

/* ==========================
   CLICK DAILY ROW
========================== */

$('#tblDetails').on('click', '#btnOview2', function (e) {

    e.stopPropagation();

    const row = detailsTable.row($(this).closest('tr')).data();
	const dirmov = row.QTY_IN > 0 ? 'IN' : 'OUT';
    const im_id = row.IM_ID;

    Swal.fire({
        title: 'تفاصيل المنتجات',
        width: '90%',
        html: `
			<div class="mb-2" id="dynamicFilters"></div>
			
			<div class="table-responsive">
			    <table id="tblProductsDetails" class="table table-bordered table-hover table-striped text-nowrap w-100">
			        <thead>
			            <tr>
			                <th>المنتج</th>
			                <th>الوصف</th>
			                <th>الحركة</th>
			                <th>الكمية</th>
			            </tr>
			        </thead>
			    </table>
			</div>
			`,
        showConfirmButton: false,
        didOpen: () => {
		    let customFilter = null;
		    const filterValues = {};
		
		    // تنظيف شامل للفلاتر المخصصة السابقة (لتجنب التراكب)
		    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(f => f !== customFilter);
		
		    // تهيئة DataTable
		    const table = $('#tblProductsDetails').DataTable({
		        processing: true,
		        serverSide: false,
		        searching: true,
		        ordering: true,
		        paging: false,
		        info: false,
		        ajax: {
		            url: '/inc/controller/reports.control.php',
		            type: 'POST',
		            data: {
		                CONTROL: 'inventoryMovementProducts',
		                im_id: im_id,
		                dir: dirmov
		            },
		            dataSrc: '' // يعيد مصفوفة مباشرة
		        },
		        columns: [
		            { data: 'PRO_NAME' },
		            { data: 'OPT_JSON' },
		            { data: 'IPM_TYPE_MOV' },
		            { data: 'IPM_QTY' }
		        ],
		        columnDefs: [{
		            targets: '_all',
		            className: 'badge-green5 text-center text-nowrap'
		        }],
		        // استخدم initComplete بدلاً من drawCallback
		        initComplete: function () {
		            const api = this.api();
		            const allData = api.data().toArray();
		
		            // إذا لم توجد بيانات، نعرض رسالة ونخرج
		            if (!allData.length) {
		                $('#dynamicFilters').html('<div class="alert alert-warning">لا توجد بيانات لعرضها</div>');
		                return;
		            }
		
		            // بناء الفلاتر (مرة واحدة فقط)
		            buildFilters(allData, api);
		        }
		    });
		
		    // دالة بناء الفلاتر
		    function buildFilters(allData, api) {
		        // 1. استخراج أسماء المنتجات الفريدة
		        const proNames = [...new Set(allData.map(row => row.PRO_NAME))];
		
		        // 2. تحليل OPT_JSON وجمع المفاتيح والقيم
		        const optKeysMap = new Map();
		        allData.forEach(row => {
		            const opt = row.OPT_JSON || '';
		            const pairs = opt.split('|').map(p => p.trim());
		            pairs.forEach(pair => {
		                if (!pair) return;
		                const parts = pair.split(':');
		                if (parts.length === 2) {
		                    const key = parts[0].trim();
		                    const val = parts[1].trim();
		                    if (!optKeysMap.has(key)) {
		                        optKeysMap.set(key, new Set());
		                    }
		                    optKeysMap.get(key).add(val);
		                }
		            });
		        });
		
				// 3. بناء HTML الفلاتر بتصميم متجانس ومنتظم
				let filtersHtml = `
				    <div class="card shadow-sm mb-3">
				        <div class="card-body">
				            <div class="row  align-items-end">
				                <!-- فلتر المنتج -->
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">المنتج</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="PRO_NAME">
				                        <option value="">الكل</option>
				                        ${proNames.map(name => `<option value="${name}">${name}</option>`).join('')}
				                    </select>
				                </div>
				`;
				
				// إضافة باقي الفلاتر (OPT_JSON)
				optKeysMap.forEach((valuesSet, key) => {
				    const values = Array.from(valuesSet).sort();
				    filtersHtml += `
				                <div class="col-md-4">
				                    <label class="form-label fw-semibold mb-1 small text-muted">${key}</label>
				                    <select class="form-control form-select form-select-sm filter-select" data-filter="${key}">
				                        <option value="">الكل</option>
				                        ${values.map(v => `<option value="${v}">${v}</option>`).join('')}
				                    </select>
				                </div>
				    `;
				});
				
				filtersHtml += `
				            </div>
				        </div>
				    </div>
				`;
				
		        $('#dynamicFilters').html(filtersHtml);
		
		        // 4. تنظيف وإضافة الفلتر المخصص الجديد
		        if (customFilter) {
		            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(f => f !== customFilter);
		        }
		
		        customFilter = function (settings, data, dataIndex) {
		            const rowData = api.row(dataIndex).data();
		            if (!rowData) return true;
		
		            for (const [filterKey, selectedVal] of Object.entries(filterValues)) {
		                if (!selectedVal) continue;
		
		                if (filterKey === 'PRO_NAME') {
		                    if (rowData.PRO_NAME !== selectedVal) return false;
		                } else {
		                    const opt = rowData.OPT_JSON || '';
		                    const pairs = opt.split('|').map(p => p.trim());
		                    let found = false;
		                    for (const p of pairs) {
		                        const parts = p.split(':');
		                        if (parts.length === 2) {
		                            const k = parts[0].trim();
		                            const v = parts[1].trim();
		                            if (k === filterKey && v === selectedVal) {
		                                found = true;
		                                break;
		                            }
		                        }
		                    }
		                    if (!found) return false;
		                }
		            }
		            return true;
		        };
		        $.fn.dataTable.ext.search.push(customFilter);
		
		        // 5. ربط أحداث التغيير (مع إزالة المستمعات القديمة)
		        $('.filter-select').off('change').on('change', function () {
		            const filterKey = $(this).data('filter');
		            const val = $(this).val();
		            filterValues[filterKey] = val;
		            api.draw(); // إعادة التصفية
		        });
		
		        // **لا نستدعي api.draw() هنا**، لأن البيانات معروضة بالفعل
		        // والفلتر الافتراضي يسمح بكل البيانات (لأن filterValues فارغة)
		    }
		}
    });

});
/* ==========================
   DETAILS DATATABLE
========================== */

function loadDetails(storeId, reportDate) {

    $('#summarySection').hide();

    $('#detailsSection').show();

    $('#btnBack').show();

    $('#selectedDateTitle').html(
        'تفاصيل حركة المخزون بتاريخ <strong>' +
        reportDate +
        '</strong>'
    );

    if ($.fn.DataTable.isDataTable('#tblDetails')) {

        $('#tblDetails').DataTable().destroy();

    }

    detailsTable = $('#tblDetails').DataTable({
	    processing: true,
	    serverSide: false,
	    pageLength: 50,
	
	    ajax: {
	        url: '/inc/controller/reports.control.php',
	        type: 'POST',
	        data: {
	            CONTROL: 'inventoryDailyDetails',
	            store_id: storeId,
	            report_date: reportDate
	        },
	        dataSrc: ''
	    },
	
	    columns: [
	    	{data: '', 
	         title: '',
	         className: 'text-center badge-green5',
	         render: (data, type, row) => {
	              return `
	                  <button id="btnOview2" data-id="${row['IM_ID']}" type="button" class="dt-controlerA btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-5">
		    			<i class="fas fa-eye"></i>
		    		  </button>`;
	         }
	        },
	        {data: 'ORDER_ID'},
	        {data: 'CREATED_DATE'},
	        {data: 'PARTNER_NAME'},
	        {data: 'IM_MOVE_TYPE'},
	        {data: 'IM_DETAILS'},
	        {data: 'QTY_IN'},
	        {data: 'QTY_OUT'}
	    ],
	    columnDefs: [{
	        targets: '_all',
	        className: 'badge-green5 text-center text-nowrap'
	    }],
	    order: [[1, 'desc']],
	    initComplete: function () {

    let api = this.api();

    let html = '<div class="row">';

    api.columns().every(function () {

        let column = this;

        // تجاهل الأعمدة الأولى والأرقام
        if (column.index() <= 1 || column.index() >= 5)
            return;

        let values = [];

        column.data().each(function (d) {

            if (!d) return;

            let value = d.toString();

            if (column.index() == 2) {

                let parts = value.split(' ');

                if (parts.length > 1)
                    value = parts[1].substring(0,2) + ':00';
            }

            if ($.inArray(value, values) === -1)
                values.push(value);

        });

        values.sort();

        let title = $(column.header()).text();

        html += `
            <div class="col-md-3 mb-2">
                <label>${title}</label>
                <select class="form-control detailsFilter"
                        data-column="${column.index()}">
                    <option value="">الكل</option>
                    ${values.map(v=>`<option value="${v}">${v}</option>`).join('')}
                </select>
            </div>
        `;

    });

    html += '</div>';

    $('#dynamicFiltersDetails').html(html);

    $('.detailsFilter').on('change', function () {

        let col = $(this).data('column');

        let val = $(this).val();

        if (col == 2 && val) {

            api.column(col)
               .search(' ' + val.substring(0,2) + ':', true, false)
               .draw();

        } else {

            api.column(col)
               .search(val ? '^'+$.fn.dataTable.util.escapeRegex(val)+'$' : '', true, false)
               .draw();

        }

    });

},
		
		footerCallback: function (row, data, start, end, display) {

			var api = this.api();

			function intVal(i) {
				return typeof i === 'string'
					? Number(i.replace(/[^0-9.-]/g, ''))
					: typeof i === 'number'
						? i
						: 0;
			}

			// إجمالي العمود قبل الأخير
			var total1 = api.column(6, { search: 'applied' }).data().reduce(function (a, b) {
				return intVal(a) + intVal(b);
			}, 0);

			// إجمالي آخر عمود
			var total2 = api.column(7, { search: 'applied' }).data().reduce(function (a, b) {
				return intVal(a) + intVal(b);
			}, 0);

			$(api.column(6).footer()).html(total1.toLocaleString());
			$(api.column(7).footer()).html(total2.toLocaleString());

		}
	});

}

/* ==========================
   BACK
========================== */

$('#btnBack').on('click', function () {
    $('#detailsSection').hide();
    $('#summarySection').show();
    $('#btnBack').hide();
});

$(document).on("click", ".OLF" ,function () {
	
	
	if ($.fn.DataTable.isDataTable('#tblSummary')) {
		var tbl1 = $('#tblSummary').DataTable();
		tbl1.clear().draw();
		$('#tblSummary').DataTable().destroy();
		
	}
	
	if ($.fn.DataTable.isDataTable('#tblProDetails')) {
		var tbl2 = $('#tblProDetails').DataTable();
		tbl2.clear().draw();
		$('#tblProDetails').DataTable().destroy();
	}
	
	let oltype = $(this).attr('id');
	$('#detailsSection').hide();
    $('#tblSummary').hide();
   	$('#tblProDetails').hide();
   	
	if(oltype == 'productMovmentReport'){
		//$('#detailsSection').hide();
    	//$('#tblSummary').hide();
    	$('#tblProDetails').show();
    	$('#dynamicFilters1').show();
	}else{
		//$('#detailsSection').hide();
    	$('#tblSummary').show();
    	$('#dynamicFilters1').hide();
    	//$('#tblProDetails').hide();
	}
});

setTimeout(function(){
	$('#defaultReport').click();
},100)

});
</script>
