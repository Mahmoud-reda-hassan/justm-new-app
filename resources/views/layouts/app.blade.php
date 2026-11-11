<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/justm/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/bootstrap-rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/sweetalert2.min.css') }}">
    <style>
        html,body{direction:rtl;text-align:right;font-family:Tahoma,Arial,sans-serif}
        body{font-size:14px;color:#343a40;background:#f4f6f9}
        .content-wrapper{background:#f4f6f9}
        .content-header{padding:15px .5rem}
        .content-header h1{font-size:1.45rem;font-weight:600;margin:0}
        .content{padding:0 .5rem 1rem}
        .content .shell,.content main,.content .wrap{max-width:none;width:100%;margin:0;padding:0}
        .content section:not(.card),.content .panel,.content .group-card{background:#fff;border:1px solid #dee2e6;border-radius:.25rem;padding:1rem;margin:0 0 1rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .content .head,.content .topbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:0 0 1rem;padding:0 0 .75rem;border-bottom:1px solid #dee2e6}
        .content h1{font-size:1.35rem;font-weight:600;margin:0 0 .5rem}
        .content h2{font-size:1.05rem;font-weight:600;margin:0 0 .85rem}
        .content h3{font-size:1rem;font-weight:600}
        .content p{margin-bottom:.75rem}
        .content table:not(.table){width:100%;background:#fff;border-collapse:collapse}
        .content table:not(.table) th,.content table:not(.table) td{padding:.65rem .75rem;border-top:1px solid #dee2e6;vertical-align:middle}
        .content table:not(.table) thead th{background:#f8f9fa;border-bottom:2px solid #dee2e6;font-weight:600;white-space:nowrap}
        .content form:not(.form-inline){margin-bottom:.75rem}
        .content input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not(.form-control),.content select:not(.form-control):not(.custom-select),.content textarea:not(.form-control){display:inline-block;max-width:100%;min-height:38px;padding:.375rem .75rem;border:1px solid #ced4da;border-radius:.25rem;background:#fff;color:#495057;vertical-align:middle}
        .content textarea{min-height:80px}
        .content label{font-weight:600;margin:0 .25rem .4rem 0}
        .content button:not([class]),.content input[type=submit]:not([class]){display:inline-block;padding:.375rem .75rem;border:1px solid #6c757d;border-radius:.25rem;background:#6c757d;color:#fff;font-weight:400;line-height:1.5;cursor:pointer}
        .content .btn.primary,.content button.primary{border-color:#007bff;background:#007bff;color:#fff}
        .content .btn.soft,.content button.soft{border-color:#6c757d;background:#6c757d;color:#fff}
        .content .btn.danger,.content button.danger{border-color:#dc3545;background:#dc3545;color:#fff}
        .content button:not([class]):hover,.content input[type=submit]:not([class]):hover{background:#5a6268;color:#fff}
        .content .btn.primary:hover,.content button.primary:hover{background:#0069d9;color:#fff}
        .content .btn.soft:hover,.content button.soft:hover{background:#5a6268;color:#fff}
        .content .btn.danger:hover,.content button.danger:hover{background:#c82333;color:#fff}
        .content button:disabled,.content .btn:disabled{opacity:.65;cursor:not-allowed}
        .content .alert{position:relative;padding:.75rem 1rem;margin:0 0 1rem;border:1px solid transparent;border-radius:.25rem}
        .content .alert-success,.content .success{background:#d4edda;color:#155724;border-color:#c3e6cb}
        .content .alert-danger,.content .errors,.content .error{background:#f8d7da;color:#721c24;border-color:#f5c6cb}
        .content .status{background:#e9ecef;color:#495057;border:1px solid #dee2e6}
        .content .status-on{background:#d4edda;color:#155724;border-color:#c3e6cb}
        .content .status-off{background:#f8d7da;color:#721c24;border-color:#f5c6cb}
        .content .muted,.content .sub,.content .eyebrow{color:#6c757d}
        .content .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin:0 0 1rem}
        .content .stat{background:#fff;border:1px solid #dee2e6;border-right:3px solid #007bff;border-radius:.25rem;padding:.85rem}
        .content .fields{display:flex;flex-wrap:wrap;gap:.75rem;align-items:end}
        .content .field{display:flex;flex-direction:column;gap:.25rem;min-width:180px;flex:1}
        .content .inline{display:inline-flex;align-items:center;gap:.35rem}
        .content .nav,.content .top-links,.content .top-actions,.content .actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
        .content a{color:#007bff}
        .content .status{display:inline-block;padding:.2rem .5rem;border-radius:.2rem}
        .content .status.on{background:#d4edda;color:#155724}
        .content .status.off{background:#f8d7da;color:#721c24}
        .content .card{box-shadow:0 1px 2px rgba(0,0,0,.05)}
        @media(max-width:767.98px){.content .head,.content .topbar{align-items:flex-start;flex-direction:column}.content table{display:block;overflow-x:auto;white-space:nowrap}.content .fields{display:block}.content .field{margin-bottom:.65rem}.content input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not(.form-control),.content select:not(.form-control):not(.custom-select),.content textarea:not(.form-control){width:100%}}
        body{background:#f4f6f9}
        .main-sidebar{right:0;left:auto}
        .content-wrapper,.main-footer,.main-header{margin-left:0!important;margin-right:250px}
        .main-header .navbar-nav{padding-right:0}
        .main-header .navbar-nav.ml-auto{margin-right:auto!important;margin-left:0!important}
        .navbar-nav .nav-link{direction:rtl}
        .brand-link{text-align:right}
        .user-panel .image{padding-left:.8rem;padding-right:0}
        .user-panel .info{padding:5px 0 5px 5px}
        .nav-sidebar .nav-link{text-align:right}
        .nav-sidebar .nav-icon{margin-left:.2rem;margin-right:0}
        .nav-sidebar .right{left:1rem;right:auto}
        .content-header h1{float:right}
        .breadcrumb{float:left;padding-left:0;padding-right:0}
        .card-title{float:right}
        .card-tools{float:left}
        .dropdown-menu{right:0;left:auto;text-align:right}
        .form-control,.custom-select{direction:rtl;text-align:right}
        .select2-container{width:100%!important;direction:rtl;text-align:right}
        .select2-container--default .select2-selection--single .select2-selection__rendered{padding-right:12px;padding-left:20px;text-align:right}
        .select2-container--default .select2-selection--single .select2-selection__arrow{right:auto;left:1px}
        .content-wrapper{min-height:calc(100vh - 114px)}
        .main-footer{text-align:right}
        .module-card{height:100%;transition:transform .15s ease,box-shadow .15s ease}
        .module-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.10)}
        .module-card .card-body{display:flex;align-items:flex-start;gap:14px}
        .module-icon{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:8px;background:#e8f1fb;color:#2878c7;font-size:20px}
        .module-card h3{font-size:15px;font-weight:600;margin:2px 0 7px}
        .module-card p{font-size:12px;color:#6c757d;line-height:1.8;margin:0}
        .nav-header{font-size:11px!important}
        @media(max-width:991.98px){
            .main-sidebar{margin-right:-250px!important;margin-left:0!important;right:0;left:auto}
            .sidebar-open .main-sidebar{margin-right:0!important}
            .content-wrapper,.main-footer,.main-header{margin-right:0!important}
        }
        @media(min-width:992px){
            .sidebar-collapse .main-sidebar{margin-right:-250px!important;margin-left:0!important}
            .sidebar-collapse .content-wrapper,.sidebar-collapse .main-footer,.sidebar-collapse .main-header{margin-right:0!important}
        }
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm">
@php($user = auth()->user())
@php($canManageDatabase = $user->is_active && $user->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists())
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="فتح وإغلاق القائمة"><i class="fas fa-bars" aria-hidden="true"></i></a></li>
            <li class="nav-item d-none d-sm-inline-block"><span class="nav-link font-weight-bold">{{ config('app.name') }}</span></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#"><span>{{ $user->name }}</span> <span class="ml-1">▾</span></a>
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-left">
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="dropdown-item text-right">تسجيل الخروج</button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text font-weight-light mr-2">{{ config('app.name') }}</span>
        </a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image"><span class="img-circle elevation-2 d-inline-flex align-items-center justify-content-center bg-light text-primary" style="width:34px;height:34px;font-weight:bold">{{ mb_substr($user->name,0,1) }}</span></div>
                <div class="info"><span class="d-block text-white">{{ $user->name }}</span></div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link @if(request()->routeIs('dashboard')) active @endif"><i class="nav-icon fas fa-tachometer-alt"></i><p>لوحة التحكم</p></a>
                    </li>
                    @if ($user->hasPermission('sales.orders.view') || $user->hasPermission('parties.view'))
                        <li class="nav-header">العمليات</li>
                        @if ($user->hasPermission('sales.orders.create'))
                            <li class="nav-item"><a href="{{ route('admin.sales.orders.create') }}" class="nav-link @if(request()->routeIs('admin.sales.orders.create')) active @endif"><i class="nav-icon fas fa-plus-circle"></i><p>إنشاء أمر البيع</p></a></li>
                        @endif
                        @if ($user->hasPermission('sales.orders.view'))
                            <li class="nav-item"><a href="{{ route('admin.sales.orders.index') }}" class="nav-link @if(request()->routeIs('admin.sales.orders.index')) active @endif"><i class="nav-icon fas fa-list-alt"></i><p>قائمة أوامر البيع</p></a></li>
                        @endif
                        @if ($user->hasPermission('parties.view'))
                            <li class="nav-item"><a href="{{ route('admin.parties.index') }}" class="nav-link"><i class="nav-icon fas fa-users"></i><p>الأطراف</p></a></li>
                        @endif
                    @endif
                    @if (($user->hasPermission('products.view') || $user->hasPermission('product_categories.view') || $user->hasPermission('product_attributes.view') || $user->hasPermission('units.view')) && $user->hasPermission('structure.scope.manage_all'))
                        <li class="nav-header">كتالوج المنتجات</li>
                        @if ($user->hasPermission('products.view'))<li class="nav-item"><a href="{{ route('admin.products.index') }}" class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>المنتجات</p></a></li>@endif
                        @if ($user->hasPermission('product_categories.view'))<li class="nav-item"><a href="{{ route('admin.catalog.categories') }}" class="nav-link"><i class="nav-icon fas fa-sitemap"></i><p>التصنيفات</p></a></li>@endif
                        @if ($user->hasPermission('product_attributes.view'))<li class="nav-item"><a href="{{ route('admin.product-attributes.index') }}" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>صفات المنتجات</p></a></li>@endif
                        @if ($user->hasPermission('units.view'))<li class="nav-item"><a href="{{ route('admin.catalog.units') }}" class="nav-link"><i class="nav-icon fas fa-balance-scale"></i><p>وحدات القياس</p></a></li>@endif
                    @endif
                    @if ($canManageDatabase || $user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('companies.view') || $user->hasPermission('branches.view') || $user->hasPermission('warehouses.view') || $user->hasPermission('shipping_zones.view') || $user->hasPermission('geography.countries.view') || $user->hasPermission('geography.governorates.view') || $user->hasPermission('geography.cities.view'))
                        <li class="nav-header">الإدارة والإعداد</li>
                        @if ($user->hasPermission('users.view'))<li class="nav-item"><a href="{{ route('admin.access.users') }}" class="nav-link"><i class="nav-icon fas fa-user"></i><p>المستخدمون</p></a></li>@endif
                        @if ($user->hasPermission('permissions.groups.view'))<li class="nav-item"><a href="{{ route('admin.access.roles') }}" class="nav-link"><i class="nav-icon fas fa-user-shield"></i><p>مجموعات الصلاحيات</p></a></li>@endif
                        @if ($user->hasPermission('companies.view'))<li class="nav-item"><a href="{{ route('admin.structure.companies') }}" class="nav-link"><i class="nav-icon fas fa-building"></i><p>الشركات</p></a></li>@endif
                        @if ($user->hasPermission('branches.view'))<li class="nav-item"><a href="{{ route('admin.structure.branches') }}" class="nav-link"><i class="nav-icon fas fa-code-branch"></i><p>الفروع</p></a></li>@endif
                        @if ($user->hasPermission('warehouses.view'))<li class="nav-item"><a href="{{ route('admin.structure.warehouses') }}" class="nav-link"><i class="nav-icon fas fa-list-alt"></i><p>المخازن</p></a></li>@endif
                        @if ($user->hasPermission('shipping_zones.view'))<li class="nav-item"><a href="{{ route('admin.shipping.zones.index') }}" class="nav-link"><i class="nav-icon fas fa-truck"></i><p>مناطق الشحن</p></a></li>@endif
                        @if ($user->hasPermission('geography.countries.view'))<li class="nav-item"><a href="{{ route('admin.geography.index', 'countries') }}" class="nav-link @if(request()->routeIs('admin.geography.*') && request()->route('entity') === 'countries') active @endif"><i class="nav-icon fas fa-globe"></i><p>الدول</p></a></li>@endif
                        @if ($user->hasPermission('geography.governorates.view'))<li class="nav-item"><a href="{{ route('admin.geography.index', 'governorates') }}" class="nav-link @if(request()->routeIs('admin.geography.*') && request()->route('entity') === 'governorates') active @endif"><i class="nav-icon fas fa-map"></i><p>المحافظات والمقاطعات</p></a></li>@endif
                        @if ($user->hasPermission('geography.cities.view'))<li class="nav-item"><a href="{{ route('admin.geography.index', 'cities') }}" class="nav-link @if(request()->routeIs('admin.geography.*') && request()->route('entity') === 'cities') active @endif"><i class="nav-icon fas fa-city"></i><p>المدن</p></a></li>@endif
                        @if ($canManageDatabase)<li class="nav-item"><a href="{{ route('admin.system.database-updates') }}" class="nav-link @if(request()->routeIs('admin.system.database-updates*')) active @endif"><i class="nav-icon fas fa-database"></i><p>تحديث قاعدة البيانات</p></a></li>@endif
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1>@yield('page-title','لوحة التحكم')</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li><li class="breadcrumb-item active">@yield('breadcrumb','لوحة التحكم')</li></ol></div>
                </div>
            </div>
        </section>
        <section class="content"><div class="container-fluid">@yield('content')</div></section>
    </div>
    <footer class="main-footer"><strong>{{ config('app.name') }}</strong><span class="float-left">نظام إدارة الأعمال</span></footer>
</div>
<script src="{{ asset('vendor/justm/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/justm/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/justm/adminlte.min.js') }}"></script>
<script src="{{ asset('vendor/justm/select2.min.js') }}"></script>
<script src="{{ asset('vendor/justm/select2-ar.js') }}"></script>
<script src="{{ asset('vendor/justm/sweetalert2.min.js') }}"></script>
@stack('scripts')
</body>
</html>
