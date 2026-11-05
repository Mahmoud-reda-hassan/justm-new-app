<?php
?>
<div class="card shadow rounded mb-3">
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

<div class="card DOL1 hid" style="display:none">
	<div class="card-body table-responsive p-2">
		<table id="tbl0" class="table table-hover table-bordered table-striped text-nowrap w-100 col-sm-12 p-0 m-0">
			<thead>
                <tr>
                	<th class="text-center text-nowarp">رقم الاذن</th>
                    <th class="text-center text-nowarp">التاريخ</th>
                    <th class="text-center text-nowarp">المورد</th>
                    <th class="text-center text-nowarp">الصنف</th>
                    <th class="text-center text-nowarp">الوصف</th>
                    <th class="badge-red5 text-center text-nowarp">الكميه</th>
                    <th class="badge-red5 text-center text-nowarp">التكلفة</th>
                    <th class="badge-green5 text-center text-nowarp">المستلم</th>
                    <th class="badge-green5 text-center text-nowarp">التكلفة</th>
                </tr>
            </thead>
		</table>
	</div>
</div>

<div class="card DOL2 hid">
	<div class="card-body table-responsive p-2">
		<table id="tbl" class="table table-hover table-bordered table-striped text-nowrap w-100 col-sm-12 p-0 m-0">
			<thead>
                <tr>
                    <th class="text-center text-nowarp">المورد</th>
                    <th class="text-center text-nowarp">الصنف</th>
                    <th class="text-center text-nowarp">الوصف</th>
                    <th class="badge-red5 text-center text-nowarp">الكميه</th>
                    <th class="badge-red5 text-center text-nowarp">التكلفة</th>
                    <th class="badge-green5 text-center text-nowarp">المستلم</th>
                    <th class="badge-green5 text-center text-nowarp">التكلفة</th>
                </tr>
            </thead>
		</table>
	</div>
</div>
		
<script>
let summaryTable = null;
let detailsTable = null;
const urlvr = `../inc/controller/reports.control.php`;
$(document).ready(()=>{
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
		            url: '/inc/Api.php?CONTROL=getdropWarehouse&type=2&ass=true',
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
		    const picker = $('#date_range').data('daterangepicker');
		    const dateFrom = picker.startDate.format('YYYY-MM-DD');
		    const dateTo   = picker.endDate.format('YYYY-MM-DD');
			if (storeId) {
				loadSummaryReport(
			        storeId,
			        dateFrom,
			        dateTo
			    );
		        return;
		    }
		    alert('اختر المخزن');
		});
		
	/* ==========================
	   SUMMARY DATATABLE
	========================== */
	function loadSummaryReport(storeId, dateFrom, dateTo) {
	
	    $('.hid').hide();
	
	    $('.DOL2').show();
	
	    $('#btnBack').hide();
	
	    if ($.fn.DataTable.isDataTable('#tbl')) {
	
	        $('#tbl').DataTable().destroy();
	
	    }
	
	    summaryTable = $('#tbl').DataTable({
	        processing: true,
	        serverSide: false,
	        //responsive: true,
	        pageLength: 25,
	        ajax: {
	            url: urlvr,
	            type: 'POST',
	            data: {
					CONTROL: 'SuppliersQtyReport', 
	                store_id: storeId,
	                date_from: dateFrom,
	                date_to: dateTo
	            },
	            dataSrc: ''
	        },
	        columns: [
	            { title: "المورد", data: "SUP_NAME" }, // العمود الأول
	            { title: "الصنف", data: "PRO_NAME" }, // العمود الثاني
	            { title: "الوصف", data: "OPT_JSON",className:"text-nowrap" }, // العمود الرابع
	            { title: "الكميه", data: "QTY",className:"text-nowrap text-center" }, // العمود الثالث
	            { title: "التكلفة", data: "QTY_PRICE",className:"text-nowrap" }, // العمود الخامس
	            { title: "المستلم", data: "QTY_SUPPLIED",className:"text-nowrap" }, // العمود السادس
	            { title: "التكلفة", data: "SUPPLIED_PRICE",className:"text-nowrap" } // العمود السابع
	        ],
	        columnDefs: [{
	            targets: '_all',
	            className: 'mdc-data-table__cell text-nowrap text-center'
	        }],
	        order: [[1,'asc']]
	    });
	
	}

	/*table = $('#tbl').DataTable({
			//scrollY: '70vh',
			//scrollX: false,
			ajax: {
				url: urlvr,
				type: 'POST', // نوع الطلب
	            data: function (d) {
	                // إضافة معلمات إضافية إذا لزم الأمر
	                d.CONTROL = Go;
	            }
			},
			orderCellsTop: true, // يضمن أن الترتيب يعمل فقط على الصف العلوي
	    	ordering: true, // يسمح بالترتيب ولكن فقط على الهيدر الأول
			processing: true,
			serverSide: true,
			columns: [
	            { title: "المورد", data: "SUP_NAME" }, // العمود الأول
	            { title: "الصنف", data: "PRO_NAME" }, // العمود الثاني
	            { title: "الوصف", data: "OPT_JSON",className:"text-nowrap" }, // العمود الرابع
	            { title: "الكميه", data: "QTY",className:"text-nowrap text-center" }, // العمود الثالث
	            { title: "التكلفة", data: "QTY_PRICE",className:"text-nowrap" }, // العمود الخامس
	            { title: "المستلم", data: "QTY_SUPPLIED",className:"text-nowrap" }, // العمود السادس
	            { title: "التكلفة", data: "SUPPLIED_PRICE",className:"text-nowrap" } // العمود السابع
	        ],
	        columnDefs: [{
	            targets: '_all',
	            className: 'mdc-data-table__cell text-nowrap text-center'
	        }],
	        headerCallback: function(thead, data, start, end, display) {
	            $(thead).addClass('bg-secondary');
	        },
		/*	buttons: [{
				text: 'Reload',
				action: function (e, dt, node, config) {
					dt.ajax.reload();
				}
			}],
			pageLength: -1,
	        lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "الكل"]],
			rowId: 'id'
	}).on('draw', function() { });*/
	
})
</script>