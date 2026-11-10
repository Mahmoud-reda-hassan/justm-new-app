<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>تهيئة {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; --ink:#182230; --muted:#667085; --line:#e5e9f0; --brand:#185adb; --bg:#f3f6fb; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; padding:28px 16px; background:var(--bg); color:var(--ink); font-family:Tahoma,Arial,sans-serif; }
        .wrap { width:min(900px,100%); margin:auto; }
        header { margin-bottom:22px; }
        .brand { display:inline-flex; align-items:center; gap:10px; font-size:22px; font-weight:800; }
        .mark { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; background:#e5edff; color:var(--brand); font-weight:900; }
        h1 { margin:18px 0 8px; font-size:25px; }
        h2 { margin-top:0; font-size:20px; }
        p { color:var(--muted); line-height:1.8; margin:6px 0; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:16px; padding:24px; margin:14px 0; box-shadow:0 8px 30px rgba(20,35,70,.04); }
        .steps { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:18px 0; }
        .step { border:1px solid var(--line); border-radius:12px; padding:13px; background:#fff; }
        .step b { display:block; margin-bottom:5px; }
        .step small { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 14px; }
        .full { grid-column:1/-1; }
        label { display:block; font-size:14px; font-weight:700; margin:14px 0 7px; }
        input,select,textarea { width:100%; border:1px solid #cfd6e2; border-radius:9px; padding:12px; font:inherit; background:#fff; }
        textarea { min-height:76px; resize:vertical; }
        input:focus,select:focus,textarea:focus { outline:3px solid #dce7ff; border-color:var(--brand); }
        button { border:0; border-radius:9px; background:var(--brand); color:white; padding:12px 18px; font:inherit; font-weight:700; cursor:pointer; }
        button.secondary { background:#eef2f8; color:#25334a; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
        .notice { border-radius:10px; padding:12px 14px; margin:12px 0; line-height:1.8; background:#edf7f0; color:#17633a; }
        .error { background:#fff0f0; color:#a32222; }
        .hint { font-size:13px; }
        .status { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:12px 0; border-bottom:1px solid var(--line); }
        .status:last-child { border:0; }
        footer { text-align:center; color:#8791a2; font-size:12px; padding:18px; }
        @media(max-width:650px) { body { padding:18px 12px; } .panel { padding:18px; } .steps { grid-template-columns:repeat(2,1fr); } .grid { grid-template-columns:1fr; } .full { grid-column:auto; } h1 { font-size:22px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand"><span class="mark">J</span><span>{{ config('app.name') }}</span></div>
        <h1>معالج التهيئة الأولية</h1>
        <p>إعداد قاعدة البيانات وبيانات التشغيل الأساسية ثم إنشاء أول حساب مدير.</p>
    </header>

    @if (session('setup_status'))
        <div class="notice">{{ session('setup_status') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    @if (!$authorized)
        <section class="panel">
            <h2>الدخول إلى وضع التهيئة</h2>
            @if (!$tokenConfigured)
                <div class="notice error">واجهة التهيئة غير مفعلة. أضف SETUP_TOKEN عشوائيًا بطول 32 حرفًا على الأقل إلى ملف البيئة، ثم أعد تحميل الإعدادات.</div>
            @else
                <p>أدخل مفتاح التهيئة المؤقت الموجود في ملف البيئة. المفتاح لا يظهر في رابط الصفحة.</p>
                <form method="post" action="{{ route('setup.authorize') }}">
                    @csrf
                    <label for="setup_token">مفتاح التهيئة</label>
                    <input id="setup_token" name="setup_token" type="password" autocomplete="off" required>
                    <div class="actions"><button type="submit">فتح معالج التهيئة</button></div>
                </form>
            @endif
        </section>
    @else
        <div class="steps">
            <div class="step"><b>١. قاعدة البيانات</b><small>الاتصال وإنشاء الجداول</small></div>
            <div class="step"><b>٢. بيانات التشغيل</b><small>الموقع والجغرافيا والشركة</small></div>
            <div class="step"><b>٣. الفرع والمخزن</b><small>أول فرع ومخزن تابع له</small></div>
            <div class="step"><b>٤. المدير</b><small>إنشاء أول حساب بصلاحيات محدودة</small></div>
        </div>

        <section class="panel">
            <h2>حالة قاعدة البيانات</h2>
            <div class="status"><span>جداول النظام</span><strong>{{ $databaseReady ? 'جاهزة' : 'لم تُنشأ بعد' }}</strong></div>
            <div class="status"><span>بيانات التشغيل الأساسية</span><strong>{{ $operatingDataReady ? 'مكتملة' : 'لم تُستكمل بعد' }}</strong></div>
            <div class="status"><span>حساب المدير الأول</span><strong>{{ $adminExists ? 'موجود' : 'لم يُنشأ بعد' }}</strong></div>
            <div class="actions">
                <form method="post" action="{{ route('setup.connection') }}">@csrf<button class="secondary" type="submit">اختبار الاتصال</button></form>
                <form method="post" action="{{ route('setup.database') }}">@csrf<button type="submit">إنشاء الجداول والبيانات الثابتة</button></form>
            </div>
            <p class="hint">بيانات الاتصال تُقرأ من ملف البيئة .env؛ لا تدخل كلمات مرور قاعدة البيانات داخل هذه الصفحة.</p>
        </section>

        @if ($databaseReady && !$operatingDataReady)
            <section class="panel">
                <h2>بيانات التشغيل الأولى</h2>
                <p>أدخل بيانات الموقع والمنشأة وموقعها الجغرافي، ثم أنشئ الفرع الرئيسي والمخزن الأول. تُحفظ كل البيانات في معاملة واحدة.</p>
                <form method="post" action="{{ route('setup.operating-data') }}">
                    @csrf
                    <div class="grid">
                        <div class="full"><label for="site_name">اسم الموقع / النظام</label><input id="site_name" name="site_name" value="{{ old('site_name', $siteName) }}" maxlength="150" required></div>
                        <div><label for="country_name_ar">الدولة - عربي</label><input id="country_name_ar" name="country_name_ar" value="{{ old('country_name_ar') }}" maxlength="100" required></div>
                        <div><label for="country_name_en">الدولة - English</label><input id="country_name_en" name="country_name_en" value="{{ old('country_name_en') }}" maxlength="100" required></div>
                        <div class="full"><label for="region_name">المنطقة (اختياري)</label><input id="region_name" name="region_name" value="{{ old('region_name') }}" maxlength="100"></div>
                        <div><label for="governorate_name_ar">المحافظة - عربي</label><input id="governorate_name_ar" name="governorate_name_ar" value="{{ old('governorate_name_ar') }}" maxlength="100" required></div>
                        <div><label for="governorate_name_en">المحافظة - English</label><input id="governorate_name_en" name="governorate_name_en" value="{{ old('governorate_name_en') }}" maxlength="100" required></div>
                        <div><label for="city_name_ar">المدينة - عربي</label><input id="city_name_ar" name="city_name_ar" value="{{ old('city_name_ar') }}" maxlength="120" required></div>
                        <div><label for="city_name_en">المدينة - English</label><input id="city_name_en" name="city_name_en" value="{{ old('city_name_en') }}" maxlength="120" required></div>
                        <div class="full"><label for="company_name">اسم الشركة الأولى</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="150" required></div>
                        <div><label for="company_phone">هاتف الشركة</label><input id="company_phone" name="company_phone" value="{{ old('company_phone') }}" maxlength="40"></div>
                        <div><label for="company_mobile">موبايل الشركة</label><input id="company_mobile" name="company_mobile" value="{{ old('company_mobile') }}" maxlength="40"></div>
                        <div><label for="company_email">البريد الإلكتروني</label><input id="company_email" name="company_email" type="email" value="{{ old('company_email') }}" maxlength="190"></div>
                        <div><label for="company_address">عنوان الشركة</label><input id="company_address" name="company_address" value="{{ old('company_address') }}" maxlength="2000"></div>
                        <div><label for="branch_name">اسم الفرع الأول</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', 'الفرع الرئيسي') }}" maxlength="120" required></div>
                        <div><label for="branch_address">عنوان الفرع</label><input id="branch_address" name="branch_address" value="{{ old('branch_address') }}" maxlength="255"></div>
                        <div><label for="warehouse_name">اسم المخزن الأول</label><input id="warehouse_name" name="warehouse_name" value="{{ old('warehouse_name', 'المخزن الرئيسي') }}" maxlength="150" required></div>
                        <div><label for="warehouse_type">نوع المخزن</label>
                            <select id="warehouse_type" name="warehouse_type" required>
                                <option value="" @selected(old('warehouse_type') === null)>اختر نوع المخزن</option>
                                <option value="internal" @selected(old('warehouse_type') === 'internal')>مخزن عادي</option>
                                <option value="sales_dispatch" @selected(old('warehouse_type') === 'sales_dispatch')>مخزن بيع مباشر</option>
                            </select>
                        </div>
                    </div>
                    <div class="actions"><button type="submit">حفظ بيانات التشغيل الأولى</button></div>
                </form>
            </section>
        @elseif ($operatingDataReady)
            <div class="notice">تم إعداد بيانات الموقع والشركة والفرع والمخزن الأول.</div>
        @endif

        @if ($databaseReady && $operatingDataReady && !$adminExists)
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
                    <div class="actions"><button type="submit">إنشاء المدير وإنهاء التهيئة</button></div>
                </form>
            </section>
        @elseif ($adminExists && !$operatingDataReady)
            <div class="notice">يوجد مستخدم بالفعل. أكمل بيانات التشغيل الأساسية؛ لن يتم إنشاء مستخدم آخر.</div>
        @endif
    @endif

    <footer>{{ config('app.name') }} · التهيئة الأولية</footer>
</div>
</body>
</html>
