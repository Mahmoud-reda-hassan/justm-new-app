<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول - Just-M</title>
    <style>
        body{font-family:Tahoma,Arial,sans-serif;background:#f4f6f9;display:grid;place-items:center;min-height:100vh;margin:0}
        main{background:#fff;padding:2rem;border-radius:12px;width:min(92vw,380px);box-shadow:0 4px 24px #0001}
        label{display:block;margin:1rem 0 .4rem}input{box-sizing:border-box;width:100%;padding:.8rem;border:1px solid #ccd2d8;border-radius:6px}
        button{margin-top:1.2rem;width:100%;padding:.8rem;background:#263b5a;color:#fff;border:0;border-radius:6px;cursor:pointer}
        .error{color:#b42318;font-size:.9rem}
    </style>
</head>
<body>
<main>
    <h1>تسجيل الدخول</h1>
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="username">اسم المستخدم</label>
        <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
        <label for="password">كلمة المرور</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <label><input type="checkbox" name="remember" value="1" style="width:auto"> تذكرني</label>
        <button type="submit">دخول</button>
    </form>
</main>
</body>
</html>
