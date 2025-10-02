<?php

namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Models\Profile;
use Illuminate\Support\Str;

class ProfileFactory
{
    public function makeFor(object $owner, ?string $incomingSponsorCode = null): Profile
    {
        $profile = new Profile();
        $profile->referral_code = $this->generateUniqueCode();
        $profile->sponsor_profile_id = $this->resolveSponsorId($incomingSponsorCode);
        return $profile;
    }

    public function generateUniqueCode(int $len = 8): string
    {
        do {
            $code = Str::upper(Str::random($len));
        } while (Profile::query()->where('referral_code', $code)->exists());
        return $code;
    }

    public function resolveSponsorId(?string $code): ?int
    {
        if (!$code) return null;

        // Доп.проверка срока действия
        $ttlDays = (int) \Settings::get('profile.referrals.link_ttl_days', 30);
        $restrictByTtl = (bool) \Settings::get('profile.referrals.enforce_ttl_on_attach', false);

        $sponsor = Profile::query()->where('referral_code', $code)->first();
        if (!$sponsor) return null;

        // if ($restrictByTtl) {
        //     // Вариант А: опираться на куку с подписью/временем.
        //     // Если куки нет/просрочена — не назначаем спонсора.
        //     // Здесь просто пример чтения куки:
        //     $cookieName = \Settings::get('profile.referrals.cookie.name', 'ref_code');
        //     $issuedAt = request()->cookies->get($cookieName.'_iat'); // timestamp
        //     if (!$issuedAt || now()->diffInDays(\Carbon\Carbon::createFromTimestamp($issuedAt)) > $ttlDays) {
        //         return null;
        //     }
        // }

        return $sponsor->id;
    }
}
