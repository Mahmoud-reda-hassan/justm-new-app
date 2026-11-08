<!-- Content Wrapper -->
<div class="container-fluid mt-4 px-3 px-md-5">

    <!-- Metro Tiles (شكل حديث) -->
    <div id="metro-container" class="row g-4 justify-content-center mb-5">
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-mandob" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-primary">
                <i class="tile-icon fas fa-users fa-3x mb-2"></i>
                <div class="tile-title fw-bold">صرف مستحقات المندوب</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-masouq" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-success">
                <i class="tile-icon fas fa-bullhorn fa-3x mb-2"></i>
                <div class="tile-title fw-bold">صرف مستحقات مسوق</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-morad" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-dark">
                <i class="tile-icon fas fa-cogs fa-3x mb-2"></i>
                <div class="tile-title fw-bold">صرف مستحقات مورد</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-client-return" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-info">
                <i class="tile-icon fas fa-undo fa-3x mb-2"></i>
                <div class="tile-title fw-bold">صرف مستحقات عميل (مرتجع)</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-open" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-secondary">
                <i class="tile-icon fas fa-hand-holding-usd fa-3x mb-2"></i>
                <div class="tile-title fw-bold">صرف مفتوح</div>
            </div>
        </div>
    </div>

    <!-- Form Section (جميع المعرفات كما هي) -->
    <div id="form-section">

        <!-- Form for Mandob (نفس المعرفات، تصميم محسن) -->
        <div id="form-mandob" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-primary"><i class="fas fa-users"></i> صرف مستحقات المندوب</h4>
            <div class="mb-3">
                <label class="form-label fw-bold">اختيار المندوب</label>
                <select class="form-control mb-3" id="select-mandob">
                    <option value="">اختر المندوب</option>
                </select>
            </div>
      
            <!-- عرض الإجماليات -->
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="alert alert-primary py-2 mb-0 text-center">
                        <small>إجمالي حساب المندوب</small>
                        <h5 class="mt-1 mb-0" id="total-mandob">0.00</h5>
                    </div>
                </div>
               
            </div>
            <div class="mt-3">
                <label for="delivery-payment-method" class="fw-bold">طريقة صرف العمولة</label>
                <select class="form-control form-control-lg mt-1" id="delivery-payment-method">
                    <option value="">اختر طريقة الدفع</option>
                </select>
            </div>
            <div class="mt-3">
                <label for="delivery-cash-box" class="fw-bold">الخزينة التي سيتم الصرف منها</label>
                <select class="form-control form-control-lg mt-1" id="delivery-cash-box" disabled>
                    <option value="">اختر طريقة الدفع أولًا</option>
                </select>
            </div>
            <div class="mt-3">
                <label for="amount-mandob" class="fw-bold">المبلغ المراد صرفه:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-mandob" min="0" step="0.01">
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button class="btn btn-primary px-4" onclick="saveDeliveryPayment()">حفظ</button>
            </div>
        </div>

        <!-- Form for Masouq -->
        <div id="form-masouq" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-success"><i class="fas fa-bullhorn"></i> صرف مستحقات المسوق</h4>
            <div class="mb-3">
                <label class="form-label fw-bold">اختيار المسوق</label>
                <select class="form-control mb-3" id="select-masouq">
                    <option value="">اختر المسوق</option>
                </select>
            </div>
            
            <!-- عرض الإجماليات -->
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="alert alert-success py-2 mb-0 text-center">
                        <small>إجمالي حساب المسوق</small>
                        <h5 class="mt-1 mb-0" id="total-masouq">0.00</h5>
                    </div>
                </div>
               
            </div>
            
            <div class="mt-3">
                <label for="marketer-payment-method" class="fw-bold">طريقة صرف العمولة</label>
                <select class="form-control form-control-lg mt-1" id="marketer-payment-method">
                    <option value="">اختر طريقة الدفع</option>
                </select>
            </div>

            <div class="mt-3">
                <label for="marketer-cash-box" class="fw-bold">الخزينة:</label>
                <select class="form-control form-control-lg mt-1" id="marketer-cash-box" disabled>
                    <option value="">اختر طريقة الدفع أولًا</option>
                </select>
            </div>

            <div class="mt-3">
                <label for="amount-masouq" class="fw-bold">المبلغ المراد صرفه:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-masouq" min="0" step="0.01">
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button class="btn btn-success px-4" onclick="saveMarketerPayment()">حفظ</button>
            </div>
        </div>

        <!-- Form for Morad (محسن بالكامل مع الحفاظ على جميع المعرفات) -->
        <div id="form-morad" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-dark"><i class="fas fa-cogs"></i> صرف مستحقات المورد</h4>

            <!-- اختيار المورد -->
            <div class="mb-4">
                <label class="form-label fw-bold">اختيار المورد</label>
                <select class="form-control" id="select-morad">
                    <option value="">اختر المورد</option>
                </select>
            </div>

            <!-- عرض الإجماليات -->
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="alert bg-dark py-2 mb-0 text-center">
                        <small>رصيد المورد المستحق (GL)</small>
                        <h5 class="mt-1 mb-0" id="total-morad">0.00</h5>
                    </div>
                </div>
            </div>

            <!-- المبلغ المراد صرفه مع تلميح -->
            <div class="mt-4">
                <label for="amount-morad" class="fw-bold">المبلغ المراد صرفه:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-morad" min="0" step="0.01">
                <small class="text-muted">يتم توزيع المبلغ تلقائيًا على الفواتير الأقدم أولًا، وأي مبلغ زائد يُسجل مقدمًا للمورد.</small>
            </div>

            <!-- طريقة الدفع المعتمدة في دفتر الأستاذ العام -->
            <div class="mt-4">
                <label for="supplier-payment-method" class="fw-bold">طريقة الدفع:</label>
                <select class="form-control mt-1" id="supplier-payment-method">
                    <option value="">اختر طريقة الدفع</option>
                </select>
            </div>

            <!-- ملاحظات -->
            <div class="mt-4">
                <label class="fw-bold">ملاحظات:</label>
                <textarea class="form-control mt-1" id="morad-note" rows="2"></textarea>
            </div>

            <!-- أزرار التحكم -->
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button class="btn btn-primary px-4" id="saveMoradPayment" onclick="saveSupplierPayment()">حفظ</button>
            </div>

            <div class="row mt-3">
                <div class="col-md-4"><div class="alert alert-info mb-2">الموزع من الدفعة الحالية: <strong id="current-payment-total">0.00</strong></div></div>
                <div class="col-md-4"><div class="alert alert-warning mb-2">مقدم جديد من الدفعة: <strong id="supplier-advance-total">0.00</strong></div></div>
                <div class="col-md-4"><div class="alert alert-success mb-2">مقدم سابق متاح: <strong id="supplier-existing-advance">0.00</strong></div></div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                <small class="text-muted">يمكن تطبيق المقدم السابق تلقائيًا على أقدم الفواتير، أو تحديد مبلغ لكل فاتورة يدويًا.</small>
                <button type="button" class="btn btn-outline-success" onclick="applySupplierAdvances('AUTO')">تطبيق المقدم تلقائيًا</button>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="applySupplierAdvances('MANUAL')">تطبيق المبالغ المحددة يدويًا</button>
            </div>
            <!-- توزيع دفعة المورد على الفواتير -->
            <div class="table-responsive mt-4">
                <table id="supplierOrdersTable" class="table table-bordered table-striped table-hover w-100 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th width="40"><input type="checkbox" id="checkAllOrders"></th>
                            <th>رقم الأمر</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المرتجع</th>
                            <th>المسدّد</th>
                            <th>المتبقي</th>
                            <th width="140">من الدفعة الحالية</th>
                            <th width="140">من المقدم السابق</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Form for Client Return -->
        <div id="form-client-return" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-info"><i class="fas fa-undo"></i> صرف مستحقات عميل (مرتجع)</h4>
            <div class="mb-3">
                <label class="form-label fw-bold">أختر العميل</label>
                <select class="form-control" id="select-client">
                    <option value="">اختر العميل</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">أختر أمر البيع</label>
                <select class="form-control" id="select-order">
                    <option value="">اختر أمر البيع</option>
                </select>
            </div>
            <div class="mb-3 mt-3">
                <label for="refund-payment-method" class="form-label fw-bold">طريقة رد المبلغ</label>
                <select class="form-control" id="refund-payment-method">
                    <option value="">اختر طريقة الدفع</option>
                </select>
            </div>
            <!-- عرض الإجماليات -->
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="alert bg-info py-2 mb-0 text-center">
                        <small>إجمالي المرتجع</small>
                        <h5 class="mt-1 mb-0" id="return-amount">0.00</h5>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button id="saveFinancialReturn" class="btn btn-info px-4">حفظ</button>
            </div>
        </div>

        <!-- Form for Open Payout -->
        <div id="form-open" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-secondary"><i class="fas fa-hand-holding-usd"></i> صرف مفتوح</h4>
            <div class="mb-3">
                <label class="form-label fw-bold">سبب الصرف</label>
                <select class="form-control" id="reason-select">
                    <option value="">اختر السبب</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">الملاحظات:</label>
                <textarea class="form-control" id="notes" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label for="amount-open" class="fw-bold">المبلغ المراد صرفه:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-open" min="0" step="0.01">
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button class="btn btn-primary px-4" onclick="backToTiles()">حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS إضافي لتجميل البلاطات وتحسين التنسيق -->
<style>
    /* خلفيات متدرجة للبلاطات */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4e73df, #224abe);
        color: white;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .bg-gradient-success {
        background: linear-gradient(135deg, #1cc88a, #13855c);
        color: white;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .bg-gradient-warning {
        background: linear-gradient(135deg, #f6c23e, #dda20a);
        color: white;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .bg-gradient-info {
        background: linear-gradient(135deg, #36b9cc, #258391);
        color: white;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .bg-gradient-secondary {
        background: linear-gradient(135deg, #858796, #5a5c69);
        color: white;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .new-tile:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 1rem rgba(0,0,0,0.2) !important;
    }
    /* إخفاء الفورم في البداية */
    .form-container {
        display: none;
    }
    .form-container.active {
        display: block;
    }
    /* تحسين مظهر الجدول */
    .table thead th {
        background-color: #2c3e50;
        color: white;
        white-space: nowrap;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    /* تحسين مظهر الحقول */
    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
    }
    /* تنسيق الأزرار داخل الفورم */
    .btn {
        border-radius: 0.5rem;
        font-weight: 500;
    }
    .btn-primary {
        background-color: #0d6efd;
        border: none;
    }
    .btn-danger {
        background-color: #dc3545;
        border: none;
    }
</style>