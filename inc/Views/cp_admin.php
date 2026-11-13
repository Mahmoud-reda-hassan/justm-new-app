<?php
$dashboardScript = [];
if (isset($_SESSION['USER_SCRIPT'])) {
    $dashboardScript = @unserialize($_SESSION['USER_SCRIPT'], ['allowed_classes' => false]);
}
$dashboardAdminPermissions = $dashboardScript['Admin'] ?? [];
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
?>
<section class="content" id="adminDashboard" dir="rtl" data-endpoint="/inc/controller/dashbord.control.php">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h4 class="mb-1">لوحة تحكم الإدارة</h4>
                <small class="text-muted">ملخص تشغيلي حسب الصلاحيات الممنوحة لمجموعة المستخدم</small>
            </div>
            <div class="d-flex align-items-center mt-2 mt-md-0">
                <small class="text-muted ml-2" id="dashboardUpdatedAt"></small>
                <button type="button" class="btn btn-outline-primary btn-sm" id="dashboardRefresh">
                    <i class="fas fa-sync-alt ml-1"></i> تحديث البيانات
                </button>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="dashboardError" role="alert"></div>
        <div class="text-center text-muted py-3" id="dashboardLoading">
            <i class="fas fa-spinner fa-spin ml-1"></i> جار تحميل بيانات لوحة التحكم...
        </div>

        <?php if ($canDashboardAdmin): ?>
        <section class="card card-outline card-primary dashboard-section" id="dashboardAdminSection">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line ml-1"></i> الملخص الإداري</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="small-box bg-info">
                            <div class="inner"><h3 data-metric="orders_today">—</h3><p>أوامر البيع اليوم</p></div>
                            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="small-box bg-warning">
                            <div class="inner"><h3 data-metric="unpaid_orders">—</h3><p>أوامر غير مسددة</p></div>
                            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        </div>
                    </div>
                    <?php if ($canDashboardSuppliers): ?>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="small-box bg-success">
                            <div class="inner"><h3 data-metric="suppliers_count">—</h3><p>عدد الموردين</p></div>
                            <div class="icon"><i class="fas fa-truck-loading"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="small-box bg-secondary">
                            <div class="inner"><h3 data-metric="stores_count">—</h3><p>عدد المخازن</p></div>
                            <div class="icon"><i class="fas fa-warehouse"></i></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card card-light">
                            <div class="card-header"><h3 class="card-title">حركة أوامر البيع خلال آخر 7 أيام</h3></div>
                            <div class="card-body"><div style="height:260px"><canvas id="dashboardOrdersChart"></canvas></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canDashboardCS1): ?>
        <section class="card card-outline card-info dashboard-section" id="dashboardCS1Section">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-headset ml-1"></i> خدمة العملاء - المتابعة</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-clipboard-list"></i></span><div class="info-box-content"><span class="info-box-text">إجمالي أوامر البيع</span><span class="info-box-number" data-metric="cs1_total_orders">—</span></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-calendar-day"></i></span><div class="info-box-content"><span class="info-box-text">أوامر البيع اليوم</span><span class="info-box-number" data-metric="cs1_today_orders">—</span></div></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canDashboardCS2): ?>
        <section class="card card-outline card-info dashboard-section" id="dashboardCS2Section">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-comments ml-1"></i> خدمة العملاء - الدعم</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-clipboard-check"></i></span><div class="info-box-content"><span class="info-box-text">إجمالي أوامر البيع</span><span class="info-box-number" data-metric="cs2_total_orders">—</span></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-calendar-day"></i></span><div class="info-box-content"><span class="info-box-text">أوامر البيع اليوم</span><span class="info-box-number" data-metric="cs2_today_orders">—</span></div></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canDashboardStorage): ?>
        <section class="card card-outline card-success dashboard-section" id="dashboardStorageSection">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-warehouse ml-1"></i> ملخص المخازن والمخزون</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-warehouse"></i></span><div class="info-box-content"><span class="info-box-text">عدد المخازن</span><span class="info-box-number" data-metric="storage_stores_count">—</span></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span><div class="info-box-content"><span class="info-box-text">إجمالي وحدات المخزون</span><span class="info-box-number" data-metric="stock_units">—</span></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span><div class="info-box-content"><span class="info-box-text">أصناف أقل من الحد الأدنى</span><span class="info-box-number" data-metric="low_stock_count">—</span></div></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canDashboardDelivery): ?>
        <section class="card card-outline card-warning dashboard-section" id="dashboardDeliverySection">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-shipping-fast ml-1"></i> ملخص التوصيل</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-truck"></i></span><div class="info-box-content"><span class="info-box-text">أوامر مسندة للتوصيل</span><span class="info-box-number" data-metric="delivery_assigned_orders">—</span></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">تم تسليمها اليوم</span><span class="info-box-number" data-metric="delivery_delivered_today">—</span></div></div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>
</section>
