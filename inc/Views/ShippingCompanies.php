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
                        <th>ملف الكشف</th>
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
                <div class="form-group" id="sc-courier-box">
                    <label>المندوب الذي يمثل الشركة (للعهدة والحسابات)</label>
                    <div id="sc-courier-new-edit">
                        <select id="sc-courier-mode" class="form-control">
                            <option value="NEW">إنشاء مستخدم مندوب جديد تلقائيًا (لا يستطيع الدخول)</option>
                            <option value="EXISTING">ربط بمندوب موجود حاليًا</option>
                        </select>
                        <select id="sc-courier-user" class="form-control mt-2" style="display:none"></select>
                        <small class="text-muted" id="sc-courier-hint">يمكن تغيير المندوب فقط طالما لا توجد عليه عهدة أو أوامر أو قيود.</small>
                    </div>
                    <div id="sc-courier-fixed" class="form-control-plaintext" style="display:none"></div>
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
                <hr>
                <h6>إعدادات ملف الكشف <small class="text-muted">(تُحفظ مع الشركة ويمكن تعديلها في أي وقت)</small></h6>
                <div class="alert alert-info small py-2 mb-2">
                    <strong>الأعمدة التي يحتاجها النظام من كشف الشركة:</strong>
                    <ul class="mb-0 pr-3">
                        <li><strong>رقم الأمر</strong> (SUB_ORDER_ID) — <span class="text-danger">مطلوب</span>: به يطابق النظام الأمر على عهدة الشركة.</li>
                        <li><strong>المبلغ المحصل</strong> — <span class="text-danger">مطلوب</span>: بمقارنته بقيمة الأمر يعرف النظام حالته (كامل / جزئي / مرتجع / مرتجع مع دفع الشحن).</li>
                        <li><strong>الحالة</strong> — اختياري: نص الحالة لتأكيد الاستنتاج وحسم الأوامر المؤجلة.</li>
                        <li><strong>رسوم الشركة</strong> — اختياري: لو رسومها تختلف أمرًا بأمر (للتسوية المالية لاحقًا).</li>
                    </ul>
                    وباقي أعمدة الملف يتجاهلها النظام.
                </div>
                <div id="cs-current" class="small mb-2"></div>
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="cs-file">ارفع ملفًا نموذجيًا من الشركة لضبط الأعمدة (xlsx / xls / csv)</label>
                        <input type="file" id="cs-file" class="form-control-file" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="form-group col-md-4" id="cs-sheet-box" style="display:none">
                        <label for="cs-sheet">الورقة</label>
                        <select id="cs-sheet" class="form-control"></select>
                    </div>
                </div>
                <div id="cs-map" style="display:none">
                    <div class="form-row">
                        <div class="form-group col-md-2"><label for="cs-hrow">صف العناوين</label><input type="number" id="cs-hrow" class="form-control" min="1" value="1"></div>
                        <div class="form-group col-md-3"><label for="cs-c-order">عمود رقم الأمر <span class="text-danger">*</span></label><select id="cs-c-order" class="form-control"></select></div>
                        <div class="form-group col-md-3"><label for="cs-c-amount">عمود المبلغ المحصل <span class="text-danger">*</span></label><select id="cs-c-amount" class="form-control"></select></div>
                        <div class="form-group col-md-2"><label for="cs-c-status">عمود الحالة</label><select id="cs-c-status" class="form-control"></select></div>
                        <div class="form-group col-md-2"><label for="cs-c-fee">عمود رسوم الشركة</label><select id="cs-c-fee" class="form-control"></select></div>
                    </div>
                    <div class="form-row align-items-center">
                        <div class="form-group col-md-7">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="cs-incl">
                                <label class="custom-control-label" for="cs-incl">المبلغ المحصل شامل قيمة الشحن</label>
                            </div>
                        </div>
                        <div class="form-group col-md-5"><label for="cs-tol">سماحية الفرق (جنيه)</label><input type="number" id="cs-tol" class="form-control" min="0" max="50" step="0.5" value="1"></div>
                    </div>
                    <div id="cs-statuses" class="mb-2"></div>
                    <div class="table-responsive"><table class="table table-sm table-bordered" id="cs-sample"></table></div>
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
