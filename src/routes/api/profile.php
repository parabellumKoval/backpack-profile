<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Profile\app\Http\Controllers\Api\ProfileController;
use Backpack\Profile\app\Http\Controllers\Auth\ResetPasswordController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('api/profile')->controller(ProfileController::class)->group(function () {
  
  Route::post('/test', 'test');

  Route::get('', 'show')->middleware(['api', 'auth:profile']);

  Route::post('/update', 'update')->middleware(['api', 'auth:profile']);

  Route::get('/referrals', 'referrals')->middleware(['api', 'auth:profile']);
});


Route::post('api/profile/change-password', [ResetPasswordController::class, 'change_password'])
  ->middleware(['api', 'auth:profile']);


