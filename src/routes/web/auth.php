<?php

use Illuminate\Support\Facades\Route;

use Backpack\Profile\app\Http\Controllers\Auth\RegisterController;
use Backpack\Profile\app\Http\Controllers\Auth\LoginController;

Route::post('/register', RegisterController::class)->middleware('web')->name('register');
Route::post('/login', LoginController::class)->middleware('web')->name('login');
Route::post('/logout', LoginController::class)->middleware('web')->name('logout');