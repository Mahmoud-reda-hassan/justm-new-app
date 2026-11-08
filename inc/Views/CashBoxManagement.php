<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>إدارة الخزائن وربط طرق الدفع</h1>
            <p class="text-muted mb-0">كل خزينة ترتبط بحساب أصول مستقل داخل الفرع. لا يتم إنشاء خزائن أو ربط طرق دفع تلقائيًا.</p>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">إعداد الفرع والخزائن</h3>
                </div>
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="cbBranch">الفرع</label>
                            <select id="cbBranch" class="form-control">
                                <option value="">اختر الفرع</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="cbName">اسم الخزينة</label>
                            <input id="cbName" type="text" maxlength="100" class="form-control" placeholder="مثال: الخزينة الرئيسية">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="cbAccount">حساب الأستاذ المرتبط</label>
                            <select id="cbAccount" class="form-control">
                                <option value="">اختر حساب أصول قابل للترحيل</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="cbId" value="0">
                    <div class="d-flex flex-wrap" style="gap:8px">
                        <button type="button" id="cbSave" class="btn btn-primary" disabled>حفظ الخزينة</button>
                        <button type="button" id="cbCancel" class="btn btn-secondary" disabled>إلغاء التعديل</button>
                    </div>
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered table-striped" id="cashBoxTable">
                            <thead>
                                <tr>
                                    <th>الخزينة</th>
                                    <th>حساب الأستاذ</th>
                                    <th>الرصيد</th>
                                    <th>الحالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="text-center text-muted">اختر الفرع لعرض الخزائن.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">الخزينة الافتراضية لطريقة الدفع النقدي</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">يمكن تحديد خزينة افتراضية اختيارية لطريقة الدفع النقدي. جميع خزائن الفرع النشطة تظل متاحة للاختيار عند تسجيل الحركة؛ التحويل البنكي والبطاقات والمحافظ لا تُربط بخزائن نقدية.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="paymentMethodCashBoxTable">
                            <thead>
                                <tr>
                                    <th>طريقة الدفع</th>
                                    <th>الخزينة المرتبطة</th>
                                    <th>حالة الربط</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="3" class="text-center text-muted">اختر الفرع أولًا.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
