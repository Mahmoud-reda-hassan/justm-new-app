<?php
$scAdmin = (isset($_SESSION['USER_GRUOP_NAME']) && in_array(trim($_SESSION['USER_GRUOP_NAME']), ['Administrator','Owner','المدير المالي'], true));
if (!$scAdmin) {
    $us = isset($_SESSION['USER_SCRIPT']) ? @unserialize($_SESSION['USER_SCRIPT']) : null;
    $scAdmin = is_array($us) && (($us['Admin']['ShippingCompanies']['admin'] ?? '') === 'true');
}
?>
<script src="/libraries/jszip/jszip.min.js"></script>
<script>window.SC_CAN_ADMIN = <?php echo $scAdmin ? 'true' : 'false'; ?>;</script>
<div class="container-fluid my-3" id="shippingCompaniesPage">
    <div class="card shadow rounded">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h5 class="mb-0">شركات الشحن</h5>
                <small class="text-muted">الشركة لا تدخل النظام. تُصرف لها العهدة وتُسجَّل نتائجها بواسطة الموظف.</small>
            </div>
            <button type="button" class="btn btn-primary" id="scAdd" style="display:none"><i class="fa fa-plus"></i> شركة جديدة</button>
        </div>
        <div class="card-body table-responsive p-2">
            <table class="table table-bordered table-hover table-striped w-100" id="scTable">
                <thead class="thead-dark">
                    <tr>
                        <th>الشركة</th>
                        <th>سياسة الاستلام</th>
                        <th>طريقة التسوية</th>
                        <th>الرسوم</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scModalTitle">شركة شحن</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sc-id" value="0">
                <div class="form-group">
                    <label for="sc-name">اسم الشركة</label>
                    <input type="text" id="sc-name" class="form-control" maxlength="100">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="sc-policy">سياسة استلام المرتجع في المخزن</label>
                        <select id="sc-policy" class="form-control">
                            <option value="SINGLE">استلام كامل بإذن واحد</option>
                            <option value="PARTIAL">استلام على دفعات بأذون متعددة</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="sc-method">طريقة التسوية المالية</label>
                        <select id="sc-method" class="form-control">
                            <option value="NET_OF_FEE">خصم رسوم الشحن من التحصيل وتوريد الصافي</option>
                            <option value="GROSS_THEN_FEE">توريد قيمة المنتجات كاملة ودفع الرسوم منفصلة</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="sc-feemode">طريقة حساب رسوم الشركة</label>
                        <select id="sc-feemode" class="form-control">
                            <option value="ORDER_SHIPPING">نفس شحن الأمر (ما دفعه العميل كشحن)</option>
                            <option value="FIXED">مبلغ ثابت لكل شحنة</option>
                            <option value="ZONE_RATE">أسعار حسب المنطقة</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6" id="sc-fixed-box" style="display:none">
                        <label for="sc-fixed">الرسم الثابت لكل شحنة</label>
                        <input type="number" id="sc-fixed" class="form-control" min="0" step="0.01" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="sc-note">ملاحظة</label>
                    <input type="text" id="sc-note" class="form-control" maxlength="255">
                </div>
                <div class="alert alert-info mb-0 small">
                    رسوم الشركة تُسجَّل كمصروف شحن خارجي وتُسوَّى مع الشركة نفسها، وليست عمولة مندوب داخلي.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="scSave">حفظ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scRatesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scRatesTitle">أسعار المناطق</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">أدخل ما تأخذه الشركة عن كل شحنة في المنطقة. اترك الخانة فارغة لو المنطقة غير مسعّرة. تُستخدم هذه الأسعار فقط عندما تكون طريقة الرسوم "أسعار حسب المنطقة".</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="scRatesTable">
                        <thead class="thead-light"><tr><th>المنطقة</th><th>شحن العميل</th><th>رسم الشركة</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-success" id="scRatesSave" style="display:none">حفظ الأسعار</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid my-3" id="scImportPage" style="display:none">
    <div class="card shadow rounded">
        <div class="card-header">
            <h5 class="mb-0">رفع كشف شركة الشحن (معاينة)</h5>
            <small class="text-muted">المعاينة لا تغيّر أي بيانات. تطبيق النتائج خطوة منفصلة تأتي بعد مراجعتك.</small>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="imp-company">شركة الشحن</label>
                    <select id="imp-company" class="form-control"></select>
                </div>
                <div class="form-group col-md-5">
                    <label for="imp-file">ملف الكشف (xlsx / xls / csv)</label>
                    <input type="file" id="imp-file" class="form-control-file" accept=".xlsx,.xls,.csv">
                </div>
                <div class="form-group col-md-3" id="imp-sheet-box" style="display:none">
                    <label for="imp-sheet">الورقة</label>
                    <select id="imp-sheet" class="form-control"></select>
                </div>
            </div>
            <div id="imp-map" style="display:none">
                <hr>
                <div class="form-row">
                    <div class="form-group col-md-2"><label for="imp-hrow">صف العناوين</label><input type="number" id="imp-hrow" class="form-control" min="1" value="1"></div>
                    <div class="form-group col-md-3"><label for="imp-c-order">عمود رقم الأمر <span class="text-danger">*</span></label><select id="imp-c-order" class="form-control"></select></div>
                    <div class="form-group col-md-3"><label for="imp-c-amount">عمود المبلغ المحصل</label><select id="imp-c-amount" class="form-control"></select></div>
                    <div class="form-group col-md-2"><label for="imp-c-status">عمود الحالة</label><select id="imp-c-status" class="form-control"></select></div>
                    <div class="form-group col-md-2"><label for="imp-c-fee">عمود رسوم الشركة</label><select id="imp-c-fee" class="form-control"></select></div>
                </div>
                <div class="form-row align-items-center">
                    <div class="form-group col-md-5">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="imp-incl">
                            <label class="custom-control-label" for="imp-incl">المبلغ المحصل شامل قيمة الشحن</label>
                        </div>
                    </div>
                    <div class="form-group col-md-3"><label for="imp-tol">سماحية الفرق (جنيه)</label><input type="number" id="imp-tol" class="form-control" min="0" max="50" step="0.5" value="1"></div>
                    <div class="form-group col-md-4 text-md-left"><button type="button" class="btn btn-primary" id="imp-run"><i class="fa fa-search"></i> معاينة</button></div>
                </div>
                <div class="table-responsive"><table class="table table-sm table-bordered" id="imp-sample"></table></div>
            </div>
            <div id="imp-result" style="display:none">
                <hr>
                <div id="imp-summary" class="mb-2"></div>
                <div id="imp-statuses" class="mb-3"></div>
                <div class="btn-group btn-group-sm mb-2" id="imp-filter">
                    <button type="button" class="btn btn-outline-secondary active" data-f="ALL">الكل</button>
                    <button type="button" class="btn btn-outline-success" data-f="OK">سليم</button>
                    <button type="button" class="btn btn-outline-warning" data-f="REVIEW">يحتاج مراجعة</button>
                    <button type="button" class="btn btn-outline-danger" data-f="ERROR">خطأ</button>
                </div>
                <div class="table-responsive"><table class="table table-sm table-bordered table-striped" id="imp-table"></table></div>
                <div id="imp-missing" class="small text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>
