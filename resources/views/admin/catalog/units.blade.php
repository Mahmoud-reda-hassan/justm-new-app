<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>وحدات القياس | Just-M</title>
<style>
:root{--ink:#17233b;--muted:#718096;--line:#e7ebf2;--canvas:#f4f6fb;--brand:#3157d5;--green:#16865b;--red:#c43d4d;--shadow:0 14px 36px rgba(25,42,78,.07)}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:14px/1.7 Tahoma,Arial,sans-serif}.shell{max-width:1150px;margin:auto;padding:26px}.head{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:20px}.eyebrow{font-size:11px;color:var(--brand);font-weight:700}.head h1{font-size:26px;margin:2px 0}.head p{margin:0;color:var(--muted)}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a,.btn{border:1px solid var(--line);border-radius:9px;padding:9px 13px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;font-size:12px}.btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:19px;margin-bottom:17px;box-shadow:var(--shadow)}h2{font-size:17px;margin:0 0 3px}.sub{color:var(--muted);font-size:12px;margin:0 0 15px}.fields{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:700;color:#53617a}.field input{width:100%;border:1px solid #dce2ed;border-radius:8px;padding:10px;background:#fff;font:inherit;color:var(--ink)}.alert{padding:11px 14px;border-radius:10px;margin-bottom:14px}.success{background:#eaf8f1;color:#146a49}.errors{background:#fff0f1;color:#982d3a}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:550px}th,td{padding:11px 10px;text-align:right;border-bottom:1px solid var(--line)}th{font-size:11px;color:var(--muted);background:#f8faff}td{font-size:12px}.status{border-radius:30px;padding:4px 9px;font-size:10px;font-weight:700}.on{background:#e8f7ef;color:var(--green)}.off{background:#fff0f1;color:var(--red)}details summary{cursor:pointer;color:var(--brand);font-weight:700}.edit{min-width:450px;padding:10px;background:#f8faff;border-radius:10px}.edit .fields{grid-template-columns:2fr 1fr 1fr auto}.actions{display:flex;gap:6px;align-items:center}.empty{text-align:center;padding:30px;color:var(--muted)}@media(max-width:760px){.shell{padding:14px}.head{align-items:flex-start;flex-direction:column}.fields,.edit .fields{grid-template-columns:1fr}.edit{min-width:0}}
</style></head>
<body><main class="shell">
<header class="head"><div><span class="eyebrow">JUST-M · كتالوج المنتجات</span><h1>وحدات القياس</h1><p>تعريف الوحدة الأساسية التي تُسجل بها كميات كل منتج ودقتها العشرية.</p></div><nav class="nav"><a href="{{ route('dashboard') }}">الرئيسية</a>@if(auth()->user()->hasPermission('product_categories.view'))<a href="{{ route('admin.catalog.categories') }}">تصنيفات المنتجات</a>@endif</nav></header>
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
</main></body></html>