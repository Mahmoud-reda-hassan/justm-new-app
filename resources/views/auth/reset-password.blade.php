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
    <h1>تعيين كلمة مرور جديدة</h1>
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">البريد الإلكتروني</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
        <label for="password">كلمة المرور الجديدة (12 حرفًا على الأقل)</label>
        <input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required>
        <label for="password_confirmation">تأكيد كلمة المرور</label>
        <input id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
        <button type="submit">حفظ كلمة المرور الجديدة</button>
    </form>
</main></body></html>
