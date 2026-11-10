@extends('layouts.app')

@section('title', 'تصنيفات المنتجات')
@section('page-title', 'تصنيفات المنتجات')
@section('breadcrumb', 'تصنيفات المنتجات')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">تنظيم المنتجات في تصنيفات رئيسية وفرعية، مع الحفاظ على العلاقات الهرمية.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('products.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.products.index') }}">المنتجات</a>@endif
        @if(auth()->user()->hasPermission('units.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.catalog.units') }}">وحدات القياس</a>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(auth()->user()->hasPermission('product_categories.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">إضافة تصنيف</h3></div>
    <form method="post" action="{{ route('admin.catalog.categories.store') }}">
        @csrf
        <div class="card-body">
            <p class="text-muted">يمكن إنشاء تصنيف رئيسي أو إسناده إلى تصنيف أب موجود.</p>
            <div class="form-row">
                <div class="form-group col-md-4"><label>اسم التصنيف</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="150"></div>
                <div class="form-group col-md-2"><label>الكود</label><input class="form-control" name="code" value="{{ old('code') }}" maxlength="50" placeholder="اختياري"></div>
                <div class="form-group col-md-4"><label>التصنيف الأب</label><select class="form-control" name="parent_id"><option value="">تصنيف رئيسي</option>@foreach($parentOptions as $option)<option value="{{ $option->id }}" @selected((string)old('parent_id') === (string)$option->id)>{{ $option->name }}{{ $option->is_active ? '' : ' — موقوف' }}</option>@endforeach</select></div>
                <div class="form-group col-md-2"><label>ترتيب العرض</label><input class="form-control" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}"></div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="fas fa-save ml-1"></i> حفظ التصنيف</button></div>
    </form>
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header">
        <h3 class="card-title">شجرة التصنيفات</h3>
        <div class="card-tools">
            <span class="badge badge-secondary">{{ number_format($categories->count()) }} تصنيف</span>
            <button type="button" class="btn btn-tool" id="expandCategoryTree" title="توسيع الكل"><i class="fas fa-expand-alt"></i></button>
            <button type="button" class="btn btn-tool" id="collapseCategoryTree" title="طي الفروع"><i class="fas fa-compress-alt"></i></button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="categoryTree">
                <thead><tr><th>التصنيف</th><th>الكود</th><th>التصنيف الأب</th><th>الترتيب</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr data-category-id="{{ $category->id }}" data-parent-id="{{ $category->parent_id ?? '' }}" data-category-depth="{{ $category->tree_depth }}">
                        <td>
                            <span class="d-inline-flex align-items-center" style="padding-right: {{ 8 + ($category->tree_depth * 22) }}px">
                                @if($category->has_children)
                                    <button type="button" class="btn btn-xs btn-default ml-1" data-category-toggle data-expanded="true" data-id="{{ $category->id }}" aria-label="طي الفروع">−</button>
                                @else
                                    <i class="fas fa-level-up-alt fa-rotate-90 text-muted ml-2"></i>
                                @endif
                                <strong>{{ $category->name }}</strong>
                            </span>
                        </td>
                        <td>{{ $category->code ?: '—' }}</td>
                        <td>{{ $category->parent?->name ?: 'رئيسي' }}</td>
                        <td>{{ $category->sort_order }}</td>
                        <td><span class="badge {{ $category->is_active ? 'badge-success' : 'badge-danger' }}">{{ $category->is_active ? 'نشط' : 'موقوف' }}</span></td>
                        <td>
                            <div class="d-flex flex-wrap align-items-center">
                                @if(auth()->user()->hasPermission('product_categories.activate'))
                                    <form method="post" action="{{ route('admin.catalog.categories.active', $category) }}" class="ml-1 mb-1">
                                        @csrf @method('patch')
                                        <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                        <button class="btn btn-sm {{ $category->is_active ? 'btn-danger' : 'btn-success' }}" type="submit">{{ $category->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                @endif
                                @if(auth()->user()->hasPermission('product_categories.update'))
                                    <details class="mb-1"><summary class="btn btn-sm btn-primary">تعديل</summary>
                                        <form class="border rounded p-3 mt-2" method="post" action="{{ route('admin.catalog.categories.update', $category) }}">
                                            @csrf @method('put')
                                            <div class="form-row">
                                                <div class="form-group col-md-4"><label>الاسم</label><input class="form-control" name="name" value="{{ $category->name }}" required></div>
                                                <div class="form-group col-md-2"><label>الكود</label><input class="form-control" name="code" value="{{ $category->code }}"></div>
                                                <div class="form-group col-md-4"><label>الأب</label><select class="form-control" name="parent_id"><option value="">رئيسي</option>@foreach($parentOptions as $option)@if($option->id !== $category->id)<option value="{{ $option->id }}" @selected($category->parent_id == $option->id)>{{ $option->name }}</option>@endif @endforeach</select></div>
                                                <div class="form-group col-md-2"><label>الترتيب</label><input class="form-control" name="sort_order" type="number" min="0" value="{{ $category->sort_order }}"></div>
                                            </div>
                                            <button class="btn btn-primary btn-sm" type="submit">حفظ التعديلات</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">لا توجد تصنيفات مسجلة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>
<script>
(function () {
    const table = document.getElementById('categoryTree');
    if (!table) return;

    function setChildrenVisible(parentId, visible) {
        table.querySelectorAll('tbody tr[data-parent-id="' + parentId + '"]').forEach(function (child) {
            child.hidden = !visible;
            const toggle = child.querySelector('[data-category-toggle]');
            if (toggle) {
                const expanded = visible && toggle.dataset.expanded === 'true';
                toggle.textContent = expanded ? '−' : '+';
                if (!visible) setChildrenVisible(child.dataset.categoryId, false);
                else if (expanded) setChildrenVisible(child.dataset.categoryId, true);
            }
        });
    }

    table.querySelectorAll('[data-category-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const expanded = this.dataset.expanded === 'true';
            this.dataset.expanded = expanded ? 'false' : 'true';
            this.textContent = expanded ? '+' : '−';
            setChildrenVisible(this.dataset.id, !expanded);
        });
    });

    document.getElementById('expandCategoryTree')?.addEventListener('click', function () {
        table.querySelectorAll('tbody tr').forEach(row => row.hidden = false);
        table.querySelectorAll('[data-category-toggle]').forEach(toggle => {
            toggle.dataset.expanded = 'true';
            toggle.textContent = '−';
        });
    });

    document.getElementById('collapseCategoryTree')?.addEventListener('click', function () {
        table.querySelectorAll('tbody tr[data-category-depth]').forEach(row => {
            if (Number(row.dataset.categoryDepth) > 0) row.hidden = true;
        });
        table.querySelectorAll('[data-category-toggle]').forEach(toggle => {
            toggle.dataset.expanded = 'false';
            toggle.textContent = '+';
        });
    });
})();
</script>
@endsection
