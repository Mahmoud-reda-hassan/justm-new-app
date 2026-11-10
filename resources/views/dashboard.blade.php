<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ config('app.name') }}</title></head>
<body>
    <h1>لوحة {{ config('app.name') }}</h1>
    <p>مرحبًا {{ auth()->user()->name }}</p>
    @if (!auth()->user()->email)
        <p>لا يوجد بريد إلكتروني لاستعادة كلمة المرور. <a href="{{ route('account.email.edit') }}">إضافة بريد إلكتروني وتأكيده</a></p>
    @elseif (!auth()->user()->hasVerifiedEmail())
        <p>البريد الإلكتروني غير مؤكد. <a href="{{ route('verification.notice') }}">تأكيد البريد لاستعادة كلمة المرور</a></p>
    @endif
    <nav>
        @if (auth()->user()->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists())
            <a href="{{ route('admin.system.database-updates') }}">تحديث قاعدة البيانات</a>
        @endif
        @if (auth()->user()->hasPermission('users.view'))
            <a href="{{ route('admin.access.users') }}">إدارة المستخدمين</a>
        @endif
        @if (auth()->user()->hasPermission('permissions.groups.view'))
            <a href="{{ route('admin.access.roles') }}">مجموعات الصلاحيات</a>
        @endif
        @if (auth()->user()->hasPermission('companies.view'))
            <a href="{{ route('admin.structure.companies') }}">الشركات</a>
        @endif
        @if (auth()->user()->hasPermission('branches.view'))
            <a href="{{ route('admin.structure.branches') }}">الفروع</a>
        @endif
        @if (auth()->user()->hasPermission('warehouses.view'))
            <a href="{{ route('admin.structure.warehouses') }}">المخازن</a>
        @endif
        @if (auth()->user()->hasPermission('products.view') && auth()->user()->hasPermission('structure.scope.manage_all'))
            <a href="{{ route('admin.products.index') }}">المنتجات</a>
        @endif
        @if (auth()->user()->hasPermission('product_attributes.view') && auth()->user()->hasPermission('structure.scope.manage_all'))
            <a href="{{ route('admin.product-attributes.index') }}">صفات المنتجات</a>
        @endif
        @if (auth()->user()->hasPermission('product_categories.view') && auth()->user()->hasPermission('structure.scope.manage_all'))
            <a href="{{ route('admin.catalog.categories') }}">تصنيفات المنتجات</a>
        @endif
        @if (auth()->user()->hasPermission('units.view') && auth()->user()->hasPermission('structure.scope.manage_all'))
            <a href="{{ route('admin.catalog.units') }}">وحدات القياس</a>
        @endif
        @if (auth()->user()->hasPermission('parties.view'))
            <a href="{{ route('admin.parties.index') }}">إدارة الأطراف</a>
        @endif
        @if (auth()->user()->hasPermission('sales.orders.view'))
            <a href="{{ route('admin.sales.orders.index') }}">أوامر البيع</a>
        @endif
    </nav>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج</button></form>
</body>
</html>
