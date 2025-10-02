<?php

use Illuminate\Support\Facades\Route;
use Backpack\Profile\app\Http\Controllers\Auth\AuthController;
use Backpack\Profile\app\Http\Controllers\Auth\PasswordResetController;
use Backpack\Profile\app\Http\Controllers\Auth\EmailVerificationController;
use Backpack\Profile\app\Http\Controllers\Auth\OAuthController;

Route::prefix('api/auth')->middleware(['api', \Backpack\Profile\app\Http\Middleware\ForceJsonResponse::class])->group(function () {
    // CSRF cookie для cookie-based SPA (если понадобится)
    // /sanctum/csrf-cookie уже есть в Sanctum

    // Регистрация/логин/логаут
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me',        [AuthController::class, 'me'])->middleware('auth:sanctum');

    // Смена пароля (аутентифицированный пользователь)
    Route::post('password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

    // Восстановление пароля по email
    Route::post('password/forgot', [PasswordResetController::class, 'sendResetLink']);
    Route::post('password/reset',  [PasswordResetController::class, 'reset']);

    // Подтверждение email
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware(['auth:sanctum','throttle:6,1']);
		
		Route::post('email/resend', [EmailVerificationController::class, 'sendForEmail'])
    		->middleware('throttle:6,1');

    // Подпись защищает ссылку; логин не обязателен
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed','throttle:6,1'])->name('verification.verify');

    // Socialite (Google/Facebook)
    Route::get('oauth/{provider}/url', [OAuthController::class, 'getRedirectUrl']); // вернёт URL провайдера
    Route::get('oauth/{provider}/callback', [OAuthController::class, 'callback']);
});