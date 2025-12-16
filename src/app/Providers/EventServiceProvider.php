<?php

namespace Backpack\Profile\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Backpack\Profile\app\Events\ReferralAttached;
use Backpack\Profile\app\Models\Profile;

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

      // Disable Laravel's default email verification listener
      // The main application will handle this via EventServiceProvider
      $this->configureEmailVerification();

      // Profile::observe(ProfileObserver::class);

      Profile::creating(function ($profile) {
          event(new \Backpack\Profile\app\Events\ProfileCreating($profile));
      });

      \Profile::userModel()::creating(function ($user) {
          event(new \Backpack\Profile\app\Events\UserCreating($user));
      });

      Profile::created(function (Profile $profile) {
          if (! $profile->sponsor_profile_id) {
              return;
          }

          $profile->loadMissing(['user', 'referrer.user']);

          $sponsor = $profile->referrer;

          if (! $sponsor || ! $sponsor->user) {
              return;
          }

          event(new ReferralAttached($profile, $sponsor));
      });
    }

    /**
     * Skip automatic email verification listener registration.
     * Prevent duplicate email verification notifications.
     */
    protected function configureEmailVerification()
    {
        // Remove any default Laravel email verification listeners
        // The host application registers the verification notification once.
        Event::forget(\Illuminate\Auth\Events\Registered::class);
    }
}
