<?php

namespace Backpack\Profile\app\Models\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use \Backpack\Profile\app\Models\Profile;
use \Backpack\Profile\app\Models\ReferralPartner;
use \Backpack\Profile\app\Models\ReferralBalance;
use \Backpack\Profile\app\Models\SocialAccount;

trait HasProfile
{
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function referralPartner(): HasOne
    {
        return $this->hasOne(ReferralPartner::class, 'user_id');
    }

    public function referralBalances(): HasMany
    {
        return $this->hasMany(ReferralBalance::class, 'user_id');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'user_id');
    }

    public static function bootHasProfile(): void
    {
        static::created(function ($owner) {
            if ($owner->profile()->exists()) return;

            $cookieName  = \Settings::get('profile.referrals.cookie.name', 'ref_code');
            $sponsorCode = request()->input('sponsor_code')
                ?? request()->cookie($cookieName);

            $profile = app('backpack.profile.profile_factory')->makeFor($owner, $sponsorCode);
            $owner->profile()->save($profile);
        });
    }
}
