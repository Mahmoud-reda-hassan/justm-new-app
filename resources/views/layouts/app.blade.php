<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/justm/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/sweetalert2.min.css') }}">
    <style>
        html,body{direction:rtl;text-align:right;font-family:Tahoma,Arial,sans-serif}
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
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="فتح وإغلاق القائمة"><span aria-hidden="true">☰</span></a></li>
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
                        <a href="{{ route('dashboard') }}" class="nav-link @if(request()->routeIs('dashboard')) active @endif"><i class="nav-icon">⌂</i><p>لوحة التحكم</p></a>
                    </li>
                    @if ($user->hasPermission('sales.orders.view') || $user->hasPermission('parties.view'))
                        <li class="nav-header">العمليات</li>
                        @if ($user->hasPermission('sales.orders.view'))
                            <li class="nav-item"><a href="{{ route('admin.sales.orders.index') }}" class="nav-link"><i class="nav-icon">▤</i><p>أوامر البيع</p></a></li>
                        @endif
                        @if ($user->hasPermission('parties.view'))
                            <li class="nav-item"><a href="{{ route('admin.parties.index') }}" class="nav-link"><i class="nav-icon">♙</i><p>الأطراف</p></a></li>
                        @endif
                    @endif
                    @if (($user->hasPermission('products.view') || $user->hasPermission('product_categories.view') || $user->hasPermission('product_attributes.view') || $user->hasPermission('units.view')) && $user->hasPermission('structure.scope.manage_all'))
                        <li class="nav-header">كتالوج المنتجات</li>
                        @if ($user->hasPermission('products.view'))<li class="nav-item"><a href="{{ route('admin.products.index') }}" class="nav-link"><i class="nav-icon">▦</i><p>المنتجات</p></a></li>@endif
                        @if ($user->hasPermission('product_categories.view'))<li class="nav-item"><a href="{{ route('admin.catalog.categories') }}" class="nav-link"><i class="nav-icon">▧</i><p>التصنيفات</p></a></li>@endif
                        @if ($user->hasPermission('product_attributes.view'))<li class="nav-item"><a href="{{ route('admin.product-attributes.index') }}" class="nav-link"><i class="nav-icon">◇</i><p>صفات المنتجات</p></a></li>@endif
                        @if ($user->hasPermission('units.view'))<li class="nav-item"><a href="{{ route('admin.catalog.units') }}" class="nav-link"><i class="nav-icon">⚖</i><p>وحدات القياس</p></a></li>@endif
                    @endif
                    @if ($user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('companies.view') || $user->hasPermission('branches.view') || $user->hasPermission('warehouses.view'))
                        <li class="nav-header">الإدارة والإعداد</li>
                        @if ($user->hasPermission('users.view'))<li class="nav-item"><a href="{{ route('admin.access.users') }}" class="nav-link"><i class="nav-icon">♧</i><p>المستخدمون</p></a></li>@endif
                        @if ($user->hasPermission('permissions.groups.view'))<li class="nav-item"><a href="{{ route('admin.access.roles') }}" class="nav-link"><i class="nav-icon">⚿</i><p>مجموعات الصلاحيات</p></a></li>@endif
                        @if ($user->hasPermission('companies.view'))<li class="nav-item"><a href="{{ route('admin.structure.companies') }}" class="nav-link"><i class="nav-icon">▥</i><p>الشركات</p></a></li>@endif
                        @if ($user->hasPermission('branches.view'))<li class="nav-item"><a href="{{ route('admin.structure.branches') }}" class="nav-link"><i class="nav-icon">⌖</i><p>الفروع</p></a></li>@endif
                        @if ($user->hasPermission('warehouses.view'))<li class="nav-item"><a href="{{ route('admin.structure.warehouses') }}" class="nav-link"><i class="nav-icon">▤</i><p>المخازن</p></a></li>@endif
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
