<?php

use Backpack\Profile\app\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([\Backpack\Profile\app\Http\Middleware\ForceJsonResponse::class])
    ->prefix('api/notifications')
    ->controller(NotificationController::class)
    ->group(function () {
        Route::get('', 'index');
        Route::get('{id}', 'show')->whereNumber('id');

        Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('mark-all/read', 'markAllRead');
        Route::post('{id}/read', 'markRead')->whereNumber('id');
        Route::post('{id}/unread', 'markUnread')->whereNumber('id');
        Route::post('{id}/archive', 'toggleArchive')->whereNumber('id');
    });
    });
