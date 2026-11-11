@extends('layouts.app')

@section('title', 'تحديث قاعدة البيانات')
@section('page-title', 'تحديث قاعدة البيانات')
@section('breadcrumb', 'تحديث قاعدة البيانات')

@section('content')
<div class="mb-3"><a class="btn btn-default btn-sm" href="{{ route('dashboard') }}"><i class="fas fa-arrow-right ml-1"></i> العودة للوحة التحكم</a></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">
        <pre class="mb-0 text-wrap" style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $errors->first() }}</pre>
    </div>
@endif

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-database"></i></span><div class="info-box-content"><span class="info-box-text">نوع الاتصال</span><span class="info-box-number">{{ $driver }}</span></div></div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-server"></i></span><div class="info-box-content"><span class="info-box-text">الخادم</span><span class="info-box-number text-sm">{{ $host }}</span></div></div>
    </div>
    <div class="col-lg-4 col-md-12">
        <div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-database"></i></span><div class="info-box-content"><span class="info-box-text">قاعدة البيانات</span><span class="info-box-number text-sm">{{ $database }}</span></div></div>
    </div>
</div>

<div class="callout callout-danger">
    <h5><i class="fas fa-exclamation-triangle ml-1"></i> تنبيه قبل التنفيذ</h5>
    تأكد بنفسك أن قاعدة البيانات المعروضة تخص بيئة الاختبار وليست قاعدة الإنتاج. التحديث ينفذ تغييرات بنيوية وقد لا يمكن التراجع عنها بمجرد تشغيلها.
</div>

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">حالة تحديثات قاعدة البيانات</h3></div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <div class="col-md-4"><div class="description-block"><h5 class="description-header">{{ count($migrations) }}</h5><span class="description-text">إجمالي التحديثات</span></div></div>
            <div class="col-md-4"><div class="description-block"><h5 class="description-header text-success">{{ count($migrations) - count($pending) }}</h5><span class="description-text">منفذ</span></div></div>
            <div class="col-md-4"><div class="description-block"><h5 class="description-header text-warning">{{ count($pending) }}</h5><span class="description-text">معلق</span></div></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead><tr><th>ملف التحديث</th><th>الحالة</th></tr></thead>
                <tbody>
                @forelse($migrations as $migration)
                    <tr><td>{{ $migration['name'] }}</td><td><span class="badge {{ $migration['ran'] ? 'badge-success' : 'badge-warning' }}">{{ $migration['ran'] ? 'منفذ' : 'معلق' }}</span></td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted">لا توجد ملفات migrations.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(count($pending))
<div class="card card-outline card-warning">
    <div class="card-header"><h3 class="card-title">تنفيذ التحديثات المعلقة</h3></div>
    <form method="post" action="{{ route('admin.system.database-updates.run') }}" onsubmit="return confirm('تأكدت أن قاعدة البيانات المعروضة هي قاعدة الاختبار؟');">
        @csrf
        <div class="card-body">
            <p>سيتم تشغيل migrations المعلقة فقط بالترتيب. لن يتم تشغيل Seeders أو إنشاء قاعدة بيانات أو حذف بيانات.</p>
            <div class="form-group"><label>اكتب اسم قاعدة البيانات كما يظهر أعلاه</label><input class="form-control" name="database_confirmation" required autocomplete="off"></div>
            <div class="form-group"><label>اكتب RUN MIGRATIONS للتأكيد</label><input class="form-control" name="run_confirmation" required autocomplete="off"></div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-warning"><i class="fas fa-play ml-1"></i> تنفيذ تحديث قاعدة البيانات</button></div>
    </form>
</div>
@else
<div class="alert alert-success"><i class="fas fa-check-circle ml-1"></i> قاعدة البيانات محدثة بالفعل.</div>
@endif
@endsection
