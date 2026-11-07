/*==================================================
=            GLOBAL VARIABLES
==================================================*/
let supplierOrdersTable = null;
let supplierPaymentRequestKey = null;
let supplierAdvanceRequestKey = null;
let supplierAdvanceRequestFingerprint = null;
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
const API = '../../inc/controller/PaymentDisbursement.control.php';
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
            if (type === 'SUPPLIER' && Object.prototype.hasOwnProperty.call(data[0], 'gl_balance')) {
                balance = {
                    legacy: parseFloat(data[0].legacy_balance) || 0,
                    gl: parseFloat(data[0].gl_balance) || 0
                };
            } else {
                balance = parseFloat(data[0].total_balance) || 0;
            }
            balanceCache[cacheKey] = balance;
        } else {
            balance = 0;
        }
    }

    // لا نخلط رصيد المورد التاريخي مع حركات GL قبل اعتماد رصيد افتتاحي.
    if (type === 'SUPPLIER' && balance && typeof balance === 'object') {
        $(targetId).text('قديم: ' + formatMoney(balance.legacy) + ' | GL: ' + formatMoney(balance.gl));
        return;
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
=            SUPPLIER TABLE & AUTO DISTRIBUTION
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
            },
            {
                data: null,
                render: data => `<input type="number" class="form-control advance-input" style="width:140px"
                    data-order="${data.ORDER_ID}" data-remain="${data.REMAIN}"
                    value="0" min="0" max="${data.REMAIN}" step="0.01">`
            }
        ],
        columnDefs: [
	        {
	            targets: [0,1,2,3,4,5,6,7,8], // أرقام الأعمدة
	            className: 'text-nowrap'
	        }
	    ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
        drawCallback: () => autoDistributeSupplierPayment($('#amount-morad').val() || 0)
    });
    request(API, { CONTROL: 'GetSupplierAdvanceBalance', supplierId }, 'POST')
        .then(data => $('#supplier-existing-advance').text(formatMoney(data?.available_advance ?? data?.data?.available_advance ?? 0)))
        .catch(() => $('#supplier-existing-advance').text('تعذر التحميل'));
}

function calculateSupplierTotal() {
    let total = 0;
    $('#supplierOrdersTable .payment-input').each(function() {
        total += parseFloat($(this).val()) || 0;
    });
    total = Math.round(total * 100) / 100;
    $('#current-payment-total').text(formatMoney(total));
    const fullAmount = parseFloat(String($('#amount-morad').val() || '0').replace(',', '.')) || 0;
    $('#supplier-advance-total').text(formatMoney(Math.max(0, fullAmount - total)));
}

function autoDistributeSupplierPayment(amount) {
    let numericAmount = parseFloat(String(amount).replace(',', '.'));
    if (!Number.isFinite(numericAmount) || numericAmount < 0) numericAmount = 0;
    numericAmount = Math.round(numericAmount * 100) / 100;

    const inputs = [];
    $('#supplierOrdersTable .payment-input').each(function () {
        inputs.push({
            el: $(this),
            orderId: parseInt($(this).data('order'), 10) || 0,
            remain: Math.max(0, parseFloat($(this).data('remain')) || 0)
        });
    });
    inputs.sort((a, b) => a.orderId - b.orderId);

    inputs.forEach(item => {
        item.el.val(0);
        item.el.closest('tr').find('.order-check').prop('checked', false);
    });

    let remaining = numericAmount;
    let allocated = 0;
    for (const item of inputs) {
        if (remaining <= 0) break;
        const value = Math.min(remaining, item.remain);
        if (value > 0) {
            item.el.val(value.toFixed(2));
            item.el.closest('tr').find('.order-check').prop('checked', true);
            allocated = Math.round((allocated + value) * 100) / 100;
            remaining = Math.round((remaining - value) * 100) / 100;
        }
    }

    $('#current-payment-total').text(formatMoney(allocated));
    $('#supplier-advance-total').text(formatMoney(Math.max(0, remaining)));
    calculateSupplierTotal();
}

async function applySupplierAdvances(mode) {
    const supplierId = Number($('#select-morad').val() || 0);
    if (!supplierId) return showAlert('warning', 'تنبيه', 'اختر المورد أولًا');
    const orders = [];
    if (mode === 'MANUAL') {
        $('#supplierOrdersTable .advance-input').each(function () {
            const amount = Math.round((parseFloat($(this).val()) || 0) * 100) / 100;
            if (amount > 0) orders.push({ orderId: Number($(this).data('order')), amount });
        });
        if (!orders.length) return showAlert('warning', 'تنبيه', 'حدد مبالغ المقدم في الفواتير أولًا');
    }
    const requestFingerprint = JSON.stringify([supplierId, mode, orders]);
    if (!supplierAdvanceRequestKey || supplierAdvanceRequestFingerprint !== requestFingerprint) {
        supplierAdvanceRequestKey = 'SADV-' + (window.crypto?.randomUUID ? window.crypto.randomUUID().replace(/-/g, '') : Date.now().toString(36) + Math.random().toString(36).slice(2));
        supplierAdvanceRequestFingerprint = requestFingerprint;
    }
    const payload = { CONTROL: 'ApplySupplierAdvances', supplier_id: supplierId, mode, request_key: supplierAdvanceRequestKey };
    orders.forEach((item, index) => { payload['orders[' + index + '][orderId]'] = item.orderId; payload['orders[' + index + '][amount]'] = item.amount.toFixed(2); });
    showLoading('جارٍ تطبيق مقدم المورد...');
    try {
        const result = await request(API_FINANCE, payload, 'POST');
        hideLoading();
        showAlert(result[0] || 'error', result[1] || 'خطأ', result[2] || 'تعذر تطبيق المقدم');
        if (result[0] === 'success') {
            supplierAdvanceRequestKey = null;
            supplierAdvanceRequestFingerprint = null;
            delete balanceCache['SUPPLIER_' + supplierId];
            if (supplierOrdersTable) supplierOrdersTable.ajax.reload();
            request(API, { CONTROL: 'GetSupplierAdvanceBalance', supplierId }, 'POST')
                .then(data => $('#supplier-existing-advance').text(formatMoney(data?.available_advance ?? 0)));
        }
    } catch (error) { hideLoading(); showAlert('error', 'خطأ', error.message); }
}

/*==================================================
=            CUSTOMER RETURN (مع جلب الرصيد)
==================================================*/
function initCustomerReturnSection() {
    let clientSelect = $('#select-client');
    let orderSelect = $('#select-order');

    initPartnerSelect2('#select-client', 'GetClientData', 'اختر العميل');

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
    const paymentMethodId = Number($('#refund-payment-method').val() || 0);
    const amount = Number($('#return-amount').text() || 0);
    if (!orderId || Number(orderId) <= 0) {
        showAlert('warning', 'تنبيه', 'لم تقم بإختيار أمر البيع أولاً');
        return;
    }
    if (!paymentMethodId) {
        showAlert('warning', 'تنبيه', 'اختر طريقة رد المبلغ');
        return;
    }
    if (!Number.isFinite(amount) || amount <= 0) {
        showAlert('warning', 'تنبيه', 'لا يوجد رصيد مرتجع متاح للصرف');
        return;
    }

    const requestKey = 'CRF-' + (window.crypto?.randomUUID
        ? window.crypto.randomUUID().replace(/-/g, '')
        : Date.now().toString(36) + Math.random().toString(36).slice(2));
    showLoading('جارى الحفظ...');
    try {
        const data = await request(API_FINANCE, {
            CONTROL: 'RefundCustomerReturn',
            oid: orderId,
            payment_method_id: paymentMethodId,
            amount: amount.toFixed(2),
            request_key: requestKey
        }, 'POST');
        hideLoading();
        if (!data) return;
        showAlert(data[0] || 'error', data[1] || 'خطأ', data[2] || 'لم يتم تأكيد رد المبلغ');
        if (data[0] === 'success') {
            const cacheKey = `CLIENT_${$('#select-client').val()}`;
            delete balanceCache[cacheKey];
            $('#select-order').val(null).trigger('change').html('<option value=""></option>');
            $('#select-client').val(null).trigger('change').html('<option value=""></option>');
            $('#refund-payment-method').val('');
            $('#return-amount').text('0.00');
        }
    } catch (error) {
        hideLoading();
        showAlert('error', 'خطأ', error.message);
    }
}

/*==================================================
=            SAVE SUPPLIER PAYMENT
==================================================*/

async function saveSupplierPayment(){

    const supplierId = $('#select-morad').val();
    const paymentMethodId = Number($('#supplier-payment-method').val() || 0);
    const totalAmount = parseFloat($('#amount-morad').val()) || 0;
    const note = $('#morad-note').val() || '';

    if(!supplierId){
        showAlert('warning','تنبيه','قم بإختيار المورد');
        return;
    }
    if(!paymentMethodId){
        showAlert('warning','تنبيه','اختر طريقة الدفع');
        return;
    }
    if(!Number.isFinite(totalAmount) || totalAmount <= 0){
        showAlert('warning','تنبيه','قم بإدخال مبلغ صحيح');
        return;
    }

    const paymentOrders = [];
    $('#supplierOrdersTable .payment-input').each(function(){
        const orderId = Number($(this).data('order') || 0);
        const amount = Number($(this).val() || 0);
        if(orderId > 0 && Number.isFinite(amount) && amount > 0){
            paymentOrders.push({orderId, amount: amount.toFixed(2)});
        }
    });
    paymentOrders.sort((a, b) => a.orderId - b.orderId);

    const requestFingerprint = JSON.stringify({
        supplierId: Number(supplierId),
        paymentMethodId,
        totalAmount: totalAmount.toFixed(2),
        note,
        orders: paymentOrders
    });
    if (!supplierPaymentRequestKey || supplierPaymentRequestFingerprint !== requestFingerprint) {
        supplierPaymentRequestKey = 'SUP-' + (window.crypto?.randomUUID
            ? window.crypto.randomUUID().replace(/-/g, '')
            : Date.now().toString(36) + Math.random().toString(36).slice(2));
        supplierPaymentRequestFingerprint = requestFingerprint;
    }

    const payload = {
        CONTROL: 'SaveSupplierPayment',
        supplier_id: supplierId,
        total_amount: totalAmount.toFixed(2),
        payment_method_id: paymentMethodId,
        request_key: supplierPaymentRequestKey,
        note
    };
    paymentOrders.forEach((item, index) => {
        payload[`orders[${index}][orderId]`] = item.orderId;
        payload[`orders[${index}][amount]`] = item.amount;
    });

    showLoading('جارى حفظ الصرف...');
    try{
        const data = await request(API_FINANCE, payload, 'POST');
        hideLoading();
        showAlert(data[0],data[1],data[2]);
        if (data[0] === 'success') {
            supplierPaymentRequestKey = null;
            supplierPaymentRequestFingerprint = null;
            $('#supplier-payment-method').val('');
            const cacheKey = `SUPPLIER_${supplierId}`;
            delete balanceCache[cacheKey];
            $('#select-morad').val(null).trigger('change').html('<option value=""></option>');
        }
    }catch(error){
        hideLoading();
        showAlert('error','خطأ',error.message);
    }
}

/*==================================================
=            MARKETER GL PAYMENT METHODS
==================================================*/
async function loadMarketerPaymentMethods() {
    const response = await request(API_FINANCE, {
        CONTROL: 'GetDeliverySettlementPaymentMethods'
    }, 'POST');
    const selects = ['#marketer-payment-method', '#delivery-payment-method', '#refund-payment-method', '#supplier-payment-method'];
    selects.forEach(selector => {
        const $select = $(selector);
        $select.empty().append($('<option>', { value: '', text: 'اختر طريقة الدفع' }));
        if (!response || response.success !== true) {
            $select.append($('<option>', { value: '', text: 'تعذر تحميل طرق الدفع' }));
            return;
        }
        (response.methods || []).forEach(method => {
            $select.append($('<option>', { value: method.id, text: method.name }));
        });
        if (!(response.methods || []).length) {
            $select.append($('<option>', { value: '', text: 'لا توجد طريقة دفع مرتبطة بحساب' }));
        }
    });
}
/*==================================================
=            SAVE MARKETER PAYMENT
==================================================*/

async function saveMarketerPayment() {
    const marketerId = Number($('#select-masouq').val() || 0);
    const paymentMethodId = Number($('#marketer-payment-method').val() || 0);
    const amount = Number($('#amount-masouq').val() || 0);
    const available = Number($('#total-masouq').text() || 0);

    if (!marketerId) return showAlert('warning', 'تنبيه', 'قم بإختيار المسوق');
    if (!paymentMethodId) return showAlert('warning', 'تنبيه', 'اختر طريقة صرف العمولة');
    if (!Number.isFinite(amount) || amount <= 0) return showAlert('warning', 'تنبيه', 'قم بإدخال مبلغ صحيح');
    if (amount - available > 0.009) {
        $('#amount-masouq').val(formatMoney(available));
        return showAlert('warning', 'تنبيه', 'المبلغ أكبر من مستحقات المسوق المتاحة في دفتر الأستاذ العام');
    }

    const requestKey = 'MCP-' + (window.crypto?.randomUUID
        ? window.crypto.randomUUID().replace(/-/g, '')
        : Date.now().toString(36) + Math.random().toString(36).slice(2));
    const payload = {
        CONTROL: 'PayMarketerCommission',
        partner_id: marketerId,
        payment_method_id: paymentMethodId,
        amount: amount.toFixed(2),
        request_key: requestKey
    };

    showLoading('جارى حفظ عملية الصرف...');
    try {
        const data = await request(API_FINANCE, payload, 'POST');
        hideLoading();
        if (!data) return;
        showAlert(data[0] || 'error', data[1] || 'خطأ', data[2] || 'لم يتم تأكيد عملية الصرف');
        if (data[0] === 'success') {
            $('#select-masouq').val(null).trigger('change').html('<option value="">اختر المسوق</option>');
            $('#marketer-payment-method').val('');
            $('#amount-masouq').val('');
            $('#total-masouq').text('0.00');
            delete balanceCache[`MARKETER_${marketerId}`];
        }
    } catch (error) {
        hideLoading();
        showAlert('error', 'خطأ', error.message);
    }
}

/*==================================================
=            SAVE DELIVERY PAYMENT
==================================================*/

async function saveDeliveryPayment() {
    const deliveryId = Number($('#select-mandob').val() || 0);
    const paymentMethodId = Number($('#delivery-payment-method').val() || 0);
    const amount = Number($('#amount-mandob').val() || 0);
    const available = Number($('#total-mandob').text() || 0);

    if (!deliveryId) return showAlert('warning', 'تنبيه', 'قم بإختيار المندوب');
    if (!paymentMethodId) return showAlert('warning', 'تنبيه', 'اختر طريقة صرف العمولة');
    if (!Number.isFinite(amount) || amount <= 0) return showAlert('warning', 'تنبيه', 'قم بإدخال مبلغ صحيح');
    if (amount - available > 0.009) {
        $('#amount-mandob').val(formatMoney(available));
        return showAlert('warning', 'تنبيه', 'المبلغ أكبر من عمولة المندوب المستحقة في دفتر الأستاذ العام');
    }

    const requestKey = 'DCP-' + (window.crypto?.randomUUID
        ? window.crypto.randomUUID().replace(/-/g, '')
        : Date.now().toString(36) + Math.random().toString(36).slice(2));
    showLoading('جارى حفظ عملية الصرف...');
    try {
        const data = await request(API_FINANCE, {
            CONTROL: 'PayDeliveryCommission',
            delivery_id: deliveryId,
            payment_method_id: paymentMethodId,
            amount: amount.toFixed(2),
            request_key: requestKey
        }, 'POST');
        hideLoading();
        if (!data) return;
        if (data.success !== true) {
            return showAlert('error', 'خطأ', data.message || data[2] || 'لم يتم تأكيد صرف العمولة');
        }
        showAlert('success', 'ناجح', data.already_posted
            ? 'تم التعرف على نفس عملية الصرف السابقة دون تكرار القيد.'
            : 'تم صرف عمولة المندوب بنجاح.');
        $('#select-mandob').val(null).trigger('change').html('<option value="">اختر المندوب</option>');
        $('#delivery-payment-method').val('');
        $('#amount-mandob').val('');
        $('#total-mandob').text('0.00');
        delete balanceCache[`DELIVERY_${deliveryId}`];
    } catch (error) {
        hideLoading();
        showAlert('error', 'خطأ', error.message);
    }
}

/*==================================================
=            DOCUMENT READY
==================================================*/
$(function() {
    initBackButton();

    // تهيئة المندوب والمسوق والمورد
    loadMarketerPaymentMethods();
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