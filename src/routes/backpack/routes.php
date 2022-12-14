<?php

Route::group([
    'namespace'  => 'Backpack\Profile\app\Http\Controllers\Admin',
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
], function () { 
    Route::crud('profile', 'ProfileCrudController');
}); 

