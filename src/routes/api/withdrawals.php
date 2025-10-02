<?php
use Illuminate\Support\Facades\Route;
use Backpack\Profile\app\Http\Controllers\Api\WithdrawalController;

Route::middleware(\Settings::get('profile.private_middlewares', ['api', 'auth.api:sanctum']))
    ->prefix('api/wallet')
    ->group(function () {
        Route::get('withdrawals',        [WithdrawalController::class, 'index']);
        Route::post('withdrawals',       [WithdrawalController::class, 'store']);
        Route::post('withdrawals/{id}/cancel', [WithdrawalController::class, 'cancel']);
    });