<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routes for AuthController using route::post in POSTMAN API
Route::controller(AuthController::class)->group(function(){
    Route::post('/register', 'register')->name('api.auth.register');
    Route::post('/login', 'login')->name('api.auth.login');
    Route::post('/reset/otp', 'resetOtp')->name('api.auth.reset.otp');
    Route::post('/reset/password', 'resetPassword')->name('api.auth.reset.password'); 

    Route::post('/otp', 'otp')->name('api.auth.otp')->middleware('auth:sanctum');
    Route::post('/verify', 'verify')->name('api.auth.verify')->middleware('auth:sanctum');
});
