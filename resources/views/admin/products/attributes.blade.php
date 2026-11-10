<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>صفات المنتجات | Just-M</title>
<style>
:root{--ink:#17233b;--muted:#718096;--line:#e7ebf2;--canvas:#f4f6fb;--brand:#3157d5;--green:#16865b;--red:#c43d4d;--shadow:0 14px 36px rgba(25,42,78,.07)}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:14px/1.7 Tahoma,Arial,sans-serif}.shell{max-width:1250px;margin:auto;padding:25px}.head{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:20px}.eyebrow{font-size:11px;color:var(--brand);font-weight:700}.head h1{font-size:26px;margin:2px 0}.head p{margin:0;color:var(--muted)}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a,.btn{border:1px solid var(--line);border-radius:9px;padding:9px 13px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;font-size:12px;cursor:pointer}.btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}.panel,.group-card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:18px;margin-bottom:15px;box-shadow:var(--shadow)}h2{font-size:17px;margin:0 0 3px}.sub,.muted{font-size:11px;color:var(--muted)}.form-row{display:grid;grid-template-columns:2fr auto;gap:9px;align-items:end}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:700;color:#53617a}.field input{width:100%;border:1px solid #dce2ed;border-radius:8px;padding:10px;font:inherit}.alert{padding:11px 14px;border-radius:10px;margin-bottom:14px}.success{background:#eaf8f1;color:#146a49}.errors{background:#fff0f1;color:#982d3a}.group-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.group-head h3{margin:0;font-size:16px}.status{border-radius:20px;padding:4px 9px;font-size:10px;font-weight:700}.on{background:#e8f7ef;color:var(--green)}.off{background:#fff0f1;color:var(--red)}.actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.value-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:14px}.value-card{border:1px solid var(--line);border-radius:10px;padding:10px}.value-card form{display:flex;gap:6px;align-items:center;margin-top:7px}.value-card input{min-width:0;flex:1;border:1px solid var(--line);border-radius:7px;padding:7px}.value-card button{white-space:nowrap}.edit{margin-top:9px}.empty{color:var(--muted);padding:20px;text-align:center}@media(max-width:700px){.shell{padding:13px}.head{align-items:flex-start;flex-direction:column}.form-row,.value-list{grid-template-columns:1fr}.value-card form{flex-wrap:wrap}}
</style></head>
<body><main class="shell">
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
</main></body></html>