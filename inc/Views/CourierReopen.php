<div class="container-fluid my-3" id="courierReopenPage">
    <div id="legacy-alert" class="alert alert-warning d-none" role="alert"></div>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">رد أوامر المندوب للمخزن</h5>
            <small class="text-muted">
                للأوامر التي اعتمدها أمين المخزن ولم تُحصّل بعد. عند الرد تُعكس القيود المالية وحركة المخزون
                بقيود مضادة (دون حذف)، ويعود الأمر لأمين المخزن ليعدّل التسليم. السبب إلزامي.
            </small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="courier-reopen-table" class="table table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>رقم الأمر</th>
                            <th>المندوب</th>
                            <th>الحالة</th>
                            <th>العنوان</th>
                            <th>الهاتف</th>
                            <th>تاريخ التسليم</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header">
            <h5 class="mb-0">ترحيل أوامر مُقفلة قبل تشغيل الأستاذ</h5>
            <small class="text-muted">
                أوامر مندوب اكتمل تحصيلها في النظام القديم ولم يُسجَّل لها قيد في الأستاذ. الترحيل يسجّل قيد التسليم
                وقيد التحصيل والعمولات بنفس أرقام النظام القديم، ولا يغيّر أي بيان قديم.
            </small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="courier-backfill-table" class="table table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>رقم الأمر</th>
                            <th>المندوب</th>
                            <th>الحالة</th>
                            <th>الشحن</th>
                            <th>آخر تحديث</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header">
            <h5 class="mb-0">مطابقة أرصدة النظام القديم (عملاء، مندوبون، مسوقون، موردون، مخزون)</h5>
            <small class="text-muted">
                الفرق = رصيد النظام القديم − رصيد الأستاذ. الترحيل يسجّل قيد تسوية مقابل حساب "أرصدة افتتاحية" (3300)
                ولا يغيّر أي بيان قديم. العملاء: تُستبعد الأوامر غير المُقفلة (تُرحَّل عند الاستلام المالي).
                راجع الأرقام قبل الترحيل.
            </small>
        </div>
        <div class="card-body">
            <button type="button" class="btn btn-success btn-sm mb-2" id="btnPostAllOpening">
                <i class="fa fa-check"></i> ترحيل الكل
            </button>
            <button type="button" class="btn btn-primary btn-sm mb-2" id="btnBackfillAll">
                <i class="fa fa-book"></i> ترحيل كل الأوامر المُقفلة
            </button>
            <div class="table-responsive">
                <table id="opening-balance-table" class="table table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>النظام القديم</th>
                            <th>الأستاذ</th>
                            <th>الفرق</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
