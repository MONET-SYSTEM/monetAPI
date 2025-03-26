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