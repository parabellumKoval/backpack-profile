<?php

namespace Backpack\Profile\app\Models\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

use \Backpack\Profile\app\Models\Profile;
use \Backpack\Profile\app\Models\ReferralPartner;
use \Backpack\Profile\app\Models\WalletBalance;
use \Backpack\Profile\app\Models\SocialAccount;

trait HasProfile
{
    private ?string $tempReferrerCodeStorage = null;

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function referralPartner(): HasOne
    {
        return $this->hasOne(ReferralPartner::class, 'user_id');
    }

    public function walletBalance(): HasOne
    {
        return $this->hasOne(WalletBalance::class, 'user_id');
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
            // $paramName = \Settings::get('profile.referrals.url_param', 'ref');
            $paramName = 'referral_code';

            $sponsorCode = 
                $owner->tempReferrerCode
                ?? request()->input($paramName)
                ?? request()->cookie($cookieName);

            $profile = app('backpack.profile.profile_factory')->makeFor($owner, $sponsorCode);

            $owner->profile()->save($profile);
        });
    }
    
    protected function tempReferrerCode(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tempReferrerCodeStorage,
            set: function ($value) {
                $this->tempReferrerCodeStorage = $value;
                return [];
            }
        );
    }
}
