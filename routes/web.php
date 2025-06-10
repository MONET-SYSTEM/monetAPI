<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;

// The default route that shows the welcome page
Route::get('/', function () {
    return view('welcome');
});

// Redirect auth/login to admin/login for AdminLTE compatibility
Route::get('/auth/login', function () {
    return redirect()->route('admin.login');
});

// Set up routes for user authentication (login, registration)
Auth::routes();

// Route for the home page after a user logs in
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Test route (remove this later)
Route::get('/admin/test', function () {
    return 'Admin route is working!';
});

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    Route::get('login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login']);
    Route::post('logout', [App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])->name('admin.logout');
    
    // Test dashboard without middleware temporarily
    Route::get('dashboard-test', function() {
        return 'Dashboard route is working!';
    })->name('admin.dashboard.test');
    
    // Admin Protected Routes
    Route::middleware('admin')->group(function () {
        Route::get('dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/', function() {
            return redirect()->route('admin.dashboard');
        });
        
        // Admin Profile Management
        Route::get('profile', [App\Http\Controllers\Admin\AdminProfileController::class, 'show'])->name('admin.profile');
        Route::put('profile', [App\Http\Controllers\Admin\AdminProfileController::class, 'update'])->name('admin.profile.update');
        Route::put('profile/password', [App\Http\Controllers\Admin\AdminProfileController::class, 'updatePassword'])->name('admin.profile.password');
        
        // System Settings
        Route::get('settings', [App\Http\Controllers\Admin\AdminSettingsController::class, 'index'])->name('admin.settings');
        Route::put('settings', [App\Http\Controllers\Admin\AdminSettingsController::class, 'update'])->name('admin.settings.update');
        Route::put('settings/mail', [App\Http\Controllers\Admin\AdminSettingsController::class, 'updateMail'])->name('admin.settings.mail');
        Route::post('settings/test-email', [App\Http\Controllers\Admin\AdminSettingsController::class, 'testEmail'])->name('admin.settings.test-email');
        Route::post('settings/test-database', [App\Http\Controllers\Admin\AdminSettingsController::class, 'testDatabase'])->name('admin.settings.test-database');
        Route::post('settings/clear-cache', [App\Http\Controllers\Admin\AdminSettingsController::class, 'clearCache'])->name('admin.settings.clear-cache');
        
        // User Management (Admin only)
        Route::resource('users', UserController::class)->names([
            'index'   => 'admin.users.index',
            'create'  => 'admin.users.create',
            'store'   => 'admin.users.store',
            'show'    => 'admin.users.show',
            'edit'    => 'admin.users.edit',
            'update'  => 'admin.users.update',
            'destroy' => 'admin.users.destroy',
        ]);

        // Account Management (Admin only)
        Route::get('accounts/trends', [AccountController::class, 'trends'])->name('admin.accounts.trends');
        Route::resource('accounts', AccountController::class)->names([
            'index'   => 'admin.accounts.index',
            'create'  => 'admin.accounts.create',
            'store'   => 'admin.accounts.store',
            'show'    => 'admin.accounts.show',
            'edit'    => 'admin.accounts.edit',
            'update'  => 'admin.accounts.update',
            'destroy' => 'admin.accounts.destroy',
        ]);

        // Transaction Management (Admin only)
        Route::get('transactions/statistics', [App\Http\Controllers\TransactionController::class, 'statistics'])->name('admin.transactions.statistics');
        Route::get('transactions/exchange-rate', [App\Http\Controllers\TransactionController::class, 'getExchangeRate'])->name('admin.transactions.exchange-rate');
        Route::get('transactions/api-token', [App\Http\Controllers\TransactionController::class, 'generateApiToken'])->name('admin.transactions.api-token');
        Route::post('transactions/transfer', [App\Http\Controllers\TransactionController::class, 'transfer'])->name('admin.transactions.transfer');
        Route::post('transactions/currency-transfer', [App\Http\Controllers\TransactionController::class, 'currencyTransfer'])->name('admin.transactions.currency-transfer');
        
        Route::resource('transactions', App\Http\Controllers\TransactionController::class)->names([
            'index'   => 'admin.transactions.index',
            'create'  => 'admin.transactions.create',
            'store'   => 'admin.transactions.store',
            'show'    => 'admin.transactions.show',
            'edit'    => 'admin.transactions.edit',
            'update'  => 'admin.transactions.update',
            'destroy' => 'admin.transactions.destroy',
        ]);
    });
});

// Profile routes (for regular users)
Route::middleware('auth')->group(function () {
    Route::get('profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/password', [App\Http\Controllers\ProfileController::class, 'editPassword'])->name('profile.password.edit');
    Route::put('profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('profile/avatar', [App\Http\Controllers\ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
});