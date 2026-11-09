<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>Just-M</title></head>
<body>
    <h1>لوحة Just-M</h1>
    <p>مرحبًا {{ auth()->user()->name }}</p>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج</button></form>
</body>
</html>
