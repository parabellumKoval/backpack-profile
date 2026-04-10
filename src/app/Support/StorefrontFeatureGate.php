<?php

namespace Backpack\Profile\app\Support;

use App\Support\StorefrontSettings;
use Backpack\Settings\Facades\Settings;
use Backpack\Store\app\Services\Store;

class StorefrontFeatureGate
{
    public function current(?string $storefront = null): ?string
    {
        if ($storefront !== null && $storefront !== '') {
            return Store::normalizeStorefrontCode($storefront);
        }

        return Store::normalizeStorefrontCode(app(StorefrontSettings::class)->current());
    }

    public function featureEnabled(
        string $key,
        bool $default = false,
        ?string $storefront = null,
        array $context = [],
        ?string $flagKey = 'enabled'
    ): bool {
        [$settingKey, $scopeKey] = $this->resolveKeys($key, $flagKey);

        $enabled = (bool) app(StorefrontSettings::class)->get(
            $settingKey,
            $default,
            $context,
            $storefront
        );

        if (!$enabled) {
            return false;
        }

        return $this->allowsStorefront($scopeKey, $storefront);
    }

    public function allowsStorefront(string $scopeKey, ?string $storefront = null): bool
    {
        $resolvedStorefront = $this->current($storefront);
        if ($resolvedStorefront === null) {
            return true;
        }

        $enabledStorefronts = $this->enabledStorefronts($scopeKey);
        if ($enabledStorefronts !== [] && !in_array($resolvedStorefront, $enabledStorefronts, true)) {
            return false;
        }

        $disabledStorefronts = $this->disabledStorefronts($scopeKey);

        return !in_array($resolvedStorefront, $disabledStorefronts, true);
    }

    /**
     * @return array<int, string>
     */
    public function enabledStorefronts(string $scopeKey): array
    {
        return $this->normalizeStorefrontList(Settings::get($scopeKey . '.enabled_storefronts', []));
    }

    /**
     * @return array<int, string>
     */
    public function disabledStorefronts(string $scopeKey): array
    {
        return $this->normalizeStorefrontList(Settings::get($scopeKey . '.disabled_storefronts', []));
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveKeys(string $key, ?string $flagKey): array
    {
        if ($flagKey === null || $flagKey === '') {
            return [$key, $key];
        }

        if ($flagKey === 'enabled') {
            return [$key . '.enabled', $key];
        }

        return [$key . '.' . $flagKey, $key . '.' . $flagKey];
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeStorefrontList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = array_map(static function ($item) {
            return Store::normalizeStorefrontCode(is_scalar($item) ? (string) $item : null);
        }, $value);

        return array_values(array_unique(array_filter($normalized)));
    }
}
