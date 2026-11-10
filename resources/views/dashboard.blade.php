@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@section('breadcrumb', 'لوحة التحكم')

@push('styles')
<style>
    .dashboard-page .small-box{border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.1);margin-bottom:18px}
    .dashboard-page .small-box h3{font-size:30px;font-weight:700}
    .dashboard-page .small-box p{font-size:14px}
    .dashboard-page .small-box .icon{top:8px}
    .dashboard-page .card{box-shadow:0 1px 1px rgba(0,0,0,.08);margin-bottom:18px}
    .dashboard-page .card-title{font-size:15px;font-weight:600}
    .dashboard-page .card-header{padding:14px 16px}
    .dashboard-page .card-body{padding:16px}
    .dashboard-page .dashboard-chart-placeholder{height:230px;border:1px dashed #cbd5e1;border-radius:4px;background:repeating-linear-gradient(to bottom,#fff 0,#fff 45px,#f1f5f9 46px);display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px}
    .dashboard-page .progress-group{font-size:13px;margin-bottom:20px;color:#495057}
    .dashboard-page .progress-group:last-child{margin-bottom:0}
    .dashboard-page .progress{height:7px;background:#edf0f4}
    .dashboard-page .empty-widget{min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#6c757d;padding:24px}
    .dashboard-page .empty-widget i{font-size:25px;color:#adb5bd;margin-bottom:12px}
    .dashboard-page .empty-widget strong{font-size:14px;font-weight:600;color:#495057;margin-bottom:5px}
    .dashboard-page .empty-widget span{font-size:12px;line-height:1.8;max-width:340px}
    .dashboard-page .info-box{min-height:74px;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.08)}
    .dashboard-page .info-box-icon{width:68px;font-size:22px}
    .dashboard-page .info-box-number{font-size:19px}
    .dashboard-page .table th{border-top:0;background:#f8f9fa;color:#6c757d;font-size:11px;white-space:nowrap}
    .dashboard-page .table td{font-size:12px;vertical-align:middle}
    @media(max-width:767.98px){.dashboard-page .small-box h3{font-size:24px}.dashboard-page .dashboard-chart-placeholder{height:180px}}
</style>
@endpush

@section('content')
<div class="dashboard-page">
    @if (!auth()->user()->email)
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle ml-1" aria-hidden="true"></i> لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}" class="alert-link">إضافة بريد إلكتروني وتأكيده</a></div>
    @elseif (!auth()->user()->hasVerifiedEmail())
        <div class="alert alert-info"><i class="fas fa-info-circle ml-1" aria-hidden="true"></i> البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}" class="alert-link">تأكيد البريد الإلكتروني</a></div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3>—</h3><p>أوامر البيع</p></div><div class="icon"><i class="fas fa-shopping-cart" aria-hidden="true"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3>—</h3><p>إجمالي المبيعات</p></div><div class="icon"><i class="fas fa-chart-line" aria-hidden="true"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3>—</h3><p>المنتجات</p></div><div class="icon"><i class="fas fa-boxes" aria-hidden="true"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-danger"><div class="inner"><h3>—</h3><p>حركات المخزون</p></div><div class="icon"><i class="fas fa-warehouse" aria-hidden="true"></i></div></div></div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h3 class="card-title">ملخص النشاط</h3><div class="card-tools text-muted small">نظرة عامة</div></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="text-center font-weight-bold text-muted mb-3">حركة المبيعات خلال الفترة</p>
                            <div class="dashboard-chart-placeholder"><span><i class="fas fa-chart-area ml-1" aria-hidden="true"></i> سيظهر الرسم البياني بعد ربط بيانات التقارير</span></div>
                        </div>
                        <div class="col-md-4 mt-4 mt-md-0">
                            <p class="text-center font-weight-bold text-muted mb-4">ملخص العمليات</p>
                            @foreach (['أوامر جديدة', 'أوامر قيد المتابعة', 'أوامر تم تسليمها', 'مرتجعات قيد المراجعة'] as $label)
                                <div class="progress-group">{{ $label }}<span class="float-left text-muted">—</span><div class="progress mt-2"><div class="progress-bar bg-secondary" style="width:0%"></div></div></div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row text-center">
                        <div class="col-sm-3 col-6 border-left"><div class="description-block"><span class="description-header text-muted">—</span><span class="description-text d-block">قيمة المبيعات</span></div></div>
                        <div class="col-sm-3 col-6 border-left"><div class="description-block"><span class="description-header text-muted">—</span><span class="description-text d-block">التحصيلات</span></div></div>
                        <div class="col-sm-3 col-6 border-left"><div class="description-block"><span class="description-header text-muted">—</span><span class="description-text d-block">المرتجعات</span></div></div>
                        <div class="col-sm-3 col-6"><div class="description-block"><span class="description-header text-muted">—</span><span class="description-text d-block">صافي الحركة</span></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header border-transparent">
                    <h3 class="card-title">أحدث أوامر البيع</h3>
                    @if (auth()->user()->hasPermission('sales.orders.view'))
                        <div class="card-tools"><a href="{{ route('admin.sales.orders.index') }}" class="btn btn-tool text-primary">عرض الكل <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i></a></div>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table m-0">
                            <thead><tr><th>رقم الأمر</th><th>العميل</th><th>التاريخ</th><th>الحالة</th><th>الإجمالي</th></tr></thead>
                            <tbody><tr><td colspan="5"><div class="empty-widget"><i class="fas fa-clipboard-list" aria-hidden="true"></i><strong>لا توجد أوامر لعرضها</strong><span>سيظهر هنا أحدث أوامر البيع عند ربط بيانات لوحة التحكم.</span></div></td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-boxes" aria-hidden="true"></i></span><div class="info-box-content"><span class="info-box-text">أصناف المنتجات</span><span class="info-box-number">—</span></div></div>
            <div class="info-box bg-success"><span class="info-box-icon"><i class="fas fa-users" aria-hidden="true"></i></span><div class="info-box-content"><span class="info-box-text">الأطراف المسجلة</span><span class="info-box-number">—</span></div></div>
            <div class="info-box bg-danger"><span class="info-box-icon"><i class="fas fa-truck" aria-hidden="true"></i></span><div class="info-box-content"><span class="info-box-text">عمليات التوصيل</span><span class="info-box-number">—</span></div></div>
            <div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-wallet" aria-hidden="true"></i></span><div class="info-box-content"><span class="info-box-text">التسويات المالية</span><span class="info-box-number">—</span></div></div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">أحدث المنتجات</h3></div>
                <div class="card-body p-0"><div class="empty-widget"><i class="fas fa-box-open" aria-hidden="true"></i><strong>لا توجد بيانات للعرض</strong><span>ستظهر أحدث المنتجات هنا بعد ربط بيانات الكتالوج.</span></div></div>
            </div>
        </div>
    </div>
</div>
@endsection
