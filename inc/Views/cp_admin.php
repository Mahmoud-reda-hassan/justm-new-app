<?php
$dashboardScript = [];
if (isset($_SESSION['USER_SCRIPT'])) {
    $dashboardScript = @unserialize($_SESSION['USER_SCRIPT'], ['allowed_classes' => false]);
}
$dashboardAdminPermissions = is_array($dashboardScript) ? ($dashboardScript['Admin'] ?? []) : [];
$dashboardRoles = $dashboardAdminPermissions['dashboard'] ?? [];
$dashboardCan = static function ($value) {
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
};
$canDashboardAdmin = $dashboardCan($dashboardRoles['Admin'] ?? false);
$canDashboardCS1 = $dashboardCan($dashboardRoles['CustomerService1'] ?? false);
$canDashboardCS2 = $dashboardCan($dashboardRoles['CustomerService2'] ?? false);
$canDashboardStorage = $dashboardCan($dashboardRoles['Storage'] ?? false);
$canDashboardDelivery = $dashboardCan($dashboardRoles['Delivery'] ?? false);
$canDashboardSuppliers = $dashboardCan($dashboardAdminPermissions['Suppliers']['View'] ?? false);
$canDashboardAny = $canDashboardAdmin || $canDashboardCS1 || $canDashboardCS2 || $canDashboardStorage || $canDashboardDelivery;
?>
<section class="content" id="adminDashboard" dir="rtl" data-endpoint="/inc/controller/dashbord.control.php">
  <div class="container-fluid">
    <div class="row align-items-center mb-3">
      <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
          <small class="text-muted mb-2 mb-md-0">ملخص تشغيلي مباشر حسب صلاحيات المستخدم</small>
          <div class="d-flex flex-wrap align-items-center">
            <input type="text" id="dashboardDateRange" class="d-none" autocomplete="off" aria-label="الفترة الزمنية">
            <div class="d-flex align-items-center border rounded px-2 py-1 ml-3 mb-2 mb-md-0 bg-light">
              <button type="button" class="btn btn-tool p-1" id="dashboardCalendar" title="اختيار الفترة الزمنية" aria-label="اختيار الفترة الزمنية"><i class="far fa-calendar-alt"></i></button>
              <small class="text-muted mr-2" id="dashboardSelectedPeriod"></small>
            </div>
            <div class="d-flex align-items-center mb-2 mb-md-0">
              <button type="button" class="btn btn-tool p-1" id="dashboardRefresh" title="تحديث الآن" aria-label="تحديث الآن"><i class="fas fa-sync-alt"></i></button>
              <small class="text-muted mr-2" id="dashboardUpdatedAt">آخر مزامنة --:--</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="alert alert-danger d-none" id="dashboardError" role="alert"></div>
    <div class="text-center text-muted py-3" id="dashboardLoading"><i class="fas fa-spinner fa-spin ml-1"></i> جار تحميل البيانات...</div>

    <?php if ($canDashboardAdmin): ?>
    <div class="row" id="dashboardTopMetrics">
      <div class="col-lg-4 col-6"><div class="small-box bg-primary"><div class="inner"><h3 data-metric="orders_system_total">—</h3><p>إجمالي الأوامر</p></div><div class="icon"><i class="ion ion-clipboard"></i></div></div></div>
      <div class="col-lg-4 col-6"><div class="small-box bg-success"><div class="inner"><h3 data-metric="orders_month">—</h3><p>إجمالي أوامر البيع خلال الفترة</p></div><div class="icon"><i class="ion ion-stats-bars"></i></div></div></div>
      <div class="col-lg-4 col-6"><div class="small-box bg-info"><div class="inner"><h3 data-metric="orders_today">—</h3><p>أوامر البيع اليوم</p></div><div class="icon"><i class="ion ion-bag"></i></div></div></div>
      <div class="col-lg-4 col-6"><div class="small-box bg-warning"><div class="inner"><h3 data-metric="unpaid_orders">—</h3><p>أوامر غير مسددة</p></div><div class="icon"><i class="ion ion-person-add"></i></div></div></div>
    </div>
    <div class="dashboard-metrics-separator my-3" style="border-top: 2px solid #dee2e6; padding-top: 1rem;"></div>
    <div class="row" id="dashboardExistsMetrics" aria-live="polite"></div>
    <div class="dashboard-exists-separator" style="clear: both; margin: 2rem 0 2.5rem; border-top: 2px solid #dee2e6;"></div>
    <?php endif; ?>

    <?php if ($canDashboardAdmin): ?>
    <section class="dashboard-section" id="dashboardAdminSection">
      <div class="row">
        <div class="col-12"><div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line ml-1"></i> الملخص الإداري</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-8"><div class="card card-light"><div class="card-header"><h3 class="card-title">حركة أوامر البيع</h3></div><div class="card-body"><div class="chart"><canvas id="dashboardOrdersChart" height="180"></canvas></div></div></div></div>
              <div class="col-md-4"><div class="card card-light"><div class="card-header"><h3 class="card-title">توزيع حالات أوامر البيع</h3></div><div class="card-body"><div class="chart"><canvas id="dashboardStatusChart" height="180"></canvas></div></div></div></div>
            </div>
            <div class="row">
              <div class="col-12"><div class="card">
                <div class="card-header"><h3 class="card-title">المنتجات الأكثر مبيعًا خلال الفترة</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
                <div class="card-body table-responsive p-0"><table id="dashboardTopProductsTable" class="table table-hover table-striped m-0"><thead><tr><th>المنتج</th><th>الكمية المباعة</th><th>قيمة المبيعات</th></tr></thead><tbody></tbody></table></div>
              </div></div>
            </div>
          </div>
        </div></div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($canDashboardCS1): ?>
    <section class="dashboard-section" id="dashboardCS1Section">
      <div class="card card-info">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-headset ml-1"></i> خدمة العملاء - المراجعة والتأكيد</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
        <div class="card-body"><div class="row">
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-clipboard-list"></i></span><div class="info-box-content"><span class="info-box-text">قيد المراجعة</span><span class="info-box-number" data-metric="cs1_review">—</span></div></div></div>
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">مؤجلات</span><span class="info-box-number" data-metric="cs1_pending">—</span></div></div></div>
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-box-open"></i></span><div class="info-box-content"><span class="info-box-text">غير متوفر</span><span class="info-box-number" data-metric="cs1_preorder">—</span></div></div></div>
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-phone-slash"></i></span><div class="info-box-content"><span class="info-box-text">العميل لا يستجيب</span><span class="info-box-number" data-metric="cs1_no_response">—</span></div></div></div>
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-dark"><i class="fas fa-user-times"></i></span><div class="info-box-content"><span class="info-box-text">إلغاء لعدم الرد</span><span class="info-box-number" data-metric="cs1_cancel_no_response">—</span></div></div></div>
          <div class="col-lg-2 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-ban"></i></span><div class="info-box-content"><span class="info-box-text">إلغاء أمر البيع</span><span class="info-box-number" data-metric="cs1_cancel_order">—</span></div></div></div>
        </div></div>
      </div>
    </section>
    <div class="dashboard-section-separator my-4" style="margin-top: 2rem !important; margin-bottom: 2rem !important; border-top: 2px solid #dee2e6; padding-top: 1.5rem;"></div>
    <?php endif; ?>

    <?php if ($canDashboardCS2): ?>
    <section class="dashboard-section" id="dashboardCS2Section">
      <div class="card card-info">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-comments ml-1"></i> خدمة العملاء - متابعة التجهيز والشحن</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
        <div class="card-body"><div class="row">
          <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-cogs"></i></span><div class="info-box-content"><span class="info-box-text">قيد التجهيز</span><span class="info-box-number" data-metric="cs2_processing">—</span></div></div></div>
          <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-box"></i></span><div class="info-box-content"><span class="info-box-text">تم التجهيز</span><span class="info-box-number" data-metric="cs2_prepared">—</span></div></div></div>
          <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-shipping-fast"></i></span><div class="info-box-content"><span class="info-box-text">جاري الشحن</span><span class="info-box-number" data-metric="cs2_shipping">—</span></div></div></div>
          <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-undo"></i></span><div class="info-box-content"><span class="info-box-text">مرفوض / مرتجع</span><span class="info-box-number" data-metric="cs2_returns">—</span></div></div></div>
        </div></div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($canDashboardStorage): ?>
    <section class="dashboard-section" id="dashboardStorageSection">
      <div class="card card-success">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-warehouse ml-1"></i> المخازن والمخزون</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
        <div class="card-body">
          <div class="row">
            <div class="col-lg-4 col-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-warehouse"></i></span><div class="info-box-content"><span class="info-box-text">عدد المخازن</span><span class="info-box-number" data-metric="storage_stores_count">—</span></div></div></div>
            <div class="col-lg-4 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span><div class="info-box-content"><span class="info-box-text">إجمالي وحدات المخزون</span><span class="info-box-number" data-metric="stock_units">—</span></div></div></div>
            <div class="col-lg-4 col-6"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span><div class="info-box-content"><span class="info-box-text">أصناف أقل من الحد الأدنى</span><span class="info-box-number" data-metric="low_stock_count">—</span></div></div></div>
          </div>
          <div class="card card-light"><div class="card-header"><h3 class="card-title">الأصناف التي تحتاج إعادة توفير</h3></div><div class="card-body table-responsive p-0"><table id="dashboardLowStockTable" class="table table-hover table-striped m-0"><thead><tr><th>المخزن</th><th>الصنف</th><th>كود الصنف</th><th>الحد الأدنى</th><th>الرصيد الحالي</th></tr></thead><tbody></tbody></table></div></div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($canDashboardDelivery): ?>
    <section class="dashboard-section" id="dashboardDeliverySection">
      <div class="card card-warning">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-shipping-fast ml-1"></i> متابعة التوصيل والمندوبين</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
        <div class="card-body">
          <div class="row">
            <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-truck"></i></span><div class="info-box-content"><span class="info-box-text">مسندة لمندوب</span><span class="info-box-number" data-metric="delivery_assigned_orders">—</span></div></div></div>
            <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">تم التسليم</span><span class="info-box-number" data-metric="delivery_delivered">—</span></div></div></div>
            <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span><div class="info-box-content"><span class="info-box-text">فشل / رفض التسليم</span><span class="info-box-number" data-metric="delivery_rejected">—</span></div></div></div>
            <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-undo"></i></span><div class="info-box-content"><span class="info-box-text">مرتجعات</span><span class="info-box-number" data-metric="delivery_returns">—</span></div></div></div>
          </div>
          <div class="card card-light"><div class="card-header"><h3 class="card-title">أداء المندوبين خلال الفترة</h3></div><div class="card-body table-responsive p-0"><table id="dashboardDeliveryTable" class="table table-hover table-striped m-0"><thead><tr><th>المندوب</th><th>الطلبات المسندة</th><th>تم التسليم</th><th>مرفوض</th><th>مرتجع</th></tr></thead><tbody></tbody></table></div></div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($canDashboardAny && ($canDashboardAdmin || $canDashboardCS1 || $canDashboardCS2 || $canDashboardDelivery)): ?>
    <section class="dashboard-section">
      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-list ml-1"></i> أحدث أوامر البيع</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
        <div class="card-body table-responsive p-0"><table id="dashboardOrdersTable" class="table table-hover table-striped m-0"><thead><tr><th>رقم الأمر</th><th>تاريخ الإنشاء</th><th>حالة المتابعة</th><th>حالة الطلب</th><th>حالة السداد</th><th>المندوب</th></tr></thead><tbody></tbody></table></div>
      </div>
    </section>
    <?php endif; ?>
  </div>
</section>
