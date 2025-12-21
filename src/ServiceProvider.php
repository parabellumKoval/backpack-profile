<?php

namespace Backpack\Profile;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\View;
use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Contracts\BonusAccount;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/profile.php';

    public function boot()
    {
        
        $helpers = __DIR__ . '/app/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $profilePaths = [__DIR__.'/resources/views'];
        $profileVendorPath = resource_path('views/vendor/profile');
        if (is_dir($profileVendorPath)) {
            array_unshift($profilePaths, $profileVendorPath);
        }
        View::addNamespace('profile-backpack', $profilePaths);

        $crudPaths = [__DIR__.'/resources/views/vendor/backpack/crud'];
        $crudVendorPath = resource_path('views/vendor/backpack/crud');
        if (is_dir($crudVendorPath)) {
            array_unshift($crudPaths, $crudVendorPath);
        }
        View::addNamespace('crud', $crudPaths);

        // Currency names
        $this->app->singleton(\Backpack\Profile\app\Contracts\CurrencyNameResolver::class, \Backpack\Profile\app\Services\CurrencyNameResolver::class);

        // Settings service registration
        $this->app->singleton(Backpack\Profile\app\Contracts\SettingsService::class, \Backpack\Profile\app\Services\ConfigSettingsService::class);

        // Translations
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'profile');
    
	    // Migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        
        // Routes
        $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
        $this->loadRoutesFrom(__DIR__.'/routes/backpack/profile_dashboard.php');

        $this->loadRoutesFrom(__DIR__.'/routes/api/profile.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api/withdrawals.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api/common.php');

        $this->loadRoutesFrom(__DIR__.'/routes/web/auth.php');


        $this->publishes([
          self::CONFIG_PATH => config_path('/backpack/profile.php'),
        ], 'config');
        
        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views'),
        ], 'views');

        $this->publishes([
            __DIR__.'/database/migrations' => resource_path('database/migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/routes/backpack/routes.php' => resource_path('/routes/backpack/profile/routes.php'),
            __DIR__.'/routes/api/profile.php' => resource_path('/routes/backpack/profile/api.php'),
            __DIR__.'/routes/web/auth.php' => resource_path('/routes/backpack/profile/auth.php'),
        ], 'routes');
    
    }

    public function register()
    {

        $this->app->register(\Backpack\Profile\app\Providers\EventServiceProvider::class);
        $this->app->singleton(\Backpack\Profile\app\Services\TriggerRegistry::class);
        $this->app->singleton('backpack.profile.profile_factory', fn() => new \Backpack\Profile\app\Services\ProfileFactory());

        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'profile'
        );
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'backpack.profile'
        );

        $this->app->booting(function () {
            $defaults = (array) config('profile', []);
            $overrides = (array) config('backpack.profile', []);
            $merged = array_replace_recursive($defaults, $overrides);

            config([
                'profile' => $merged,
                'backpack.profile' => $merged,
            ]);
        });

        $impl = config('profile.currency_converter');

        if ($impl) {
            $this->app->bind(
                \Backpack\Profile\app\Contracts\CurrencyConverter::class,
                $impl
            );
        } else {
            // опционально: заглушка, которая бросает исключение при вызове,
            // чтобы было явно видно, что конвертер не сконфигурирован.
            $this->app->bind(
                \Backpack\Profile\app\Contracts\CurrencyConverter::class,
                function () {
                    return new class implements \Backpack\Profile\app\Contracts\CurrencyConverter {
                        public function convert(float $amount, string $from, string $to): float {
                            throw new \RuntimeException('Profile CurrencyConverter is not bound. Set profile.currency_converter or bind the contract in your app.');
                        }
                    };
                }
            );
        }

        // Facades
        $this->registerFacadeAlias();

        $this->app->singleton(
            BonusAccount::class,
            \Backpack\Profile\app\Services\BonusAccountService::class
        );
    }

    protected function registerFacadeAlias()
    {
        // Делаем alias глобально
        AliasLoader::getInstance()->alias('Profile', \Backpack\Profile\app\Facades\Profile::class);
    }
}
