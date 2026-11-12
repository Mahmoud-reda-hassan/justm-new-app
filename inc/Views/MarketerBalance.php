<?php
session_start();

?>
  <div class="card shadow mt-4 ml-0 mr-0 rounded">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0">نتائج الحركات</h5>
    </div>
    <div class="card-body table-responsive p-2">
      <form id="filter-form">
        <div class="form-row text-center">
          <div class="form-group col-md-6 ">
            <input id="dateRange" type="text" class="form-control" name="from_date" required>
          </div>
          <div class="form-group col-md-6 text-left">
	          <button type="submit" class="btn btn-success">
		         <i class="fas fa-search"></i> بحث
		      </button>
	      </div>
        </div>
        
      </form>
      <table id="balance-table" class="table table-bordered table-hover table-striped">
        <thead class="thead-dark">
          <tr>
          	<th>ملاحظة</th>
            <th>تاريخ</th>
            <th>رقم الامر</th>
            <th>الكميه المطلوبه</th>
            <th>الكميه المستلمه</th>
            <th>المرتجع</th>
            <th>دائن</th>
            <th>مدين</th>
            <th>الرصيد</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>

<!--<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>-->


<script>

	$(document).ready(function () {
	  const urlvr = `../inc/controller/reports.control.php`;
	  $(async function() {
	    $('#dateRange').daterangepicker({
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
	  var table = $('#balance-table').DataTable({
	    processing: true,
	    serverSide: true,
	    searching: false,
	    ajax: {
	      url: urlvr,
	      type: 'POST',
	      data: function (d) {
	        return $.extend({}, d, {
	          CONTROL: 'AccountStatement',
	          daterange: $('input[name=from_date]').val(),
	          partner_type: 'MARKETER',
	          partner_id: <?php echo $_SESSION['USER_ID']; ?>
	        });
	      }
	    },
	    columns: [
	      { data: 'note',className:"text-nowrap text-center"},
	      { data: 'transaction_date',className:"text-nowrap text-center" },
	      { data: 'ORDER_ID',className:"text-nowrap text-center" },
		  { data: 'QTY_ORDER',className:"text-nowrap text-center" },
		  { data: 'QTY_SUPPLIED',className:"text-nowrap text-center" },
		  { data: 'QTY_RETURN',className:"text-nowrap text-center" },
	      { data: 'debit',className:"text-nowrap text-center"},
	      { data: 'credit',className:"text-nowrap"},
	      { data: 'running_balance',className:"text-nowrap text-center"},
	    ],
	    pageLength: -1,
        lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "الكل"]],
	  });
	
	  $('#filter-form').on('submit', function (e) {
	    e.preventDefault();
	    table.ajax.reload();
	  });
	let control = '';

	if(partnertype == 'CLIENT'){
		control = 'getClientList';
		$('#partnerLebal').html('العميل');
	}else if(partnertype == 'SUPPLIER'){
		control = 'supplierslist';
		$('#partnerLebal').html('المورد');
	}else if(partnertype == 'MARKETER'){
		control = 'getMarketerList';
		$('#partnerLebal').html('المسوق');
	}else if(partnertype == 'DELIVERY'){
		control = 'getDeliveryList';
		$('#partnerLebal').html('المندوب');
	}
	  // حقل اختيار المورد Select2
    $('#partnerselect').select2({
        placeholder: `اختر ${$('#partnerLebal').html()}`,
        allowClear: true,
        language: 'ar', // دعم اللغة
        selectionCssClass: 'form-control',
	    width: '100%',
	    dir: 'rtl',
        ajax: {
            url: `${urlApi}?CONTROL=${control}`, // المسار إلى ملف PHP
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
                    results: data.map(function(supplier) {
                        return {
                            id: supplier.id, // القيمة
                            text: supplier.name // النص المعروض
                        };
                    })
                };
            },
            cache: true
        }
    });
	  
	});
</script>
