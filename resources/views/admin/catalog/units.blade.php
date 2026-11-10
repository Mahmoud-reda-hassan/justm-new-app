@extends('layouts.app')

@section('title', 'وحدات القياس')
@section('page-title', 'وحدات القياس')
@section('breadcrumb', 'وحدات القياس')

@section('content')
<header class="head"><div><span class="eyebrow">JUST-M · كتالوج المنتجات</span><h1>وحدات القياس</h1><p>تعريف الوحدة الأساسية التي تُسجل بها كميات كل منتج ودقتها العشرية.</p></div><nav class="nav"><a href="{{ route('dashboard') }}">الرئيسية</a>@if(auth()->user()->hasPermission('products.view'))<a href="{{ route('admin.products.index') }}">المنتجات</a>@endif @if(auth()->user()->hasPermission('product_categories.view'))<a href="{{ route('admin.catalog.categories') }}">تصنيفات المنتجات</a>@endif</nav></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(auth()->user()->hasPermission('units.create'))
<section class="panel"><h2>إضافة وحدة قياس</h2><p class="sub">حدد اسم الوحدة ورمزها وعدد الخانات العشرية المسموح بها.</p>
<form method="post" action="{{ route('admin.catalog.units.store') }}">@csrf
<div class="fields">
<div class="field"><label>اسم الوحدة</label><input name="name" value="{{ old('name') }}" required maxlength="80" placeholder="قطعة، كرتونة، كيلوجرام"></div>
<div class="field"><label>الرمز</label><input name="symbol" value="{{ old('symbol') }}" maxlength="20" placeholder="قطعة، كجم"></div>
<div class="field"><label>الخانات العشرية</label><input name="decimal_places" type="number" min="0" max="6" value="{{ old('decimal_places',0) }}" required></div>
<button class="btn primary" type="submit">حفظ الوحدة</button>
</div></form></section>
@endif
<section class="panel"><h2>الوحدات المسجلة</h2><p class="sub">{{ $units->total() }} وحدة قياس.</p>
<div class="table-wrap"><table><thead><tr><th>اسم الوحدة</th><th>الرمز</th><th>الدقة العشرية</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
@forelse($units as $unit)
<tr><td><strong>{{ $unit->name }}</strong></td><td>{{ $unit->symbol ?: '—' }}</td><td>{{ $unit->decimal_places }}</td><td><span class="status {{ $unit->is_active?'on':'off' }}">{{ $unit->is_active?'نشط':'موقوف' }}</span></td><td>
<div class="actions">
@if(auth()->user()->hasPermission('units.activate'))<form method="post" action="{{ route('admin.catalog.units.active',$unit) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $unit->is_active?0:1 }}"><button class="btn" type="submit">{{ $unit->is_active?'إيقاف':'تفعيل' }}</button></form>@endif
@if(auth()->user()->hasPermission('units.update'))<details><summary>تعديل</summary><form class="edit" method="post" action="{{ route('admin.catalog.units.update',$unit) }}">@csrf @method('put')
<div class="fields">
<div class="field"><label>اسم الوحدة</label><input name="name" value="{{ $unit->name }}" required></div>
<div class="field"><label>الرمز</label><input name="symbol" value="{{ $unit->symbol }}"></div>
<div class="field"><label>الدقة العشرية</label><input name="decimal_places" type="number" min="0" max="6" value="{{ $unit->decimal_places }}" required></div>
<button class="btn primary" type="submit">حفظ</button>
</div></form></details>@endif
</div></td></tr>
@empty<tr><td colspan="5" class="empty">لا توجد وحدات قياس بعد.</td></tr>@endforelse
</tbody></table></div>{{ $units->links() }}</section>
@endsection
