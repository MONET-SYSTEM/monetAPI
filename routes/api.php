<?php

use App\Http\Controllers\Api\AccountTypeController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\AccountController;

// Routes for Authentication using route::post in POSTMAN API
Route::controller(AuthController::class)->group(function(){
    Route::post('/register', 'register')->name('api.auth.register');
    Route::post('/login', 'login')->name('api.auth.login');
    Route::post('/reset/otp', 'resetOtp')->name('api.auth.reset.otp');
    Route::post('/reset/password', 'resetPassword')->name('api.auth.reset.password'); 

    Route::post('/otp', 'otp')->name('api.auth.otp')->middleware('auth:sanctum');
    Route::post('/verify', 'verify')->name('api.auth.verify')->middleware('auth:sanctum');
});

// Routes for Currency using route::post in POSTMAN API
Route::controller(CurrencyController::class)->group(function(){
    Route::get('/currency', 'index')->name('api.currency.index')->middleware('auth:sanctum');
    Route::get('/currency/{id}', 'get')->name('api.currency.get')->middleware('auth:sanctum');

});

// Routes for Account types using route::post in POSTMAN API
Route::controller(AccountTypeController::class)->group(function() {
    Route::get('/account-type', 'index')->name('api.account.type.index')->middleware('auth:sanctum');
    Route::get('/account-type/{id}', 'get')->name('api.account.type.get')->middleware('auth:sanctum');

});

Route::controller(AccountController::class)->group(function () {
    Route::get('/account', 'index')->name('api.account.index')->middleware('auth:sanctum');
    Route::get('/account/{id}', 'get')->name('api.account.get')->middleware('auth:sanctum');
    Route::post('/account', 'store')->name('api.account.store')->middleware('auth:sanctum');
    Route::patch('/account/{id}', 'update')->name('api.account.update')->middleware('auth:sanctum');
    Route::delete('/account/{id}', 'delete')->name('api.account.delete')->middleware('auth:sanctum');
});




