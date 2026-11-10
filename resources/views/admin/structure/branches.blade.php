@extends('layouts.app')

@section('title', 'الفروع')
@section('page-title', 'الفروع')
@section('breadcrumb', 'الفروع')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">إدارة الفروع التابعة للشركات، مع تحديد الفرع الرئيسي لكل شركة.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('companies.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.companies') }}">الشركات</a>@endif
        @if(auth()->user()->hasPermission('warehouses.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.warehouses') }}">المخازن</a>@endif
        @if(auth()->user()->hasPermission('branches.create'))<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createBranchModal"><i class="fas fa-plus ml-1"></i> إضافة فرع</button>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">دليل الفروع</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($branches->total()) }} فرع</span></div></div>
    <div class="card-body">
        <form method="get" class="form-row mb-3">
            <div class="col-md-6 mb-2"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث باسم الفرع أو الشركة أو المدينة أو العنوان"></div>
            <div class="col-auto mb-2"><button class="btn btn-default" type="submit"><i class="fas fa-search ml-1"></i> بحث</button> <a class="btn btn-outline-secondary" href="{{ route('admin.structure.branches') }}">مسح</a></div>
        </form>
        <div class="table-responsive">
        <table class="table table-bordered table-hover table-striped">
            <thead><tr><th>الفرع</th><th>الشركة</th><th>المدينة / العنوان</th><th>المخازن</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($branches as $branch)
                <tr>
                    <td><strong>{{ $branch->name }}</strong>@if($branch->is_main)<span class="badge badge-info mr-1">رئيسي</span>@endif</td>
                    <td>{{ $branch->company->name }}</td>
                    <td>{{ $branch->city?->name_ar ?: '—' }}<div class="text-muted small">{{ $branch->address }}</div></td>
                    <td>{{ $branch->warehouses_count }}</td>
                    <td><span class="badge {{ $branch->is_active ? 'badge-success' : 'badge-danger' }}">{{ $branch->is_active ? 'نشط' : 'موقوف' }}</span></td>
                    <td><div class="btn-group btn-group-sm">
                        @if(auth()->user()->hasPermission('branches.update'))<button class="btn btn-primary" data-toggle="modal" data-target="#editBranchModal{{ $branch->id }}">تعديل</button>@endif
                        @if(auth()->user()->hasPermission('branches.activate'))
                            <form method="post" action="{{ route('admin.structure.branches.active', $branch) }}" onsubmit="return confirm('{{ $branch->is_active ? 'هل تريد إيقاف الفرع؟' : 'هل تريد تفعيل الفرع؟' }}')">
                                @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $branch->is_active ? 0 : 1 }}">
                                <button class="btn {{ $branch->is_active ? 'btn-danger' : 'btn-success' }}">{{ $branch->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        @endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">لا توجد فروع ضمن نطاق الوصول.</td></tr>@endforelse
            </tbody>
        </table>
    </div>{{ $branches->links() }}</div>
</div>

@if(auth()->user()->hasPermission('branches.create'))
<div class="modal fade" id="createBranchModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.branches.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">إضافة فرع</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div class="form-row">
            <div class="form-group col-md-6"><label>الشركة</label><select class="form-control" name="company_id" required><option value="">اختر الشركة</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
            <div class="form-group col-md-6"><label>اسم الفرع</label><input class="form-control" name="name" required maxlength="150"></div>
            <div class="form-group col-md-6"><label>المدينة</label><select class="form-control" name="city_id"><option value="">اختياري</option>@foreach($cities as $city)<option value="{{ $city->id }}">{{ $city->name_ar }}</option>@endforeach</select></div>
            <div class="form-group col-md-6"><label>العنوان</label><input class="form-control" name="address" maxlength="500"></div>
            <div class="form-group col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_main" id="create-branch-main" value="1"><label class="form-check-label" for="create-branch-main">الفرع الرئيسي للشركة</label></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ الفرع</button></div>
    </form>
</div></div></div>
@endif

@if(auth()->user()->hasPermission('branches.update'))
@foreach($branches as $branch)
<div class="modal fade" id="editBranchModal{{ $branch->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.branches.update', $branch) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">تعديل الفرع: {{ $branch->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div class="form-row">
            <div class="form-group col-md-6"><label>الشركة</label><select class="form-control" name="company_id" required>@foreach($companies as $company)<option value="{{ $company->id }}" @selected($branch->company_id == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
            <div class="form-group col-md-6"><label>اسم الفرع</label><input class="form-control" name="name" value="{{ $branch->name }}" required maxlength="150"></div>
            <div class="form-group col-md-6"><label>المدينة</label><select class="form-control" name="city_id"><option value="">اختياري</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($branch->city_id == $city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
            <div class="form-group col-md-6"><label>العنوان</label><input class="form-control" name="address" value="{{ $branch->address }}" maxlength="500"></div>
            <div class="form-group col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_main" id="edit-branch-main-{{ $branch->id }}" value="1" @checked($branch->is_main)><label class="form-check-label" for="edit-branch-main-{{ $branch->id }}">الفرع الرئيسي للشركة</label></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
    </form>
</div></div></div>
@endforeach
@endif
@endsection
