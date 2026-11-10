<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة الأطراف</title>
<style>
body{font-family:Tahoma,Arial;background:#f5f6f8;margin:0;padding:20px;color:#222}main{max-width:1450px;margin:auto}
section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
input,select,textarea,button{padding:9px;margin:3px;border:1px solid #bbb;border-radius:5px;max-width:100%}
input,select,textarea{box-sizing:border-box}textarea{min-width:180px;min-height:38px}
table{width:100%;border-collapse:collapse}td,th{padding:9px;border-bottom:1px solid #ddd;text-align:right;vertical-align:top}
.inline{display:inline-block}.status{background:#e8f5e9;padding:10px}.muted{color:#666;font-size:13px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:7px}
.roles{display:flex;gap:12px;flex-wrap:wrap;margin:10px 0}.role{display:inline-flex;align-items:center;gap:4px}
.actions{min-width:240px}.filters{display:flex;gap:6px;flex-wrap:wrap}
@media(max-width:700px){body{padding:9px}section{overflow-x:auto}table{min-width:900px}}
</style>
</head>
<body><main>
<h1>إدارة الأطراف</h1>
<p><a href="{{ route('dashboard') }}">الرئيسية</a> · العملاء والموردون والمسوقون والوسطاء والمندوبون وشركات الشحن</p>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif
@if($errors->any())<section><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

@if(auth()->user()->hasPermission('parties.create'))
<section><h2>إضافة طرف جديد</h2>
<form method="post" action="{{ route('admin.parties.store') }}">@csrf
<div class="grid">
<input name="code" maxlength="50" placeholder="كود الطرف (اختياري)">
<select name="entity_type" required><option value="individual">فرد</option><option value="company">شركة / مؤسسة</option></select>
<input name="name" required maxlength="180" placeholder="الاسم">
<input name="contact_person" maxlength="150" placeholder="الشخص المسؤول / جهة الاتصال">
<input name="phone" maxlength="40" placeholder="الهاتف">
<input name="mobile" maxlength="40" placeholder="المحمول">
<input name="email" type="email" placeholder="البريد الإلكتروني">
<select name="city_id"><option value="">المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}">{{ $city->name_ar }}</option>@endforeach</select>
<input name="tax_number" maxlength="80" placeholder="الرقم الضريبي">
<input name="commercial_register" maxlength="80" placeholder="السجل التجاري">
<input name="address" placeholder="العنوان">
<textarea name="notes" placeholder="ملاحظات"></textarea>
</div>
<strong>صفات الطرف (يمكن اختيار أكثر من صفة)</strong>
<div class="roles">@foreach($partyRoles as $key => $label)<label class="role"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked($key === 'customer')>{{ $label }}</label>@endforeach</div>
<button type="submit">حفظ الطرف</button>
</form></section>
@endif

<section><h2>الأطراف المسجلة</h2>
<form class="filters" method="get" action="{{ route('admin.parties.index') }}">
<input name="q" value="{{ request('q') }}" placeholder="بحث بالاسم أو الكود أو الهاتف">
<select name="role"><option value="">كل الصفات</option>@foreach($partyRoles as $key => $label)<option value="{{ $key }}" @selected(request('role')===$key)>{{ $label }}</option>@endforeach</select>
<select name="status"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>موقوف</option></select>
<button>بحث</button><a href="{{ route('admin.parties.index') }}">مسح</a>
</form>
<table><thead><tr><th>الكود</th><th>الاسم / النوع</th><th>الصفات</th><th>التواصل</th><th>المدينة</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
@forelse($parties as $party)
<tr>
<td>{{ $party->code }}</td><td>{{ $party->name }}<div class="muted">{{ $party->entity_type==='company'?'شركة / مؤسسة':'فرد' }}</div></td>
<td>@foreach($party->partyRoles as $partyRole)<span>{{ $partyRoles[$partyRole->role] ?? $partyRole->role }}</span>@if(!$loop->last)، @endif @endforeach</td>
<td>{{ $party->phone }} {{ $party->mobile }}<div>{{ $party->email }}</div></td><td>{{ $party->city?->name_ar }}</td>
<td>{{ $party->is_active?'نشط':'موقوف' }}</td>
<td class="actions">
@if(auth()->user()->hasPermission('parties.update'))
<details><summary>تعديل البيانات</summary>
<form method="post" action="{{ route('admin.parties.update',$party) }}">@csrf @method('put')
<div class="grid">
<input name="code" value="{{ $party->code }}" placeholder="الكود">
<select name="entity_type"><option value="individual" @selected($party->entity_type==='individual')>فرد</option><option value="company" @selected($party->entity_type==='company')>شركة / مؤسسة</option></select>
<input name="name" value="{{ $party->name }}" required>
<input name="contact_person" value="{{ $party->contact_person }}" placeholder="الشخص المسؤول">
<input name="phone" value="{{ $party->phone }}" placeholder="الهاتف">
<input name="mobile" value="{{ $party->mobile }}" placeholder="المحمول">
<input name="email" type="email" value="{{ $party->email }}" placeholder="البريد">
<select name="city_id"><option value="">المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($party->city_id==$city->id)>{{ $city->name_ar }}</option>@endforeach</select>
<input name="tax_number" value="{{ $party->tax_number }}" placeholder="الرقم الضريبي">
<input name="commercial_register" value="{{ $party->commercial_register }}" placeholder="السجل التجاري">
<input name="address" value="{{ $party->address }}" placeholder="العنوان">
<textarea name="notes" placeholder="ملاحظات">{{ $party->notes }}</textarea>
</div>
<div class="roles">@foreach($partyRoles as $key => $label)<label class="role"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked($party->partyRoles->contains('role',$key))>{{ $label }}</label>@endforeach</div>
<button>حفظ التعديلات</button></form></details>
@endif
@if(auth()->user()->hasPermission('parties.activate'))
<form class="inline" method="post" action="{{ route('admin.parties.active',$party) }}">@csrf @method('patch')
<input type="hidden" name="is_active" value="{{ $party->is_active?0:1 }}"><button>{{ $party->is_active?'إيقاف':'تفعيل' }}</button>
</form>
@endif
</td></tr>
@empty<tr><td colspan="7">لا توجد أطراف مطابقة.</td></tr>@endforelse
</tbody></table>{{ $parties->links() }}
</section>
</main></body></html>
