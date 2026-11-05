(function ($) {
    'use strict';

    const endpoint = '../../inc/controller/finance.control.php';
    const $toggle = $('#allowShippingAmountEdit');
    const $status = $('#shippingSettingsStatus');

    function request(control, data) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: Object.assign({ CONTROL: control }, data || {})
        });
    }

    function showError(xhr) {
        const message = xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON[2])
            ? (xhr.responseJSON.message || xhr.responseJSON[2])
            : 'تعذر تنفيذ العملية. حاول مرة أخرى.';
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'خطأ', text: String(message) });
        } else {
            window.alert(String(message));
        }
    }

    request('GetShippingAmountEditSetting')
        .done(function (response) {
            if (!response || response.success !== true) {
                showError({ responseJSON: response });
                return;
            }
            $toggle.prop('checked', response.allow_shipping_amount_edit === true);
            $toggle.prop('disabled', false);
        })
        .fail(showError);

    $toggle.prop('disabled', true);

    $('#saveShippingSettings').on('click', function () {
        const $button = $(this);
        $button.prop('disabled', true);
        $status.text('جارٍ الحفظ...');

        request('UpdateShippingAmountEditSetting', {
            allow_shipping_amount_edit: $toggle.is(':checked') ? '1' : '0'
        })
        .done(function (response) {
            if (!response || response.success !== true) {
                showError({ responseJSON: response });
                return;
            }
            $toggle.prop('checked', response.allow_shipping_amount_edit === true);
            $status.text('تم حفظ الإعداد.');
            if (window.Swal) {
                Swal.fire({ icon: 'success', title: 'تم الحفظ', text: 'تم تحديث إعدادات الشحن.' });
            }
        })
        .fail(showError)
        .always(function () {
            $button.prop('disabled', false);
        });
    });
})(jQuery);
