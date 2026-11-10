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
        .wrap { width:min(960px,100%); margin:auto; }
        header { margin-bottom:22px; }
        .brand { display:inline-flex; align-items:center; gap:10px; font-size:22px; font-weight:800; }
        .mark { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; background:#e5edff; color:var(--brand); font-weight:900; }
        h1 { margin:18px 0 8px; font-size:25px; }
        h2 { margin-top:0; font-size:20px; }
        h3 { margin:22px 0 8px; font-size:16px; }
        p { color:var(--muted); line-height:1.8; margin:6px 0; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:16px; padding:24px; margin:14px 0; box-shadow:0 8px 30px rgba(20,35,70,.04); }
        .steps { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:18px 0; }
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
        @media(max-width:650px) { body { padding:18px 12px; } .panel { padding:18px; } .steps { grid-template-columns:1fr; } .grid { grid-template-columns:1fr; } .full { grid-column:auto; } h1 { font-size:22px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand"><span class="mark">J</span><span>{{ config('app.name') }}</span></div>
        <h1>معالج التهيئة الأولية</h1>
        <p>إنشاء المؤسسة والفرع الرئيسي والمخزن وحساب مدير النظام في خطوة تأسيس واحدة.</p>
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
            <div class="step"><b>٢. المؤسسة ومدير النظام</b><small>الفرع الرئيسي والعنوان وبيانات الدخول</small></div>
            <div class="step"><b>٣. تأكيد البريد</b><small>تفعيل وسيلة استعادة كلمة المرور</small></div>
        </div>

        <section class="panel">
            <h2>حالة التهيئة</h2>
            <div class="status"><span>جداول النظام</span><strong>{{ $databaseReady ? 'جاهزة' : 'لم تُنشأ بعد' }}</strong></div>
            <div class="status"><span>بيانات المؤسسة والفرع والمخزن</span><strong>{{ $operatingDataReady ? 'مكتملة' : 'لم تُستكمل بعد' }}</strong></div>
            <div class="status"><span>حساب مدير النظام</span><strong>{{ $adminExists ? 'موجود' : 'لم يُنشأ بعد' }}</strong></div>
            <div class="actions">
                <form method="post" action="{{ route('setup.connection') }}">@csrf<button class="secondary" type="submit">اختبار الاتصال</button></form>
                <form method="post" action="{{ route('setup.database') }}">@csrf<button type="submit">إنشاء الجداول والبيانات الثابتة</button></form>
            </div>
            <p class="hint">بيانات الاتصال تُقرأ من ملف البيئة .env؛ لا تدخل كلمات مرور قاعدة البيانات داخل هذه الصفحة.</p>
        </section>

        @if ($databaseReady && !$adminExists && !$operatingDataReady)
            <section class="panel">
                <h2>إعداد مدير النظام والمؤسسة</h2>
                <p>أدخل بيانات المدير والمؤسسة والفرع الرئيسي وعنوانه. سيتم إنشاء البيانات الجغرافية والفرع والمخزن وحساب المدير معًا في معاملة واحدة، ولن يُنشأ المدير إذا فشل أي جزء.</p>
                <form method="post" action="{{ route('setup.admin') }}">
                    @csrf
                    <h3>بيانات مدير النظام</h3>
                    <div class="grid">
                        <div><label for="name">اسم المدير</label><input id="name" name="name" value="{{ old('name') }}" maxlength="150" required></div>
                        <div><label for="username">اسم المستخدم</label><input id="username" name="username" value="{{ old('username') }}" maxlength="100" autocomplete="username" required></div>
                        <div class="full"><label for="email">البريد الإلكتروني للمدير (مطلوب للتحقق واستعادة كلمة المرور)</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></div>
                        <div><label for="password">كلمة المرور (12 حرفًا على الأقل)</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
                        <div><label for="password_confirmation">تأكيد كلمة المرور</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
                    </div>

                    <h3>بيانات الشركة أو المؤسسة</h3>
                    <div class="grid">
                        <div><label for="company_name">اسم الشركة / المؤسسة</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="150" required></div>
                        <div><label for="company_phone">رقم هاتف المؤسسة الأول</label><input id="company_phone" name="company_phone" type="tel" value="{{ old('company_phone') }}" maxlength="40" required></div>
                    </div>

                    <h3>الفرع الرئيسي والعنوان</h3>
                    <div class="grid">
                        <div><label for="branch_name">اسم الفرع الرئيسي</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', 'الفرع الرئيسي') }}" maxlength="120" required></div>
                        <div><label for="country_id">الدولة</label>
                            <select id="country_id" name="country_id" required>
                                <option value="">اختر الدولة</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected((string) old('country_id', $defaultCountryId) === (string) $country->id)>{{ $country->name_ar }} — {{ $country->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="governorate_id">المحافظة</label>
                            <select id="governorate_id" name="governorate_id" required>
                                <option value="">اختر المحافظة</option>
                                @foreach ($governorates as $governorate)
                                    <option value="{{ $governorate->id }}" data-country-id="{{ $governorate->country_id }}" @selected((string) old('governorate_id') === (string) $governorate->id)>{{ $governorate->name_ar }} — {{ $governorate->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="city_id">المدينة</label>
                            <select id="city_id" name="city_id" required>
                                <option value="">اختر المدينة</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}" data-governorate-id="{{ $city->governorate_id }}" @selected((string) old('city_id') === (string) $city->id)>{{ $city->name_ar }} — {{ $city->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="full"><label for="branch_address">العنوان التفصيلي للفرع</label><textarea id="branch_address" name="branch_address" maxlength="255" required>{{ old('branch_address') }}</textarea></div>
                    </div>

                    <h3>المخزن الأول</h3>
                    <div class="grid">
                        <div><label for="warehouse_name">اسم المخزن</label><input id="warehouse_name" name="warehouse_name" value="{{ old('warehouse_name', 'المخزن الرئيسي') }}" maxlength="150" required></div>
                        <div><label for="warehouse_type">نوع المخزن</label>
                            <select id="warehouse_type" name="warehouse_type" required>
                                <option value="">اختر نوع المخزن</option>
                                <option value="internal" @selected(old('warehouse_type') === 'internal')>مخزن عادي</option>
                                <option value="sales_dispatch" @selected(old('warehouse_type') === 'sales_dispatch')>مخزن بيع مباشر</option>
                            </select>
                        </div>
                    </div>
                    <div class="actions"><button type="submit">إنشاء المؤسسة والفرع والمدير</button></div>
                </form>
            </section>
        @elseif ($adminExists && !$operatingDataReady)
            <section class="panel">
                <h2>استكمال بيانات المؤسسة الحالية</h2>
                <p>يوجد مدير بالفعل؛ هذه الخطوة لاستكمال بيانات المؤسسة والفرع فقط دون إنشاء مستخدم آخر.</p>
                <form method="post" action="{{ route('setup.operating-data') }}">
                    @csrf
                    <div class="grid">
                        <div><label for="country_id">الدولة</label>
                            <select id="country_id" name="country_id" required>
                                <option value="">اختر الدولة</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected((string) old('country_id', $defaultCountryId) === (string) $country->id)>{{ $country->name_ar }} — {{ $country->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="governorate_id">المحافظة</label>
                            <select id="governorate_id" name="governorate_id" required>
                                <option value="">اختر المحافظة</option>
                                @foreach ($governorates as $governorate)
                                    <option value="{{ $governorate->id }}" data-country-id="{{ $governorate->country_id }}" @selected((string) old('governorate_id') === (string) $governorate->id)>{{ $governorate->name_ar }} — {{ $governorate->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="city_id">المدينة</label>
                            <select id="city_id" name="city_id" required>
                                <option value="">اختر المدينة</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}" data-governorate-id="{{ $city->governorate_id }}" @selected((string) old('city_id') === (string) $city->id)>{{ $city->name_ar }} — {{ $city->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="full"><label for="company_name">اسم الشركة / المؤسسة</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required></div>
                        <div><label for="company_phone">رقم هاتف المؤسسة الأول</label><input id="company_phone" name="company_phone" value="{{ old('company_phone') }}" required></div>
                        <div><label for="branch_name">اسم الفرع الرئيسي</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', 'الفرع الرئيسي') }}" required></div>
                        <div class="full"><label for="branch_address">عنوان الفرع</label><textarea id="branch_address" name="branch_address" required>{{ old('branch_address') }}</textarea></div>
                        <div><label for="warehouse_name">اسم المخزن الأول</label><input id="warehouse_name" name="warehouse_name" value="{{ old('warehouse_name', 'المخزن الرئيسي') }}" required></div>
                        <div><label for="warehouse_type">نوع المخزن</label><select id="warehouse_type" name="warehouse_type" required><option value="">اختر النوع</option><option value="internal">مخزن عادي</option><option value="sales_dispatch">مخزن بيع مباشر</option></select></div>
                    </div>
                    <div class="actions"><button type="submit">حفظ بيانات المؤسسة والفرع</button></div>
                </form>
            </section>
        @elseif ($databaseReady && $operatingDataReady && !$adminExists)
            <section class="panel">
                <h2>إنشاء مدير النظام</h2>
                <p>بيانات المؤسسة موجودة بالفعل. أدخل بيانات المدير، ويجب أن يكون البريد الإلكتروني صالحًا لتأكيده واستعادة كلمة المرور.</p>
                <form method="post" action="{{ route('setup.admin') }}">
                    @csrf
                    <div class="grid">
                        <div><label for="name">اسم المدير</label><input id="name" name="name" value="{{ old('name') }}" required></div>
                        <div><label for="username">اسم المستخدم</label><input id="username" name="username" value="{{ old('username') }}" required></div>
                        <div class="full"><label for="email">البريد الإلكتروني</label><input id="email" name="email" type="email" value="{{ old('email') }}" required></div>
                        <div><label for="password">كلمة المرور (12 حرفًا على الأقل)</label><input id="password" name="password" type="password" minlength="12" required></div>
                        <div><label for="password_confirmation">تأكيد كلمة المرور</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required></div>
                    </div>
                    <div class="actions"><button type="submit">إنشاء المدير</button></div>
                </form>
            </section>
        @endif
    @endif

    <footer>{{ config('app.name') }} · التهيئة الأولية</footer>
</div>

<script>
(() => {
    const country = document.getElementById('country_id');
    const governorate = document.getElementById('governorate_id');
    const city = document.getElementById('city_id');
    if (!country || !governorate || !city) return;

    const filterOptions = (select, attribute, selectedParent) => {
        Array.from(select.options).forEach((option, index) => {
            if (index === 0) return;
            const visible = Boolean(selectedParent) && option.dataset[attribute] === selectedParent;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        const selected = select.selectedOptions[0];
        if (selected && selected.disabled) select.value = '';
        select.disabled = !selectedParent;
    };

    const refreshGovernorates = (reset = false) => {
        if (reset) {
            governorate.value = '';
            city.value = '';
        }
        filterOptions(governorate, 'countryId', country.value);
        filterOptions(city, 'governorateId', governorate.value);
    };

    country.addEventListener('change', () => refreshGovernorates(true));
    governorate.addEventListener('change', () => {
        city.value = '';
        filterOptions(city, 'governorateId', governorate.value);
    });

    refreshGovernorates(false);
})();
</script>

</body>
</html>
