/* معالج خطوات إنشاء أمر البيع الجديد - يحافظ على مسارات البحث والحفظ القديمة */
(function ($) {
    'use strict';
    if (!$) return;

    const readJson = (key, fallback) => {
        try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback; }
        catch (_) { return fallback; }
    };
    const getItems = () => {
        const value = readJson('itemarr', {});
        return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    };
    const hasCustomer = () => {
        const order = readJson('newOrder', {});
        return !!(order && order.uid && order.telid && order.addid && order.telstr && order.addstr);
    };
    let currentStep = 1;

    function renderStep() {
        const $form = $('#addorderform');
        if (!$form.length) return;
        $form.removeClass('jm-wizard-step-1 jm-wizard-step-2 jm-wizard-step-3').addClass('jm-wizard-step-' + currentStep);
        $('.jm-order-step').each(function (index) {
            const active = index + 1 === currentStep;
            $(this).toggleClass('active', active).toggleClass('completed', index + 1 < currentStep);
            if (active) $(this).attr('aria-current', 'step'); else $(this).removeAttr('aria-current');
        });
        const itemsCount = Object.keys(getItems()).length;
        const customerReady = hasCustomer();
        $('#jmWizardBack').toggle(currentStep > 1);
        $('#jmWizardNext').toggle(currentStep < 3).prop('disabled', currentStep === 1 && itemsCount === 0);
        $('#jmWizardNext').html(currentStep === 1
            ? 'متابعة إلى سلة الشراء <i class="fas fa-arrow-left"></i>'
            : 'متابعة إلى اختيار العميل <i class="fas fa-arrow-left"></i>');
        $('#jmWizardHint').text(currentStep === 1
            ? (itemsCount ? 'تم اختيار ' + itemsCount + ' صنف. يمكنك المتابعة إلى مراجعة السلة.' : 'أضف منتجًا واحدًا على الأقل للمتابعة.')
            : currentStep === 2
                ? 'راجع الكميات والأسعار والخصومات والشحن قبل اختيار العميل.'
                : customerReady ? 'بيانات العميل جاهزة. راجع ملخص الطلب ثم احفظ.' : 'ابحث عن العميل أو أضف عميلًا جديدًا ثم اختر الهاتف والعنوان.');

        // البحث عن العميل لا يظهر إلا في المرحلة الثالثة، ويختفي بعد اعتماد بياناته.
        $('#sreachform').toggle(currentStep === 3 && !customerReady);
        if (currentStep !== 3) {
            $('#addClientform, #editClientData').hide();
        }
        $('.invoice-info').toggle(currentStep === 3 && customerReady);
        $('.order-foot').toggle(currentStep === 3 && customerReady);
        if (currentStep === 3 && customerReady) {
            $('#sreachform, #addClientform, #editClientData').hide();
        }
        if (currentStep === 3 && !customerReady && !$('#addClientform').is(':visible') && !$('#editClientData').is(':visible')) {
            $('#sreachform').show();
        }
    }

    function setStep(step) {
        if (step < 1 || step > 3) return;
        if (step === 2 && Object.keys(getItems()).length === 0) {
            if (window.Toast_fire) Toast_fire(['w', 'أضف منتجًا واحدًا على الأقل قبل الانتقال إلى السلة.']);
            return;
        }
        if (step === 3 && Object.keys(getItems()).length === 0) {
            if (window.Toast_fire) Toast_fire(['w', 'السلة فارغة. أضف المنتجات أولًا.']);
            return;
        }
        currentStep = step;
        renderStep();
        const target = (step === 3 && !hasCustomer()) ? $('#sreachform') : $('#addorderform');
        $('html, body').animate({ scrollTop: (target.offset() ? target.offset().top : 0) - 20 }, 180);
    }

    $(document).on('click', '#jmWizardNext', function () {
        if (currentStep === 1) setStep(2);
        else if (currentStep === 2) setStep(3);
    });
    $(document).on('click', '#jmWizardBack', function () { setStep(currentStep - 1); });

    // تحديث حالة المتابعة عند إضافة/حذف/تعديل صنف.
    $(document).on('click', '.jm-store-add, #jmAddConfiguredProduct, .jm-edit-cart-product, .delete', function () {
        window.setTimeout(renderStep, 80);
    });

    // زر اعتماد العميل موجود داخل HTML الذي يرجعه Client_tbl؛ ننتظر انتهاء معالجه القديم.
    $(document).on('click', '#btnAddNewOrder', function () {
        window.setTimeout(function () {
            if (hasCustomer()) {
                currentStep = 3;
                renderStep();
            }
        }, 120);
    });

    // إذا استُعيدت مسودة محفوظة محليًا، افتح المرحلة المناسبة دون فقد محتوياتها.
    $(function () {
        if (typeof Go !== 'undefined' && Go !== 'NewOrder') {
            $('.jm-order-steps, .jm-order-wizard-nav').hide();
            $('#sreachform').hide();
            $('.invoice-info, .order-foot').show();
            return;
        }
        const itemsCount = Object.keys(getItems()).length;
        currentStep = hasCustomer() ? 3 : (itemsCount ? 2 : 1);
        renderStep();
    });

    // يتيح لباقي ملفات الواجهة تحديث المرحلة بعد اختيار العميل أو استعادة المسودة.
    window.jmWizardSetStep = setStep;
    window.jmWizardRefresh = renderStep;
})(window.jQuery);
