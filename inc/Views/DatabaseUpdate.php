<div class="container-fluid my-3" id="dbUpdatePage">
    <div class="alert alert-warning">
        <b>قبل التحديث:</b> خذ نسخة احتياطية كاملة من قاعدة البيانات. التحديث يضيف حسابات وإعدادات وتقارير ولا يحذف أو يعدّل أي بيانات قائمة، ويمكن إعادة تشغيله بأمان.
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">تحديثات قاعدة البيانات</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-3" id="db-update-table">
                    <thead><tr><th>الملف</th><th>الأوامر</th><th>الحالة</th><th>آخر تنفيذ</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <button type="button" class="btn btn-success" id="btnDbUpdate" disabled>
                <i class="fa fa-database"></i> تحديث قاعدة البيانات الآن
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnDbRecheck">إعادة الفحص</button>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header"><h5 class="mb-0">فحص الجاهزية</h5></div>
        <div class="card-body"><ul class="list-unstyled mb-0" id="db-checks">جارٍ الفحص...</ul></div>
    </div>
</div>
