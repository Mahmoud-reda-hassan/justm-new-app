<?php
if ((int)($_SESSION['ADMIN_TYPE'] ?? 0) !== 1) {
    http_response_code(404);
    echo '<div class="alert alert-danger">الصفحة غير موجودة</div>';
    return;
}
?>
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">إعدادات الشحن والتوصيل</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="allowShippingAmountEdit">
                <label class="custom-control-label" for="allowShippingAmountEdit">
                    السماح بتعديل قيمة الشحن أثناء تأكيد محاولة التوصيل
                </label>
            </div>
            <small class="form-text text-muted">
                عند إيقاف الخيار، يجب استخدام قيمة الشحن المسجلة على أمر البيع دون تعديلها أثناء التأكيد.
                القيمة الافتراضية لهذا الخيار هي الإيقاف.
            </small>
        </div>
        <div class="alert alert-info mb-0">
            تغيير هذا الخيار لا يعدل رسوم أي طلب سابق. كما أن تفعيل الخيار وحده لا يغير الرسوم؛
            يجب أن يرسل نموذج تأكيد الشحنة قيمة التعديل، وسيتم التحقق من السماح بها في الخادم.
        </div>
    </div>
    <div class="card-footer">
        <button type="button" class="btn btn-primary" id="saveShippingSettings">
            <i class="fas fa-save"></i> حفظ الإعدادات
        </button>
        <span id="shippingSettingsStatus" class="mr-2 text-muted" aria-live="polite"></span>
    </div>
</div>
