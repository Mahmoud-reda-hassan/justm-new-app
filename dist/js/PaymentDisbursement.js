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
    // القوائم تعتمد على الأرصدة الحالية: لا نستخدم نتائج بحث قديمة
    Object.keys(searchCache).forEach(k => { searchCache[k] = {}; });
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
    // الرصيد يتغير من شاشات أخرى (أوامر شراء/مرتجعات): لا نعتمد على نسخة قديمة محفوظة في الصفحة
    delete balanceCache[cacheKey];
    let balance = balanceCache[cacheKey];
    
    if (balance === undefined) {
        const data = await request(API, {
            CONTROL: 'GetPartnersBalance',
            pid: partnerId,
            pt: type,
            _t: Date.now()
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
= SUPPLIER PURCHASE ORDERS (صرف للمورد)
==================================================*/
let payMax = 0;   // ما تدين به الشركة للمورد (الحد الأقصى للصرف)

async function loadPayOrders(supplierId) {
    const tb = $('#payOrdersTable tbody');
    $('#payCheckAll').prop('checked', false);
    if (!supplierId) { tb.html('<tr><td colspan="8" class="text-center text-muted">اختر المورد</td></tr>'); return; }
    tb.html('<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i></td></tr>');
    const res = await request(API, { CONTROL: 'GetSupplierOrderList', supplierId }, 'POST');
    const rows = (res && res.data) ? res.data : [];
    if (!rows.length) { tb.html('<tr><td colspan="8" class="text-center text-muted">لا توجد أوامر شراء غير مسددة (قد يكون المستحق رصيد أول مدة)</td></tr>'); return; }
    tb.html(rows.map(r => `<tr>
        <td><input type="checkbox" class="pay-check"></td>
        <td>${r.ORDER_ID}</td><td>${r.CREATE_AT}</td>
        <td>${formatMoney(r.TOTAL_PRICE)}</td><td>${formatMoney(r.RETURN_TOTAL)}</td><td>${formatMoney(r.TOTAL_PAYMENTS)}</td>
        <td><span class="text-danger">${formatMoney(r.REMAIN)}</span></td>
        <td><input type="number" class="form-control pay-input" style="width:140px" data-order="${r.ORDER_ID}" data-remain="${r.REMAIN}" value="0" min="0" max="${r.REMAIN}" step="0.01"></td>
    </tr>`).join(''));
}

function syncPayTotal() {
    let total = 0;
    $('#payOrdersTable .pay-input').each(function() { total += parseFloat($(this).val()) || 0; });
    const amountInput = $('#amount-morad');
    if (!amountInput.is(':focus')) amountInput.val(total ? total.toFixed(2) : 0);
}

function distributePay(amount) {
    if (amount > payMax) {
        showAlert('warning', 'تنبيه', `المبلغ يتجاوز إجمالي المستحق للمورد (${formatMoney(payMax)})`);
        amount = payMax;
        $('#amount-morad').val(amount);
    }
    let left = Math.round(amount * 100) / 100;
    $('#payOrdersTable .pay-input').each(function() {
        const remain = parseFloat($(this).data('remain')) || 0;
        const take = Math.max(0, Math.min(left, remain));
        $(this).val(take);
        $(this).closest('tr').find('.pay-check').prop('checked', take > 0);
        left = Math.round((left - take) * 100) / 100;
    });
}

/*==================================================
= CUSTOMER RETURN (مع جلب الرصيد)
==================================================*/
function initCustomerReturnSection() {
    let clientSelect = $('#select-client');
    initPartnerSelect2('#select-client', 'GetClientReturnDueData', 'اختر عميلاً له مرتجعات مستحقة');

    clientSelect.off('change').on('change', function() {
        loadReturnOrders($(this).val());
    });

    // تحديد يدوي
    $(document).on('change', '.return-check', function() { syncReturnTotal(); });
    $('#returnCheckAll').on('change', function() {
        $('#returnOrdersTable .return-check').prop('checked', $(this).is(':checked'));
        syncReturnTotal();
    });
    // كتابة مبلغ => تحديد الأوامر الأقدم فالأحدث التي يغطيها المبلغ بالكامل
    $('#amount-return').on('input', function() {
        const amount = parseFloat($(this).val()) || 0;
        let left = Math.round(amount * 100) / 100;
        $('#returnOrdersTable .return-check').each(function() {
            const v = parseFloat($(this).data('amount')) || 0;
            const ok = v > 0 && v <= left + 0.005;
            $(this).prop('checked', ok);
            if (ok) left = Math.round((left - v) * 100) / 100;
        });
        let total = 0;
        $('#returnOrdersTable .return-check:checked').each(function() { total += parseFloat($(this).data('amount')) || 0; });
        $('#return-amount').text(formatMoney(total));
        if (amount > 0 && left > 0.004) {
            showAlert('warning', 'تنبيه', `المتبقي ${formatMoney(left)} لا يكفي لصرف أمر مرتجع كامل (كل أمر يُصرف كاملًا)`);
        }
    });
}

async function loadReturnOrders(clientId) {
    const tb = $('#returnOrdersTable tbody');
    $('#returnCheckAll').prop('checked', false);
    $('#amount-return').val('');
    $('#return-amount').text('0.00');
    $('#return-due-total').text('0.00');
    if (!clientId) { tb.html('<tr><td colspan="4" class="text-center text-muted">اختر العميل</td></tr>'); return; }
    tb.html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i></td></tr>');
    const res = await request(API, { CONTROL: 'GetClientReturnOrdersDue', cid: clientId, _t: Date.now() });
    const rows = (res && res.data) ? res.data : [];
    if (!rows.length) { tb.html('<tr><td colspan="4" class="text-center text-muted">لا توجد مرتجعات مستحقة الصرف لهذا العميل</td></tr>'); return; }
    let due = 0;
    tb.html(rows.map(r => { due += parseFloat(r.amount) || 0; return `<tr>
        <td><input type="checkbox" class="return-check" data-order="${r.id}" data-amount="${r.amount}"></td>
        <td>${r.name || r.id}</td><td>${r.last_update || ''}</td><td>${formatMoney(r.amount)}</td></tr>`; }).join(''));
    $('#return-due-total').text(formatMoney(due));
}

function syncReturnTotal() {
    let total = 0;
    $('#returnOrdersTable .return-check:checked').each(function() { total += parseFloat($(this).data('amount')) || 0; });
    $('#return-amount').text(formatMoney(total));
    $('#amount-return').val(total ? total.toFixed(2) : '');
}

/*==================================================
=            SAVE CUSTOMER RETURN
==================================================*/
async function saveFinancialReturn() {
    const ids = $('#returnOrdersTable .return-check:checked').map(function() { return $(this).data('order'); }).get();
    if (!ids.length) {
        showAlert('warning', 'نأسف', 'لم تقم بإختيار أمر مرتجع أولاً');
        return;
    }
    showLoading('جارى الحفظ...');
    let done = 0, failMsg = null;
    try {
        for (const oid of ids) {
            const data = await request(API_FINANCE, { CONTROL: 'PaymentOfCustomerDues', oid }, 'POST');
            if (data && data[0] === 'success') { done++; continue; }
            failMsg = (data && data[2]) ? `أمر رقم ${oid}: ${data[2]}` : `تعذر صرف الأمر رقم ${oid}`;
            break;
        }
    } catch (err) {
        failMsg = err.message;
    } finally {
        hideLoading();
    }
    if (failMsg) {
        showAlert('error', 'تم صرف ' + done + ' من ' + ids.length, failMsg);
    } else {
        showAlert('success', 'ناجح', `تم صرف ${done} أمر مرتجع للعميل`);
    }
    await loadReturnOrders($('#select-client').val());
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

	$('#payOrdersTable .pay-input').each(function(){
	
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
    initPartnerSelect2('#select-mandob', 'GetDeliveryDueData', 'اختر مندوباً له مستحقات');
    initPartnerSelect2('#select-masouq', 'GetMasouqDueData', 'اختر مسوقاً له مستحقات');
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
        payMax = sid ? Math.max(0, parseFloat(balanceCache['SUPPLIER_' + sid]) || 0) : 0;
        loadPayOrders(sid);
    });

    // أحداث جدول أوامر الشراء (توزيع تلقائي من الأقدم للأحدث أو تحديد يدوي)
    $(document).on('input', '.pay-input', function() {
        let remain = parseFloat($(this).data('remain')) || 0;
        let val = parseFloat($(this).val()) || 0;
        if (val > remain) val = remain;
        if (val < 0) val = 0;
        $(this).val(val);
        $(this).closest('tr').find('.pay-check').prop('checked', val > 0);
        syncPayTotal();
    });
    $(document).on('change', '.pay-check', function() {
        const input = $(this).closest('tr').find('.pay-input');
        input.val($(this).is(':checked') ? (parseFloat(input.data('remain')) || 0) : 0);
        syncPayTotal();
    });
    $('#payCheckAll').on('change', function() {
        const on = $(this).is(':checked');
        $('#payOrdersTable .pay-check').prop('checked', on).each(function() {
            const input = $(this).closest('tr').find('.pay-input');
            input.val(on ? (parseFloat(input.data('remain')) || 0) : 0);
        });
        syncPayTotal();
    });
    // كتابة مبلغ => يوزَّع تلقائيًا من الأقدم للأحدث
    $('#amount-morad').on('input', function() {
        let raw = $(this).val().replace(/[^0-9.,]/g, '').replace(',', '.');
        const parts = raw.split('.');
        if (parts.length > 2) raw = parts[0] + '.' + parts.slice(1).join('');
        $(this).val(raw);
        distributePay(parseFloat(raw) || 0);
    });

    // قسم العميل والمرتجعات
    initCustomerReturnSection();

    // أزرار الحفظ
    $('#saveFinancialReturn').on('click', function() {
        saveFinancialReturn();
    });

    // أزرار إظهار النماذج
    $('#btn-mandob').click(() => showForm('mandob'));
    $('#btn-masouq').click(() => showForm('masouq'));
    $('#btn-morad').click(() => showForm('morad'));
    $('#btn-client-return').click(() => {
        $('#select-client').val(null).trigger('change');
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