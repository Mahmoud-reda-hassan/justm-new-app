<?php

use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\DatabaseUpdateController;
use App\Http\Controllers\Admin\OperatingStructureController;
use App\Http\Controllers\Admin\ProductCatalogController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductAttributeController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileEmailController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('setup')->name('setup.')->group(function (): void {
    Route::get('/', [SetupController::class, 'show'])->name('show');
    Route::post('/access', [SetupController::class, 'authorizeSetup'])->middleware('throttle:5,1')->name('authorize');
    Route::post('/connection', [SetupController::class, 'testConnection'])->middleware('throttle:10,1')->name('connection');
    Route::post('/database', [SetupController::class, 'installDatabase'])->middleware('throttle:3,1')->name('database');
    Route::post('/operating-data', [SetupController::class, 'saveOperatingData'])->middleware('throttle:3,1')->name('operating-data');
    Route::post('/admin', [SetupController::class, 'createFirstAdmin'])->middleware('throttle:3,1')->name('admin');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/account/email', [ProfileEmailController::class, 'edit'])->name('account.email.edit');
    Route::put('/account/email', [ProfileEmailController::class, 'update'])->middleware('throttle:5,1')->name('account.email.update');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/special-users', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'enter'])->whereIn('workspace', ['admin', 'marketer', 'intermediary', 'delivery'])->name('workspaces.enter');
    Route::get('/admin', [WorkspaceController::class, 'admin'])->name('admin.home');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/admin/system/database-updates', [DatabaseUpdateController::class, 'index'])->name('admin.system.database-updates');
    Route::post('/admin/system/database-updates', [DatabaseUpdateController::class, 'run'])->middleware('throttle:2,1')->name('admin.system.database-updates.run');

    Route::prefix('admin/access')->name('admin.access.')->group(function (): void {
        Route::get('/users', [AccessControlController::class, 'users'])
            ->middleware('permission:users.view')->name('users');
        Route::post('/users', [AccessControlController::class, 'storeUser'])
            ->middleware('permission:users.create')->name('users.store');
        Route::put('/users/{user}', [AccessControlController::class, 'updateUser'])
            ->middleware('permission:users.update')->name('users.update');
        Route::patch('/users/{user}/active', [AccessControlController::class, 'setUserActive'])
            ->middleware('permission:users.activate')->name('users.active');
        Route::put('/users/{user}/password', [AccessControlController::class, 'resetUserPassword'])
            ->middleware('permission:users.reset_password')->name('users.password');
        Route::put('/users/{user}/roles', [AccessControlController::class, 'assignUserRoles'])
            ->middleware('permission:users.assign_groups')->name('users.roles');
        Route::put('/users/{user}/scopes', [AccessControlController::class, 'assignUserScopes'])
            ->middleware('permission:users.assign_scopes')->name('users.scopes');

        Route::get('/roles', [AccessControlController::class, 'roles'])
            ->middleware('permission:permissions.groups.view')->name('roles');
        Route::post('/roles', [AccessControlController::class, 'storeRole'])
            ->middleware('permission:permissions.groups.create')->name('roles.store');
        Route::put('/roles/{role}', [AccessControlController::class, 'updateRole'])
            ->middleware('permission:permissions.groups.update')->name('roles.update');
        Route::put('/roles/{role}/permissions', [AccessControlController::class, 'assignRolePermissions'])
            ->middleware('permission:permissions.groups.assign')->name('roles.permissions');
    });

    Route::prefix('admin/structure')->name('admin.structure.')->group(function (): void {
        Route::get('/companies', [OperatingStructureController::class, 'companies'])->middleware('permission:companies.view')->name('companies');
        Route::post('/companies', [OperatingStructureController::class, 'storeCompany'])->middleware('permission:companies.create')->name('companies.store');
        Route::put('/companies/{company}', [OperatingStructureController::class, 'updateCompany'])->middleware('permission:companies.update')->name('companies.update');
        Route::patch('/companies/{company}/active', [OperatingStructureController::class, 'setCompanyActive'])->middleware('permission:companies.activate')->name('companies.active');

        Route::get('/branches', [OperatingStructureController::class, 'branches'])->middleware('permission:branches.view')->name('branches');
        Route::post('/branches', [OperatingStructureController::class, 'storeBranch'])->middleware('permission:branches.create')->name('branches.store');
        Route::put('/branches/{branch}', [OperatingStructureController::class, 'updateBranch'])->middleware('permission:branches.update')->name('branches.update');
        Route::patch('/branches/{branch}/active', [OperatingStructureController::class, 'setBranchActive'])->middleware('permission:branches.activate')->name('branches.active');

        Route::get('/warehouses', [OperatingStructureController::class, 'warehouses'])->middleware('permission:warehouses.view')->name('warehouses');
        Route::post('/warehouses', [OperatingStructureController::class, 'storeWarehouse'])->middleware('permission:warehouses.create')->name('warehouses.store');
        Route::put('/warehouses/{warehouse}', [OperatingStructureController::class, 'updateWarehouse'])->middleware('permission:warehouses.update')->name('warehouses.update');
        Route::patch('/warehouses/{warehouse}/active', [OperatingStructureController::class, 'setWarehouseActive'])->middleware('permission:warehouses.activate')->name('warehouses.active');
    });

    Route::prefix('admin/products')->name('admin.products.')->group(function (): void {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view')->name('index');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create')->name('store');
        Route::post('/generate-codes', [ProductController::class, 'generateCodes'])->middleware('permission:products.create')->name('generate-codes');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('update');
        Route::patch('/{product}/active', [ProductController::class, 'setActive'])->middleware('permission:products.activate')->name('active');
        Route::patch('/{product}/visible', [ProductController::class, 'setVisible'])->middleware('permission:products.update')->name('visible');
        Route::post('/{product}/variants', [ProductController::class, 'storeVariant'])->middleware('permission:products.create')->name('variants.store');
        Route::put('/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])->middleware('permission:products.update')->name('variants.update');
        Route::patch('/{product}/variants/{variant}/active', [ProductController::class, 'setVariantActive'])->middleware('permission:products.activate')->name('variants.active');
    });

    Route::prefix('admin/product-attributes')->name('admin.product-attributes.')->group(function (): void {
        Route::get('/', [ProductAttributeController::class, 'index'])->middleware('permission:product_attributes.view')->name('index');
        Route::post('/groups', [ProductAttributeController::class, 'storeGroup'])->middleware('permission:product_attributes.create')->name('groups.store');
        Route::put('/groups/{group}', [ProductAttributeController::class, 'updateGroup'])->middleware('permission:product_attributes.update')->name('groups.update');
        Route::patch('/groups/{group}/active', [ProductAttributeController::class, 'setGroupActive'])->middleware('permission:product_attributes.activate')->name('groups.active');
        Route::post('/groups/{group}/values', [ProductAttributeController::class, 'storeValue'])->middleware('permission:product_attributes.create')->name('values.store');
        Route::put('/values/{value}', [ProductAttributeController::class, 'updateValue'])->middleware('permission:product_attributes.update')->name('values.update');
        Route::patch('/values/{value}/active', [ProductAttributeController::class, 'setValueActive'])->middleware('permission:product_attributes.activate')->name('values.active');
    });

    Route::prefix('admin/catalog')->name('admin.catalog.')->group(function (): void {
        Route::get('/categories', [ProductCatalogController::class, 'categories'])->middleware('permission:product_categories.view')->name('categories');
        Route::post('/categories', [ProductCatalogController::class, 'storeCategory'])->middleware('permission:product_categories.create')->name('categories.store');
        Route::put('/categories/{category}', [ProductCatalogController::class, 'updateCategory'])->middleware('permission:product_categories.update')->name('categories.update');
        Route::patch('/categories/{category}/active', [ProductCatalogController::class, 'setCategoryActive'])->middleware('permission:product_categories.activate')->name('categories.active');

        Route::get('/units', [ProductCatalogController::class, 'units'])->middleware('permission:units.view')->name('units');
        Route::post('/units', [ProductCatalogController::class, 'storeUnit'])->middleware('permission:units.create')->name('units.store');
        Route::put('/units/{unit}', [ProductCatalogController::class, 'updateUnit'])->middleware('permission:units.update')->name('units.update');
        Route::patch('/units/{unit}/active', [ProductCatalogController::class, 'setUnitActive'])->middleware('permission:units.activate')->name('units.active');
    });

    Route::prefix('admin/parties')->name('admin.parties.')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Admin\PartyController::class, 'index'])->middleware('permission:parties.view')->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\PartyController::class, 'store'])->middleware('permission:parties.create')->name('store');
        Route::post('/quick-customer', [\App\Http\Controllers\Admin\PartyController::class, 'quickStoreCustomer'])->middleware('permission:parties.create')->name('quick-customer.store');
        Route::put('/{party}/quick-customer', [\App\Http\Controllers\Admin\PartyController::class, 'quickUpdateCustomer'])->middleware('permission:parties.update')->name('quick-customer.update');
        Route::put('/{party}', [\App\Http\Controllers\Admin\PartyController::class, 'update'])->middleware('permission:parties.update')->name('update');
        Route::patch('/{party}/active', [\App\Http\Controllers\Admin\PartyController::class, 'setActive'])->middleware('permission:parties.activate')->name('active');
    });

    Route::prefix('admin/geography')->name('admin.geography.')->group(function (): void {
        Route::get('/{entity}', [GeographicSettingsController::class, 'index'])->whereIn('entity', ['countries', 'governorates', 'cities'])->name('index');
        Route::post('/{entity}', [GeographicSettingsController::class, 'store'])->whereIn('entity', ['countries', 'governorates', 'cities'])->name('store');
        Route::put('/{entity}/{id}', [GeographicSettingsController::class, 'update'])->whereIn('entity', ['countries', 'governorates', 'cities'])->whereNumber('id')->name('update');
        Route::patch('/{entity}/{id}/active', [GeographicSettingsController::class, 'setActive'])->whereIn('entity', ['countries', 'governorates', 'cities'])->whereNumber('id')->name('active');
    });

    Route::prefix('admin/shipping/zones')->name('admin.shipping.zones.')->group(function (): void {
        Route::get('/', [ShippingZoneController::class, 'index'])->middleware('permission:shipping_zones.view')->name('index');
        Route::post('/', [ShippingZoneController::class, 'store'])->middleware('permission:shipping_zones.create')->name('store');
        Route::put('/{zone}', [ShippingZoneController::class, 'update'])->middleware('permission:shipping_zones.update')->name('update');
        Route::patch('/{zone}/active', [ShippingZoneController::class, 'setActive'])->middleware('permission:shipping_zones.activate')->name('active');
    });

    Route::prefix('admin/sales/orders')->name('admin.sales.orders.')->group(function (): void {
        Route::get('/', [SalesOrderController::class, 'index'])->middleware('permission:sales.orders.view')->name('index');
        Route::get('/create', [SalesOrderController::class, 'create'])->middleware('permission:sales.orders.create')->name('create');
        Route::get('/customers/{customer}/history', [SalesOrderController::class, 'customerHistory'])->middleware('permission:sales.orders.create')->name('customer-history');
        Route::post('/', [SalesOrderController::class, 'store'])->middleware('permission:sales.orders.create')->name('store');
    });
});
