<!-- Content Wrapper -->
    <div id="crv" class="container-fluid crv mt-5">
                
                <!-- Metro Tiles -->
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

                <!-- Form Section -->
                <div id="form-section">
                    <!-- Form for Mandob -->
                    <div id="form-mandob" class="form-container m-0 p-0" style="margin:-5px; padding:0px">
						<?php
						require_once ABSPATH . "inc/Views/ReceivePayments.php";
						?>
					</div>

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
                    </div>-->

                    <!-- Form for Morad -->
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

                    <!-- Form for Client Return -->
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

                    <!-- Form for Open Payout -->
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
            </div>