<?php
use Illuminate\Support\Facades\Route;
use Backpack\Profile\app\Http\Controllers\Api\PointsRateController;

Route::middleware(['api'])
    ->prefix('api')
    ->group(function () {
        Route::get('points/rates', PointsRateController::class);
    });
