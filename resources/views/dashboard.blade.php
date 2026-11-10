<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم - {{ config('app.name') }}</title>
    <style>
        :root{--navy:#172b4d;--blue:#2864dc;--ink:#202b3c;--muted:#718096;--line:#e7ecf3;--surface:#fff;--page:#f4f7fb;--green:#16845b;--amber:#a96508}
        *{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--ink);font-family:Tahoma,Arial,sans-serif}
        a{color:inherit;text-decoration:none}.shell{min-height:100vh;display:grid;grid-template-columns:250px minmax(0,1fr)}
        .sidebar{background:var(--navy);color:#dce6f6;padding:24px 16px;display:flex;flex-direction:column;gap:28px}
        .brand{display:flex;align-items:center;gap:12px;padding:0 8px 20px;border-bottom:1px solid #ffffff20}
        .brand-mark{width:42px;height:42px;border-radius:12px;background:#ffffff18;display:grid;place-items:center;font-size:21px;font-weight:bold;color:#fff}
        .brand strong{display:block;color:#fff;font-size:17px}.brand small{display:block;color:#aebed5;margin-top:5px}
        .nav-title{font-size:11px;color:#9fb0c9;padding:0 10px;margin:0 0 8px}
        .nav-list{display:grid;gap:5px}.nav-link{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:9px;font-size:13px;color:#dce6f6;transition:.15s}
        .nav-link:hover,.nav-link.active{background:#ffffff18;color:#fff}.nav-icon{width:22px;text-align:center;font-size:16px}
        .sidebar-foot{margin-top:auto;border-top:1px solid #ffffff20;padding-top:16px;color:#9fb0c9;font-size:11px;line-height:1.8}
        .main{min-width:0}.topbar{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 30px}
        .topbar-title{font-size:14px;font-weight:bold}.user-chip{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:12px}
        .avatar{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#e9efff;color:var(--blue);font-weight:bold}
        .content{max-width:1440px;margin:auto;padding:30px}.welcome{display:flex;align-items:center;justify-content:space-between;gap:20px;background:linear-gradient(115deg,#1d3761,#2e64bd);color:#fff;border-radius:16px;padding:26px 30px;margin-bottom:24px;box-shadow:0 8px 24px #1d37611a}
        .welcome h1{font-size:23px;margin:0 0 9px}.welcome p{margin:0;color:#d9e5fb;font-size:13px;line-height:1.8}
        .welcome-mark{font-size:42px;opacity:.9;padding:0 14px}.section-head{display:flex;align-items:center;justify-content:space-between;margin:28px 0 13px}
        .section-head h2{font-size:16px;margin:0}.section-head span{font-size:11px;color:var(--muted)}
        .cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .module{display:flex;align-items:flex-start;gap:14px;background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px;min-height:112px;transition:.15s}
        .module:hover{border-color:#b9cbed;box-shadow:0 6px 18px #263b5a0d;transform:translateY(-1px)}
        .module-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;background:#edf3ff;color:var(--blue);display:grid;place-items:center;font-size:19px}
        .module h3{font-size:14px;margin:2px 0 7px}.module p{font-size:11px;line-height:1.7;color:var(--muted);margin:0}
        .module-arrow{margin-right:auto;color:#9aa8ba;font-size:18px}
        .notice{padding:13px 16px;border-radius:10px;margin-bottom:12px;font-size:12px;line-height:1.8;background:#fff8e9;border:1px solid #f2dfb7;color:#80520e}
        .notice a{text-decoration:underline;font-weight:bold}.notice.info{background:#eef5ff;border-color:#d5e4fb;color:#315987}
        .empty{background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:22px;color:var(--muted);font-size:12px;text-align:center}
        .logout{width:100%;border:1px solid #ffffff35;background:transparent;color:#fff;border-radius:8px;padding:10px;font:inherit;font-size:12px;cursor:pointer}
        @media(max-width:1050px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.shell{grid-template-columns:220px minmax(0,1fr)}}
        @media(max-width:720px){.shell{display:block}.sidebar{padding:14px;gap:14px}.brand{padding-bottom:12px}.nav-list{grid-template-columns:repeat(2,minmax(0,1fr))}.nav-title,.sidebar-foot{display:none}.topbar{height:58px;padding:0 16px}.content{padding:18px 14px}.welcome{padding:20px}.welcome h1{font-size:19px}.welcome-mark{font-size:30px}.cards{grid-template-columns:1fr}.module{min-height:92px}}
    </style>
</head>
<body>
@php($user = auth()->user())
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">J</div>
            <div><strong>{{ config('app.name') }}</strong><small>نظام إدارة العمليات</small></div>
        </div>
        <div>
            <p class="nav-title">القائمة الرئيسية</p>
            <nav class="nav-list">
                <a class="nav-link active" href="{{ route('dashboard') }}"><span class="nav-icon">⌂</span>لوحة التحكم</a>
                @if ($user->hasPermission('sales.orders.view'))
                    <a class="nav-link" href="{{ route('admin.sales.orders.index') }}"><span class="nav-icon">▤</span>أوامر البيع</a>
                @endif
                @if ($user->hasPermission('parties.view'))
                    <a class="nav-link" href="{{ route('admin.parties.index') }}"><span class="nav-icon">♙</span>الأطراف</a>
                @endif
                @if ($user->hasPermission('products.view') && $user->hasPermission('structure.scope.manage_all'))
                    <a class="nav-link" href="{{ route('admin.products.index') }}"><span class="nav-icon">▦</span>المنتجات</a>
                @endif
                @if ($user->hasPermission('users.view'))
                    <a class="nav-link" href="{{ route('admin.access.users') }}"><span class="nav-icon">♧</span>المستخدمون</a>
                @endif
            </nav>
        </div>
        <div class="sidebar-foot">
            <div>مسجل الدخول باسم</div>
            <strong style="color:#fff">{{ $user->name }}</strong>
            <form method="POST" action="{{ route('logout') }}" style="margin-top:14px">@csrf<button class="logout" type="submit">تسجيل الخروج</button></form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">لوحة التحكم</div>
            <div class="user-chip"><span>{{ $user->name }}</span><span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span></div>
        </header>
        <div class="content">
            <section class="welcome">
                <div>
                    <h1>مرحبًا، {{ $user->name }}</h1>
                    <p>من هنا يمكنك الوصول إلى وحدات النظام المتاحة لحسابك.<br>تظهر لك الشاشات التي تملك صلاحية استخدامها فقط.</p>
                </div>
                <div class="welcome-mark">◈</div>
            </section>

            @if (!$user->email)
                <div class="notice">لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}">إضافة بريد إلكتروني وتأكيده</a></div>
            @elseif (!$user->hasVerifiedEmail())
                <div class="notice info">البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}">تأكيد البريد الإلكتروني</a></div>
            @endif

            <div class="section-head"><h2>إدارة النظام</h2><span>حسب الصلاحيات المسندة لحسابك</span></div>
            <div class="cards">
                @if ($user->hasPermission('users.view'))
                    <a class="module" href="{{ route('admin.access.users') }}"><span class="module-icon">♙</span><span><h3>إدارة المستخدمين</h3><p>حسابات الدخول وربطها بالأطراف وإدارة حالة المستخدم.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('permissions.groups.view'))
                    <a class="module" href="{{ route('admin.access.roles') }}"><span class="module-icon">⚿</span><span><h3>مجموعات الصلاحيات</h3><p>تجهيز مجموعات التشغيل وتحديد صلاحياتها.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('companies.view'))
                    <a class="module" href="{{ route('admin.structure.companies') }}"><span class="module-icon">▥</span><span><h3>الشركات</h3><p>عرض وإدارة الشركات المتاحة ضمن نطاقك.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('branches.view'))
                    <a class="module" href="{{ route('admin.structure.branches') }}"><span class="module-icon">⌖</span><span><h3>الفروع</h3><p>إدارة الفروع التابعة للشركات.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('warehouses.view'))
                    <a class="module" href="{{ route('admin.structure.warehouses') }}"><span class="module-icon">▤</span><span><h3>المخازن</h3><p>عرض وإدارة المخازن ونطاقاتها.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('products.view') && $user->hasPermission('structure.scope.manage_all'))
                    <a class="module" href="{{ route('admin.products.index') }}"><span class="module-icon">▦</span><span><h3>المنتجات</h3><p>كتالوج المنتجات والأكواد والأسعار.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('product_attributes.view') && $user->hasPermission('structure.scope.manage_all'))
                    <a class="module" href="{{ route('admin.product-attributes.index') }}"><span class="module-icon">◇</span><span><h3>صفات المنتجات</h3><p>مجموعات الصفات والقيم المستخدمة في الأكواد.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('product_categories.view') && $user->hasPermission('structure.scope.manage_all'))
                    <a class="module" href="{{ route('admin.catalog.categories') }}"><span class="module-icon">▧</span><span><h3>تصنيفات المنتجات</h3><p>تنظيم التصنيفات الرئيسية والفرعية.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('units.view') && $user->hasPermission('structure.scope.manage_all'))
                    <a class="module" href="{{ route('admin.catalog.units') }}"><span class="module-icon">⚖</span><span><h3>وحدات القياس</h3><p>تعريف وحدات القياس الأساسية.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('parties.view'))
                    <a class="module" href="{{ route('admin.parties.index') }}"><span class="module-icon">♧</span><span><h3>إدارة الأطراف</h3><p>العملاء والموردون والمسوقون والوسطاء والمندوبون وشركات الشحن.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->hasPermission('sales.orders.view'))
                    <a class="module" href="{{ route('admin.sales.orders.index') }}"><span class="module-icon">▤</span><span><h3>أوامر البيع</h3><p>متابعة أوامر البيع المسجلة ضمن نطاق حسابك.</p></span><span class="module-arrow">‹</span></a>
                @endif
                @if ($user->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists())
                    <a class="module" href="{{ route('admin.system.database-updates') }}"><span class="module-icon">⟳</span><span><h3>تحديث قاعدة البيانات</h3><p>مراجعة وتشغيل تحديثات قاعدة البيانات المسموح بها.</p></span><span class="module-arrow">‹</span></a>
                @endif
            </div>

            @if (!$user->hasPermission('users.view') && !$user->hasPermission('permissions.groups.view') && !$user->hasPermission('companies.view') && !$user->hasPermission('branches.view') && !$user->hasPermission('warehouses.view') && !$user->hasPermission('products.view') && !$user->hasPermission('parties.view') && !$user->hasPermission('sales.orders.view'))
                <div class="empty">لا توجد شاشات تشغيل مسندة إلى حسابك حاليًا. تواصل مع مسؤول النظام لإسناد الصلاحيات المناسبة.</div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
