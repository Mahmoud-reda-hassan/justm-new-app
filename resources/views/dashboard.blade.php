@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@section('breadcrumb', 'لوحة التحكم')

@section('content')
@php
    $user = auth()->user();
    $hasOperations = $user->hasPermission('sales.orders.view') || $user->hasPermission('parties.view');
    $hasCatalog = ($user->hasPermission('products.view') || $user->hasPermission('product_categories.view') || $user->hasPermission('product_attributes.view') || $user->hasPermission('units.view')) && $user->hasPermission('structure.scope.manage_all');
    $hasAdministration = $user->hasPermission('users.view') || $user->hasPermission('permissions.groups.view') || $user->hasPermission('companies.view') || $user->hasPermission('branches.view') || $user->hasPermission('warehouses.view');
@endphp

@if (!$user->email)
    <div class="alert alert-warning"><i class="mr-1">!</i> لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}" class="alert-link">إضافة بريد إلكتروني وتأكيده</a></div>
@elseif (!$user->hasVerifiedEmail())
    <div class="alert alert-info"><i class="mr-1">i</i> البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}" class="alert-link">تأكيد البريد الإلكتروني</a></div>
@endif

<div class="row">
    <div class="col-12 mb-3">
        <div class="card card-outline card-primary">
            <div class="card-body">
                <h5 class="mb-1">مرحبًا {{ $user->name }}</h5>
                <p class="text-muted mb-0">اختر الوحدة التي تريد العمل عليها من القائمة أو الاختصارات التالية.</p>
            </div>
        </div>
    </div>
</div>

@if ($hasOperations)
    <div class="row">
        <div class="col-12"><div class="card-header border-0 px-0"><h3 class="card-title font-weight-bold">العمليات</h3></div></div>
        @if ($user->hasPermission('sales.orders.view'))
            <div class="col-xl-4 col-md-6 mb-3">
                <a href="{{ route('admin.sales.orders.index') }}" class="text-dark">
                    <div class="card module-card mb-0"><div class="card-body">
                        <span class="module-icon"><i class="fas fa-shopping-cart"></i></span><div><h3>أوامر البيع</h3><p>عرض ومتابعة أوامر البيع المتاحة ضمن نطاق حسابك.</p></div>
                    </div></div>
                </a>
            </div>
        @endif
        @if ($user->hasPermission('parties.view'))
            <div class="col-xl-4 col-md-6 mb-3">
                <a href="{{ route('admin.parties.index') }}" class="text-dark">
                    <div class="card module-card mb-0"><div class="card-body">
                        <span class="module-icon"><i class="fas fa-users"></i></span><div><h3>إدارة الأطراف</h3><p>العملاء والموردون والمسوقون والوسطاء والمندوبون وشركات الشحن.</p></div>
                    </div></div>
                </a>
            </div>
        @endif
    </div>
@endif

@if ($hasCatalog)
    <div class="row">
        <div class="col-12"><div class="card-header border-0 px-0"><h3 class="card-title font-weight-bold">كتالوج المنتجات</h3></div></div>
        @if ($user->hasPermission('products.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.products.index') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-boxes"></i></span><div><h3>المنتجات</h3><p>بيانات المنتجات والأكواد والأسعار.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('product_categories.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.catalog.categories') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-sitemap"></i></span><div><h3>تصنيفات المنتجات</h3><p>تنظيم التصنيفات الرئيسية والفرعية.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('product_attributes.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.product-attributes.index') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-sliders-h"></i></span><div><h3>صفات المنتجات</h3><p>مجموعات الصفات والقيم المستخدمة في تركيبات المنتجات.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('units.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.catalog.units') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-balance-scale"></i></span><div><h3>وحدات القياس</h3><p>تعريف وحدات القياس الأساسية.</p></div></div></div></a></div>
        @endif
    </div>
@endif

@if ($hasAdministration)
    <div class="row">
        <div class="col-12"><div class="card-header border-0 px-0"><h3 class="card-title font-weight-bold">الإدارة والإعداد</h3></div></div>
        @if ($user->hasPermission('users.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.access.users') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-user"></i></span><div><h3>إدارة المستخدمين</h3><p>حسابات الدخول وربطها بالأطراف وحالة المستخدم.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('permissions.groups.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.access.roles') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-user-shield"></i></span><div><h3>مجموعات الصلاحيات</h3><p>تحديد صلاحيات التشغيل لكل مجموعة.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('companies.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.structure.companies') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-building"></i></span><div><h3>الشركات</h3><p>إدارة الشركات المسجلة في النظام.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('branches.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.structure.branches') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-code-branch"></i></span><div><h3>الفروع</h3><p>إدارة الفروع التابعة للشركات.</p></div></div></div></a></div>
        @endif
        @if ($user->hasPermission('warehouses.view'))
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.structure.warehouses') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-shopping-cart"></i></span><div><h3>المخازن</h3><p>إدارة المخازن ونطاقاتها.</p></div></div></div></a></div>
        @endif
        @if ($user->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists())
            <div class="col-xl-4 col-md-6 mb-3"><a href="{{ route('admin.system.database-updates') }}" class="text-dark"><div class="card module-card mb-0"><div class="card-body"><span class="module-icon"><i class="fas fa-sync-alt"></i></span><div><h3>تحديث قاعدة البيانات</h3><p>مراجعة تحديثات قاعدة البيانات المسموح بها.</p></div></div></div></a></div>
        @endif
    </div>
@endif

@if (!$hasOperations && !$hasCatalog && !$hasAdministration)
    <div class="card"><div class="card-body text-center text-muted py-5">لا توجد وحدات تشغيل مسندة إلى حسابك حاليًا. تواصل مع مسؤول النظام لإسناد الصلاحيات المناسبة.</div></div>
@endif
@endsection
