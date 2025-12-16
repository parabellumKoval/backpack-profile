<?php

namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileFactory
{
    public function makeFor(object $owner, ?string $incomingSponsorCode = null): Profile
    {
        $profile = new Profile();
        $profile->referral_code = $this->generateUniqueCode();
        $profile->sponsor_profile_id = $this->resolveSponsorId($incomingSponsorCode);
        $profile->locale = $this->resolveLocale();
        $profile->country_code = $this->resolveCountryCode();

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

    protected function resolveLocale(): ?string
    {
        foreach ($this->localeCandidates() as $candidate) {
            $normalized = $this->normalizeLocale($candidate);

            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    protected function localeCandidates(): array
    {
        $request = $this->currentRequest();

        return array_filter([
            $request?->input('locale'),
            $request?->input('language'),
            $request?->header('X-Locale'),
            $request?->header('X-Language'),
            $request?->cookie('locale'),
            app()->getLocale(),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function resolveCountryCode(): ?string
    {
        foreach ($this->countryCandidates() as $candidate) {
            $normalized = $this->normalizeCountry($candidate);

            if ($normalized) {
                return $normalized;
            }
        }

        if (class_exists(\Backpack\Store\Facades\Store::class)) {
            try {
                $storeCountry = \Backpack\Store\Facades\Store::country();
                $normalized = $this->normalizeCountry($storeCountry);

                if ($normalized) {
                    return $normalized;
                }
            } catch (\Throwable $e) {
                // Store context is optional; ignore failures when the package is unavailable.
            }
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    protected function countryCandidates(): array
    {
        $request = $this->currentRequest();

        return array_filter([
            $request?->input('country'),
            $request?->input('region'),
            $request?->header('X-Region'),
            $request?->header('X-Country'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $normalized = strtolower(str_replace('_', '-', $locale));

        if (str_contains($normalized, '-')) {
            $normalized = explode('-', $normalized)[0];
        }

        $supported = (array) config('app.supported_locales', []);

        if (!empty($supported) && !in_array($normalized, $supported, true)) {
            return null;
        }

        return $normalized;
    }

    protected function normalizeCountry(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        $cleaned = preg_replace('/[^a-zA-Z]/', '', $country);
        $code = strtoupper(substr((string) $cleaned, 0, 2));

        return strlen($code) === 2 ? $code : null;
    }

    protected function currentRequest(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}
