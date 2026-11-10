@extends('layouts.app')

@section('title', 'إدارة المستخدمين')
@section('page-title', 'إدارة المستخدمين')
@section('breadcrumb', 'إدارة المستخدمين')

@section('content')
<h1>إدارة المستخدمين</h1>
    <p><a href="{{ route('admin.access.roles') }}">إدارة مجموعات الصلاحيات</a> | <a href="{{ route('dashboard') }}">الرئيسية</a></p>
    @if (session('status')) <div class="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <section><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section> @endif
    <section>
        <h2>إضافة مستخدم</h2>
        <form method="post" action="{{ route('admin.access.users.store') }}">@csrf
            <input name="name" placeholder="الاسم" required maxlength="150">
            <input name="username" placeholder="اسم الدخول" required maxlength="100">
            <input type="email" name="email" placeholder="البريد الإلكتروني">
            <input type="password" name="password" placeholder="كلمة مرور (12 حرفًا على الأقل)" required minlength="12">
            <input type="password" name="password_confirmation" placeholder="تأكيد كلمة المرور" required minlength="12">
            <select name="party_id"><option value="">حساب داخلي غير مرتبط بطرف</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string)old("party_id", $selectedPartyId)===(string)$party->id)>{{ $party->name }}{{ $party->is_active ? "" : " — موقوف" }}</option>@endforeach</select>
            <button type="submit">إنشاء</button>
        </form>
        <p class="muted">يمكن ربط حساب العميل بالمتجر، أو ربطه بمسوق / وسيط أو مندوب. شركة الشحن والمورد جهات مستقلة ولا يتحولان إلى حساب دخول تلقائيًا. الصلاحيات الإدارية تُسند من مجموعات الصلاحيات بشكل منفصل.</p>
    </section>
    <section>
        <h2>المستخدمون</h2>
        <table><thead><tr><th>الاسم / الدخول</th><th>الطرف المرتبط</th><th>الحالة</th><th>مجموعات الصلاحيات</th><th>الإجراءات</th><th>نطاق البيانات</th></tr></thead><tbody>
        @forelse ($users as $user)
            <tr><td>{{ $user->name }}<br><span class="muted">{{ $user->username }} · {{ $user->email }}</span></td>
                <td>{{ $user->party?->name ?? "—" }}@if($user->party)<br><span class="muted">{{ collect($user->party->assignedRoles())->map(fn($role) => $partyRoleLabels[$role] ?? $role)->implode("، ") }}</span>@endif</td>
                <td>{{ $user->is_active ? 'نشط' : 'موقوف' }}</td>
                <td>
                    <form method="post" action="{{ route('admin.access.users.roles', $user) }}">@csrf @method('put')
                        @foreach ($roles as $role)
                            <label><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))>{{ $role->name }}</label><br>
                        @endforeach
                        <button type="submit">حفظ المجموعات</button>
                    </form>
                </td>
                <td>
                    <form class="inline" method="post" action="{{ route('admin.access.users.update', $user) }}">@csrf @method('put')
                        <input name="name" value="{{ $user->name }}" required>
                        <input name="username" value="{{ $user->username }}" required>
                        <input type="email" name="email" value="{{ $user->email }}">
                        <select name="party_id"><option value="">بدون طرف مرتبط</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string)$user->party_id===(string)$party->id)>{{ $party->name }}{{ $party->is_active ? "" : " — موقوف" }}</option>@endforeach</select>
                        <button>تحديث</button>
                    </form>
                    <form class="inline" method="post" action="{{ route('admin.access.users.active', $user) }}">@csrf @method('patch')
                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                        <button>{{ $user->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                    </form>
                    <details><summary>إعادة تعيين كلمة المرور</summary>
                        <form method="post" action="{{ route('admin.access.users.password', $user) }}">@csrf @method('put')
                            <input type="password" name="password" required minlength="12" placeholder="كلمة المرور الجديدة">
                            <input type="password" name="password_confirmation" required minlength="12" placeholder="تأكيد كلمة المرور">
                            <button>حفظ كلمة المرور</button>
                        </form>
                    </details>
                </td>
                <td>
                    @if(auth()->user()->hasPermission('users.assign_scopes'))
                    <form method="post" action="{{ route('admin.access.users.scopes', $user) }}">@csrf @method('put')
                        <strong>الشركات</strong><br>
                        <select name="company_ids[]" multiple size="3">
                            @foreach($companies as $company)<option value="{{ $company->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type==='company' && $scope->scope_id==$company->id))>{{ $company->name }}</option>@endforeach
                        </select><br>
                        <strong>الفروع</strong><br>
                        <select name="branch_ids[]" multiple size="3">
                            @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type==='branch' && $scope->scope_id==$branch->id))>{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach
                        </select><br>
                        <strong>المخازن</strong><br>
                        <select name="warehouse_ids[]" multiple size="3">
                            @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type==='warehouse' && $scope->scope_id==$warehouse->id))>{{ $warehouse->name }} — {{ $warehouse->branch->name }}</option>@endforeach
                        </select><br><button>حفظ النطاق</button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="6">لا يوجد مستخدمون.</td></tr> @endforelse
        </tbody></table>
        {{ $users->links() }}
    </section>
@endsection
