<?php
$glGroup = trim((string)($_SESSION['USER_GRUOP_NAME'] ?? ''));
$glUs    = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
$glP     = function ($k) use ($glUs) { $v = is_array($glUs) ? ($glUs['Admin']['GlLedger'][$k] ?? null) : null; return $v === 'true' || $v === true; };
$glApproved = in_array($glGroup, ['Administrator', 'Owner', 'المدير المالي'], true);
$glPost  = $glApproved || $glP('post');
$glAdmin = in_array($glGroup, ['Administrator', 'Owner'], true) || $glP('admin');
?>
<script>window.GL_PERMS = {post: <?php echo $glPost ? 'true' : 'false'; ?>, admin: <?php echo $glAdmin ? 'true' : 'false'; ?>};</script>
<div class="container-fluid my-3" id="glLedgerPage">
    <ul class="nav nav-tabs mb-3" role="tablist" id="glTabs">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-tb">ميزان المراجعة</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-acc">كشف حساب</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-partner">كشف طرف</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-journal">دفتر اليومية</a></li>
        <?php if ($glPost) { ?>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-cash">افتتاحي النقدية</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-correct">تصحيح تحصيل</a></li>
        <?php } ?>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-admin">الفترات والإعدادات</a></li>
    </ul>

    <div class="tab-content">
        <!-- ميزان المراجعة -->
        <div class="tab-pane fade show active" id="tab-tb">
            <div class="form-inline mb-2">
                <label class="ml-2">من</label><input type="date" id="tb-from" class="form-control form-control-sm ml-3">
                <label class="ml-2">إلى</label><input type="date" id="tb-to" class="form-control form-control-sm ml-3">
                <button class="btn btn-sm btn-primary" id="tb-run">عرض</button>
                <span class="mr-3" id="tb-check"></span>
            </div>
            <div class="table-responsive"><table id="tb-table" class="table table-bordered table-sm w-100 text-nowrap">
                <thead><tr><th>الكود</th><th>الحساب</th><th>النوع</th><th>افتتاحي مدين</th><th>افتتاحي دائن</th><th>حركة مدين</th><th>حركة دائن</th><th>ختامي مدين</th><th>ختامي دائن</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>

        <!-- كشف حساب -->
        <div class="tab-pane fade" id="tab-acc">
            <div class="form-inline mb-2">
                <select id="acc-id" class="form-control form-control-sm ml-3" style="min-width:240px"><option value="">اختر الحساب</option></select>
                <label class="ml-2">من</label><input type="date" id="acc-from" class="form-control form-control-sm ml-3">
                <label class="ml-2">إلى</label><input type="date" id="acc-to" class="form-control form-control-sm ml-3">
                <button class="btn btn-sm btn-primary" id="acc-run">عرض</button>
                <span class="mr-3" id="acc-sum"></span>
            </div>
            <div class="table-responsive"><table id="acc-table" class="table table-bordered table-sm w-100 text-nowrap">
                <thead><tr><th>التاريخ</th><th>رقم القيد</th><th>النوع</th><th>المرجع</th><th>البيان</th><th>الطرف</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>

        <!-- كشف طرف -->
        <div class="tab-pane fade" id="tab-partner">
            <div class="form-inline mb-2">
                <select id="pt-type" class="form-control form-control-sm ml-3">
                    <option value="CLIENT">عميل</option><option value="SUPPLIER">مورد</option>
                    <option value="DELIVERY">مندوب</option><option value="MARKETER">مسوق</option>
                </select>
                <input type="text" id="pt-q" class="form-control form-control-sm ml-3" placeholder="اكتب الاسم ثم اختر" autocomplete="off">
                <select id="pt-id" class="form-control form-control-sm ml-3" style="min-width:200px"><option value="">اختر الطرف</option></select>
                <label class="ml-2">من</label><input type="date" id="pt-from" class="form-control form-control-sm ml-3">
                <label class="ml-2">إلى</label><input type="date" id="pt-to" class="form-control form-control-sm ml-3">
                <button class="btn btn-sm btn-primary" id="pt-run">عرض</button>
            </div>
            <div id="pt-sum" class="mb-2"></div>
            <div class="table-responsive"><table id="pt-table" class="table table-bordered table-sm w-100 text-nowrap">
                <thead><tr><th>التاريخ</th><th>رقم القيد</th><th>النوع</th><th>المرجع</th><th>الحساب</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>

        <!-- دفتر اليومية -->
        <div class="tab-pane fade" id="tab-journal">
            <div class="form-inline mb-2">
                <label class="ml-2">من</label><input type="date" id="jr-from" class="form-control form-control-sm ml-3">
                <label class="ml-2">إلى</label><input type="date" id="jr-to" class="form-control form-control-sm ml-3">
                <select id="jr-ref" class="form-control form-control-sm ml-3"><option value="">كل الأنواع</option></select>
                <button class="btn btn-sm btn-primary" id="jr-run">عرض</button>
                <small class="text-muted mr-3">اضغط على قيد لعرض سطوره</small>
            </div>
            <div class="table-responsive"><table id="jr-table" class="table table-bordered table-sm w-100 text-nowrap">
                <thead><tr><th>رقم القيد</th><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>البيان</th><th>مدين</th><th>دائن</th><th>السطور</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>

        <?php if ($glPost) { ?>
        <!-- افتتاحي النقدية -->
        <div class="tab-pane fade" id="tab-cash">
            <div class="alert alert-info">أدخل الرصيد الفعلي من الجرد لكل صندوق أو بنك. القيد: مدين الصندوق/البنك، دائن الأرصدة الافتتاحية. تعديل المبلغ لاحقًا يسجّل قيد الفرق ولا يحذف شيئًا.</div>
            <div class="form-inline mb-2"><label class="ml-2">تاريخ الافتتاح</label><input type="date" id="cash-date" class="form-control form-control-sm ml-3"></div>
            <div class="table-responsive"><table id="cash-table" class="table table-bordered table-sm w-100 text-nowrap">
                <thead><tr><th>النوع</th><th>الاسم</th><th>الحساب</th><th>الافتتاحي المسجّل</th><th>الرصيد الحالي بالأستاذ</th><th>الرصيد الافتتاحي الجديد</th><th>إجراء</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>

        <!-- تصحيح تحصيل -->
        <div class="tab-pane fade" id="tab-correct">
            <div class="alert alert-warning">عكس قيد تحصيل أمر مندوب على الأستاذ فقط. لا يعيد فتح الأمر ولا يغيّر النظام القديم ولا حركات المخزن. السبب إلزامي.</div>
            <div class="form-inline mb-2">
                <input type="text" id="cor-ref" class="form-control form-control-sm ml-3" placeholder="رقم الأمر (SUB_ORDER_ID أو الرقم الداخلي)" style="min-width:300px">
                <button class="btn btn-sm btn-primary" id="cor-find">بحث</button>
            </div>
            <div id="cor-result"></div>
        </div>
        <?php } ?>

        <!-- الفترات والإعدادات -->
        <div class="tab-pane fade" id="tab-admin">
            <div class="card mb-3"><div class="card-header">قيمة المخزون: الأستاذ مقابل التكلفة الحالية</div>
                <div class="card-body" id="adm-valuation">جارٍ التحميل...</div></div>
            <div class="card mb-3"><div class="card-header">إقفال الفترات المحاسبية</div>
                <div class="card-body">
                    <?php if ($glAdmin) { ?>
                    <div class="form-inline mb-3">
                        <label class="ml-2">من</label><input type="date" id="per-from" class="form-control form-control-sm ml-3">
                        <label class="ml-2">إلى</label><input type="date" id="per-to" class="form-control form-control-sm ml-3">
                        <input type="text" id="per-note" class="form-control form-control-sm ml-3" placeholder="ملاحظة">
                        <button class="btn btn-sm btn-danger" id="per-close">إقفال الفترة</button>
                    </div>
                    <?php } ?>
                    <small class="text-muted d-block mb-2">أي قيد بتاريخ داخل فترة مقفولة يُرفض، من أي شاشة.</small>
                    <div class="table-responsive"><table id="per-table" class="table table-bordered table-sm w-100">
                        <thead><tr><th>من</th><th>إلى</th><th>ملاحظة</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody></tbody>
                    </table></div>
                </div></div>
            <div class="card mb-3"><div class="card-header">مصدر الأرصدة في الشاشات المحوّلة</div>
                <div class="card-body">
                    <p class="mb-2">الافتراضي <b>النظام القديم</b>: لا تتغيّر أي شاشة. اختيار <b>الأستاذ</b> يجعل شاشات صرف المستحقات تقرأ الرصيد من الأستاذ. ارجع للقديم في أي وقت.</p>
                    <select id="flag-source" class="form-control form-control-sm d-inline-block" style="width:220px" <?php echo $glAdmin ? '' : 'disabled'; ?>>
                        <option value="legacy">النظام القديم (افتراضي)</option><option value="gl">الأستاذ الجديد</option>
                    </select>
                </div></div>
        </div>
    </div>
</div>
