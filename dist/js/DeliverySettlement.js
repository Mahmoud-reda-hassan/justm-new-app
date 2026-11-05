/* المرحلة 14: واجهة تسوية عهدة المندوب الجديدة - لا تغيّر وظائف الشاشة القديمة */
(function ($) {
    'use strict';

    const endpoint = '../../inc/controller/finance.control.php';
    let currentMode = 'USER_CHOICE';
    let openOrders = [];
    let lastPreview = null;

    const money = value => (Number(value || 0)).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

    function request(control, data = {}) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: Object.assign({ CONTROL: control }, data)
        });
    }

    function showError(error) {
        let message = 'تعذر تنفيذ العملية.';
        if (error && error.responseJSON) {
            const body = error.responseJSON;
            message = body.message || body[2] || body.error || message;
        } else if (error && error.message) {
            message = error.message;
        }
        if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: String(message) });
        else window.alert(String(message));
    }

    function showSuccess(message) {
        if (window.Swal) Swal.fire({ icon: 'success', title: 'تم', text: message });
        else window.alert(message);
    }

    // بصمة محلية مختصرة لمفتاح التخزين؛ لا نخزن نص الملاحظة أو بيانات العملية داخل sessionStorage.
    function pendingFingerprint(value) {
        let first = 2166136261;
        let second = 2246822519;
        for (let i = 0; i < value.length; i++) {
            const code = value.charCodeAt(i);
            first = Math.imul(first ^ code, 16777619);
            second = Math.imul(second ^ code, 3266489917);
        }
        return (first >>> 0).toString(16) + (second >>> 0).toString(16);
    }

    // يحتفظ بمفتاح مستقل لكل بيانات عملية حتى النجاح؛ تغيير البيانات لا يمحو مفاتيح المحاولات المعلقة الأخرى.
    function getPendingRequestKey(scope, fingerprint) {
        const storageKey = 'justm:pending:' + scope;
        try {
            const saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}');
            if (saved[fingerprint]) return saved[fingerprint];
            const key = 'DS-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            saved[fingerprint] = key;
            window.sessionStorage.setItem(storageKey, JSON.stringify(saved));
            return key;
        } catch (error) {
            window.__justmPendingKeys = window.__justmPendingKeys || {};
            const saved = window.__justmPendingKeys[scope] || {};
            if (saved[fingerprint]) return saved[fingerprint];
            const key = 'DS-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            saved[fingerprint] = key;
            window.__justmPendingKeys[scope] = saved;
            return key;
        }
    }

    function clearPendingRequestKey(scope, fingerprint) {
        const storageKey = 'justm:pending:' + scope;
        try {
            const saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}');
            delete saved[fingerprint];
            if (Object.keys(saved).length) window.sessionStorage.setItem(storageKey, JSON.stringify(saved));
            else window.sessionStorage.removeItem(storageKey);
        } catch (error) {}
        if (window.__justmPendingKeys && window.__justmPendingKeys[scope]) {
            delete window.__justmPendingKeys[scope][fingerprint];
        }
    }

    function selectedIds() {
        return $('#dsOrdersTable tbody input.ds-order-check:checked').map(function () {
            return Number(this.value);
        }).get();
    }

    function selectedTotals() {
        const ids = new Set(selectedIds());
        const rows = openOrders.filter(row => ids.has(Number(row.ORDER_ID)));
        return {
            amount: rows.reduce((sum, row) => sum + Number(row.OPEN_AMOUNT || 0), 0),
            commission: rows.reduce((sum, row) => sum + Number(row.OPEN_COMMISSION || 0), 0)
        };
    }

    function renderOrders() {
        const $tbody = $('#dsOrdersTable tbody').empty();
        if (!openOrders.length) {
            $tbody.append('<tr><td colspan="5" class="text-center text-muted">لا توجد تحصيلات مفتوحة لهذا المندوب</td></tr>');
            updateSelectedTotals();
            return;
        }
        openOrders.forEach(row => {
            const orderNo = row.SUB_ORDER_ID || row.ORDER_ID;
            const date = row.DATE_CREATED || '';
            const $tr = $('<tr>');
            $('<td>').append($('<input>', { type: 'checkbox', class: 'ds-order-check', value: row.ORDER_ID })).appendTo($tr);
            $('<td>').text(orderNo).appendTo($tr);
            $('<td>').text(date).appendTo($tr);
            $('<td>').text(money(row.OPEN_AMOUNT)).appendTo($tr);
            $('<td>').text(money(row.OPEN_COMMISSION)).appendTo($tr);
            $tbody.append($tr);
        });
        updateSelectedTotals();
    }

    function updateSelectedTotals() {
        const totals = selectedTotals();
        $('#dsSelectedTotal').text(money(totals.amount));
        $('#dsSelectedCommission').text(money(totals.commission));
        $('#dsSettle').prop('disabled', !$('#dsDelivery').val() || !$('#dsPaymentMethod').val() || !openOrders.length);
        refreshCashPreview();
    }

    function refreshCashPreview() {
        const amountValue = $('#dsAmount').val();
        const totals = selectedTotals();
        const gross = amountValue !== '' ? Number(amountValue) : totals.amount;
        // معاينة تقريبية فقط؛ المبلغ النهائي للعمولة يحسبه السيرفر من القيود الفعلية.
        let selectedCommission = totals.commission;
        if (lastPreview && Array.isArray(lastPreview.allocations)) {
            selectedCommission = lastPreview.allocations.reduce((sum, row) => sum + Math.min(Number(row.settlement_amount || 0), Number(row.open_commission || 0)), 0);
        }
        const cash = currentMode === 'DEDUCT_FROM_CUSTODY'
            ? Math.max(0, gross - Math.min(gross, selectedCommission))
            : gross;
        $('#dsCashPreview').val(money(cash));
    }

    function loadDeliveryList() {
        const $select = $('#dsDelivery').empty().append($('<option>', { value: '', text: 'اختر المندوب' }));
        const $legacy = $('#SelectUserDelivery');
        if ($legacy.length && $legacy.find('option').length > 1) {
            $legacy.find('option').each(function () {
                if (this.value) $select.append($('<option>', { value: this.value, text: $(this).text() }));
            });
            return $.Deferred().resolve().promise();
        }
        return $.getJSON('../../inc/controller/api.control.php?CONTROL=UserListForType&utype=delivery')
            .done(rows => {
                if (Array.isArray(rows)) rows.forEach(row => {
                    $select.append($('<option>', { value: row.ID, text: row.NAME }));
                });
            }).fail(() => {
                $('#dsPreviewResult').text('تعذر تحميل قائمة المندوبين. استخدم القائمة القديمة ثم أعد المحاولة أو راجع مسار API.');
            });
    }

    function loadPolicy() {
        return request('GetDeliveryCommissionMode').done(response => {
            if (!response || !response.success) { showError(new Error((response && response[2]) || 'تعذر قراءة سياسة العمولة.')); return; }
            currentMode = response.mode;
            $('#dsPolicyMode').val(currentMode);
            applyModeUI();
        });
    }

    function applyModeUI() {
        const forced = currentMode !== 'USER_CHOICE';
        $('#dsModeWrap').toggle(!forced);
        if (forced) $('#dsMode').val(currentMode);
        refreshCashPreview();
    }

    function loadPaymentMethods() {
        return request('GetDeliverySettlementPaymentMethods').done(response => {
            if (!response || !response.success) { showError(new Error((response && response[2]) || 'تعذر تحميل طرق الدفع المرتبطة بحسابات الفرع.')); return; }
            const $select = $('#dsPaymentMethod').empty().append($('<option>', { value: '', text: 'اختر طريقة الدفع' }));
            (response.methods || []).forEach(method => {
                $select.append($('<option>', { value: method.id, text: method.name }));
            });
            if (!(response.methods || []).length) {
                $('#dsPreviewResult').text('لا توجد طرق دفع مفعّلة ومربوطة بحساب أستاذ لهذا الفرع. راجع إعدادات ربط طرق الدفع.');
            }
        });
    }

    function loadOpenOrders() {
        const deliveryId = Number($('#dsDelivery').val() || 0);
        openOrders = [];
        lastPreview = null;
        $('#dsPreviewResult').text('جارٍ تحميل الأوامر المفتوحة...');
        if (!deliveryId) {
            renderOrders();
            $('#dsPreviewResult').text('اختر المندوب لعرض الأوامر المفتوحة.');
            return $.Deferred().resolve().promise();
        }
        return request('GetDeliveryOpenOrders', { delivery_id: deliveryId }).done(response => {
            if (!response || !response.success) { showError(new Error((response && response[2]) || 'تعذر تحميل الأوامر المفتوحة.')); return; }
            openOrders = Array.isArray(response.orders) ? response.orders : [];
            renderOrders();
            $('#dsPreviewResult').text(`تم تحميل ${openOrders.length} أمر مفتوح. الأوامر المسددة بالكامل لا تظهر هنا.`);
        }).fail(showError);
    }

    function getPreviewRequest() {
        const ids = selectedIds();
        const amount = Number($('#dsAmount').val() || 0);
        if (amount > 0) {
            const data = { delivery_id: $('#dsDelivery').val(), amount: amount };
            if (ids.length) data.order_ids = JSON.stringify(ids);
            return data;
        }
        if (ids.length) return { delivery_id: $('#dsDelivery').val(), order_ids: JSON.stringify(ids) };
        throw new Error('حدد أمرًا واحدًا على الأقل أو أدخل مبلغًا للتوزيع التلقائي.');
    }

    function preview() {
        let data;
        try { data = getPreviewRequest(); }
        catch (error) { showError(error); return $.Deferred().reject(error).promise(); }
        return request('PreviewDeliverySettlement', data).done(response => {
            if (!response || !response.success) { showError(new Error((response && response[2]) || 'تعذر معاينة التوزيع.')); return; }
            lastPreview = response.preview;
            const previewData = response.preview || {};
            const lines = [];
            if (previewData.allocations) {
                previewData.allocations.forEach(row => lines.push(
                    `الأمر ${row.sub_order_id || row.order_id}: ${money(row.settlement_amount)} (متبقي بعد التسوية ${money(row.open_after)})`
                ));
                lines.push(`الموزع: ${money(previewData.allocated_amount)} — غير موزع: ${money(previewData.unallocated_amount)}`);
            } else {
                (previewData.orders || []).forEach(row => lines.push(`الأمر ${row.order_id}: ${money(row.open_amount)}`));
                lines.push(`الإجمالي: ${money(previewData.total_amount)}`);
            }
            $('#dsPreviewResult').html('');
            lines.forEach(line => $('#dsPreviewResult').append($('<div>').text(line)));
            $('#dsSettle').prop('disabled', !$('#dsPaymentMethod').val() || !$('#dsDelivery').val() || !lines.length);
            refreshCashPreview();
        }).fail(showError);
    }

    function settle() {
        const deliveryId = Number($('#dsDelivery').val() || 0);
        const paymentMethodId = Number($('#dsPaymentMethod').val() || 0);
        const ids = selectedIds();
        const amountText = $('#dsAmount').val();
        const amount = Number(amountText || 0);
        if (!deliveryId || !paymentMethodId) return showError(new Error('اختر المندوب وطريقة الدفع.'));
        if (amount <= 0 && !ids.length) return showError(new Error('حدد الأوامر أو أدخل مبلغ التسوية.'));
        if (amount > 0 && lastPreview === null) {
            return showError(new Error('نفّذ معاينة التوزيع قبل تسجيل الاستلام.'));
        }
        if (amount > 0 && lastPreview && Number(lastPreview.unallocated_amount || 0) > 0.009) {
            return showError(new Error('المبلغ أكبر من إجمالي الرصيد المفتوح المحدد؛ قلّل المبلغ أو اختر أوامر إضافية.'));
        }
        const payload = {
            delivery_id: deliveryId,
            payment_method_id: paymentMethodId,
            note: $('#dsNote').val(),
            commission_mode: currentMode === 'USER_CHOICE' ? $('#dsMode').val() : currentMode
        };
        if (ids.length) payload.order_ids = JSON.stringify(ids);
        if (amount > 0) payload.amount = amount;
        const fingerprint = pendingFingerprint(JSON.stringify(payload));
        payload.request_key = getPendingRequestKey('settlement', fingerprint);
        const confirmation = `سيتم تسجيل تسوية عهدة المندوب. ${amount > 0 ? 'المبلغ: ' + money(amount) : 'إجمالي الأوامر المحددة: ' + money(selectedTotals().amount)}. هل تريد المتابعة؟`;
        const execute = () => request('SettleDeliveryCollections', payload).done(response => {
            if (!response || response.success !== true) { showError(new Error((response && (response.message || response[2] || response.error)) || 'لم يتم تأكيد نجاح التسوية.')); return; }
            clearPendingRequestKey('settlement', fingerprint);
            showSuccess(response.already_posted ? 'تم التعرف على نفس الطلب السابق؛ لم يتم تكرار القيد.' : (response.message || 'تم تسجيل استلام العهدة بنجاح.'));
            $('#dsAmount, #dsNote').val('');
            lastPreview = null;
            loadOpenOrders();
        }).fail(showError);
        if (window.Swal) Swal.fire({ icon: 'question', title: 'تأكيد التسوية', text: confirmation, showCancelButton: true, confirmButtonText: 'تأكيد', cancelButtonText: 'إلغاء' }).then(result => { if (result.isConfirmed) execute(); });
        else if (window.confirm(confirmation)) execute();
    }

    $(function () {
        if (!$('#deliverySettlementPanel').length) return;
        $('#dsDelivery').select2({ placeholder: 'اختر المندوب', allowClear: true, language: 'ar', dir: 'rtl', width: '100%' });
        loadDeliveryList();
        loadPolicy().fail(showError);
        loadPaymentMethods().fail(showError);

        $('#dsDelivery').on('change', loadOpenOrders);
        $('#dsOrdersTable').on('change', '.ds-order-check', function () {
            updateSelectedTotals();
            lastPreview = null;
        });
        $('#dsSelectAll').on('change', function () {
            $('#dsOrdersTable tbody input.ds-order-check').prop('checked', this.checked);
            updateSelectedTotals();
            lastPreview = null;
        });
        $('#dsAmount').on('input', function () { lastPreview = null; refreshCashPreview(); });
        $('#dsMode').on('change', function () { lastPreview = null; refreshCashPreview(); });
        $('#dsPaymentMethod').on('change', updateSelectedTotals);
        $('#dsRefresh').on('click', loadOpenOrders);
        $('#dsPreview').on('click', preview);
        $('#dsSettle').on('click', settle);
        $('#dsSavePolicy').on('click', function () {
            const mode = $('#dsPolicyMode').val();
            request('UpdateDeliveryCommissionMode', { mode }).done(response => {
                if (!response || !response.success) { showError(new Error((response && response[2]) || 'تعذر حفظ سياسة العمولة.')); return; }
                currentMode = response.mode;
                applyModeUI();
                showSuccess('تم حفظ سياسة العمولة العامة.');
            }).fail(showError);
        });
    });
})(jQuery);
