<?php

// use Illuminate\Support\Facades\Route;

// use Backpack\Profile\app\Http\Controllers\Auth\RegisterController;
// use Backpack\Profile\app\Http\Controllers\Auth\LoginController;
// use Backpack\Profile\app\Http\Controllers\Auth\ResetPasswordController;

// Route::post('/register', RegisterController::class)->middleware('web')->name('register');
// Route::post('/login', LoginController::class)->middleware('web')->name('login');
// Route::post('/logout', [LoginController::class, 'logout'])->middleware('web')->name('logout');

// Route::post('/forgot-password', [ResetPasswordController::class, 'forgotPassword']);
// Route::post('/change-password', [ResetPasswordController::class, 'changePassword']);

// Route::get('/password/reset/{token}', [ResetPasswordController::class, 'resetPassword'])->name('password.reset');

// // OAUTH
// Route::prefix('auth')->group(function() {
// 	Route::middleware('web')->any('/{provider}', 'App\Http\Controllers\Auth\OAuthController@redirect')->where('provider', 'google|facebook');
// 	Route::middleware('web')->get('/{provider}/callback', 'App\Http\Controllers\Auth\OAuthController@callback')->where('provider', 'google|facebook');
// 	Route::middleware('web')->get('/loginByToken', 'App\Http\Controllers\Auth\OAuthController@loginByToken');
// });