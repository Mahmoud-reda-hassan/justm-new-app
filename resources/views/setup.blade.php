<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>تهيئة Just-M</title>
    <style>
        :root { color-scheme: light; --ink:#182230; --muted:#667085; --line:#e5e9f0; --brand:#185adb; --bg:#f3f6fb; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; padding:28px 16px; background:var(--bg); color:var(--ink); font-family:Tahoma,Arial,sans-serif; }
        .wrap { width:min(760px,100%); margin:auto; }
        header { margin-bottom:22px; }
        .brand { display:inline-flex; align-items:center; gap:10px; font-size:22px; font-weight:800; }
        .mark { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; background:#e5edff; color:var(--brand); font-weight:900; }
        h1 { margin:18px 0 8px; font-size:25px; }
        p { color:var(--muted); line-height:1.8; margin:6px 0; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:16px; padding:24px; margin:14px 0; box-shadow:0 8px 30px rgba(20,35,70,.04); }
        .steps { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:18px 0; }
        .step { border:1px solid var(--line); border-radius:12px; padding:13px; background:#fff; }
        .step b { display:block; margin-bottom:5px; }
        .step small { color:var(--muted); }
        label { display:block; font-size:14px; font-weight:700; margin:14px 0 7px; }
        input { width:100%; border:1px solid #cfd6e2; border-radius:9px; padding:12px; font:inherit; background:#fff; }
        input:focus { outline:3px solid #dce7ff; border-color:var(--brand); }
        button { border:0; border-radius:9px; background:var(--brand); color:white; padding:12px 18px; font:inherit; font-weight:700; cursor:pointer; }
        button.secondary { background:#eef2f8; color:#25334a; }
        button:disabled { opacity:.45; cursor:not-allowed; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
        .notice { border-radius:10px; padding:12px 14px; margin:12px 0; line-height:1.8; background:#edf7f0; color:#17633a; }
        .error { background:#fff0f0; color:#a32222; }
        .hint { font-size:13px; }
        .status { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:12px 0; border-bottom:1px solid var(--line); }
        .status:last-child { border:0; }
        footer { text-align:center; color:#8791a2; font-size:12px; padding:18px; }
        @media(max-width:560px) { body { padding:18px 12px; } .panel { padding:18px; } .steps { grid-template-columns:1fr; } .actions button { flex:1; } h1 { font-size:22px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand"><span class="mark">J</span><span>Just-M</span></div>
        <h1>معالج تثبيت النظام</h1>
        <p>إعداد قاعدة البيانات وإنشاء أول حساب مدير بخطوات آمنة.</p>
    </header>

    @if (session('setup_status'))
        <div class="notice">{{ session('setup_status') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    @if (!$authorized)
        <section class="panel">
            <h2>الدخول إلى وضع التثبيت</h2>
            @if (!$tokenConfigured)
                <div class="notice error">واجهة التثبيت غير مفعلة. أضف SETUP_TOKEN عشوائيًا بطول 32 حرفًا على الأقل إلى ملف البيئة، ثم أعد تحميل الإعدادات.</div>
            @else
                <p>أدخل مفتاح التثبيت المؤقت الموجود في ملف البيئة. المفتاح لا يظهر في رابط الصفحة.</p>
                <form method="post" action="{{ route('setup.authorize') }}">
                    @csrf
                    <label for="setup_token">مفتاح التثبيت</label>
                    <input id="setup_token" name="setup_token" type="password" autocomplete="off" required>
                    <div class="actions"><button type="submit">فتح معالج التثبيت</button></div>
                </form>
            @endif
        </section>
    @else
        <div class="steps">
            <div class="step"><b>١. الاتصال</b><small>اختبار إعدادات قاعدة البيانات</small></div>
            <div class="step"><b>٢. الجداول</b><small>تشغيل migrations والبيانات الأولية</small></div>
            <div class="step"><b>٣. المدير</b><small>إنشاء أول حساب بصلاحيات محدودة</small></div>
        </div>

        <section class="panel">
            <h2>حالة قاعدة البيانات</h2>
            <div class="status"><span>اتصال قاعدة البيانات</span><strong>يتم اختباره عند الطلب</strong></div>
            <div class="status"><span>جداول النظام</span><strong>{{ $databaseReady ? 'موجودة' : 'لم تُنشأ بعد' }}</strong></div>
            <div class="status"><span>حساب المدير الأول</span><strong>{{ $adminExists ? 'تم إنشاؤه' : 'لم يُنشأ بعد' }}</strong></div>
            <div class="actions">
                <form method="post" action="{{ route('setup.connection') }}">@csrf<button class="secondary" type="submit">اختبار الاتصال</button></form>
                <form method="post" action="{{ route('setup.database') }}">@csrf<button type="submit">إنشاء الجداول والبيانات الأولية</button></form>
            </div>
            <p class="hint">بيانات الاتصال تُقرأ من ملف البيئة .env؛ لا تدخل كلمات مرور قاعدة البيانات داخل هذه الصفحة.</p>
        </section>

        @if ($databaseReady && !$adminExists)
            <section class="panel">
                <h2>إنشاء أول مدير</h2>
                <p>سيحصل الحساب على صلاحيات إدارة المستخدمين ومجموعات الصلاحيات فقط، دون صلاحيات مالية أو تشغيلية.</p>
                <form method="post" action="{{ route('setup.admin') }}">
                    @csrf
                    <label for="name">اسم المدير</label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                    <label for="username">اسم المستخدم</label>
                    <input id="username" name="username" value="{{ old('username') }}" maxlength="100" autocomplete="username" required>
                    <label for="email">البريد الإلكتروني (اختياري)</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                    <label for="password">كلمة المرور (12 حرفًا على الأقل)</label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                    <label for="password_confirmation">تأكيد كلمة المرور</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                    <div class="actions"><button type="submit">إنشاء المدير وإنهاء التثبيت</button></div>
                </form>
            </section>
        @elseif ($adminExists)
            <div class="notice">تم إنشاء مستخدم بالفعل؛ تم إغلاق واجهة التثبيت نهائيًا.</div>
        @endif
    @endif

    <footer>Just-M · بيئة الإعداد الأولي</footer>
</div>
</body>
</html>
