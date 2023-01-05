<?php

use Illuminate\Support\Facades\Route;

use Backpack\Profile\app\Http\Controllers\Auth\RegisterController;
use Backpack\Profile\app\Http\Controllers\Auth\LoginController;

Route::post('/register', RegisterController::class)->name('register');
Route::post('/login', LoginController::class)->name('login');
Route::post('/logout', LoginController::class)->name('logout');