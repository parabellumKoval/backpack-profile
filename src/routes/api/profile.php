<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Profile\app\Http\Controllers\Api\ProfileController;
// use Backpack\Profile\app\Http\Controllers\Auth\ResetPasswordController;

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

Route::prefix('api/profile')->middleware([\Backpack\Profile\app\Http\Middleware\ForceJsonResponse::class])->controller(ProfileController::class)->group(function () {
  
  Route::post('/test', 'test');

  Route::get('', 'show')->middleware(['auth:profile']);

  Route::post('/update', 'update')->middleware(['auth:sanctum']);

  Route::get('/referrals', 'referrals')->middleware(['auth:profile']);
});


// Route::post('api/profile/change-password', [ResetPasswordController::class, 'change_password'])
//   ->middleware(['auth:profile']);


