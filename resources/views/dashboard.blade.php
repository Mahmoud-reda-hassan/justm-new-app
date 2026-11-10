<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ config('app.name') }}</title></head>
<body>
    <h1>لوحة {{ config('app.name') }}</h1>
    <p>مرحبًا {{ auth()->user()->name }}</p>
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
