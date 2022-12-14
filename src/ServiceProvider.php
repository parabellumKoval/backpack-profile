<?php

namespace Backpack\Profile;

use Illuminate\Support\Facades\View;

use Backpack\Profile\app\Observers\ProfileObserver;
use Backpack\Profile\app\Models\Profile;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/profile.php';

    public function boot()
    {
        Profile::observe(ProfileObserver::class);

        $this->publishes([
            self::CONFIG_PATH => config_path('/backpack/profile.php'),
        ], 'config');

        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'shop');
    
	      // Migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        
        // Routes
        $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api/profile.php');

        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views'),
        ]);
        
        // View::composer('*', function ($view) {
        //     $user = \Auth::user();
        //     $transaction = $user? $user->transactions()->where('is_completed', 1)->orderBy('created_at', 'desc')->first(): null;
        //     $balance = $transaction? $transaction->balance: 0;
            
        //     $referrer = Usermeta::where('referral_code', request()->get('ref'))->where('referral_code', '!=', null)->first();
        //     $ref_id = $referrer ? $referrer->id : null;
        //     session()->put('ref_id', $ref_id);

        //     $view->with('user', $user)->with('ref_id', $ref_id)->with('balance', $balance);
        // });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'profile'
        );

        $this->app->bind('profile', function () {
            return new Profile();
        });
    }
}
