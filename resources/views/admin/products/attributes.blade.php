@extends('layouts.app')

@section('title', 'صفات المنتجات')
@section('page-title', 'صفات المنتجات')
@section('breadcrumb', 'صفات المنتجات')

@section('content')
<header class="head"><div><span class="eyebrow">JUST-M · كتالوج المنتجات</span><h1>مجموعات صفات المنتجات</h1><p>مثل اللون والمقاس والخامة؛ كل كود منتج يحدد قيمة واحدة من كل مجموعة مرتبطة به.</p></div><nav class="nav"><a href="{{ route('dashboard') }}">الرئيسية</a>@if(auth()->user()->hasPermission('products.view'))<a href="{{ route('admin.products.index') }}">المنتجات</a>@endif</nav></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(auth()->user()->hasPermission('product_attributes.create'))
<section class="panel"><h2>إضافة مجموعة صفات</h2><p class="sub">أنشئ المجموعة أولًا ثم أضف قيمها.</p><form class="form-row" method="post" action="{{ route('admin.product-attributes.groups.store') }}">@csrf<div class="field"><label>اسم المجموعة</label><input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="اللون، المقاس، الخامة"></div><button class="btn primary">إنشاء المجموعة</button></form></section>
@endif
@forelse($groups as $group)
<section class="group-card">
<div class="group-head"><div><h3>{{ $group->name }}</h3><span class="muted">{{ $group->values->count() }} قيمة · مرتبطة بـ {{ $group->products_count }} منتج</span></div><span class="status {{ $group->is_active?'on':'off' }}">{{ $group->is_active?'نشطة':'موقوفة' }}</span></div>
<div class="actions">
@if(auth()->user()->hasPermission('product_attributes.update'))<details class="edit"><summary>تعديل اسم المجموعة</summary><form class="form-row" method="post" action="{{ route('admin.product-attributes.groups.update',$group) }}">@csrf @method('put')<div class="field"><input name="name" value="{{ $group->name }}" required></div><button class="btn primary">حفظ</button></form></details>@endif
@if(auth()->user()->hasPermission('product_attributes.activate'))<form method="post" action="{{ route('admin.product-attributes.groups.active',$group) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $group->is_active?0:1 }}"><button class="btn">{{ $group->is_active?'إيقاف المجموعة':'تفعيل المجموعة' }}</button></form>@endif
</div>
@if(auth()->user()->hasPermission('product_attributes.create') && $group->is_active)<form class="form-row" style="margin-top:13px" method="post" action="{{ route('admin.product-attributes.values.store',$group) }}">@csrf<div class="field"><label>إضافة قيمة إلى {{ $group->name }}</label><input name="value" required maxlength="120" placeholder="أحمر، أزرق، كبير..."></div><button class="btn primary">إضافة قيمة</button></form>@endif
<div class="value-list">
@forelse($group->values as $value)
<div class="value-card"><strong>{{ $value->value }}</strong> <span class="status {{ $value->is_active?'on':'off' }}">{{ $value->is_active?'نشطة':'موقوفة' }}</span>
@if(auth()->user()->hasPermission('product_attributes.update'))<form method="post" action="{{ route('admin.product-attributes.values.update',$value) }}">@csrf @method('put')<input name="value" value="{{ $value->value }}" required><button class="btn">حفظ</button></form>@endif
@if(auth()->user()->hasPermission('product_attributes.activate'))<form method="post" action="{{ route('admin.product-attributes.values.active',$value) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $value->is_active?0:1 }}"><button class="btn">{{ $value->is_active?'إيقاف':'تفعيل' }}</button></form>@endif
</div>
@empty<div class="empty">لا توجد قيم لهذه المجموعة.</div>@endforelse
</div>
</section>
@empty<div class="panel empty">لا توجد مجموعات صفات مسجلة.</div>@endforelse
{{ $groups->links() }}
@endsection
