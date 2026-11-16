<?php

$partnerMap = [

    'CLIENT' => [
        'columns' => [
            'debit'  => 'عليه',
            'credit' => 'سدد',
        ],
        'labels' => [
            'DEBIT'  => ['ar' => 'عليه فلوس', 'en' => 'Receivable'],
            'CREDIT' => ['ar' => 'دفع', 'en' => 'Payment Received'],
        ],
        'color' => [
        	'DEBIT'  => 'text-danger',
        	'CREDIT' => 'text-success',
        ],
    ],

    'SUPPLIER' => [
        'columns' => [
            'debit'  => 'سددنا',
            'credit' => 'علينا',
        ],
        'labels' => [
            'DEBIT'  => ['ar' => 'تم السداد', 'en' => 'Payment Paid'],
            'CREDIT' => ['ar' => 'عليك فلوس', 'en' => 'Payable'],
        ],
        'color' => [
        	'DEBIT'  => 'text-success',
        	'CREDIT' => 'text-danger',
        ],
    ],

    'MARKETER' => [
        'columns' => [
            'debit'  => 'اتدفع',
            'credit' => 'له',
        ],
        'labels' => [
            'DEBIT'  => ['ar' => 'تم الدفع', 'en' => 'Paid'],
            'CREDIT' => ['ar' => 'له عمولة', 'en' => 'Commission Due'],
        ],
        'color' => [
        	'DEBIT'  => 'text-danger',
        	'CREDIT' => 'text-success',
        ],
    ],

    'DELIVERY' => [
        'columns' => [
            'debit'  => 'اتدفع',
            'credit' => 'له',
        ],
        'labels' => [
            'DEBIT'  => ['ar' => 'دفع / عهدة', 'en' => 'Paid / Cash in Hand'],
            'CREDIT' => ['ar' => 'له عمولة', 'en' => 'Commission Due'],
        ],
        'color' => [
        	'DEBIT'  => 'text-success',
        	'CREDIT' => 'text-danger',
        ],
    ],

];

	$debit  = $partnerMap[$_GET['ptr']]['labels']['DEBIT']['ar']; //مدين
	$credit = $partnerMap[$_GET['ptr']]['labels']['CREDIT']['ar']; //دائن
	$debitColor  = $partnerMap[$_GET['ptr']]['color']['DEBIT']; //مدين
	$creditColor = $partnerMap[$_GET['ptr']]['color']['CREDIT']; //دائن
?>
<div class="mr-3 ml-3 mt-4 ml-0 mr-0 pl-0 pr-0">
  <div class="card shadow rounded">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">البحث في الحركات </h5>
    </div>
    <div class="card-body">
      <form id="filter-form">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>الفترة</label>
            <input id="dateRange" type="text" class="form-control" name="from_date" required>
          </div>
          <div class="form-group col-md-6">
            <label id='partnerLebal'>الشريك</label>
            <select id="partnerselect" class="form-control" name="partner_id" required>
              <!-- يتم ملؤها ديناميكيًا -->
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-search"></i> بحث
        </button>
      </form>
    </div>
  </div>

  <div class="card shadow mt-4 ml-0 mr-0 rounded">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0">نتائج الحركات</h5>
    </div>
    <div class="card-body table-responsive p-2">
      <div id="balance-summary" class="alert alert-secondary mb-3" style="display:none"></div>
      <small class="text-muted d-block mb-2">الرصيد: <span class="text-primary">له</span> = مستحق للطرف عند الشركة، <span class="text-danger">عليه</span> = مستحق للشركة عند الطرف.</small>
      <table id="balance-table" class="table table-bordered table-hover table-striped">
        <thead class="thead-dark">
          <tr>
            <th>تاريخ</th>
            <th>رقم الامر</th>
            <th>الكميه المطلوبه</th>
            <th>الكميه المستلمه</th>
            <th>المرتجع</th>
            <th><?php echo $debit;?></th>
            <th><?php echo $credit;?></th>
            <th>الرصيد (له / عليه)</th>
            <th>ملاحظة</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>



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
				"اليوم":        [moment(), moment()],
				"أمس":          [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				"اول امس":      [moment().subtract(2, 'days'), moment().subtract(2, 'days')],
				"آخر 7 أيام":   [moment().subtract(6, 'days'), moment()],
				"آخر 30 يوم":   [moment().subtract(29, 'days'), moment()],
				"الشهر الماضي": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				"هذا الشهر":    [moment().startOf('month'), moment().endOf('month')]
				
			},
	    });
	
	    $('#date_range').on('apply.daterangepicker', function(ev, picker) {
	      $(this).val(picker.startDate.format('YYYY-MM-DD') + ' إلى ' + picker.endDate.format('YYYY-MM-DD'));
	    });
	
	    $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
	      $(this).val('');
	    });
	  });

	  // له / عليه: الرصيد = دائن − مدين. الموجب = له (مستحق للطرف عند الشركة) والسالب = عليه (مستحق للشركة عند الطرف)
	  function fmtMoney(n){ return Math.abs(parseFloat(n)||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
	  function balanceSide(v){
	    v = parseFloat(v);
	    if (isNaN(v)) return {txt:'', cls:''};
	    if (v > 0.004)  return {txt:'له',    cls:'text-primary font-weight-bold'};
	    if (v < -0.004) return {txt:'عليه', cls:'text-danger font-weight-bold'};
	    return {txt:'متزن', cls:'text-muted'};
	  }
	  function renderBalance(data, type){
	    if (type !== 'display' || data === null || data === '' || typeof data === 'undefined') return data;
	    var s = balanceSide(data);
	    return '<span class="' + s.cls + '">' + fmtMoney(data) + ' ' + s.txt + '</span>';
	  }
	  function updateBalanceSummary(json, who){
	    var rows = (json && json.data) ? json.data : [];
	    var last = rows.length ? rows[rows.length-1] : null;
	    if (!last || last.note !== 'رصيد حالي') { $('#balance-summary').hide(); return; }
	    var s = balanceSide(last.running_balance);
	    var detail = s.txt === 'له' ? 'مستحق له عند الشركة' : (s.txt === 'عليه' ? 'مستحق للشركة عنده' : 'لا يوجد رصيد');
	    $('#balance-summary').removeClass('alert-primary alert-danger alert-secondary')
	      .addClass(s.txt === 'له' ? 'alert-primary' : (s.txt === 'عليه' ? 'alert-danger' : 'alert-secondary'))
	      .html('<strong>' + (who ? who + ' — ' : '') + 'الرصيد حتى نهاية الفترة: ' + s.txt + ' ' + (s.txt === 'متزن' ? '' : fmtMoney(last.running_balance)) + '</strong> <small>(' + detail + ')</small>')
	      .show();
	  }
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
	          partner_type: partnertype,
	          partner_id: $('select[name=partner_id]').val()
	        });
	      }
	    },
	    columns: [
	      { data: 'transaction_date',className:"text-nowrap text-center" },
	      { data: 'ORDER_ID',className:"text-nowrap text-center" },
		  { data: 'QTY_ORDER',className:"text-nowrap text-center" },
		  { data: 'QTY_SUPPLIED',className:"text-nowrap text-center" },
		  { data: 'QTY_RETURN',className:"text-nowrap text-center" },
	      { data: 'debit',className:"text-nowrap <?php echo $debitColor;?>"},
	      { data: 'credit',className:"text-nowrap <?php echo $creditColor;?>"},
	      { data: 'running_balance',className:"text-nowrap text-center", render: renderBalance},
	      { data: 'note',className:"text-nowrap text-center"},
	    ],
	    pageLength: -1,
        lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "الكل"]]
	  });
	
	  table.on('xhr.dt', function (e, settings, json) { updateBalanceSummary(json, $('#partnerselect option:selected').text().trim()); });
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
