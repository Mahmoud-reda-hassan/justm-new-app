@extends('layouts.app')

@section('title', 'صفات المنتجات')
@section('page-title', 'صفات المنتجات')
@section('breadcrumb', 'صفات المنتجات')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">تعريف مجموعات الصفات مثل اللون والمقاس والخامة؛ كل كود منتج يحدد قيمة واحدة من كل مجموعة مرتبطة به.</p>
    @if(auth()->user()->hasPermission('products.view'))<a class="btn btn-default btn-sm mb-2" href="{{ route('admin.products.index') }}">العودة للمنتجات</a>@endif
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(auth()->user()->hasPermission('product_attributes.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">إضافة مجموعة صفات</h3></div>
    <form method="post" action="{{ route('admin.product-attributes.groups.store') }}">@csrf
        <div class="card-body">
            <p class="text-muted">أنشئ المجموعة أولًا، ثم أضف قيمها.</p>
            <div class="form-row align-items-end">
                <div class="form-group col-md-6"><label>اسم المجموعة</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="اللون، المقاس، الخامة"></div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">إنشاء المجموعة</button></div>
    </form>
</div>
@endif

@forelse($groups as $group)
<div class="card card-outline {{ $group->is_active ? 'card-secondary' : 'card-danger' }}">
    <div class="card-header">
        <h3 class="card-title">{{ $group->name }} <small class="text-muted">({{ $group->values->count() }} قيمة · {{ $group->products_count }} منتج)</small></h3>
        <div class="card-tools"><span class="badge {{ $group->is_active ? 'badge-success' : 'badge-danger' }}">{{ $group->is_active ? 'نشطة' : 'موقوفة' }}</span></div>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center mb-3">
            @if(auth()->user()->hasPermission('product_attributes.update'))
                <details class="ml-2 mb-2">
                    <summary class="btn btn-sm btn-primary">تعديل اسم المجموعة</summary>
                    <form class="border rounded p-3 mt-2" method="post" action="{{ route('admin.product-attributes.groups.update', $group) }}">
                        @csrf @method('put')
                        <div class="form-row align-items-end"><div class="form-group col-md-6"><label>اسم المجموعة</label><input class="form-control" name="name" value="{{ $group->name }}" required></div><div class="form-group col-md-2"><button class="btn btn-primary">حفظ</button></div></div>
                    </form>
                </details>
            @endif
            @if(auth()->user()->hasPermission('product_attributes.activate'))
                <form class="mb-2" method="post" action="{{ route('admin.product-attributes.groups.active', $group) }}">
                    @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $group->is_active ? 0 : 1 }}">
                    <button class="btn btn-sm {{ $group->is_active ? 'btn-danger' : 'btn-success' }}">{{ $group->is_active ? 'إيقاف المجموعة' : 'تفعيل المجموعة' }}</button>
                </form>
            @endif
        </div>

        @if(auth()->user()->hasPermission('product_attributes.create') && $group->is_active)
            <form class="border rounded p-3 mb-3" method="post" action="{{ route('admin.product-attributes.values.store', $group) }}">@csrf
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6"><label>إضافة قيمة إلى {{ $group->name }}</label><input class="form-control" name="value" required maxlength="120" placeholder="أحمر، أزرق، كبير..."></div>
                    <div class="form-group col-md-2"><button class="btn btn-primary">إضافة قيمة</button></div>
                </div>
            </form>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead><tr><th>القيمة</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                @forelse($group->values as $value)
                    <tr>
                        <td>
                            @if(auth()->user()->hasPermission('product_attributes.update'))
                                <form class="form-row align-items-center mb-0" method="post" action="{{ route('admin.product-attributes.values.update', $value) }}">
                                    @csrf @method('put')
                                    <div class="col-md-7"><input class="form-control form-control-sm" name="value" value="{{ $value->value }}" required></div>
                                    <div class="col-md-2"><button class="btn btn-primary btn-sm">حفظ</button></div>
                                </form>
                            @else
                                {{ $value->value }}
                            @endif
                        </td>
                        <td><span class="badge {{ $value->is_active ? 'badge-success' : 'badge-danger' }}">{{ $value->is_active ? 'نشطة' : 'موقوفة' }}</span></td>
                        <td>
                            @if(auth()->user()->hasPermission('product_attributes.activate'))
                                <form method="post" action="{{ route('admin.product-attributes.values.active', $value) }}">
                                    @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $value->is_active ? 0 : 1 }}">
                                    <button class="btn btn-sm {{ $value->is_active ? 'btn-danger' : 'btn-success' }}">{{ $value->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">لا توجد قيم لهذه المجموعة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
<div class="card"><div class="card-body text-center text-muted">لا توجد مجموعات صفات مسجلة.</div></div>
@endforelse
<div class="mt-3">{{ $groups->links() }}</div>
@endsection
