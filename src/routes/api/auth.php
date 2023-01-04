<?php

use Illuminate\Support\Facades\Route;

use Backpack\Profile\app\Http\Controllers\Auth\RegisterController;
use Backpack\Profile\app\Http\Controllers\Auth\LoginController;

Route::post('api/register', RegisterController::class)->name('register');
Route::post('api/login', LoginController::class)->name('login');
Route::post('api/logout', LoginController::class)->name('logout');