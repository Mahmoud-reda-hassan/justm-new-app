<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f6f9; color: #202938; font-family: Tahoma, Arial, sans-serif; }
        main { max-width: 620px; margin: 24px; padding: 36px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; text-align: center; }
        h1 { margin-top: 0; }
        p { line-height: 1.9; color: #596579; }
        small { color: #778196; }
    </style>
</head>
<body>
<main>
    <h1>{{ config('app.name') }}</h1>
    <p>منصة Just-M لإدارة عمليات الشركة.</p>
    @auth
        <p><a href="{{ route('dashboard') }}">العودة إلى حسابك</a></p>
        @php($user = auth()->user())
        @if (($user->party && $user->party->is_active && $user->party->partyRoles()->whereIn('role', \App\Models\Party::LOGIN_ROLES)->exists()) || $user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('structure.scope.manage_all'))
            <p><a href="{{ route('workspaces.index') }}">دخول المستخدمين المميزين</a></p>
        @endif
    @else
        <small><a href="{{ route('login') }}">تسجيل الدخول للمتابعة</a></small>
    @endauth
</main>
</body>
</html>
