<?php

namespace Backpack\Profile\app\Listeners;

use Illuminate\Support\Facades\Hash;

use Backpack\Profile\app\Events\ProfileCreating;
use Backpack\Profile\app\Models\Profile;
 
class ProfileCreatingListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }
 
    /**
     * Handle the event.
     *
     * @param  Backpack\Profile\app\Events\ProfileCreating  $event
     * @return void
     */
    public function handle(ProfileCreating $event)
    {
    //   $event->profile->referrer_code = $this->generateUniqueReferralCode();
    }

    public function generateUniqueReferralCode() {
      $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz'), 0, 12);

      if(Profile::where('referrer_code', $code)->first()) {
          return $this->generateUniqueReferralCode();
      }

      return $code;
    }
}