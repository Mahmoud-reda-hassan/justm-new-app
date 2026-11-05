<!-- Filters -->
    <div class="card shadow rounded mb-3">

        <!--<div class="card-header">
            <h5 class="mb-0">تقرير حركة المخزون اليومية</h5>
        </div>-->

        <div class="card-body ">

            <div class="row">

                <div class="col-md-8">

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
                تفصيلى اوامر البيع
            </div>

            <div class="card-body table-responsive p-2">

                <table id="tblSummary" class="table table-bordered table-hover table-striped text-nowarp">
                    <thead>
	                    <tr>
	                    	<th>عرض</th>
	                        <th>الحالة</th>
					        <th>عدد الاوامر</th>
					        <th>عدد القطع</th>
					        <th>عدد المرفوض</th>
					        <th>عدد التسليم</th>
					        <th>عدد المرتجع</th>
	                    </tr>
                    </thead>
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

            <div class="card-body">
                <table id="tblDetails" class="table table-bordered table-hover table-striped table-sm w-100">
				    <thead>
				        <tr>
				        	<!--<th></th>-->
				        	<th>رقم الامر</th>
				            <th>الحالة</th>
				            <th>القطع المطلوبة</th>
				            <th>قطع مستلمة</th>
				            <th>قطع مرفوضة</th>
				            <th>قطع مرتجع بعد البيع</th>
				            <th>أضافة عمولة</th>
				            <th>خصم عموله</th>
				            
				        </tr>
				        <tr class="filters">
						    <!--<th></th>-->
				        	<th></th>
						    <th></th>
						    <th></th>
						    <th></th>
						    <th></th>
						    <th></th>
						    <th></th>
						    <th></th>
						</tr>
				    </thead>
				</table>
            </div>

        </div>
    </div>



<script>

let summaryTable = null;
let detailsTable = null;

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
	      $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
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



    const picker = $('#date_range').data('daterangepicker');

    const dateFrom = picker.startDate.format('YYYY-MM-DD');
    const dateTo   = picker.endDate.format('YYYY-MM-DD');

    loadSummaryReport(
        dateFrom,
        dateTo
    );

});

/* ==========================
   SUMMARY DATATABLE
========================== */

function loadSummaryReport(dateFrom, dateTo) {

    $('#detailsSection').hide();

    $('#summarySection').show();

    $('#btnBack').hide();

    if ($.fn.DataTable.isDataTable('#tblSummary')) {

        $('#tblSummary').DataTable().destroy();

    }

    summaryTable = $('#tblSummary').DataTable({

        processing: true,
        serverSide: false,
        //responsive: true,
        pageLength: 25,

        ajax: {

            url: '/inc/controller/reports.control.php',

            type: 'POST',

            data: {
				CONTROL: 'marketerDailyReport', 
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
            {data: 'ORDER_STATUSE'},
            {data: 'COUNT_ORDERS'},

            {data: 'QTY'}, //وارد مورد
            {data: 'REJECTED_QTY'},//استرداد من مندوب
            {data: 'DELIVERED_QTY'}, // وارد مرتجعات مندوب
            {data: 'RETURNED_QTY'}, //وارد مرتجعات استبدال
        ],
        columnDefs: [
		    {
		        targets: [0,1,2,3,4,5], // أرصدة الفتح والإغلاق
		        className:'text-center text-nowrap '
		    }
		],

        order: [[1,'asc']]

    });

}

/* ==========================
   CLICK DAILY ROW
========================== */

$('#tblSummary').on('click', 'tbody tr', function () {

    const row = summaryTable.row(this).data();
    const picker = $('#date_range').data('daterangepicker');
	const dateFrom = picker.startDate.format('YYYY-MM-DD');
    const dateTo   = picker.endDate.format('YYYY-MM-DD');
    
    if (!row) return;

    loadDetails(
        row.ORDER_STATUSE,
        dateFrom,
        dateTo
    );

});

/* ==========================
   CLICK DAILY ROW
========================== */

$('#tblDetails').on('click', '#btnOview2', function (e) {

    e.stopPropagation();

    const row = detailsTable.row($(this).closest('tr')).data();

    const im_id = row.IM_ID;

    Swal.fire({
        title: 'تفاصيل المنتجات',
        width: '90%',
        html: `
            <div class="table-responsive">
                <table id="tblProductsDetails" class="table table-bordered table-striped table-sm w-100">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الباركود</th>
                            <th>الكمية</th>
                        </tr>
                    </thead>
                </table>
            </div>
        `,
        showConfirmButton: false,
        didOpen: () => {

            $('#tblProductsDetails').DataTable({
                processing: true,
                serverSide: false,
                searching: false,
                paging: false,
                info: false,

                ajax: {
                    url: '/inc/controller/reports.control.php',
                    type: 'POST',
                    data: {
                        CONTROL: 'marketerDailyDetails',
                        order_statuse: im_id
                    },
                    dataSrc: ''
                },

                columns: [
                    { data: 'PRODUCT_NAME' },
                    { data: 'BARCODE' },
                    { data: 'QTY' }
                ]
            });

        }
    });

});
/* ==========================
   DETAILS DATATABLE
========================== */

function loadDetails(orderStatuse, dateFrom, dateTo) {

    $('#summarySection').hide();

    $('#detailsSection').show();

    $('#btnBack').show();

    $('#selectedDateTitle').html(
        'تفاصيل اوامر البيع للحاله  <strong>' +
        orderStatuse +
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
	            CONTROL: 'marketerDailyDetails',
	            order_statuse: orderStatuse,
	            date_from: dateFrom,
                date_to: dateTo
	        },
	        dataSrc: ''
	    },
	
	    columns: [
	    	/*{data: '', 
		         title: '',
		         className: 'text-center badge-green5',
		         render: (data, type, row) => {
		              return `
		                  <button id="btnOview2" data-id="${row['IM_ID']}" type="button" class="dt-controlerA btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-5">
			    			<i class="fas fa-eye"></i>
			    		  </button>`;
		         }
	        },*/
	        {data: 'SUB_ORDER_ID'},
	        {data: 'ORDER_STATUSE_NAME'},
	        {data: 'QTY'},
	        {data: 'DELIVERED_QTY'},
	        {data: 'REJACTED_QTY'},
	        {data: 'RETURNED_QTY'},
	        {data: 'credit'},
	        {data: 'debit'}
	    ],
	
	    columnDefs: [{
	        targets: '_all',
	        className: 'badge-green5 text-center text-nowrap'
	    }],
	
	    order: [[2, 'asc']],
	
	    initComplete: function () {
			$(column.header())
			    .closest('thead')
			    .find('tr.filters th')
			    .empty();
		    this.api().columns().every(function () {
		
		        let column = this;
		
		        // تجاهل الكمية الواردة والمنصرفة
		        if (column.index() >= 4 || column.index() <=0 ) {
		            return;
		        }
		
		        let select = $('<select class="form-control form-control-sm">' +
		                            '<option value="">الكل</option>' +
		                       '</select>')
		            .appendTo(
		                $(column.header())
		                    .closest('thead')
		                    .find('tr.filters th')
		                    .eq(column.index())
		            )
		            .on('change', function () {
		
		                let val = $.fn.dataTable.util.escapeRegex($(this).val());
		
		                column
		                    .search(val ? '^' + val + '$' : '', true, false)
		                    .draw();
		            });
		
		        column.data().unique().sort().each(function (d) {
		
		            if (d !== null && d !== '') {
		                select.append(`<option value="${d}">${d}</option>`);
		            }
		
		        });
		
		        select.select2({
		            width: '100%'
		        });
		
		    });
		
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

});
</script>
