<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;

// The default route that shows the welcome page
Route::get('/', function () {
    return view('welcome');
});

// Set up routes for user authentication (login, registration)
Auth::routes();

// Route for the home page after a user logs in
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Define resourceful routes for user management under the admin panel
// These routes are protected so only authenticated users can access them
Route::resource('admin/users', UserController::class)->middleware('auth')->names([

        'index'   => 'admin.users.index',   // List all users
        'create'  => 'admin.users.create',  // Show form to create a new user
        'store'   => 'admin.users.store',   // Save a new user
        'show'    => 'admin.users.show',    // Display a specific user's details
        'edit'    => 'admin.users.edit',    // Show form to edit a user
        'update'  => 'admin.users.update',  // Update an existing user
        'destroy' => 'admin.users.destroy', // Delete a user
]);

Route::get('admin/accounts/trends', [AccountController::class, 'trends'])->middleware('auth')->name('admin.accounts.trends');

// Define resourceful routes for account management under the admin panel
// These routes are protected so only authenticated users can access them
Route::resource('admin/accounts', AccountController::class)->middleware('auth')->names([

        'index'   => 'admin.accounts.index',    // List all accounts
        'create'  => 'admin.accounts.create',   // Show form to create a new account
        'store'   => 'admin.accounts.store',    // Save a new account
        'show'    => 'admin.accounts.show',     // Display a specific account's details
        'edit'    => 'admin.accounts.edit',     // Show form to edit an account
        'update'  => 'admin.accounts.update',   // Update an existing account
        'destroy' => 'admin.accounts.destroy',  // Delete an account
]);

// Transactions routes
Route::get('admin/transactions/statistics', [App\Http\Controllers\TransactionController::class, 'statistics'])
    ->middleware('auth')
    ->name('admin.transactions.statistics');
    
Route::get('admin/transactions/exchange-rate', [App\Http\Controllers\TransactionController::class, 'getExchangeRate'])
    ->middleware('auth')
    ->name('admin.transactions.exchange-rate');

Route::get('admin/transactions/api-token', [App\Http\Controllers\TransactionController::class, 'generateApiToken'])
    ->middleware('auth')
    ->name('admin.transactions.api-token');
    
Route::post('admin/transactions/transfer', [App\Http\Controllers\TransactionController::class, 'transfer'])
    ->middleware('auth')
    ->name('admin.transactions.transfer');
    
Route::post('admin/transactions/currency-transfer', [App\Http\Controllers\TransactionController::class, 'currencyTransfer'])
    ->middleware('auth')
    ->name('admin.transactions.currency-transfer');

Route::resource('admin/transactions', App\Http\Controllers\TransactionController::class)
    ->middleware('auth')
    ->names([
        'index'   => 'admin.transactions.index',    // List all transactions
        'create'  => 'admin.transactions.create',   // Show form to create a new transaction
        'store'   => 'admin.transactions.store',    // Save a new transaction
        'show'    => 'admin.transactions.show',     // Display a specific transaction's details
        'edit'    => 'admin.transactions.edit',     // Show form to edit a transaction
        'update'  => 'admin.transactions.update',   // Update an existing transaction
        'destroy' => 'admin.transactions.destroy',  // Delete a transaction
    ]);

// Transfer route
Route::post('admin/transactions/transfer', [App\Http\Controllers\TransactionController::class, 'transfer'])
    ->middleware('auth')
    ->name('admin.transactions.transfer');

// API Monitoring routes
Route::get('admin/api/monitor', [App\Http\Controllers\ApiMonitorController::class, 'index'])
    ->middleware('auth')
    ->name('admin.api.monitor');

Route::get('admin/api/logs/{requestId}', [App\Http\Controllers\ApiMonitorController::class, 'showLog'])
    ->middleware('auth')
    ->name('admin.api.logs.show');

Route::get('admin/api/export', [App\Http\Controllers\ApiMonitorController::class, 'exportLogs'])
    ->middleware('auth')
    ->name('admin.api.export');

// Profile routes
Route::get('profile', [App\Http\Controllers\ProfileController::class, 'show'])
    ->middleware('auth')
    ->name('profile.show');

Route::get('profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])
    ->middleware('auth')
    ->name('profile.edit');

Route::put('profile', [App\Http\Controllers\ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

Route::get('profile/password', [App\Http\Controllers\ProfileController::class, 'editPassword'])
    ->middleware('auth')
    ->name('profile.password.edit');

Route::put('profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])
    ->middleware('auth')
    ->name('profile.password.update');

Route::delete('profile/avatar', [App\Http\Controllers\ProfileController::class, 'deleteAvatar'])
    ->middleware('auth')
    ->name('profile.avatar.delete');