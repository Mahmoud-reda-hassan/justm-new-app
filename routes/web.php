<?php

use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileEmailController;
use App\Http\Controllers\SetupController;
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
