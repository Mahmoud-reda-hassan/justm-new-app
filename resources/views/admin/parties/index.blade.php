<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة الأطراف | Just-M</title>
<style>
:root{--ink:#17233b;--muted:#718096;--line:#e7ebf2;--surface:#fff;--canvas:#f4f6fb;--brand:#3157d5;--green:#16865b;--red:#c43d4d;--shadow:0 14px 36px rgba(25,42,78,.07)}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font-family:Tahoma,Arial,sans-serif;font-size:14px;line-height:1.7}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}.shell{max-width:1480px;margin:auto;padding:28px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:24px}.eyebrow{font-size:12px;color:var(--brand);font-weight:700}.topbar h1{font-size:29px;margin:3px 0 0;font-weight:800}.topbar p{margin:3px 0;color:var(--muted)}.top-links{display:flex;gap:9px;flex-wrap:wrap}
.link-btn,.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:10px 15px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none}
.link-btn{background:#fff;border:1px solid var(--line);color:var(--ink)}.btn-primary{background:var(--brand);color:#fff}.btn-soft{background:#eef2ff;color:var(--brand)}.btn-danger{background:#fff0f1;color:var(--red)}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat,.panel,.party-card{background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
.stat{padding:17px 19px}.stat-label{color:var(--muted);font-size:12px}.stat-value{font-size:25px;font-weight:800;margin-top:5px}.stat-note{color:var(--muted);font-size:11px}
.panel{padding:21px;margin-bottom:20px}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.panel-head h2{font-size:18px;margin:0}.panel-head p{color:var(--muted);margin:3px 0 0;font-size:12px}.tag{background:#eef2ff;color:var(--brand);border-radius:30px;padding:5px 11px;font-size:11px;font-weight:700}
.fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:12px;font-weight:700;color:#526079}.field input,.field select,.field textarea,.filter input,.filter select{width:100%;min-width:0;border:1px solid #dce2ed;border-radius:9px;padding:10px 11px;background:#fff;color:var(--ink);font:inherit;outline:none}.field input:focus,.field select:focus,.field textarea:focus,.filter input:focus,.filter select:focus{border-color:#8ca3f2;box-shadow:0 0 0 3px rgba(49,87,213,.09)}.field textarea{min-height:42px;resize:vertical}.span-2{grid-column:span 2}
.roles{display:flex;gap:9px;flex-wrap:wrap;margin-top:7px}.role-check{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:9px;padding:7px 10px;background:#fbfcff;font-size:12px}.role-check input{accent-color:var(--brand)}
.form-actions{display:flex;gap:9px;margin-top:17px;flex-wrap:wrap}
.alert{padding:12px 15px;border-radius:11px;margin-bottom:15px}.alert-success{background:#eaf8f1;color:#146a49;border:1px solid #ccebdc}.alert-errors{background:#fff0f1;color:#982d3a;border:1px solid #f2cbd0}
.filter{display:grid;grid-template-columns:minmax(220px,2fr) minmax(150px,1fr) minmax(130px,1fr) auto auto;gap:9px;align-items:center}
.list-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:24px 0 13px}.list-head h2{font-size:18px;margin:0}.list-head p{font-size:12px;color:var(--muted);margin:2px 0 0}.party-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.party-card{padding:18px;min-width:0}.party-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.party-code{font-size:11px;color:var(--muted)}.party-head h3{font-size:17px;margin:3px 0 0;overflow-wrap:anywhere}.party-type{color:var(--muted);font-size:11px}.status{display:inline-flex;align-items:center;border-radius:30px;padding:4px 10px;font-size:11px;font-weight:700;white-space:nowrap}.status-on{background:#e8f7ef;color:var(--green)}.status-off{background:#fff0f1;color:var(--red)}
.badges{display:flex;gap:6px;flex-wrap:wrap;margin:13px 0}.badge-role{background:#eef2ff;color:#354fae;border-radius:7px;padding:4px 8px;font-size:11px;font-weight:700}
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;padding:13px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.info-item{min-width:0}.info-label{font-size:10px;color:var(--muted)}.info-value{font-size:12px;font-weight:600;overflow-wrap:anywhere}
.accounts{margin-top:13px;background:#f8faff;border:1px solid #e8edf7;border-radius:11px;padding:12px}.accounts-head{display:flex;justify-content:space-between;align-items:center;gap:8px}.accounts-head strong{font-size:12px}.account-list{list-style:none;padding:0;margin:8px 0 0}.account-list li{display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-top:1px solid #e8edf7;font-size:12px}.account-list small{display:block;color:var(--muted)}.empty-note{font-size:11px;color:var(--muted);margin:7px 0 0}
.card-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:14px}.details{border-top:1px solid var(--line);margin-top:13px;padding-top:11px}.details summary{cursor:pointer;color:var(--brand);font-weight:700;font-size:12px}.edit-form{padding-top:13px}.edit-form .fields{grid-template-columns:repeat(2,minmax(0,1fr))}
.pagination-wrap{margin-top:18px}.empty-state{background:#fff;border:1px dashed #cbd4e3;border-radius:15px;text-align:center;padding:35px;color:var(--muted)}
@media(max-width:950px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.fields{grid-template-columns:repeat(2,minmax(0,1fr))}.filter{grid-template-columns:1fr 1fr}.filter input{grid-column:span 2}}
@media(max-width:680px){.shell{padding:15px}.topbar{align-items:flex-start;flex-direction:column}.topbar h1{font-size:24px}.stats{gap:9px}.stat{padding:13px}.stat-value{font-size:21px}.panel{padding:15px}.party-grid{grid-template-columns:1fr}.fields,.edit-form .fields{grid-template-columns:1fr}.span-2{grid-column:auto}.filter{grid-template-columns:1fr}.filter input{grid-column:auto}.panel-head{align-items:flex-start}.info-grid{gap:7px}}
</style>
</head>
<body>
<div class="shell">
<header class="topbar">
    <div><span class="eyebrow">JUST-M · إدارة البيانات الأساسية</span><h1>إدارة الأطراف</h1><p>ملف موحد للعملاء والموردين والمسوقين والوسطاء والمندوبين وشركات الشحن.</p></div>
    <nav class="top-links">
        <a class="link-btn" href="{{ route('dashboard') }}">الرئيسية</a>
        @if(auth()->user()->hasPermission('users.view'))<a class="link-btn" href="{{ route('admin.access.users') }}">إدارة حسابات الدخول</a>@endif
    </nav>
</header>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-errors"><strong>راجع البيانات التالية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="stats">
    <div class="stat"><div class="stat-label">إجمالي الأطراف</div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-note">كل السجلات</div></div>
    <div class="stat"><div class="stat-label">أطراف نشطة</div><div class="stat-value">{{ number_format($stats['active']) }}</div><div class="stat-note">متاحة للاستخدام</div></div>
    <div class="stat"><div class="stat-label">المسوقون</div><div class="stat-value">{{ number_format($stats['marketers']) }}</div><div class="stat-note">بحسب صفة الطرف</div></div>
    <div class="stat"><div class="stat-label">المندوبون الداخليون</div><div class="stat-value">{{ number_format($stats['deliveryAgents']) }}</div><div class="stat-note">بحسب صفة الطرف</div></div>
</section>

@if(auth()->user()->hasPermission('parties.create'))
<section class="panel">
    <div class="panel-head"><div><h2>إضافة طرف جديد</h2><p>سجل الشخص أو الشركة مرة واحدة، ثم حدد كل الصفات التي تنطبق عليه.</p></div><span class="tag">بيانات أساسية</span></div>
    <form method="post" action="{{ route('admin.parties.store') }}">@csrf
        <div class="fields">
            <div class="field"><label>كود الطرف</label><input name="code" value="{{ old('code') }}" maxlength="50" placeholder="اختياري"></div>
            <div class="field"><label>نوع الطرف</label><select name="entity_type" required><option value="individual" @selected(old('entity_type','individual')==='individual')>فرد</option><option value="company" @selected(old('entity_type')==='company')>شركة / مؤسسة</option></select></div>
            <div class="field span-2"><label>الاسم</label><input name="name" value="{{ old('name') }}" required maxlength="180" placeholder="الاسم أو اسم الشركة"></div>
            <div class="field"><label>الشخص المسؤول</label><input name="contact_person" value="{{ old('contact_person') }}" maxlength="150"></div>
            <div class="field"><label>الهاتف</label><input name="phone" value="{{ old('phone') }}" maxlength="40"></div>
            <div class="field"><label>المحمول</label><input name="mobile" value="{{ old('mobile') }}" maxlength="40"></div>
            <div class="field"><label>البريد الإلكتروني</label><input name="email" type="email" value="{{ old('email') }}" maxlength="190"></div>
            <div class="field"><label>المدينة</label><select name="city_id"><option value="">اختر المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected((string)old('city_id')===(string)$city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
            <div class="field"><label>الرقم الضريبي</label><input name="tax_number" value="{{ old('tax_number') }}" maxlength="80"></div>
            <div class="field"><label>السجل التجاري</label><input name="commercial_register" value="{{ old('commercial_register') }}" maxlength="80"></div>
            <div class="field span-2"><label>العنوان</label><input name="address" value="{{ old('address') }}" maxlength="4000"></div>
            <div class="field span-2"><label>ملاحظات</label><textarea name="notes">{{ old('notes') }}</textarea></div>
        </div>
        <div class="field" style="margin-top:15px"><label>صفات الطرف — يمكن اختيار أكثر من صفة</label><div class="roles">@foreach($partyRoles as $key => $label)<label class="role-check"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', ['customer']), true))>{{ $label }}</label>@endforeach</div></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ الطرف</button></div>
    </form>
</section>
@endif

<section class="panel">
    <div class="panel-head"><div><h2>دليل الأطراف</h2><p>ابحث بالاسم أو الكود أو بيانات الاتصال، واعرض الأطراف حسب الصفة والحالة.</p></div><span class="tag">{{ $parties->total() }} سجل</span></div>
    <form class="filter" method="get" action="{{ route('admin.parties.index') }}">
        <input name="q" value="{{ request('q') }}" placeholder="بحث بالاسم أو الكود أو الهاتف أو البريد">
        <select name="role"><option value="">كل الصفات</option>@foreach($partyRoles as $key => $label)<option value="{{ $key }}" @selected(request('role')===$key)>{{ $label }}</option>@endforeach</select>
        <select name="status"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>موقوف</option></select>
        <button class="btn btn-primary" type="submit">تطبيق البحث</button><a class="btn btn-soft" href="{{ route('admin.parties.index') }}">مسح الفلاتر</a>
    </form>
</section>

<div class="list-head"><div><h2>الأطراف المسجلة</h2><p>كل طرف يظهر مرة واحدة حتى لو كان يحمل أكثر من صفة.</p></div></div>
<div class="party-grid">
@forelse($parties as $party)
    <article class="party-card">
        <div class="party-head">
            <div><span class="party-code">{{ $party->code ? 'كود '.$party->code : 'بدون كود' }}</span><h3>{{ $party->name }}</h3><div class="party-type">{{ $party->entity_type==='company'?'شركة / مؤسسة':'فرد' }}</div></div>
            <span class="status {{ $party->is_active?'status-on':'status-off' }}">{{ $party->is_active?'نشط':'موقوف' }}</span>
        </div>
        <div class="badges">@foreach($party->assignedRoles() as $role)<span class="badge-role">{{ $partyRoles[$role] ?? $role }}</span>@endforeach</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">الهاتف / المحمول</div><div class="info-value">{{ $party->phone ?: '—' }} {{ $party->mobile ?: '' }}</div></div>
            <div class="info-item"><div class="info-label">البريد الإلكتروني</div><div class="info-value">{{ $party->email ?: '—' }}</div></div>
            <div class="info-item"><div class="info-label">المدينة</div><div class="info-value">{{ $party->city?->name_ar ?: '—' }}</div></div>
            <div class="info-item"><div class="info-label">العنوان</div><div class="info-value">{{ $party->address ?: '—' }}</div></div>
        </div>
        @if($party->partyRoles->contains(fn($role) => in_array($role->role, $loginRoles, true)))
        <div class="accounts">
            <div class="accounts-head"><strong>حسابات الدخول المرتبطة</strong><span class="tag">{{ $party->users->count() }}</span></div>
            @if($party->users->isNotEmpty())
                <ul class="account-list">@foreach($party->users as $account)<li><span>{{ $account->name }}<small>{{ $account->username }}</small></span><span class="status {{ $account->is_active?'status-on':'status-off' }}">{{ $account->is_active?'نشط':'موقوف' }}</span></li>@endforeach</ul>
            @else
                <p class="empty-note">لا يوجد حساب دخول مرتبط بهذا الطرف حتى الآن.</p>
            @endif
            <p class="empty-note">صلاحيات الحساب تُدار من مجموعات الصلاحيات، ولا تُمنح تلقائيًا بسبب صفة الطرف.</p>
        </div>
        @endif
        <div class="card-actions">
            @if($canCreateUsers && $party->is_active && $party->partyRoles->contains(fn($role) => in_array($role->role, $loginRoles, true)))
                <a class="btn btn-soft" href="{{ route('admin.access.users', ['party_id' => $party->id]) }}">إنشاء حساب دخول</a>
            @endif
            @if(auth()->user()->hasPermission('parties.activate'))
                <form method="post" action="{{ route('admin.parties.active',$party) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $party->is_active?0:1 }}"><button class="btn {{ $party->is_active?'btn-danger':'btn-soft' }}" type="submit">{{ $party->is_active?'إيقاف الطرف':'تفعيل الطرف' }}</button></form>
            @endif
        </div>
        @if(auth()->user()->hasPermission('parties.update'))
        <details class="details">
            <summary>تعديل بيانات الطرف وصفاته</summary>
            <form class="edit-form" method="post" action="{{ route('admin.parties.update',$party) }}">@csrf @method('put')
                <div class="fields">
                    <div class="field"><label>الكود</label><input name="code" value="{{ $party->code }}" maxlength="50"></div>
                    <div class="field"><label>نوع الطرف</label><select name="entity_type"><option value="individual" @selected($party->entity_type==='individual')>فرد</option><option value="company" @selected($party->entity_type==='company')>شركة / مؤسسة</option></select></div>
                    <div class="field span-2"><label>الاسم</label><input name="name" value="{{ $party->name }}" required maxlength="180"></div>
                    <div class="field"><label>الشخص المسؤول</label><input name="contact_person" value="{{ $party->contact_person }}"></div>
                    <div class="field"><label>الهاتف</label><input name="phone" value="{{ $party->phone }}"></div>
                    <div class="field"><label>المحمول</label><input name="mobile" value="{{ $party->mobile }}"></div>
                    <div class="field"><label>البريد الإلكتروني</label><input name="email" type="email" value="{{ $party->email }}"></div>
                    <div class="field"><label>المدينة</label><select name="city_id"><option value="">اختر المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($party->city_id==$city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
                    <div class="field"><label>الرقم الضريبي</label><input name="tax_number" value="{{ $party->tax_number }}"></div>
                    <div class="field"><label>السجل التجاري</label><input name="commercial_register" value="{{ $party->commercial_register }}"></div>
                    <div class="field span-2"><label>العنوان</label><input name="address" value="{{ $party->address }}"></div>
                    <div class="field span-2"><label>ملاحظات</label><textarea name="notes">{{ $party->notes }}</textarea></div>
                </div>
                <div class="roles">@foreach($partyRoles as $key => $label)<label class="role-check"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $party->assignedRoles(), true))>{{ $label }}</label>@endforeach</div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ التعديلات</button></div>
            </form>
        </details>
        @endif
    </article>
@empty
    <div class="empty-state">لا توجد أطراف مطابقة لخيارات البحث الحالية.</div>
@endforelse
</div>
<div class="pagination-wrap">{{ $parties->links() }}</div>
</div>
</body>
</html>
