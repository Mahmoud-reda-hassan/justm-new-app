@extends('layouts.app')

@section('title', 'وحدات القياس')
@section('page-title', 'وحدات القياس')
@section('breadcrumb', 'وحدات القياس')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">تعريف الوحدة الأساسية التي تُسجل بها كميات كل منتج ودقتها العشرية.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('products.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.products.index') }}">المنتجات</a>@endif
        @if(auth()->user()->hasPermission('product_categories.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.catalog.categories') }}">تصنيفات المنتجات</a>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(auth()->user()->hasPermission('units.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">إضافة وحدة قياس</h3></div>
    <form method="post" action="{{ route('admin.catalog.units.store') }}">
        @csrf
        <div class="card-body">
            <p class="text-muted">حدد اسم الوحدة ورمزها وعدد الخانات العشرية المسموح بها.</p>
            <div class="form-row">
                <div class="form-group col-md-5"><label>اسم الوحدة</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="80" placeholder="قطعة، كرتونة، كيلوجرام"></div>
                <div class="form-group col-md-3"><label>الرمز</label><input class="form-control" name="symbol" value="{{ old('symbol') }}" maxlength="20" placeholder="قطعة، كجم"></div>
                <div class="form-group col-md-2"><label>الخانات العشرية</label><input class="form-control" name="decimal_places" type="number" min="0" max="6" value="{{ old('decimal_places', 0) }}" required></div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="fas fa-save ml-1"></i> حفظ الوحدة</button></div>
    </form>
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header"><h3 class="card-title">الوحدات المسجلة</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($units->total()) }} وحدة</span></div></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead><tr><th>اسم الوحدة</th><th>الرمز</th><th>الدقة العشرية</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                @forelse($units as $unit)
                    <tr>
                        <td><strong>{{ $unit->name }}</strong></td>
                        <td>{{ $unit->symbol ?: '—' }}</td>
                        <td>{{ $unit->decimal_places }}</td>
                        <td><span class="badge {{ $unit->is_active ? 'badge-success' : 'badge-danger' }}">{{ $unit->is_active ? 'نشط' : 'موقوف' }}</span></td>
                        <td>
                            <div class="d-flex flex-wrap align-items-center">
                                @if(auth()->user()->hasPermission('units.activate'))
                                    <form method="post" action="{{ route('admin.catalog.units.active', $unit) }}" class="ml-1 mb-1">
                                        @csrf @method('patch')
                                        <input type="hidden" name="is_active" value="{{ $unit->is_active ? 0 : 1 }}">
                                        <button class="btn btn-sm {{ $unit->is_active ? 'btn-danger' : 'btn-success' }}" type="submit">{{ $unit->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                @endif
                                @if(auth()->user()->hasPermission('units.update'))
                                    <details class="mb-1"><summary class="btn btn-sm btn-primary">تعديل</summary>
                                        <form class="border rounded p-3 mt-2" method="post" action="{{ route('admin.catalog.units.update', $unit) }}">
                                            @csrf @method('put')
                                            <div class="form-row">
                                                <div class="form-group col-md-5"><label>اسم الوحدة</label><input class="form-control" name="name" value="{{ $unit->name }}" required></div>
                                                <div class="form-group col-md-3"><label>الرمز</label><input class="form-control" name="symbol" value="{{ $unit->symbol }}"></div>
                                                <div class="form-group col-md-2"><label>الدقة العشرية</label><input class="form-control" name="decimal_places" type="number" min="0" max="6" value="{{ $unit->decimal_places }}" required></div>
                                            </div>
                                            <button class="btn btn-primary btn-sm" type="submit">حفظ التعديلات</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">لا توجد وحدات قياس مسجلة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $units->links() }}</div>
    </div>
</div>
@endsection
