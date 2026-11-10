<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إدارة المستخدمين</title>
    <style>
        body{font-family:Tahoma,Arial,sans-serif;background:#f5f6f8;color:#222;margin:0;padding:24px}
        main{max-width:1100px;margin:auto} section{background:#fff;padding:18px;margin:0 0 18px;border:1px solid #ddd;border-radius:8px}
        input,button,select{padding:9px;margin:4px;border:1px solid #bbb;border-radius:5px} button{cursor:pointer}
        table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #ddd;text-align:right;vertical-align:top}
        .muted{color:#666;font-size:13px}.status{padding:10px;background:#e8f5e9;margin-bottom:12px}
        form.inline{display:inline-block;margin:3px} h1,h2{margin-top:0}
    </style>
</head>
<body><main>
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
            <button type="submit">إنشاء</button>
        </form>
        <p class="muted">الحساب يُنشأ دون أي مجموعة صلاحيات. يجب إسناد مجموعة مناسبة بشكل منفصل.</p>
    </section>
    <section>
        <h2>المستخدمون</h2>
        <table><thead><tr><th>الاسم / الدخول</th><th>الحالة</th><th>مجموعات الصلاحيات</th><th>الإجراءات</th><th>نطاق البيانات</th></tr></thead><tbody>
        @forelse ($users as $user)
            <tr><td>{{ $user->name }}<br><span class="muted">{{ $user->username }} · {{ $user->email }}</span></td>
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
        @empty <tr><td colspan="5">لا يوجد مستخدمون.</td></tr> @endforelse
        </tbody></table>
        {{ $users->links() }}
    </section>
</main></body></html>
