<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>سندات القبض والصرف — الأستاذ العام</h1>
            <p class="text-muted mb-0">كل سند ينشئ قيدًا مرحلًا في دفتر الأستاذ. التحصيل النقدي الزائد عن مديونية العميل يُحفظ كرصيد دائن قابل للاستخدام.</p>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">إنشاء سند جديد</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="fvType">نوع السند</label>
                            <select id="fvType" class="form-control">
                                <option value="CUSTOMER_RECEIPT">قبض من عميل</option>
                                <option value="SUPPLIER_REFUND">استلام رد مبلغ من مورد</option>
                                <option value="GENERAL_RECEIPT">قبض إيراد آخر</option>
                                <option value="GENERAL_PAYMENT">صرف مصروف</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="fvCashBox">الخزينة</label>
                            <select id="fvCashBox" class="form-control">
                                <option value="">تحميل الخزائن...</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="fvDate">تاريخ السند</label>
                            <input id="fvDate" type="datetime-local" class="form-control">
                        </div>
                    </div>

                    <div id="fvPartnerPanel" class="form-row">
                        <div class="form-group col-md-6">
                            <label for="fvPartner">العميل / المورد</label>
                            <select id="fvPartner" class="form-control">
                                <option value="">اختر الطرف</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>رصيد الطرف الحالي</label>
                            <div id="fvPartnerBalance" class="form-control bg-light">—</div>
                        </div>
                    </div>

                    <div id="fvAccountPanel" class="form-row" style="display:none">
                        <div class="form-group col-md-12">
                            <label for="fvAccount">حساب الإيراد / المصروف</label>
                            <select id="fvAccount" class="form-control">
                                <option value="">اختر الحساب</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="fvAmount">المبلغ</label>
                            <input id="fvAmount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-group col-md-8">
                            <label for="fvDescription">البيان / سبب القبض أو الصرف</label>
                            <input id="fvDescription" type="text" maxlength="500" class="form-control" placeholder="اكتب بيانًا واضحًا للسند">
                        </div>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px">
                        <button type="button" id="fvSave" class="btn btn-primary">ترحيل السند</button>
                        <button type="button" id="fvReset" class="btn btn-secondary">تفريغ الحقول</button>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>تنبيه:</strong> هذه الشاشة تستخدم طريقة الدفع النقدي والخزينة المرتبطة بها فقط. التحويلات البنكية والبطاقات والمحافظ تُضاف بعد استكمال وحدة الحسابات البنكية.
                    </div>
                </div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">آخر سندات الفرع</h3>
                    <div class="card-tools">
                        <button type="button" id="fvRefresh" class="btn btn-sm btn-outline-secondary">تحديث</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="fvTable">
                            <thead>
                                <tr>
                                    <th>رقم السند</th>
                                    <th>النوع</th>
                                    <th>التاريخ</th>
                                    <th>الطرف</th>
                                    <th>الخزينة</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="8" class="text-center text-muted">جارٍ تحميل السندات...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
