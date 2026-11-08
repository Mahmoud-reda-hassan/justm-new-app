(function ($) {
    'use strict';

    const endpoint = 'inc/controller/finance.control.php';
    let cashBoxes = [];
    let partners = [];
    let accounts = [];
    let pendingRequest = null;

    const subtypeLabels = {
        CUSTOMER_RECEIPT: 'قبض من عميل',
        SUPPLIER_REFUND: 'استلام رد مبلغ من مورد',
        GENERAL_RECEIPT: 'قبض إيراد آخر',
        GENERAL_PAYMENT: 'صرف مصروف'
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function notify(icon, title, text) {
        if (window.Swal) {
            Swal.fire({ icon: icon, title: title, text: text || '', confirmButtonText: 'حسنًا' });
        } else {
            window.alert((title || '') + (text ? '\n' + text : ''));
        }
    }

    function request(data) {
        return $.ajax({
            url: endpoint,
            type: 'POST',
            dataType: 'json',
            data: data
        }).then(function (response) {
            if (response && response.success === true) return response;
            const message = Array.isArray(response)
                ? (response[2] || 'تعذر تنفيذ العملية.')
                : (response.message || response.error || 'تعذر تنفيذ العملية.');
            return $.Deferred().reject({ responseJSON: { message: message } }).promise();
        });
    }

    function errorMessage(xhr) {
        return xhr?.responseJSON?.message || xhr?.responseJSON?.[2] || 'حدث خطأ أثناء الاتصال بالخادم.';
    }

    function makeRequestKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return 'FV-' + window.crypto.randomUUID();
        }
        const bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
            return 'FV-' + Array.from(bytes).map(x => x.toString(16).padStart(2, '0')).join('');
        }
        return 'FV-' + Date.now() + '-' + Math.random().toString(36).slice(2, 18);
    }

    function localDateTimeValue() {
        const date = new Date();
        const pad = value => String(value).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
            'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function normalizedDate() {
        const value = String($('#fvDate').val() || '').trim();
        if (!value) return '';
        return value.replace('T', ' ') + ':00';
    }

    function currentSubtype() {
        return String($('#fvType').val() || 'CUSTOMER_RECEIPT');
    }

    function isPartnerVoucher(subtype) {
        return subtype === 'CUSTOMER_RECEIPT' || subtype === 'SUPPLIER_REFUND';
    }

    function partnerType(subtype) {
        return subtype === 'CUSTOMER_RECEIPT' ? 'CLIENT' : 'SUPPLIER';
    }

    function invalidatePending() {
        pendingRequest = null;
    }

    function renderCashBoxes() {
        const options = ['<option value="">اختر الخزينة</option>'];
        cashBoxes.forEach(function (box) {
            options.push('<option value="' + Number(box.id) + '">' +
                escapeHtml(box.name + ' — ' + box.account_code) + '</option>');
        });
        $('#fvCashBox').html(options.join(''));
        $('#fvSave').prop('disabled', cashBoxes.length === 0);
        if (!cashBoxes.length) {
            notify('warning', 'لا توجد خزينة جاهزة', 'أنشئ خزينة للفرع واربطها بطريقة الدفع النقدي أولًا.');
        }
    }

    function loadCashBoxes() {
        return request({ CONTROL: 'GetFinancialVoucherCashBoxes' })
            .done(function (response) {
                cashBoxes = response.cash_boxes || [];
                renderCashBoxes();
            })
            .fail(function (xhr) {
                notify('error', 'تعذر تحميل الخزائن', errorMessage(xhr));
            });
    }

    function loadPartners(type) {
        $('#fvPartner').html('<option value="">جارٍ التحميل...</option>');
        request({ CONTROL: 'GetFinancialVoucherPartners', partner_type: type })
            .done(function (response) {
                partners = response.partners || [];
                const options = ['<option value="">اختر الطرف</option>'];
                partners.forEach(function (partner) {
                    options.push('<option value="' + Number(partner.id) + '">' +
                        escapeHtml(partner.name) + ' — #' + Number(partner.id) + '</option>');
                });
                $('#fvPartner').html(options.join(''));
            })
            .fail(function (xhr) {
                $('#fvPartner').html('<option value="">تعذر تحميل الأطراف</option>');
                notify('error', 'تعذر تحميل الأطراف', errorMessage(xhr));
            });
    }

    function loadAccounts(voucherType) {
        $('#fvAccount').html('<option value="">جارٍ تحميل الحسابات...</option>');
        request({ CONTROL: 'GetFinancialVoucherAccounts', voucher_type: voucherType })
            .done(function (response) {
                accounts = response.accounts || [];
                const options = ['<option value="">اختر الحساب</option>'];
                accounts.forEach(function (account) {
                    options.push('<option value="' + Number(account.id) + '">' +
                        escapeHtml(account.account_code + ' — ' + account.account_name) + '</option>');
                });
                $('#fvAccount').html(options.join(''));
            })
            .fail(function (xhr) {
                $('#fvAccount').html('<option value="">تعذر تحميل الحسابات</option>');
                notify('error', 'تعذر تحميل الحسابات', errorMessage(xhr));
            });
    }

    function configureSubtype() {
        const subtype = currentSubtype();
        const partnerVoucher = isPartnerVoucher(subtype);
        $('#fvPartnerPanel').toggle(partnerVoucher);
        $('#fvAccountPanel').toggle(!partnerVoucher);
        $('#fvPartnerBalance').text('—');
        $('#fvPartner').val('');
        $('#fvAccount').val('');

        if (partnerVoucher) {
            loadPartners(partnerType(subtype));
        } else {
            loadAccounts(subtype === 'GENERAL_RECEIPT' ? 'RECEIPT' : 'PAYMENT');
        }
        invalidatePending();
    }

    function loadPartnerBalance() {
        const subtype = currentSubtype();
        const id = Number($('#fvPartner').val() || 0);
        if (!id || !isPartnerVoucher(subtype)) {
            $('#fvPartnerBalance').text('—');
            return;
        }

        request({
            CONTROL: 'GetFinancialVoucherPartnerBalance',
            partner_type: partnerType(subtype),
            partner_id: id
        }).done(function (response) {
            const balance = Number(response.balance || 0);
            let label = balance > 0
                ? 'مدين: ' + balance.toFixed(2)
                : (balance < 0 ? 'دائن: ' + Math.abs(balance).toFixed(2) : 'الرصيد: 0.00');
            if (subtype === 'CUSTOMER_RECEIPT') {
                label += ' | رصيد دائن قابل للاستخدام: ' + Number(response.customer_credit || 0).toFixed(2);
            } else if (subtype === 'SUPPLIER_REFUND') {
                label += ' | المتاح لرده: ' + Math.max(0, balance).toFixed(2);
            }
            $('#fvPartnerBalance').text(label);
        }).fail(function (xhr) {
            $('#fvPartnerBalance').text('تعذر تحميل الرصيد');
            notify('error', 'تعذر تحميل رصيد الطرف', errorMessage(xhr));
        });
    }

    function renderVouchers(vouchers) {
        const rows = (vouchers || []).map(function (voucher) {
            const status = voucher.status === 'POSTED'
                ? '<span class="badge badge-success">مرحل</span>'
                : (voucher.status === 'REVERSED'
                    ? '<span class="badge badge-secondary">معكوس</span>'
                    : '<span class="badge badge-warning">' + escapeHtml(voucher.status) + '</span>');
            const partner = voucher.partner_id
                ? escapeHtml(voucher.partner_type) + ' #' + Number(voucher.partner_id)
                : '—';
            const action = voucher.status === 'POSTED'
                ? '<button type="button" class="btn btn-sm btn-outline-danger js-fv-reverse" data-id="' + Number(voucher.id) + '">عكس</button>'
                : '—';
            return '<tr>' +
                '<td>' + escapeHtml(voucher.voucher_no) + '</td>' +
                '<td>' + escapeHtml(subtypeLabels[voucher.voucher_subtype] || voucher.voucher_subtype) + '</td>' +
                '<td>' + escapeHtml(voucher.voucher_date) + '</td>' +
                '<td>' + partner + '</td>' +
                '<td>' + escapeHtml(voucher.cash_box_name || '') + '</td>' +
                '<td class="text-left">' + Number(voucher.total_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' + action + '</td>' +
                '</tr>';
        });
        $('#fvTable tbody').html(rows.length ? rows.join('') :
            '<tr><td colspan="8" class="text-center text-muted">لا توجد سندات مسجلة لهذا الفرع.</td></tr>');
    }

    function loadVouchers() {
        request({ CONTROL: 'GetFinancialVouchers' })
            .done(function (response) {
                renderVouchers(response.vouchers || []);
            })
            .fail(function (xhr) {
                $('#fvTable tbody').html('<tr><td colspan="8" class="text-center text-danger">تعذر تحميل السندات.</td></tr>');
                notify('error', 'تعذر تحميل السندات', errorMessage(xhr));
            });
    }

    function buildPayload() {
        const subtype = currentSubtype();
        const amount = Number($('#fvAmount').val() || 0);
        const payload = {
            CONTROL: 'PostFinancialVoucher',
            voucher_subtype: subtype,
            cash_box_id: Number($('#fvCashBox').val() || 0),
            payment_method_id: 1,
            voucher_date: normalizedDate(),
            amount: amount,
            description: String($('#fvDescription').val() || '').trim()
        };

        if (isPartnerVoucher(subtype)) {
            payload.partner_id = Number($('#fvPartner').val() || 0);
        } else {
            payload.lines = JSON.stringify([{
                account_id: Number($('#fvAccount').val() || 0),
                amount: amount,
                line_description: payload.description
            }]);
        }
        return payload;
    }

    function submitVoucher() {
        const payload = buildPayload();
        if (!payload.cash_box_id || !payload.voucher_date || !payload.amount ||
            payload.amount <= 0 || !payload.description) {
            notify('warning', 'بيانات ناقصة', 'اختر الخزينة والتاريخ وأدخل مبلغًا وبيانًا صحيحًا.');
            return;
        }
        if (isPartnerVoucher(payload.voucher_subtype) && !payload.partner_id) {
            notify('warning', 'اختر الطرف', 'يجب تحديد العميل أو المورد.');
            return;
        }
        if (!isPartnerVoucher(payload.voucher_subtype) && !JSON.parse(payload.lines)[0].account_id) {
            notify('warning', 'اختر الحساب', 'حدد حساب الإيراد أو المصروف.');
            return;
        }

        const comparable = Object.assign({}, payload);
        delete comparable.CONTROL;
        const signature = JSON.stringify(comparable);
        if (!pendingRequest || pendingRequest.signature !== signature) {
            pendingRequest = { signature: signature, key: makeRequestKey() };
        }

        $('#fvSave').prop('disabled', true).text('جارٍ الترحيل...');
        request(Object.assign({}, payload, { idempotency_key: pendingRequest.key }))
            .done(function (response) {
                pendingRequest = null;
                notify('success', 'تم ترحيل السند', 'رقم السند: ' + (response.voucher?.voucher_no || 'تم الحفظ'));
                $('#fvAmount').val('');
                $('#fvDescription').val('');
                loadPartnerBalance();
                loadVouchers();
            })
            .fail(function (xhr) {
                notify('error', 'تعذر ترحيل السند', errorMessage(xhr) + '\nيمكنك إعادة المحاولة بنفس البيانات دون تكرار القيد.');
            })
            .always(function () {
                $('#fvSave').prop('disabled', cashBoxes.length === 0).text('ترحيل السند');
            });
    }

    function reverseVoucher(voucherId) {
        const run = function (reason) {
            request({ CONTROL: 'ReverseFinancialVoucher', voucher_id: voucherId, reason: reason })
                .done(function () {
                    notify('success', 'تم عكس السند', 'تم إنشاء قيد عكسي مع الاحتفاظ بالمستند الأصلي وسجل التخصيصات.');
                    loadVouchers();
                    loadPartnerBalance();
                })
                .fail(function (xhr) {
                    notify('error', 'تعذر عكس السند', errorMessage(xhr));
                });
        };

        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'عكس السند؟',
                input: 'textarea',
                inputLabel: 'سبب العكس',
                inputPlaceholder: 'اكتب سببًا واضحًا للعكس...',
                inputValidator: value => !String(value || '').trim() ? 'سبب العكس مطلوب.' : undefined,
                showCancelButton: true,
                confirmButtonText: 'تنفيذ العكس',
                cancelButtonText: 'إلغاء'
            }).then(result => {
                if (result.isConfirmed) run(String(result.value).trim());
            });
        } else {
            const reason = window.prompt('سبب العكس:');
            if (reason && reason.trim()) run(reason.trim());
        }
    }

    function resetForm() {
        $('#fvAmount').val('');
        $('#fvDescription').val('');
        $('#fvPartner').val('');
        $('#fvAccount').val('');
        $('#fvPartnerBalance').text('—');
        $('#fvDate').val(localDateTimeValue());
        invalidatePending();
    }

    $(function () {
        $('#fvDate').val(localDateTimeValue());
        $('#fvType').on('change', configureSubtype);
        $('#fvPartner').on('change', function () {
            invalidatePending();
            loadPartnerBalance();
        });
        $('#fvCashBox, #fvAccount, #fvAmount, #fvDescription, #fvDate').on('change input', invalidatePending);
        $('#fvSave').on('click', submitVoucher);
        $('#fvReset').on('click', resetForm);
        $('#fvRefresh').on('click', loadVouchers);
        $('#fvTable').on('click', '.js-fv-reverse', function () {
            reverseVoucher(Number($(this).data('id')));
        });

        loadCashBoxes();
        configureSubtype();
        loadVouchers();
    });
})(jQuery);
