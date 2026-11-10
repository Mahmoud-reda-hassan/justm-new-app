@extends('layouts.app')

@section('title', 'إدارة الأطراف')
@section('page-title', 'إدارة الأطراف')
@section('breadcrumb', 'إدارة الأطراف')

@section('content')
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
@endsection
