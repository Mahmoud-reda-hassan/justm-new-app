<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>تصنيفات المنتجات | Just-M</title>
<style>
:root{--ink:#17233b;--muted:#718096;--line:#e7ebf2;--surface:#fff;--canvas:#f4f6fb;--brand:#3157d5;--green:#16865b;--red:#c43d4d;--shadow:0 14px 36px rgba(25,42,78,.07)}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:14px/1.7 Tahoma,Arial,sans-serif}.shell{max-width:1250px;margin:auto;padding:26px}.head{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:20px}.eyebrow{font-size:11px;color:var(--brand);font-weight:700}.head h1{font-size:26px;margin:2px 0}.head p{margin:0;color:var(--muted)}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a,.btn{border:1px solid var(--line);border-radius:9px;padding:9px 13px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;font-size:12px}.btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}.panel{background:#fff;border:1px solid var(--line);border-radius:15px;padding:19px;margin-bottom:17px;box-shadow:var(--shadow)}h2{font-size:17px;margin:0 0 3px}.sub{color:var(--muted);font-size:12px;margin:0 0 15px}.fields{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:700;color:#53617a}.field input,.field select{width:100%;border:1px solid #dce2ed;border-radius:8px;padding:10px;background:#fff;font:inherit;color:var(--ink)}.alert{padding:11px 14px;border-radius:10px;margin-bottom:14px}.success{background:#eaf8f1;color:#146a49}.errors{background:#fff0f1;color:#982d3a}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:690px}th,td{padding:11px 10px;text-align:right;border-bottom:1px solid var(--line);vertical-align:middle}th{font-size:11px;color:var(--muted);background:#f8faff}td{font-size:12px}.status{border-radius:30px;padding:4px 9px;font-size:10px;font-weight:700}.on{background:#e8f7ef;color:var(--green)}.off{background:#fff0f1;color:var(--red)}details summary{cursor:pointer;color:var(--brand);font-weight:700}.edit{min-width:550px;padding:10px;background:#f8faff;border-radius:10px}.edit .fields{grid-template-columns:2fr 1fr 1fr 1fr}.actions{display:flex;gap:6px;align-items:center}.empty{text-align:center;padding:30px;color:var(--muted)}@media(max-width:760px){.shell{padding:14px}.head{align-items:flex-start;flex-direction:column}.fields,.edit .fields{grid-template-columns:1fr}.edit{min-width:0}}
</style></head>
<body><main class="shell">
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
</main></body></html>