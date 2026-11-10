@extends('layouts.app')

@section('title', 'المخازن')
@section('page-title', 'المخازن')
@section('breadcrumb', 'المخازن')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">إدارة المخازن التابعة للفروع وتحديد نوع كل مخزن.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('companies.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.companies') }}">الشركات</a>@endif
        @if(auth()->user()->hasPermission('branches.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.structure.branches') }}">الفروع</a>@endif
        @if(auth()->user()->hasPermission('warehouses.create'))<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createWarehouseModal"><i class="fas fa-plus ml-1"></i> إضافة مخزن</button>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">دليل المخازن</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($warehouses->total()) }} مخزن</span></div></div>
    <div class="card-body">
        <form method="get" class="form-row mb-3">
            <div class="col-md-6 mb-2"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث باسم المخزن أو الفرع أو الشركة أو النوع"></div>
            <div class="col-auto mb-2"><button class="btn btn-default" type="submit"><i class="fas fa-search ml-1"></i> بحث</button> <a class="btn btn-outline-secondary" href="{{ route('admin.structure.warehouses') }}">مسح</a></div>
        </form>
        <div class="table-responsive">
        <table class="table table-bordered table-hover table-striped">
            <thead><tr><th>المخزن</th><th>الفرع</th><th>الشركة</th><th>النوع</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($warehouses as $warehouse)
                <tr>
                    <td><strong>{{ $warehouse->name }}</strong></td>
                    <td>{{ $warehouse->branch->name }}</td>
                    <td>{{ $warehouse->branch->company->name }}</td>
                    <td>{{ $warehouse->type->name }}</td>
                    <td><span class="badge {{ $warehouse->is_active ? 'badge-success' : 'badge-danger' }}">{{ $warehouse->is_active ? 'نشط' : 'موقوف' }}</span></td>
                    <td><div class="btn-group btn-group-sm">
                        @if(auth()->user()->hasPermission('warehouses.update'))<button class="btn btn-primary" data-toggle="modal" data-target="#editWarehouseModal{{ $warehouse->id }}">تعديل</button>@endif
                        @if(auth()->user()->hasPermission('warehouses.activate'))
                            <form method="post" action="{{ route('admin.structure.warehouses.active', $warehouse) }}" onsubmit="return confirm('{{ $warehouse->is_active ? 'هل تريد إيقاف المخزن؟' : 'هل تريد تفعيل المخزن؟' }}')">
                                @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $warehouse->is_active ? 0 : 1 }}">
                                <button class="btn {{ $warehouse->is_active ? 'btn-danger' : 'btn-success' }}">{{ $warehouse->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        @endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">لا توجد مخازن ضمن نطاق الوصول.</td></tr>@endforelse
            </tbody>
        </table>
    </div>{{ $warehouses->links() }}</div>
</div>

@if(auth()->user()->hasPermission('warehouses.create'))
<div class="modal fade" id="createWarehouseModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.warehouses.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">إضافة مخزن</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <div class="form-group"><label>الفرع</label><select class="form-control" name="branch_id" required><option value="">اختر الفرع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>نوع المخزن</label><select class="form-control" name="warehouse_type_id" required><option value="">اختر النوع</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>اسم المخزن</label><input class="form-control" name="name" required maxlength="150"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ المخزن</button></div>
    </form>
</div></div></div>
@endif

@if(auth()->user()->hasPermission('warehouses.update'))
@foreach($warehouses as $warehouse)
<div class="modal fade" id="editWarehouseModal{{ $warehouse->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.structure.warehouses.update', $warehouse) }}">@csrf @method('put')
        <div class="modal-header"><h5 class="modal-title">تعديل المخزن: {{ $warehouse->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <div class="form-group"><label>الفرع</label><select class="form-control" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($warehouse->branch_id == $branch->id)>{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>نوع المخزن</label><select class="form-control" name="warehouse_type_id" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected($warehouse->warehouse_type_id == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>اسم المخزن</label><input class="form-control" name="name" value="{{ $warehouse->name }}" required maxlength="150"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
    </form>
</div></div></div>
@endforeach
@endif
@endsection
