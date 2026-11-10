@extends('layouts.app')

@section('title', 'تصنيفات المنتجات')
@section('page-title', 'تصنيفات المنتجات')
@section('breadcrumb', 'تصنيفات المنتجات')

@section('content')
<header class="head"><div><span class="eyebrow">JUST-M · كتالوج المنتجات</span><h1>تصنيفات المنتجات</h1><p>تنظيم المنتجات في شجرة تصنيفات رئيسية وفرعية.</p></div><nav class="nav"><a href="{{ route('dashboard') }}">الرئيسية</a>@if(auth()->user()->hasPermission('products.view'))<a href="{{ route('admin.products.index') }}">المنتجات</a>@endif @if(auth()->user()->hasPermission('units.view'))<a href="{{ route('admin.catalog.units') }}">وحدات القياس</a>@endif</nav></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(auth()->user()->hasPermission('product_categories.create'))
<section class="panel"><h2>إضافة تصنيف</h2><p class="sub">يمكن إنشاء تصنيف رئيسي أو إسناده إلى تصنيف أب.</p>
<form method="post" action="{{ route('admin.catalog.categories.store') }}">@csrf
<div class="fields">
<div class="field"><label>اسم التصنيف</label><input name="name" value="{{ old('name') }}" required maxlength="150"></div>
<div class="field"><label>الكود</label><input name="code" value="{{ old('code') }}" maxlength="50" placeholder="اختياري"></div>
<div class="field"><label>التصنيف الأب</label><select name="parent_id"><option value="">تصنيف رئيسي</option>@foreach($parentOptions as $option)<option value="{{ $option->id }}" @selected((string)old('parent_id')===(string)$option->id)>{{ $option->name }}{{ $option->is_active?'':' — موقوف' }}</option>@endforeach</select></div>
<div class="field"><label>ترتيب العرض</label><input name="sort_order" type="number" min="0" value="{{ old('sort_order',0) }}"></div>
<button class="btn primary" type="submit">حفظ التصنيف</button>
</div></form></section>
@endif
<section class="panel"><h2>دليل التصنيفات</h2><p class="sub">{{ $categories->total() }} تصنيف مسجل.</p>
<div class="table-wrap"><table><thead><tr><th>التصنيف</th><th>الكود</th><th>التصنيف الأب</th><th>الترتيب</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
@forelse($categories as $category)
<tr><td><strong>{{ $category->name }}</strong></td><td>{{ $category->code ?: '—' }}</td><td>{{ $category->parent?->name ?: 'رئيسي' }}</td><td>{{ $category->sort_order }}</td><td><span class="status {{ $category->is_active?'on':'off' }}">{{ $category->is_active?'نشط':'موقوف' }}</span></td><td>
<div class="actions">
@if(auth()->user()->hasPermission('product_categories.activate'))<form method="post" action="{{ route('admin.catalog.categories.active',$category) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $category->is_active?0:1 }}"><button class="btn" type="submit">{{ $category->is_active?'إيقاف':'تفعيل' }}</button></form>@endif
@if(auth()->user()->hasPermission('product_categories.update'))<details><summary>تعديل</summary><form class="edit" method="post" action="{{ route('admin.catalog.categories.update',$category) }}">@csrf @method('put')
<div class="fields">
<div class="field"><label>الاسم</label><input name="name" value="{{ $category->name }}" required></div>
<div class="field"><label>الكود</label><input name="code" value="{{ $category->code }}"></div>
<div class="field"><label>الأب</label><select name="parent_id"><option value="">رئيسي</option>@foreach($parentOptions as $option)@if($option->id!==$category->id)<option value="{{ $option->id }}" @selected($category->parent_id==$option->id)>{{ $option->name }}</option>@endif @endforeach</select></div>
<div class="field"><label>الترتيب</label><input name="sort_order" type="number" min="0" value="{{ $category->sort_order }}"></div>
<button class="btn primary" type="submit">حفظ</button>
</div></form></details>@endif
</div></td></tr>
@empty<tr><td colspan="6" class="empty">لا توجد تصنيفات بعد.</td></tr>@endforelse
</tbody></table></div>{{ $categories->links() }}</section>
@endsection
