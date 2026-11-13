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
function backToTiles() {
    $('#metro-container').show();
    $('.form-container').removeClass('active');   // بدون hide()
    $('#btnBack').hide();
    if (supplierOrdersTable) {
        supplierOrdersTable.clear().draw();
    }
    $('.payment-input').val(0);
    $('.order-check').prop('checked', false);
}

function showForm(formType) {
    $('#metro-container').hide();
    $('.form-container').removeClass('active').hide();
    $('#form-' + formType).addClass('active').show();
    if (!$('#metro-container').is(':visible')) {
        $('#btnBack').show();
    }
}

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

/*function autoDistributeSupplierPayment(amount) {
    // تحويل أي فاصلة إلى نقطة، ثم تحويل إلى رقم
    let rawAmount = amount.toString().replace(',', '.');
    let numericAmount = parseFloat(rawAmount);
    if (isNaN(numericAmount)) numericAmount = 0;
    
    numericAmount = parseFloat(numericAmount.toFixed(2)); // التقريب لأقرب سنت
    
    // إذا كان المبلغ صفراً أو أقل نعيد التعيين
    if (numericAmount <= 0) {
        $('.payment-input').val(0);
        $('.order-check').prop('checked', false);
        calculateSupplierTotal();
        return;
    }
    
    // جمع كل الصفوف وفرزها حسب رقم الأمر
    let inputs = [];
    $('.payment-input').each(function() {
        inputs.push({
            el: $(this),
            orderId: parseInt($(this).data('order')) || 0,
            remain: parseFloat($(this).data('remain')) || 0
        });
    });
    inputs.sort((a, b) => a.orderId - b.orderId);
    
    let remaining = numericAmount;
    let totalAvailable = inputs.reduce((sum, item) => sum + item.remain, 0);
    
    // إذا كان المبلغ المدخل أكبر من إجمالي المستحق، نعرض تحذيراً ونحد المبلغ
    if (numericAmount > totalAvailable) {
        showAlert('warning', 'تنبيه', `المبلغ المطلوب (${formatMoney(numericAmount)}) يتجاوز إجمالي المستحق (${formatMoney(totalAvailable)}). سيتم توزيع ${formatMoney(totalAvailable)} فقط.`);
        remaining = totalAvailable;
        $('#amount-morad').val(formatMoney(totalAvailable));
    }
    
    // إعادة تعيين كل الحقول
    $('.payment-input').val(0);
    $('.order-check').prop('checked', false);
    
    // التوزيع
    for (let item of inputs) {
        if (remaining <= 0) break;
        const row = item.el.closest('tr');
        if (remaining >= item.remain) {
            item.el.val(item.remain);
            row.find('.order-check').prop('checked', true);
            remaining -= item.remain;
        } else {
            item.el.val(remaining);
            row.find('.order-check').prop('checked', true);
            remaining = 0;
        }
    }
    calculateSupplierTotal();
}*/

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
= CUSTOMER RETURN (مع جلب الرصيد)
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
                    CONTROL: 'GetClientRetrnOrder',
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
                CONTROL: 'GetRefundBalance',
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
            CONTROL: 'PaymentOfCustomerDues',
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