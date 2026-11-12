// Back to tiles view
/*function backToTiles() {
	$('#metro-container').show(); // Show the metro tiles again
	$('.form-container').removeClass('active'); // Hide all forms
	$('#btnBack').hide();
	$('.btnPay').hide();
	if (!$("#crv").hasClass("container-fluid")) {
		$("#crv").addClass('container-fluid');
	}
	
}*/

function backToTiles() {
    $('#metro-container').show();
    $('.form-container').removeClass('active');   // بدون hide()
    $('#btnBack').hide();
    $('.btnPay').hide();
    if (supplierOrdersTable) {
        supplierOrdersTable.clear().draw();
    }
    if (!$("#crv").hasClass("container-fluid")) {
		$("#crv").addClass('container-fluid');
	}
    $('.payment-input').val(0);
    $('.order-check').prop('checked', false);
}


// Show the form based on clicked tile
function showForm(formType) {
	$('#metro-container').hide();  // Hide the metro tiles
	$('.form-container').removeClass('active').hide();
	$('#btnBack').hide();
	$('.form-container').removeClass('active');  // Hide all forms
	$('#form-' + formType).addClass('active');   // Show the selected form
	if(!$('#metro-container').is(':visible')){
		$('#btnBack').show();
	}

$('#metro-container').hide();
    
    $('#form-' + formType).addClass('active').show();
    if (!$('#metro-container').is(':visible')) {
        $('#btnBack').show();
    }


	if( $(`#form-mandob`).is(':visible')) {
		
		//if ($("#crv").hasClass("container-fluid")) {
		    $(".crv").removeClass('container-fluid');

		    $('.btnPay').show();
		    $("#btnCancelAllPayment").click();
		    $('#SelectUserDelivery').val(null).trigger('change');
		//} 
	}
	
}
// إخفاء جميع الفورم
function hideAllForms() {
    $('#form-mandob, #form-masouq, #form-morad, #form-omer-sell, #form-open').hide();
}

$(document).ready(function() {
  $('.btnPay').hide();
  $('.btn-app-pade').append('<button id="btnBack" class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="backToTiles();" style="display:none;" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnay mr-2" ></span></button>');
  
  

  // التعامل مع اختيار الأزرار
  $('.metro-option').on('click', function() {
    let option = $(this).data('option');
    
    hideAllForms();  // إخفاء جميع الفورم

    if (option == 1) {
      $('#form-mandob').show();
      loadMandobData();  // تحميل بيانات المندوبين
    } else if (option == 2) {
      $('#form-masouq').show();
      loadMasouqData();  // تحميل بيانات المسوقين
    } else if (option == 3) {
      $('#form-morad').show();
      loadMoradData();  // تحميل بيانات الموردين
    } else if (option == 4) {
      $('#form-omer-sell').show();
      loadClientData();  // تحميل بيانات العملاء
    } else if (option == 5) {
      $('#form-open').show();
    }
  });

  // تحميل بيانات المندوبين
  function loadMandobData() {
    // استعلام بيانات المندوبين من قاعدة البيانات أو API
    // هنا نقوم بتحميل البيانات من الخادم باستخدام jQuery AJAX
    $.ajax({
      url: 'get_mandob_data.php',  // نقطة النهاية للحصول على بيانات المندوبين
      method: 'GET',
      success: function(data) {
        let mandobData = JSON.parse(data);
        let mandobSelect = $('#select-mandob');
        mandobSelect.empty();  // إفراغ القائمة الحالية
        mandobSelect.append('<option value="">اختر المندوب</option>');
        mandobData.forEach(function(mandob) {
          mandobSelect.append('<option value="' + mandob.id + '">' + mandob.name + '</option>');
        });

        mandobSelect.on('change', function() {
          let selectedMandobId = $(this).val();
          if (selectedMandobId) {
            // استعلام للحصول على إجمالي حساب المندوب
            $.ajax({
              url: 'get_mandob_balance.php',
              method: 'GET',
              data: { mandob_id: selectedMandobId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-mandob').text(totalBalance);
                $('#amount-mandob').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات المسوقين
  function loadMasouqData() {
    // استعلام بيانات المسوقين من قاعدة البيانات أو API
    $.ajax({
      url: 'get_masouq_data.php',
      method: 'GET',
      success: function(data) {
        let masouqData = JSON.parse(data);
        let masouqSelect = $('#select-masouq');
        masouqSelect.empty();
        masouqSelect.append('<option value="">اختر المسوق</option>');
        masouqData.forEach(function(masouq) {
          masouqSelect.append('<option value="' + masouq.id + '">' + masouq.name + '</option>');
        });

        masouqSelect.on('change', function() {
          let selectedMasouqId = $(this).val();
          if (selectedMasouqId) {
            // استعلام للحصول على إجمالي حساب المسوق
            $.ajax({
              url: 'get_masouq_balance.php',
              method: 'GET',
              data: { masouq_id: selectedMasouqId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-masouq').text(totalBalance);
                $('#amount-masouq').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات الموردين
  function loadMoradData() {
    // استعلام بيانات الموردين من قاعدة البيانات أو API
    $.ajax({
      url: 'get_morad_data.php',
      method: 'GET',
      success: function(data) {
        let moradData = JSON.parse(data);
        let moradSelect = $('#select-morad');
        moradSelect.empty();
        moradSelect.append('<option value="">اختر المورد</option>');
        moradData.forEach(function(morad) {
          moradSelect.append('<option value="' + morad.id + '">' + morad.name + '</option>');
        });

        moradSelect.on('change', function() {
          let selectedMoradId = $(this).val();
          if (selectedMoradId) {
            // استعلام للحصول على إجمالي المبلغ الذي يجب سداده للمورد
            $.ajax({
              url: 'get_morad_balance.php',
              method: 'GET',
              data: { morad_id: selectedMoradId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-morad').text(totalBalance);
                $('#amount-morad').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات العملاء
  function loadClientData() {
    // استعلام بيانات العملاء من قاعدة البيانات أو API
    $.ajax({
      url: 'get_client_data.php',
      method: 'GET',
      success: function(data) {
        let clientData = JSON.parse(data);
        let clientSelect = $('#select-client');
        clientSelect.empty();
        clientSelect.append('<option value="">اختر العميل</option>');
        clientData.forEach(function(client) {
          clientSelect.append('<option value="' + client.id + '">' + client.name + '</option>');
        });

        clientSelect.on('change', function() {
          let selectedClientId = $(this).val();
          if (selectedClientId) {
            // استعلام للحصول على أوامر البيع الخاصة بالعميل
            $.ajax({
              url: 'get_orders_for_client.php',
              method: 'GET',
              data: { client_id: selectedClientId },
              success: function(orderData) {
                let orderDataParsed = JSON.parse(orderData);
                let orderSelect = $('#select-order');
                orderSelect.empty();
                orderSelect.append('<option value="">اختر أمر البيع</option>');
                orderDataParsed.forEach(function(order) {
                  orderSelect.append('<option value="' + order.id + '">' + order.order_number + '</option>');
                });

                orderSelect.on('change', function() {
                  let selectedOrderId = $(this).val();
                  if (selectedOrderId) {
                    // استعلام للحصول على المبلغ المرتجع لهذا الأمر
                    $.ajax({
                      url: 'get_return_amount.php',
                      method: 'GET',
                      data: { order_id: selectedOrderId },
                      success: function(returnData) {
                        let returnAmount = JSON.parse(returnData).return_amount;
                        $('#return-amount').text(returnAmount);
                      }
                    });
                  }
                });
              }
            });
          }
        });
      }
    });
  }

  // تخصيص الفورم للصرف المفتوح
  $('#amount-open').on('input', function() {
    let amount = parseFloat($(this).val());
    if (amount <= 0) {
      Swal.fire('الرجاء إدخال مبلغ صالح');
    }
  });

  // إضافة التحقق من صحة المدخلات عند الحفظ
  $('#save-button').on('click', function() {
    let amount = parseFloat($('#amount-mandob').val() || 0);
    if (amount <= 0) {
      Swal.fire('يرجى إدخال مبلغ صحيح');
      return false;
    }
  });

  // Event listeners for metro tiles
  $('#btn-mandob').click(function () {
  	   setTimeout(function() {
  	   	showForm('mandob')
  	   	
  	   }, 1000);
        
   });

  $('#btn-masouq').click(function () {
    	showForm('masouq');
   });

   $('#btn-morad').click(function () {
    	showForm('morad');
   });

   $('#btn-client-return').click(function () {
		showForm('client-return');
   });

   $('#btn-open').click(function () {
		showForm('open');
   });

});


/*==================================================
=            GLOBAL VARIABLES
==================================================*/
let supplierOrdersTable = null;
let currentLoading = null;
const balanceCache = {};        // لتخزين أرصدة الشركاء
const searchCache = {           // لتخزين نتائج البحث (اختياري للـ Select2)
    GetDeliveryData: {},
    GetMasouqData: {},
    GetMoradData: {},
    GetClientData: {}
};

/*==================================================
=            HELPERS & API
==================================================*/
const API         = '../../inc/controller/PaymentDisbursement.control.php';
const API_FINANCE = '../../inc/controller/finance.control.php';

function formatMoney(value) {
    return parseFloat(value || 0).toFixed(2);
}

function showAlert(icon, title, text = '') {
    if (currentLoading) {
        currentLoading.close();
        currentLoading = null;
    }
    Swal.fire({ icon, title, text });
}

function showLoading(title = 'جارى التنفيذ...') {
    if (currentLoading) currentLoading.close();
    currentLoading = Swal.fire({
        title,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });
}

function hideLoading() {
    if (currentLoading) {
        currentLoading.close();
        currentLoading = null;
    }
}

async function request(url, data = {}, method = 'GET') {
    try {
        const options = { method };
        if (method === 'POST') {
            options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            options.body = new URLSearchParams(data);
        }
        let query = '';
        if (method === 'GET' && Object.keys(data).length) {
            query = '?' + new URLSearchParams(data);
        }
        const response = await fetch(url + query, options);
        if (!response.ok) throw new Error('Server Error');
        return await response.json();
    } catch (error) {
        showAlert('error', 'خطأ', error.message);
        return null;
    }
}

/*==================================================
=            UI & NAVIGATION (المعدلة)
==================================================*/

/*function showForm(formType) {
    $('#metro-container').hide();
    $('.form-container').removeClass('active').hide();
    $('#form-' + formType).addClass('active').show();
    if (!$('#metro-container').is(':visible')) {
        $('#btnBack').show();
    }
}*/

function initBackButton() {
    if ($('#btnBack').length === 0) {
        $('.btn-app-pade').append(`
            <button id="btnBack" class="btn btn-app-tools btnh text-dark btnBack" style="display:none">
                <i class="fas fa-arrow-left"></i>
                <span class="btnname">الرجوع</span>
            </button>
        `);
        $('#btnBack').on('click', backToTiles);
    }
}

/*==================================================
=            SELECT2 (مع تحسين التخزين المؤقت)
==================================================*/
function initPartnerSelect2(element, control, placeholder) {

    searchCache[control] ??= {};

    $(element).select2({
        placeholder: placeholder,
        dir: "rtl",
        language: "ar",
        allowClear: true,
        dropdownParent: $('body'),
        width: '100%',
        minimumInputLength: 0,

        ajax: {
            url: `${API}?CONTROL=${control}`,
            dataType: 'json',
            delay: 400,
            cache: true,

            data: params => ({
                search: params.term || ''
            }),

            processResults: (response, params) => {

                const searchTerm = params.term || '';

                // دعم لو الـ API رجعت Array أو Object فيه results/data
                let data =
                    response?.results ||
                    response?.data ||
                    response;

                // حفظ فى الكاش
                searchCache[control][searchTerm] = data;

                return {
                    results: Array.isArray(data)
                        ? data.map(item => ({
                            id: item.id,
                            text: `${item.name || ''} - ${item.phone || ''}`
                        }))
                        : []
                };
            },

            transport: (settings, success, failure) => {

                const searchTerm = settings.data.search || '';

                // من الكاش
                if (searchCache[control][searchTerm]) {
                    success(searchCache[control][searchTerm]);
                    return;
                }

                // من السيرفر
                $.ajax(settings)
                    .then(success)
                    .fail(failure);
            }
        }
    });
} 

/*==================================================
=            LOAD BALANCE (مع مؤشر التحميل)
==================================================*/
function showBalanceLoading(targetId) {
    $(targetId).html('<i class="fas fa-spinner fa-spin"></i> جارى التحميل...');
}

function resetBalance(targetId) {
    $(targetId).text('0.00');
}

async function loadPartnerBalance(partnerId, type, targetId, amountFieldId = null) {
    if (!partnerId) {
        resetBalance(targetId);
        if (amountFieldId) $(amountFieldId).attr('max', 0);
        return;
    }
    
    // إظهار مؤشر التحميل
    showBalanceLoading(targetId);
    
    const cacheKey = `${type}_${partnerId}`;
    let balance = balanceCache[cacheKey];
    
    if (balance === undefined) {
        const data = await request(API, {
            CONTROL: 'GetPartnersBalance',
            pid: partnerId,
            pt: type
        });
        if (data && data[0]) {
            balance = parseFloat(data[0].total_balance) || 0;
            balanceCache[cacheKey] = balance;
        } else {
            balance = 0;
        }
    }
    
    // تحديث الواجهة
    $(targetId).text(formatMoney(balance));
    if (amountFieldId) {
        $(amountFieldId).attr('max', balance);
        let currentVal = parseFloat($(amountFieldId).val()) || 0;
        if (currentVal > balance) $(amountFieldId).val(balance);
    }
}

/*==================================================
= SUPPLIER TABLE & AUTO DISTRIBUTION
==================================================*/
function loadSupplierOrders(supplierId) {
    if (!supplierId) return;
    if (supplierOrdersTable) {
        supplierOrdersTable.destroy();
        supplierOrdersTable = null;
    }
    $('#supplierOrdersTable tbody').empty();
    supplierOrdersTable = $('#supplierOrdersTable').DataTable({
        dom: 't',
        paging: false,
	    searching: false,
	    ordering: false,
	    info: false,
	    lengthChange: false,
	    processing: true,
	    serverSide: false,
	    responsive: false,
	    autoWidth: false,
	    
    	
        
        
        
        pageLength: -1,
        ajax: {
            url: API,
            type: 'POST',
            data: { CONTROL: 'GetSupplierOrderList', supplierId }
        },
        columns: [
            { data: null, render: () => '<input type="checkbox" class="order-check">' },
            { data: 'ORDER_ID' },
            { data: 'CREATE_AT' },
            { data: 'TOTAL_PRICE', render: d => formatMoney(d) },
            { data: 'RETURN_TOTAL', render: d => formatMoney(d) },
            { data: 'TOTAL_PAYMENTS', render: d => formatMoney(d) },
            { data: 'REMAIN', render: d => `<span class="text-danger">${formatMoney(d)}</span>` },
            {
                data: null,
                render: data => `<input type="number" class="form-control payment-input " style="width:140px"
                    data-order="${data.ORDER_ID}"
                    data-remain="${data.REMAIN}"
                    value="0" min="0" max="${data.REMAIN}" step="0.01">`
            }
        ],
        columnDefs: [
	        {
	            targets: [0,1,2,3,4,5,6,7], // أرقام الأعمدة
	            className: 'text-nowrap'
	        }
	    ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
        drawCallback: () => calculateSupplierTotal()
    });
}

function calculateSupplierTotal() {
    let total = 0;
    $('.payment-input').each(function() {
        let val = parseFloat($(this).val()) || 0;
        total += val;
    });
    // تنسيق المبلغ دون تغيير محتوى حقل الإدخال إن كان المستخدم يكتب فيه
    let formattedTotal = formatMoney(total);
    $('#current-payment-total').text(formattedTotal);
    
    // تحديث حقل المبلغ الرئيسي فقط إذا لم يكن المستخدم يكتب فيه حاليًا
    let amountInput = $('#amount-morad');
    if (!amountInput.is(':focus')) {
        amountInput.val(formattedTotal);
    }
}

function autoDistributeSupplierPayment(amount) {

    let rawAmount = amount.toString().replace(',', '.');

    let numericAmount = parseFloat(rawAmount);

    if (isNaN(numericAmount)) {
        numericAmount = 0;
    }

    numericAmount = parseFloat(
        numericAmount.toFixed(2)
    );

    let totalAvailable = $('#total-morad').text();
	
    /*$('.payment-input').each(function () {

        totalAvailable += parseFloat(
            $(this).data('remain')
        ) || 0;

    });*/

    /*==============================
    =            max balance
    ==============================*/

    if (numericAmount > totalAvailable) {

        showAlert(
            'warning',
            'تنبيه',
            `المبلغ يتجاوز إجمالى المستحق (${formatMoney(totalAvailable)})`
        );

        numericAmount = totalAvailable;

        /*$('#amount-morad').val(
            formatMoney(totalAvailable)
        );*/

    }

    /*==============================
    =            update total
    ==============================*/

    $('#current-payment-total').text(
        formatMoney(numericAmount)
    );

}
/*==================================================
=            CUSTOMER RETURN (مع جلب الرصيد)
==================================================*/
function initCustomerReturnSection() {
    let clientSelect = $('#select-client');
    let orderSelect = $('#select-order');

    initPartnerSelect2('#select-client', 'GetClientData_in', 'اختر العميل');

    orderSelect.select2({
        placeholder: 'اختر أمر البيع',
        allowClear: false,
        dir: "rtl",
        language: "ar",
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: API,
            dataType: 'json',
            delay: 600,
            cache: true,
            data: function(params) {
                let clientId = clientSelect.val();
                if (!clientId) return false;
                return {
                    CONTROL: 'GetClientdirctOrder',
                    cid: clientId,
                    search: params.term || ''
                };
            },
            processResults: function(data) {
                return {
                    results: data.map(order => ({
                        id: order.id,
                        text: order.name
                    }))
                };
            }
        }
    });

    clientSelect.off('change').on('change', function() {
        orderSelect.val(null).trigger('change');
        $('#return-amount').text('0.00');
        orderSelect.html('<option value=""></option>');
        orderSelect.trigger('change.select2');
    });

    orderSelect.off('change').on('change', async function() {
        let orderId = $(this).val();
        $('#return-amount').text('0.00');
        if (!orderId) return;
        
        $('#return-amount').html('<i class="fas fa-spinner fa-spin"></i>');
        try {
            const data = await request(API, {
                CONTROL: 'GetDirectBalance',
                oid: orderId
            });
            let balance = (data && data[0] && data[0].total_balance) ? data[0].total_balance : 0;
            $('#return-amount').text(formatMoney(balance));
        } catch(e) {
            console.error(e);
            $('#return-amount').text('0.00');
        }
    });
}

/*==================================================
=            SAVE CUSTOMER RETURN
==================================================*/
async function saveFinancialReturn(orderId) {
    if (!orderId || orderId <= 0) {
        showAlert('warning', 'نأسف', 'لم تقم بإختيار أمر البيع أولاً');
        return;
    }
    showLoading('جارى الحفظ...');
    try {
        const data = await request(API_FINANCE, {
            CONTROL: 'PaymentOfCustomerDirect',
            oid: orderId
        }, 'POST');
        if (!data) return;
        if (data[0] === 'success') {
        	const cacheKey = `${'CLIENT'}_${$('#select-order').val()}`;
            delete balanceCache[cacheKey];
            $('#select-order').val(null).trigger('change').html('<option value=""></option>');
            $('#select-client').val(null).trigger('change').html('<option value=""></option>');
            $('#return-amount').text('0.00');
            
        }
       
        showAlert(data[0], data[1], data[2]);
    } catch (err) {
        showAlert('error', 'خطأ', err.message);
    } finally {
        hideLoading();
    }
}

/*==================================================
=            SAVE SUPPLIER PAYMENT
==================================================*/

async function saveSupplierPayment(){

    const supplierId = $('#select-morad').val();
    const totalAmount = parseFloat($('#amount-morad').val()) || 0;

    if(!supplierId){
		showAlert('warning','تنبيه','قم بإختيار المورد');
        return;
    }
    if(totalAmount <= 0){
        showAlert('warning','تنبيه','قم بإدخال مبلغ صحيح');
        return;
    }

    let payload = {
        CONTROL : 'SaveSupplierPayment',
        supplier_id : supplierId,
        total_amount : totalAmount
    };

    let index = 0;

	$('#supplierOrdersTable .payment-input').each(function(){
	
	    let orderId = $(this).data('order');
	
	    let amount = parseFloat($(this).val()) || 0;
	
	    if(amount <= 0) return;
	
	    payload[`orders[${index}][orderId]`] = orderId;
	
	    payload[`orders[${index}][amount]`] = amount;
	
	    index++;
	
	});
	
   /* if(index === 0){
        showAlert('warning','تنبيه','لا يوجد أوامر للصرف');
        return;
    }*/

    showLoading('جارى حفظ الصرف...');

    try{
		const data = await request(API_FINANCE, payload, 'POST');
        hideLoading();
        showAlert(data[0],data[1],data[2]);
       
        const cacheKey = `${'SUPPLIER'}_${$('#select-morad').val()}`;
        delete balanceCache[cacheKey];
        $('#select-morad').val(null).trigger('change').html('<option value=""></option>');
                
            
    }catch(error){
        hideLoading();
        showAlert('error','خطأ',error.message);
    }

}

/*==================================================
=            SAVE MARKETER PAYMENT
==================================================*/

async function saveMarketerPayment(){

    const marketerId = $('#select-masouq').val();
	const totalbalance = $("#total-masouq").text();
    const amount = parseFloat(
        $('#amount-masouq').val()
    ) || 0;

    /*==============================
    =            validation
    ==============================*/

    if(!marketerId){

        showAlert(
            'warning',
            'تنبيه',
            'قم بإختيار المسوق'
        );

        return;

    }

    if(amount <= 0){

        showAlert(
            'warning',
            'تنبيه',
            'قم بإدخال مبلغ صحيح'
        );

        return;

    }
    if(amount > totalbalance){

        showAlert(
            'warning',
            'تنبيه',
            'المبلغ الذى ادخلته اكبر من مستحقات المسوق'
        );
		$('#amount-masouq').val(totalbalance);
        return;

    }

    /*==============================
    =            payload
    ==============================*/

    const payload = {

        CONTROL : 'PaymentOfMarketerDues',
        partner_id : marketerId,
        balance : totalbalance,
        amount : amount

    };

    showLoading('جارى حفظ عملية الصرف...');

    try{

        const data = await request(
            API_FINANCE,
            payload,
            'POST'
        );

        hideLoading();

        if(!data)
            return;

        showAlert(
            data[0],
            data[1],
            data[2]
        );

        /*==============================
        =            success
        ==============================*/

        if(data[0] === 'success'){
			$('#select-masouq').val(null).trigger('change').html('<option value=""></option>');
            $('#amount-masouq').val('');
			
            delete balanceCache[
                `MARKETER_${marketerId}`
            ];

            /*loadPartnerBalance(
                marketerId,
                'MARKETER',
                '#total-masouq',
                '#amount-masouq'
            );*/

        }

    }catch(error){

        hideLoading();

        showAlert(
            'error',
            'خطأ',
            error.message
        );

    }

}

/*==================================================
=            SAVE DELIVERY PAYMENT
==================================================*/

async function saveDeliveryPayment(){

    const deliveryId = $('#select-mandob').val();
    const totalbalance = $("#total-mandob").text();
    const amount = parseFloat(
        $('#amount-mandob').val()
    ) || 0;

    /*==============================
    =            validation
    ==============================*/

    if(!deliveryId){

        showAlert(
            'warning',
            'تنبيه',
            'قم بإختيار المندوب'
        );

        return;

    }

    if(amount <= 0){

        showAlert(
            'warning',
            'تنبيه',
            'قم بإدخال مبلغ صحيح'
        );

        return;

    }
    if(amount > totalbalance){

        showAlert(
            'warning',
            'تنبيه',
            'المبلغ المراد صرفه اكبر من مستحقات المندوب'
        );
		$('#amount-mandob').val(totalbalance);
        return;

    }

    /*==============================
    =            payload
    ==============================*/

    const payload = {

        CONTROL : 'PaymentOfDeliveryDues',
        partner_id : deliveryId,
		balance :totalbalance,
        amount : amount

    };

    showLoading('جارى حفظ عملية الصرف...');

    try{

        const data = await request(
            API_FINANCE,
            payload,
            'POST'
        );

        hideLoading();

        if(!data)
            return;

        showAlert(
            data[0],
            data[1],
            data[2]
        );

        /*==============================
        =            success
        ==============================*/

        if(data[0] === 'success'){
			$('#select-mandob').val(null).trigger('change').html('<option value=""></option>');
            $('#amount-mandob').val('');

            delete balanceCache[
                `DELIVERY_${deliveryId}`
            ];
            
            /*loadPartnerBalance(
                deliveryId,
                'DELIVERY',
                '#total-mandob',
                '#amount-mandob'
            );*/

        }

    }catch(error){

        hideLoading();

        showAlert(
            'error',
            'خطأ',
            error.message
        );

    }

}

/*==================================================
=            DOCUMENT READY
==================================================*/
$(function() {
    initBackButton();

    // تهيئة المندوب والمسوق والمورد
    initPartnerSelect2('#select-mandob', 'GetDeliveryData', 'اختر المندوب');
    initPartnerSelect2('#select-masouq', 'GetMasouqData', 'اختر المسوق');
    initPartnerSelect2('#select-morad', 'GetMoradData', 'اختر المورد');
	
    // أحداث تحميل الرصيد مع مؤشر التحميل
    $('#select-mandob').on('change', function() {
        loadPartnerBalance($(this).val(), 'DELIVERY', '#total-mandob', '#amount-mandob');
    });
    $('#select-masouq').on('change', function() {
        loadPartnerBalance($(this).val(), 'MARKETER', '#total-masouq', '#amount-masouq');
    });
    $('#select-morad').on('change', async function() {
        const sid = $(this).val();
        $('.payment-input').val(0).change();
        $('#amount-morad').val(0).change();
        await loadPartnerBalance(sid, 'SUPPLIER', '#total-morad', '#total-morad');
        sidnaw = !sid ? -1 : sid;
        loadSupplierOrders(sidnaw);
    });

    // أحداث جدول المورد والتوزيع
    $(document).on('input', '.payment-input', function() {
        let remain = parseFloat($(this).data('remain')) || 0;
        let val = parseFloat($(this).val()) || 0;
        if (val > remain) val = remain;
        $(this).val(val);
        $(this).closest('tr').find('.order-check').prop('checked', val > 0);
        calculateSupplierTotal();
    });
    $(document).on('change', '.order-check', function() {
        const row = $(this).closest('tr');
        const input = row.find('.payment-input');
        const remain = parseFloat(input.data('remain')) || 0;
        input.val($(this).is(':checked') ? remain : 0);
        calculateSupplierTotal();
    });
    // بدلاً من الربط بـ 'input' فقط، نستخدم معالجة محسنة
	$('#amount-morad').on('input', function() {
	    let rawValue = $(this).val();
	    // السماح فقط بالأرقام والنقطة أو الفاصلة
	    rawValue = rawValue.replace(/[^0-9.,]/g, '');
	    // استبدال الفاصلة بنقطة
	    rawValue = rawValue.replace(',', '.');
	    // منع أكثر من نقطة
	    let parts = rawValue.split('.');
	    if (parts.length > 2) {
	        rawValue = parts[0] + '.' + parts.slice(1).join('');
	    }
	    $(this).val(rawValue);
	    
	    // تحويل إلى رقم وتوزيع
	    let numeric = parseFloat(rawValue);
	    if (!isNaN(numeric)) {
	        autoDistributeSupplierPayment(numeric);
	    } else if (rawValue === '') {
	        autoDistributeSupplierPayment(0);
	    }
	});

    // قسم العميل والمرتجعات
    initCustomerReturnSection();

    // أزرار الحفظ
    $('#saveFinancialReturn').on('click', function() {
        saveFinancialReturn($('#select-order').val());
    });

    // أزرار إظهار النماذج
    $('#btn-mandob').click(() => showForm('mandob'));
    $('#btn-masouq').click(() => showForm('masouq'));
    $('#btn-morad').click(() => showForm('morad'));
    $('#btn-client-return').click(() => {
        $('#select-client').val(null).trigger('change');
        $('#select-order').val(null).trigger('change');
        $('#return-amount').text('0.00');
        showForm('client-return');
    });
    $('#btn-open').click(() => showForm('open'));

    // التحقق من صحة المبلغ في الصرف المفتوح
    $('#amount-open').on('input', function() {
        let amount = parseFloat($(this).val());
        if (isNaN(amount) || amount <= 0) {
            // يمكن إضافة تنبيه اختياري
        }
    });
    $('#amount-morad').on('focus', function () {
	    $(this).select();
	});
});