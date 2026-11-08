(function ($) {
    'use strict';

    const endpoint = '../../inc/controller/finance.control.php';
    const branchesEndpoint = '../../inc/Api.php';
    let branches = [];
    let accounts = [];
    let cashBoxes = [];
    let paymentMethods = [];

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
            const message = Array.isArray(response) ? (response[2] || 'تعذر تنفيذ العملية.') : (response.message || 'تعذر تنفيذ العملية.');
            return $.Deferred().reject({ responseJSON: { message: message } }).promise();
        });
    }

    function errorMessage(xhr) {
        return xhr?.responseJSON?.message || xhr?.responseJSON?.[2] || 'حدث خطأ أثناء الاتصال بالخادم.';
    }

    function selectedBranchId() {
        return Number($('#cbBranch').val() || 0);
    }

    function resetForm() {
        $('#cbId').val('0');
        $('#cbName').val('');
        $('#cbAccount').val('');
        $('#cbSave').text('حفظ الخزينة').prop('disabled', selectedBranchId() <= 0);
        $('#cbCancel').prop('disabled', true);
    }

    function renderBranches() {
        const selected = selectedBranchId();
        const html = ['<option value="">اختر الفرع</option>'];
        branches.forEach(function (branch) {
            html.push('<option value="' + Number(branch.id) + '">' + escapeHtml(branch.name) + '</option>');
        });
        $('#cbBranch').html(html.join(''));
        if (selected && branches.some(branch => Number(branch.id) === selected)) {
            $('#cbBranch').val(String(selected));
        } else if (branches.length) {
            $('#cbBranch').val(String(branches[0].id));
        }
    }

    function renderAccounts() {
        const html = ['<option value="">اختر حساب أصول قابل للترحيل</option>'];
        accounts.forEach(function (account) {
            html.push('<option value="' + Number(account.id) + '">' +
                escapeHtml(account.account_code + ' — ' + account.account_name) + '</option>');
        });
        $('#cbAccount').html(html.join(''));
    }

    function renderCashBoxes() {
        const rows = cashBoxes.map(function (box) {
            const active = Number(box.is_active) === 1;
            const action = active
                ? '<button type="button" class="btn btn-sm btn-outline-danger js-box-toggle" data-id="' + Number(box.id) + '" data-active="0">إيقاف</button>'
                : '<button type="button" class="btn btn-sm btn-outline-success js-box-toggle" data-id="' + Number(box.id) + '" data-active="1">تفعيل</button>';
            return '<tr>' +
                '<td>' + escapeHtml(box.name) + '</td>' +
                '<td>' + escapeHtml(box.account_code + ' — ' + box.account_name) + '</td>' +
                '<td class="text-left">' + Number(box.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                '<td>' + (active ? '<span class="badge badge-success">نشطة</span>' : '<span class="badge badge-secondary">موقوفة</span>') + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-primary js-box-edit" data-id="' + Number(box.id) + '">تعديل</button> ' + action + '</td>' +
                '</tr>';
        });
        $('#cashBoxTable tbody').html(rows.length ? rows.join('') : '<tr><td colspan="5" class="text-center text-muted">لا توجد خزائن مسجلة لهذا الفرع.</td></tr>');
    }

    function renderPaymentMethods() {
        const options = ['<option value="0">غير مربوط</option>'];
        cashBoxes.filter(box => Number(box.is_active) === 1).forEach(function (box) {
            options.push('<option value="' + Number(box.id) + '">' + escapeHtml(box.name) + ' — ' +
                escapeHtml(box.account_code) + '</option>');
        });

        const rows = paymentMethods.map(function (method) {
            const selected = Number(method.cash_box_id || 0);
            const hasBrokenMapping = Number(method.account_id || 0) > 0 && !selected;
            const status = selected
                ? '<span class="badge badge-success">مربوط</span>'
                : (hasBrokenMapping
                    ? '<span class="badge badge-warning">الحساب المرتبط ليس خزينة نشطة</span>'
                    : '<span class="badge badge-secondary">غير مربوط</span>');
            return '<tr>' +
                '<td>' + escapeHtml(method.name) + '</td>' +
                '<td><select class="form-control form-control-sm js-method-box" data-method="' + Number(method.id) + '">' +
                    options.join('').replace('value="' + selected + '"', 'value="' + selected + '" selected') +
                '</select></td>' +
                '<td>' + status + '</td>' +
                '</tr>';
        });
        $('#paymentMethodCashBoxTable tbody').html(rows.length ? rows.join('') : '<tr><td colspan="3" class="text-center text-muted">لا توجد طرق دفع نشطة.</td></tr>');
    }

    function loadManagement() {
        const branchId = selectedBranchId();
        resetForm();
        if (!branchId) {
            cashBoxes = [];
            paymentMethods = [];
            renderCashBoxes();
            renderPaymentMethods();
            return;
        }

        request({ CONTROL: 'GetCashBoxManagementData', branch_id: branchId })
            .done(function (response) {
                branches = response.branches || branches;
                accounts = response.accounts || [];
                cashBoxes = response.cash_boxes || [];
                paymentMethods = response.payment_methods || [];
                renderAccounts();
                renderCashBoxes();
                renderPaymentMethods();
            })
            .fail(function (xhr) {
                notify('error', 'تعذر تحميل الإعدادات', errorMessage(xhr));
            });
    }

    function saveCashBox() {
        const branchId = selectedBranchId();
        const boxName = String($('#cbName').val() || '').trim();
        const accountId = Number($('#cbAccount').val() || 0);
        const boxId = Number($('#cbId').val() || 0);
        if (!branchId || !boxName || !accountId) {
            notify('warning', 'بيانات ناقصة', 'اختر الفرع وحساب الخزينة واكتب اسمها.');
            return;
        }

        $('#cbSave').prop('disabled', true);
        request({
            CONTROL: 'SaveCashBox',
            branch_id: branchId,
            box_id: boxId,
            box_name: boxName,
            account_id: accountId,
            is_active: 1
        }).done(function (response) {
            notify('success', 'تم الحفظ', response.message || 'تم حفظ الخزينة.');
            resetForm();
            loadManagement();
        }).fail(function (xhr) {
            notify('error', 'تعذر حفظ الخزينة', errorMessage(xhr));
        }).always(function () {
            $('#cbSave').prop('disabled', false);
        });
    }

    function toggleCashBox(boxId, active) {
        const box = cashBoxes.find(item => Number(item.id) === boxId);
        if (!box) return;
        const action = active ? 'تفعيل' : 'إيقاف';
        const run = function () {
            request({
                CONTROL: 'SaveCashBox',
                branch_id: selectedBranchId(),
                box_id: boxId,
                box_name: box.name,
                account_id: Number(box.account_id),
                is_active: active
            }).done(function (response) {
                notify('success', 'تمت العملية', response.message || 'تم تحديث حالة الخزينة.');
                loadManagement();
            }).fail(function (xhr) {
                notify('error', 'تعذر ' + action + ' الخزينة', errorMessage(xhr));
            });
        };

        if (window.Swal) {
            Swal.fire({
                icon: 'question',
                title: action + ' الخزينة؟',
                text: active ? 'ستصبح الخزينة متاحة للاستخدام.' : 'لا يمكن إيقاف خزينة لها رصيد غير مسوّى.',
                showCancelButton: true,
                confirmButtonText: action,
                cancelButtonText: 'إلغاء'
            }).then(result => { if (result.isConfirmed) run(); });
        } else if (window.confirm(action + ' الخزينة؟')) {
            run();
        }
    }

    function mapPaymentMethod(methodId, cashBoxId) {
        request({
            CONTROL: 'SetCashBoxPaymentMethod',
            branch_id: selectedBranchId(),
            payment_method_id: methodId,
            cash_box_id: cashBoxId
        }).done(function (response) {
            notify('success', 'تم تحديث الربط', response.message || '');
            loadManagement();
        }).fail(function (xhr) {
            notify('error', 'تعذر ربط طريقة الدفع', errorMessage(xhr));
            loadManagement();
        });
    }

    $(function () {
        $('#cbBranch').on('change', loadManagement);
        $('#cbSave').on('click', saveCashBox);
        $('#cbCancel').on('click', resetForm);

        $('#cashBoxTable').on('click', '.js-box-edit', function () {
            const id = Number($(this).data('id'));
            const box = cashBoxes.find(item => Number(item.id) === id);
            if (!box) return;
            $('#cbId').val(String(box.id));
            $('#cbName').val(box.name);
            $('#cbAccount').val(String(box.account_id));
            $('#cbSave').text('حفظ التعديل').prop('disabled', false);
            $('#cbCancel').prop('disabled', false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        $('#cashBoxTable').on('click', '.js-box-toggle', function () {
            toggleCashBox(Number($(this).data('id')), Number($(this).data('active')) === 1);
        });

        $('#paymentMethodCashBoxTable').on('change', '.js-method-box', function () {
            mapPaymentMethod(Number($(this).data('method')), Number($(this).val() || 0));
        });

        $.ajax({
            url: branchesEndpoint,
            type: 'GET',
            dataType: 'json',
            cache: false,
            data: { CONTROL: 'getdropBranches' }
        }).done(function (response) {
            // getdropBranches يرجع كل فرع كمصفوفة: [BR_ID, COMPANIE_ID, BR_NAME].
            branches = Array.isArray(response) ? response.map(function (branch) {
                return {
                    id: Number(branch[0]),
                    company_id: Number(branch[1]),
                    name: String(branch[2] || '')
                };
            }).filter(function (branch) {
                return branch.id > 0 && branch.name !== '';
            }) : [];

            renderBranches();
            if (selectedBranchId()) loadManagement();
        }).fail(function (xhr) {
            notify('error', 'تعذر تحميل بيانات الفروع',
                xhr?.responseJSON?.message || 'تعذر جلب الفروع من واجهة النظام.');
        });
    });
})(jQuery);
