<?php
// routes/backpack/profile_dashboard.php

use Illuminate\Support\Facades\Route;
use Backpack\Profile\app\Http\Controllers\Admin\ProfileDashboardController;

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {
    Route::get('profile-dashboard', [ProfileDashboardController::class, 'index'])->name('bp.profile.dashboard');
});
