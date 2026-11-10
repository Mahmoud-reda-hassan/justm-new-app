<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم | {{ config('app.name') }}</title>
    <style>
        :root{--navy:#1f2d3d;--navy2:#26384c;--blue:#2878c7;--blue-soft:#eaf3fc;--ink:#263445;--muted:#788697;--line:#e5eaf0;--page:#f4f6f9;--white:#fff;--green:#23966b;--shadow:0 2px 8px rgba(28,45,66,.055)}
        *{box-sizing:border-box}html{min-height:100%;background:var(--page)}body{margin:0;color:var(--ink);font-family:Tahoma,Arial,sans-serif;font-size:14px;background:var(--page)}
        a{color:inherit;text-decoration:none}.app{min-height:100vh;display:grid;grid-template-columns:258px minmax(0,1fr)}
        .sidebar{background:var(--navy);color:#c7d1dc;min-height:100vh;display:flex;flex-direction:column;box-shadow:2px 0 10px #1d2b3a14}
        .brand{height:66px;display:flex;align-items:center;gap:12px;padding:0 20px;border-bottom:1px solid #ffffff12;color:#fff}
        .brand-logo{height:36px;width:36px;border-radius:8px;background:linear-gradient(145deg,#3b91df,#2365a8);display:grid;place-items:center;font-weight:bold;font-size:20px;color:#fff}
        .brand-name{font-size:16px;font-weight:bold;letter-spacing:.2px}.brand-caption{font-size:10px;color:#9eafc1;margin-top:4px}
        .profile{display:flex;align-items:center;gap:11px;padding:19px 17px;border-bottom:1px solid #ffffff12}
        .profile-avatar{height:40px;width:40px;border-radius:50%;background:#dbeafe;color:#245b91;display:grid;place-items:center;font-weight:bold;font-size:16px;flex:0 0 40px}
        .profile-name{color:#fff;font-size:12px;font-weight:bold;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.profile-role{font-size:10px;color:#9eafc1;margin-top:5px}
        .nav{padding:14px 10px;display:grid;gap:4px}.nav-caption{font-size:10px;color:#8294a8;padding:10px 12px 6px}
        .nav-link{min-height:40px;padding:10px 12px;display:flex;align-items:center;gap:12px;border-radius:5px;color:#c7d1dc;font-size:12px;transition:background .15s,color .15s}
        .nav-link:hover{background:#ffffff0c;color:#fff}.nav-link.active{background:var(--blue);color:#fff;box-shadow:0 3px 8px #102b491f}
        .nav-ico{width:20px;text-align:center;font-size:16px;color:#9fb2c6}.nav-link.active .nav-ico{color:#fff}
        .nav-group{margin-top:7px}.nav-group-title{padding:10px 12px 6px;color:#8194a9;font-size:10px;font-weight:bold}
        .nav-sub{display:grid;gap:2px;padding-right:10px}.nav-sub .nav-link{min-height:34px;padding:8px 12px;font-size:11px}
        .sidebar-bottom{margin-top:auto;padding:14px 12px;border-top:1px solid #ffffff12}.logout{display:flex;align-items:center;gap:11px;color:#c7d1dc;padding:10px 12px;border-radius:5px;font:inherit;font-size:12px;width:100%;background:none;border:0;cursor:pointer;text-align:right}.logout:hover{background:#ffffff0c;color:#fff}
        .main{min-width:0}.topbar{height:66px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 28px;box-shadow:0 1px 2px #25364a08}
        .topbar-left{display:flex;align-items:center;gap:16px}.crumb{font-size:12px;color:var(--muted)}.crumb strong{color:#34465a;font-weight:600}.topbar-right{display:flex;align-items:center;gap:16px}.top-user{font-size:12px;color:#657487}
        .content{padding:25px 28px 36px;max-width:1500px;margin:0 auto}.page-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:20px}
        .page-heading h1{font-size:21px;margin:0 0 7px;color:#27384a}.page-heading p{margin:0;color:var(--muted);font-size:12px;line-height:1.7}
        .page-tag{background:#fff;border:1px solid var(--line);border-radius:5px;padding:8px 12px;font-size:11px;color:#728196}
        .alert{border-radius:5px;padding:12px 15px;margin-bottom:12px;font-size:12px;line-height:1.8;border-right:3px solid #d99a26;background:#fff8e9;color:#77520d}
        .alert.info{border-right-color:#4b8ac7;background:#edf5fc;color:#315d83}.alert a{text-decoration:underline;font-weight:bold}
        .section{margin-top:24px}.section-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}.section-head:before{content:"";height:18px;width:3px;background:var(--blue);border-radius:2px}.section-head h2{font-size:14px;margin:0;color:#34465a}.section-head span{font-size:10px;color:#98a4b2;margin-right:auto}
        .module-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
        .module{background:#fff;border:1px solid var(--line);border-radius:6px;min-height:100px;padding:17px;display:flex;align-items:flex-start;gap:13px;box-shadow:var(--shadow);transition:transform .15s,border-color .15s,box-shadow .15s}
        .module:hover{transform:translateY(-2px);border-color:#b9d1e8;box-shadow:0 6px 16px #25364a12}
        .module-icon{height:42px;width:42px;flex:0 0 42px;border-radius:6px;background:var(--blue-soft);color:#2878c7;display:grid;place-items:center;font-size:19px}
        .module-body{min-width:0}.module h3{font-size:13px;margin:2px 0 7px;color:#33465a}.module p{font-size:10px;line-height:1.75;color:#8793a1;margin:0}
        .module-arrow{margin-right:auto;color:#a4afbb;font-size:18px;line-height:1}
        .empty{background:#fff;border:1px dashed #ccd5df;border-radius:6px;padding:24px;text-align:center;color:#7c8998;font-size:12px}
        @media(max-width:1100px){.module-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.app{grid-template-columns:230px minmax(0,1fr)}}
        @media(max-width:720px){.app{display:block}.sidebar{min-height:auto}.brand{height:58px}.profile{padding:11px 16px}.nav{padding:8px 10px;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.nav-caption,.nav-group-title{grid-column:1/-1;padding:7px 9px 3px}.nav-group{display:contents}.nav-sub{display:contents}.nav-link{min-height:38px;padding:9px;font-size:11px}.nav-sub .nav-link{min-height:38px}.sidebar-bottom{padding:8px 10px}.topbar{height:52px;padding:0 14px}.top-user{display:none}.content{padding:19px 13px 28px}.page-heading h1{font-size:18px}.page-tag{display:none}.module-grid{grid-template-columns:1fr}.module{min-height:86px}}
    </style>
</head>
<body>
@php($user = auth()->user())
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-logo">J</span>
            <span><span class="brand-name">{{ config('app.name') }}</span><span class="brand-caption" style="display:block">نظام إدارة الأعمال</span></span>
        </a>
        <div class="profile">
            <span class="profile-avatar">{{ mb_substr($user->name, 0, 1) }}</span>
            <span style="min-width:0"><span class="profile-name">{{ $user->name }}</span><span class="profile-role" style="display:block">حساب النظام</span></span>
        </div>
        <nav class="nav" aria-label="القائمة الرئيسية">
            <div class="nav-caption">القائمة الرئيسية</div>
            <a class="nav-link active" href="{{ route('dashboard') }}"><span class="nav-ico">⌂</span>لوحة التحكم</a>

            @if ($user->hasPermission('sales.orders.view') || $user->hasPermission('parties.view'))
                <div class="nav-group">
                    <div class="nav-group-title">العمليات</div>
                    <div class="nav-sub">
                        @if ($user->hasPermission('sales.orders.view'))
                            <a class="nav-link" href="{{ route('admin.sales.orders.index') }}"><span class="nav-ico">▤</span>أوامر البيع</a>
                        @endif
                        @if ($user->hasPermission('parties.view'))
                            <a class="nav-link" href="{{ route('admin.parties.index') }}"><span class="nav-ico">♙</span>الأطراف</a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($user->hasPermission('products.view') || $user->hasPermission('product_categories.view') || $user->hasPermission('product_attributes.view') || $user->hasPermission('units.view'))
                <div class="nav-group">
                    <div class="nav-group-title">كتالوج المنتجات</div>
                    <div class="nav-sub">
                        @if ($user->hasPermission('products.view') && $user->hasPermission('structure.scope.manage_all'))
                            <a class="nav-link" href="{{ route('admin.products.index') }}"><span class="nav-ico">▦</span>المنتجات</a>
                        @endif
                        @if ($user->hasPermission('product_categories.view') && $user->hasPermission('structure.scope.manage_all'))
                            <a class="nav-link" href="{{ route('admin.catalog.categories') }}"><span class="nav-ico">▧</span>التصنيفات</a>
                        @endif
                        @if ($user->hasPermission('product_attributes.view') && $user->hasPermission('structure.scope.manage_all'))
                            <a class="nav-link" href="{{ route('admin.product-attributes.index') }}"><span class="nav-ico">◇</span>صفات المنتجات</a>
                        @endif
                        @if ($user->hasPermission('units.view') && $user->hasPermission('structure.scope.manage_all'))
                            <a class="nav-link" href="{{ route('admin.catalog.units') }}"><span class="nav-ico">⚖</span>وحدات القياس</a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('companies.view') || $user->hasPermission('branches.view') || $user->hasPermission('warehouses.view'))
                <div class="nav-group">
                    <div class="nav-group-title">الإدارة والإعداد</div>
                    <div class="nav-sub">
                        @if ($user->hasPermission('users.view'))
                            <a class="nav-link" href="{{ route('admin.access.users') }}"><span class="nav-ico">♧</span>المستخدمون</a>
                        @endif
                        @if ($user->hasPermission('permissions.groups.view'))
                            <a class="nav-link" href="{{ route('admin.access.roles') }}"><span class="nav-ico">⚿</span>مجموعات الصلاحيات</a>
                        @endif
                        @if ($user->hasPermission('companies.view'))
                            <a class="nav-link" href="{{ route('admin.structure.companies') }}"><span class="nav-ico">▥</span>الشركات</a>
                        @endif
                        @if ($user->hasPermission('branches.view'))
                            <a class="nav-link" href="{{ route('admin.structure.branches') }}"><span class="nav-ico">⌖</span>الفروع</a>
                        @endif
                        @if ($user->hasPermission('warehouses.view'))
                            <a class="nav-link" href="{{ route('admin.structure.warehouses') }}"><span class="nav-ico">▤</span>المخازن</a>
                        @endif
                    </div>
                </div>
            @endif
        </nav>
        <div class="sidebar-bottom">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="logout" type="submit"><span class="nav-ico">⇥</span>تسجيل الخروج</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left"><span class="crumb">الرئيسية</span><span style="color:#c4ccd5">/</span><strong class="crumb">لوحة التحكم</strong></div>
            <div class="topbar-right"><span class="top-user">{{ $user->name }}</span><span class="profile-avatar" style="height:32px;width:32px;flex-basis:32px;font-size:13px">{{ mb_substr($user->name, 0, 1) }}</span></div>
        </header>

        <div class="content">
            <div class="page-heading">
                <div><h1>لوحة التحكم</h1><p>مرحبًا {{ $user->name }}، اختر الوحدة التي تريد العمل عليها من القائمة أو الاختصارات التالية.</p></div>
                <span class="page-tag">نظرة عامة على وحدات النظام</span>
            </div>

            @if (!$user->email)
                <div class="alert">لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}">إضافة بريد إلكتروني وتأكيده</a></div>
            @elseif (!$user->hasVerifiedEmail())
                <div class="alert info">البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}">تأكيد البريد الإلكتروني</a></div>
            @endif

            @php($hasOperations = $user->hasPermission('sales.orders.view') || $user->hasPermission('parties.view'))
            @if ($hasOperations)
                <section class="section">
                    <div class="section-head"><h2>العمليات</h2><span>الوحدات التشغيلية المتاحة</span></div>
                    <div class="module-grid">
                        @if ($user->hasPermission('sales.orders.view'))
                            <a class="module" href="{{ route('admin.sales.orders.index') }}"><span class="module-icon">▤</span><span class="module-body"><h3>أوامر البيع</h3><p>عرض ومتابعة أوامر البيع المتاحة ضمن نطاق حسابك.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('parties.view'))
                            <a class="module" href="{{ route('admin.parties.index') }}"><span class="module-icon">♙</span><span class="module-body"><h3>إدارة الأطراف</h3><p>العملاء والموردون والمسوقون والوسطاء والمندوبون وشركات الشحن.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                    </div>
                </section>
            @endif

            @php($hasCatalog = ($user->hasPermission('products.view') || $user->hasPermission('product_categories.view') || $user->hasPermission('product_attributes.view') || $user->hasPermission('units.view')) && $user->hasPermission('structure.scope.manage_all'))
            @if ($hasCatalog)
                <section class="section">
                    <div class="section-head"><h2>كتالوج المنتجات</h2><span>تعريف المنتجات ومكوناتها</span></div>
                    <div class="module-grid">
                        @if ($user->hasPermission('products.view'))
                            <a class="module" href="{{ route('admin.products.index') }}"><span class="module-icon">▦</span><span class="module-body"><h3>المنتجات</h3><p>بيانات المنتجات والأكواد والأسعار.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('product_categories.view'))
                            <a class="module" href="{{ route('admin.catalog.categories') }}"><span class="module-icon">▧</span><span class="module-body"><h3>تصنيفات المنتجات</h3><p>تنظيم التصنيفات الرئيسية والفرعية.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('product_attributes.view'))
                            <a class="module" href="{{ route('admin.product-attributes.index') }}"><span class="module-icon">◇</span><span class="module-body"><h3>صفات المنتجات</h3><p>مجموعات الصفات والقيم المستخدمة في تركيبات المنتجات.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('units.view'))
                            <a class="module" href="{{ route('admin.catalog.units') }}"><span class="module-icon">⚖</span><span class="module-body"><h3>وحدات القياس</h3><p>تعريف وحدات القياس الأساسية.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                    </div>
                </section>
            @endif

            @php($hasAdministration = $user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('companies.view') || $user->hasPermission('branches.view') || $user->hasPermission('warehouses.view'))
            @if ($hasAdministration)
                <section class="section">
                    <div class="section-head"><h2>الإدارة والإعداد</h2><span>إعداد هيكل النظام وإدارة الوصول</span></div>
                    <div class="module-grid">
                        @if ($user->hasPermission('users.view'))
                            <a class="module" href="{{ route('admin.access.users') }}"><span class="module-icon">♧</span><span class="module-body"><h3>إدارة المستخدمين</h3><p>حسابات الدخول وربطها بالأطراف وحالة المستخدم.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('permissions.groups.view'))
                            <a class="module" href="{{ route('admin.access.roles') }}"><span class="module-icon">⚿</span><span class="module-body"><h3>مجموعات الصلاحيات</h3><p>تحديد صلاحيات التشغيل لكل مجموعة.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('companies.view'))
                            <a class="module" href="{{ route('admin.structure.companies') }}"><span class="module-icon">▥</span><span class="module-body"><h3>الشركات</h3><p>إدارة الشركات المسجلة في النظام.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('branches.view'))
                            <a class="module" href="{{ route('admin.structure.branches') }}"><span class="module-icon">⌖</span><span class="module-body"><h3>الفروع</h3><p>إدارة الفروع التابعة للشركات.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->hasPermission('warehouses.view'))
                            <a class="module" href="{{ route('admin.structure.warehouses') }}"><span class="module-icon">▤</span><span class="module-body"><h3>المخازن</h3><p>إدارة المخازن ونطاقاتها.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                        @if ($user->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists())
                            <a class="module" href="{{ route('admin.system.database-updates') }}"><span class="module-icon">⟳</span><span class="module-body"><h3>تحديث قاعدة البيانات</h3><p>مراجعة تحديثات قاعدة البيانات المسموح بها.</p></span><span class="module-arrow">‹</span></a>
                        @endif
                    </div>
                </section>
            @endif

            @if (!$hasOperations && !$hasCatalog && !$hasAdministration)
                <div class="empty">لا توجد وحدات تشغيل مسندة إلى حسابك حاليًا. تواصل مع مسؤول النظام لإسناد الصلاحيات المناسبة.</div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
