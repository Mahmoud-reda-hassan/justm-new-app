<!-- Content Wrapper -->
<div class="container-fluid mt-4 px-3 px-md-5">

    <!-- Metro Tiles (شكل حديث) -->
    <div id="metro-container" class="row g-4 justify-content-center mb-5">
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-mandob" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-primary btn-mandob">
                <i class="tile-icon fas fa-user-tie"></i>
                <div class="tile-title fw-bold">استلام مدفوعات المبيعات من المندوب</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-client-return" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-dark">
                <i class="tile-icon fas fa-users"></i>
                <div class="tile-title fw-bold">استلام مدفوعات المبيعات من العميل</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-morad" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-success">
                <i class="tile-icon fas fa-cogs"></i>
                <div class="tile-title fw-bold">استلام نقدى من مورد (مرتجع)</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 col-xl-2">
            <div id="btn-open" class="metro-tile new-tile text-center p-4 rounded-4 shadow-sm bg-gradient-secondary">
                <i class="tile-icon fas fa-hand-holding-usd"></i>
                <div class="tile-title fw-bold">استلام مفتوح</div>
            </div>
        </div>
    </div>

    <!-- Form Section (جميع المعرفات كما هي) -->
    <div id="form-section">

        <!-- Form for Mandob (نفس المعرفات، تصميم محسن) -->
        <div id="form-mandob" class="form-container m-0 p-0" style="margin:-5px; padding:0px">
			<?php
				require_once ABSPATH . "inc/Views/ReceivePayments.php";
			?>
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
            <h4 class="border-bottom pb-2 mb-4 text-dark"><i class="fas fa-cogs"></i> استلام مديونية المورد</h4>

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
                        <small>إجمالي المديونية على المورد</small>
                        <h5 class="mt-1 mb-0" id="total-morad">0.00</h5>
                    </div>
                </div>
            </div>

            <!-- المبلغ المراد صرفه مع تلميح -->
            <div class="mt-4">
                <label for="amount-morad" class="fw-bold">المبلغ المراد صرفه:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-morad" min="0" step="0.01">
                <small class="text-muted">عند كتابة مبلغ سيتم توزيعه تلقائيًا على أوامر الشراء</small>
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

            <!-- جدول أوامر الشراء 
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
                            <th width="140">المبلغ الحالي</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>-->
        </div>

        <!-- Form for Client Return -->
        <div id="form-client-return" class="form-container bg-white rounded-4 shadow-lg p-4 mb-5">
            <h4 class="border-bottom pb-2 mb-4 text-info"><i class="fas fa-undo"></i>استلام مدفوعات البيع المباشر</h4>
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
            <!-- عرض الإجماليات -->
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="alert bg-info py-2 mb-0 text-center">
                        <small>إجمالي مديونية العميل</small>
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
            <h4 class="border-bottom pb-2 mb-4 text-secondary"><i class="fas fa-hand-holding-usd"></i> استلام نقدى مفتوح</h4>
            <div class="mb-3">
                <label class="form-label fw-bold">نوع الاستلام</label>
                <select class="form-control" id="reason-select">
                    <option value="">اختر السبب</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">الملاحظات:</label>
                <textarea class="form-control" id="notes" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label for="amount-open" class="fw-bold">المبلغ المستلم:</label>
                <input type="number" class="form-control form-control-lg mt-1" id="amount-open" min="0" step="0.01">
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-danger px-4" onclick="backToTiles()">رجوع</button>
                <button class="btn btn-primary px-4" onclick="backToTiles()">حفظ</button>
            </div>
        </div>
    </div>
</div>




<!-- Content Wrapper 
    <div id="crv" class="container-fluid crv mt-5">
                
                <!-- Metro Tiles --
                <div id="metro-container" class="metro-container">
                    <div class="metro-tile" id="btn-mandob">
                        <i class="tile-icon fas fa-user-tie"></i>
                        <div class="tile-title">استلام مدفوعات المبيعات من المندوب</div>
                    </div>
                    
                    <div class="metro-tile" id="btn-morad">
                        <i class="tile-icon fas fa-cogs"></i>
                        <div class="tile-title">استلام نقدى من مورد (مرتجع)</div>
                    </div>
                    <div class="metro-tile" id="btn-client-return">
                        <i class="tile-icon fas fa-users"></i>
                        <div class="tile-title">استلام مدفوعات المبيعات من العميل</div>
                    </div>
                    <div class="metro-tile" id="btn-open">
                        <i class="tile-icon fas fa-hand-holding-usd"></i>
                        <div class="tile-title">استلام مفتوح</div>
                    </div>
                </div>

                <!-- Form Section --
                <div id="form-section">
                    
                    <!-- Form for Masouq 
                    <div id="form-masouq" class="form-container">
                        <h5>اختيار المسوق</h5>
                        <select class="form-control mb-3" id="select-masouq">
                            <option value="">اختر المسوق</option>
                        </select>
                        <div class="mt-3">
                            <label>إجمالي حساب المسوق:</label>
                            <span id="total-masouq">0.00</span>
                        </div>
                        <div class="mt-3">
                            <label for="amount-masouq">المبلغ المراد صرفه:</label>
                            <input type="number" class="form-control" id="amount-masouq" min="0" step="0.01">
                        </div>
                        <button class="btn btn-primary btn-back" onclick="backToTiles()">رجوع</button>
                    </div>--

                    <!-- Form for Morad --
                    <div id="form-morad" class="form-container">
                        <h5>اختيار المورد</h5>
                        <select class="form-control mb-3" id="select-morad">
                            <option value="">اختر المورد</option>
                        </select>
                        <div class="mt-3">
                            <label>إجمالي المبلغ الواجب سداده:</label>
                            <span id="total-morad">0.00</span>
                        </div>
                        <div class="mt-3">
                            <label for="amount-morad">المبلغ المراد استلامه:</label>
                            <input type="number" class="form-control" id="amount-morad" min="0" step="0.01">
                        </div>
                        <button class="btn btn-primary btn-back" onclick="backToTiles()">رجوع</button>
                    </div>

                    <!-- Form for Client Return --
                    <div id="form-client-return" class="form-container">
                        <h5>اختيار العميل و أمر البيع</h5>
                        <select class="form-control mb-3" id="select-client">
                            <option value="">اختر العميل</option>
                        </select>
                        <select class="form-control mb-3" id="select-order">
                            <option value="">اختر أمر البيع</option>
                        </select>
                        <div class="mt-3">
                            <label>المبلغ المطلوب سداده:</label>
                            <span id="return-amount">0.00</span>
                        </div>
                        <button class="btn btn-primary btn-back" onclick="backToTiles()">رجوع</button>
                    </div>

                    <!-- Form for Open Payout --
                    <div id="form-open" class="form-container">
                        <h5>سبب الاستلام</h5>
                        <select class="form-control mb-3" id="reason-select">
                            <option value="">اختر السبب</option>
                        </select>
                        <div class="mt-3">
                            <label>الملاحظات:</label>
                            <textarea class="form-control" id="notes"></textarea>
                        </div>
                        <div class="mt-3">
                            <label for="amount-open">المبلغ المراد استلامه:</label>
                            <input type="number" class="form-control" id="amount-open" min="0" step="0.01">
                        </div>
                        <button class="btn btn-primary btn-back" onclick="backToTiles()">رجوع</button>
                    </div>
                </div>
            </div>-->