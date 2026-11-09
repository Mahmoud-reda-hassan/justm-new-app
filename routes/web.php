<?php

use App\\Http\\Controllers\\Admin\\AccessControlController;
use App\\Http\\Controllers\\Auth\\AuthenticatedSessionController;
use Illuminate\\Support\\Facades\\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

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

        Route::get('/roles', [AccessControlController::class, 'roles'])
            ->middleware('permission:permissions.groups.view')->name('roles');
        Route::post('/roles', [AccessControlController::class, 'storeRole'])
            ->middleware('permission:permissions.groups.create')->name('roles.store');
        Route::put('/roles/{role}', [AccessControlController::class, 'updateRole'])
            ->middleware('permission:permissions.groups.update')->name('roles.update');
        Route::put('/roles/{role}/permissions', [AccessControlController::class, 'assignRolePermissions'])
            ->middleware('permission:permissions.groups.assign')->name('roles.permissions');
    });
});
