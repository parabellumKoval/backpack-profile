<?php

namespace Backpack\Profile\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
      \Backpack\Profile\app\Events\ProfileCreating::class => [
        \Backpack\Profile\app\Listeners\ProfileCreatingListener::class,
      ],
      \Backpack\Profile\app\Events\UserCreating::class => [
        \Backpack\Profile\app\Listeners\UserCreatingListener::class,
      ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
      parent::boot();

      // Profile::observe(ProfileObserver::class);

      \Backpack\Profile\app\Models\Profile::creating(function ($profile) {
          event(new \Backpack\Profile\app\Events\ProfileCreating($profile));
      });

      \Profile::userModel()::creating(function ($user) {
          event(new \Backpack\Profile\app\Events\UserCreating($user));
      });
    }
}