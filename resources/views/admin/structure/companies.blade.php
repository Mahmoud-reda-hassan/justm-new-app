@extends('layouts.app')

@section('title', 'الشركات')
@section('page-title', 'الشركات')
@section('breadcrumb', 'الشركات')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">إدارة الشركات والمؤسسات التي تتبعها الفروع والمخازن.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('branches.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.branches') }}">الفروع</a>@endif
        @if(auth()->user()->hasPermission('warehouses.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.warehouses') }}">المخازن</a>@endif
        @if(auth()->user()->hasPermission('companies.create'))<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createCompanyModal"><i class="fas fa-plus ml-1"></i> إضافة شركة</button>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">دليل الشركات</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($companies->total()) }} شركة</span></div></div>
    <div class="card-body">
        <form method="get" class="form-row mb-3">
            <div class="col-md-6 mb-2"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث باسم الشركة"></div>
            <div class="col-auto mb-2"><button class="btn btn-default" type="submit"><i class="fas fa-search ml-1"></i> بحث</button> <a class="btn btn-outline-secondary" href="{{ route('admin.structure.companies') }}">مسح</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead><tr><th>الشركة</th><th>بيانات الاتصال</th><th>الفروع</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                @forelse($companies as $company)
                    <tr>
                        <td><strong>{{ $company->name }}</strong><div class="text-muted small">{{ $company->address ?: '—' }}</div></td>
                        <td>{{ $company->phone ?: '—' }}<div class="text-muted small">{{ $company->mobile }} {{ $company->email }}</div></td>
                        <td>{{ $company->branches_count }} <small class="text-muted">(نشط: {{ $company->active_branches_count }})</small></td>
                        <td><span class="badge {{ $company->is_active ? 'badge-success' : 'badge-danger' }}">{{ $company->is_active ? 'نشطة' : 'موقوفة' }}</span></td>
                        <td><div class="btn-group btn-group-sm">
                            @if(auth()->user()->hasPermission('companies.update'))<button class="btn btn-primary" data-toggle="modal" data-target="#editCompanyModal{{ $company->id }}">تعديل</button>@endif
                            @if(auth()->user()->hasPermission('companies.activate'))
                                <form method="post" action="{{ route('admin.structure.companies.active', $company) }}" onsubmit="return confirm('{{ $company->is_active ? 'هل تريد إيقاف الشركة؟' : 'هل تريد تفعيل الشركة؟' }}')">
                                    @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $company->is_active ? 0 : 1 }}">
                                    <button class="btn {{ $company->is_active ? 'btn-danger' : 'btn-success' }}">{{ $company->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                </form>
                            @endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="5" class="text-center text-muted py-4">لا توجد شركات ضمن نطاق الوصول.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    </div>
</div>

@if(auth()->user()->hasPermission('companies.create'))
<div class="modal fade" id="createCompanyModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.companies.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">إضافة شركة</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div class="form-row">
            <div class="form-group col-md-6"><label>اسم الشركة</label><input class="form-control" name="name" required maxlength="150"></div>
            <div class="form-group col-md-3"><label>الهاتف</label><input class="form-control" name="phone" maxlength="40"></div>
            <div class="form-group col-md-3"><label>المحمول</label><input class="form-control" name="mobile" maxlength="40"></div>
            <div class="form-group col-md-3"><label>الفاكس</label><input class="form-control" name="fax" maxlength="40"></div>
            <div class="form-group col-md-5"><label>البريد الإلكتروني</label><input class="form-control" name="email" type="email"></div>
            <div class="form-group col-md-4"><label>المدينة</label><select class="form-control" name="city_id"><option value="">اختياري</option>@foreach($cities as $city)<option value="{{ $city->id }}">{{ $city->name_ar }}</option>@endforeach</select></div>
            <div class="form-group col-12"><label>العنوان</label><textarea class="form-control" name="address" rows="2"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ الشركة</button></div>
    </form>
</div></div></div>
@endif

@if(auth()->user()->hasPermission('companies.update'))
@foreach($companies as $company)
<div class="modal fade" id="editCompanyModal{{ $company->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.companies.update', $company) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">تعديل الشركة: {{ $company->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div class="form-row">
            <div class="form-group col-md-6"><label>اسم الشركة</label><input class="form-control" name="name" value="{{ $company->name }}" required maxlength="150"></div>
            <div class="form-group col-md-3"><label>الهاتف</label><input class="form-control" name="phone" value="{{ $company->phone }}" maxlength="40"></div>
            <div class="form-group col-md-3"><label>المحمول</label><input class="form-control" name="mobile" value="{{ $company->mobile }}" maxlength="40"></div>
            <div class="form-group col-md-3"><label>الفاكس</label><input class="form-control" name="fax" value="{{ $company->fax }}" maxlength="40"></div>
            <div class="form-group col-md-5"><label>البريد الإلكتروني</label><input class="form-control" name="email" type="email" value="{{ $company->email }}"></div>
            <div class="form-group col-md-4"><label>المدينة</label><select class="form-control" name="city_id"><option value="">بدون مدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($company->city_id == $city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
            <div class="form-group col-12"><label>العنوان</label><textarea class="form-control" name="address" rows="2">{{ $company->address }}</textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
    </form>
</div></div></div>
@endforeach
@endif
@endsection
