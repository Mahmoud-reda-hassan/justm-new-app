<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        body{font-family:Tahoma,Arial,sans-serif;background:#f4f6f9;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px}
        main{background:#fff;padding:2rem;border-radius:12px;width:min(92vw,440px);box-shadow:0 4px 24px #0001}
        label{display:block;margin:1rem 0 .4rem}input{box-sizing:border-box;width:100%;padding:.8rem;border:1px solid #ccd2d8;border-radius:6px}
        button{margin-top:1.2rem;width:100%;padding:.8rem;background:#263b5a;color:#fff;border:0;border-radius:6px;cursor:pointer}
        a{color:#185adb}.error{color:#b42318;font-size:.9rem}.status{padding:10px;background:#edf7f0;color:#17633a;border-radius:8px;line-height:1.7}
        p{line-height:1.8;color:#526071}
    </style>
</head>
<body><main>
    <h1>استعادة كلمة المرور</h1>
    <p>أدخل البريد الإلكتروني المؤكد المرتبط بحسابك. سنرسل رابط إعادة التعيين إذا كان الحساب نشطًا والبريد موثقًا.</p>
    @if (session('status')) <p class="status">{{ session('status') }}</p> @endif
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">البريد الإلكتروني</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button type="submit">إرسال رابط الاستعادة</button>
    </form>
    <p><a href="{{ route('login') }}">العودة إلى تسجيل الدخول</a></p>
</main></body></html>
