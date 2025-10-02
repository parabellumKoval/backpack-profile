<?php
use Backpack\Profile\app\Http\Controllers\Admin\RewardEventCrudController;
use Backpack\Profile\app\Http\Controllers\Admin\RewardCrudController;
use Backpack\Profile\app\Http\Controllers\Admin\WalletLedgerCrudController;

Route::group([
    'namespace'  => 'Backpack\Profile\app\Http\Controllers\Admin',
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
], function () { 
    Route::crud('profile', 'ProfileCrudController');
    Route::crud('referrals', 'ReferralsCrudController');
    Route::crud('withdrawals', 'WithdrawalRequestCrudController');


    Route::crud('reward-events', 'RewardEventCrudController');
    Route::crud('rewards',       'RewardCrudController');
    Route::crud('wallet-ledger', 'WalletLedgerCrudController');

    //
    Route::post('reward-events/{id}/process', 'RewardEventCrudController@process');
    Route::post('reward-events/{id}/reverse', 'RewardEventCrudController@reverse');

    // CRUD VIEW
    Route::get('referrals/settings', 'SettingsCrudController@index');
    Route::post('referrals/settings', 'SettingsCrudController@store');

    // 
    Route::post('withdrawals/{id}/approve', 'WithdrawalRequestCrudController@approve');
    Route::post('withdrawals/{id}/reject', 'WithdrawalRequestCrudController@reject');
    Route::post('withdrawals/{id}/paid', 'WithdrawalRequestCrudController@paid');
});

