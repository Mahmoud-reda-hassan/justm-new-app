<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ config('app.name') }}</title></head>
<body>
    <h1>لوحة {{ config('app.name') }}</h1>
    <p>مرحبًا {{ auth()->user()->name }}</p>
    @if (!auth()->user()->email)
        <p>لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}">إضافة بريد إلكتروني وتأكيده</a></p>
    @elseif (!auth()->user()->hasVerifiedEmail())
        <p>البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}">تأكيد البريد لاستعادة كلمة المرور</a></p>
    @endif
    <nav>
        @if (auth()->user()->hasPermission('users.view'))
            <a href="{{ route('admin.access.users') }}">إدارة المستخدمين</a>
        @endif
        @if (auth()->user()->hasPermission('permissions.groups.view'))
            <a href="{{ route('admin.access.roles') }}">مجموعات الصلاحيات</a>
        @endif
    </nav>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج</button></form>
</body>
</html>
