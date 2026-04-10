<?php

namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Support\StorefrontFeatureGate;
use Backpack\Store\app\Services\Store;
use Illuminate\Database\Eloquent\Model;

class LoyaltyDiscountService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {
    }

    public function isEnabled(): bool
    {
        return app(StorefrontFeatureGate::class)->featureEnabled('profile.loyalty', false);
    }

    public function isConfigured(): bool
    {
        return (bool) \Settings::get('profile.loyalty.enabled', false);
    }

    public function isEnabledForStorefront(?string $storefront = null): bool
    {
        return app(StorefrontFeatureGate::class)->featureEnabled('profile.loyalty', false, $storefront);
    }

    public function baseCurrency(): string
    {
        $configured = \Settings::get('profile.loyalty.base_currency', config('dress.store.base_currency', 'USD'));

        return strtoupper((string) $configured);
    }

    /**
     * @return array<int, array{name:string, amount_from:float, discount_percent:float}>
     */
    public function levels(): array
    {
        $levels = (array) \Settings::get('profile.loyalty.levels', []);
        $normalized = [];

        foreach ($levels as $index => $level) {
            if (!is_array($level)) {
                continue;
            }

            $amountFrom = $level['amount_from'] ?? $level['threshold_amount'] ?? $level['amount'] ?? null;
            $discountPercent = $level['discount_percent'] ?? $level['discount'] ?? $level['value'] ?? null;

            if ($amountFrom === null || $discountPercent === null) {
                continue;
            }

            $amountFrom = round(max(0, (float) $amountFrom), 2);
            $discountPercent = round(min(100, max(0, (float) $discountPercent)), 2);
            $name = trim((string) ($level['name'] ?? ''));

            $normalized[] = [
                'name' => $name !== '' ? $name : sprintf('Level %d', $index + 1),
                'amount_from' => $amountFrom,
                'discount_percent' => $discountPercent,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            return $left['amount_from'] <=> $right['amount_from'];
        });

        return array_values($normalized);
    }

    /**
     * @return array{name:?string, amount_from:float, discount_percent:float}
     */
    public function resolveLevel(float $totalSpent): array
    {
        $resolved = [
            'name' => null,
            'amount_from' => 0.0,
            'discount_percent' => 0.0,
        ];

        foreach ($this->levels() as $level) {
            if ($totalSpent < (float) $level['amount_from']) {
                break;
            }

            $resolved = $level;
        }

        return $resolved;
    }

    public function recalculateForUserId(int $userId): ?float
    {
        if ($userId <= 0 || !$this->isConfigured()) {
            return null;
        }

        $profileModel = $this->profileModelClass();

        /** @var Profile|null $profile */
        $profile = $profileModel::query()->where('user_id', $userId)->first();
        if (!$profile) {
            return null;
        }

        $levels = $this->levels();
        $totalSpent = $this->resolveCompletedSpentTotal($userId);
        $resolvedLevel = $levels !== [] ? $this->resolveLevel($totalSpent) : [
            'name' => null,
            'amount_from' => 0.0,
            'discount_percent' => 0.0,
        ];

        $discountPercent = round((float) ($resolvedLevel['discount_percent'] ?? 0.0), 2);
        $metaPayload = [
            'enabled' => true,
            'base_currency' => $this->baseCurrency(),
            'total_spent' => round($totalSpent, 2),
            'level_name' => $resolvedLevel['name'] ?? null,
            'amount_from' => round((float) ($resolvedLevel['amount_from'] ?? 0.0), 2),
            'discount_percent' => $discountPercent,
            'recalculated_at' => now()->toIso8601String(),
        ];

        $profile->mergeMeta([
            'loyalty' => $metaPayload,
        ]);

        $profile->discount_percent = $discountPercent;
        $profile->save();

        return $discountPercent;
    }

    protected function resolveCompletedSpentTotal(int $userId): float
    {
        $orderModel = $this->orderModelClass();
        $profileModel = $this->profileModelClass();

        $profileIds = $profileModel::query()
            ->where('user_id', $userId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $userModelCandidates = $this->userModelCandidates();

        /** @var \Illuminate\Support\Collection<int, Model> $orders */
        $orders = $orderModel::query()
            ->select([
                'id',
                'orderable_id',
                'orderable_type',
                'storefront_code',
                'currency_code',
                'fx_rate',
                'price',
                'grand_total',
                'shipping_total',
                'tax_total',
            ])
            ->where('status', 'completed')
            ->where(function ($query) use ($userId, $profileIds, $userModelCandidates) {
                $firstUserModel = true;
                foreach ($userModelCandidates as $userModel) {
                    $method = $firstUserModel ? 'where' : 'orWhere';
                    $query->{$method}(function ($inner) use ($userId, $userModel) {
                        $inner
                            ->where('orderable_type', $userModel)
                            ->where('orderable_id', $userId);
                    });
                    $firstUserModel = false;
                }

                if ($profileIds !== []) {
                    $query->orWhere(function ($inner) use ($profileIds, $profileModel) {
                        $inner
                            ->where('orderable_type', $profileModel)
                            ->whereIn('orderable_id', $profileIds);
                    });
                }
            })
            ->get();

        return round($orders
            ->filter(fn (Model $order) => $this->isEnabledForStorefront(
                (string) ($order->storefront_code ?: Store::defaultStorefront())
            ))
            ->sum(fn (Model $order) => $this->resolveOrderSpentAmount($order)), 2);
    }

    protected function resolveOrderSpentAmount(Model $order): float
    {
        $amount = max(0.0, round(
            (float) ($order->grand_total ?? $order->price ?? 0)
            - (float) ($order->shipping_total ?? 0)
            - (float) ($order->tax_total ?? 0),
            2
        ));

        if ($amount <= 0) {
            return 0.0;
        }

        $targetCurrency = $this->baseCurrency();
        $storeBaseCurrency = strtoupper((string) config('dress.store.base_currency', 'USD'));
        $orderCurrency = strtoupper((string) ($order->currency_code ?? $targetCurrency));
        $fxRate = (float) ($order->fx_rate ?? 0);

        if ($orderCurrency === $targetCurrency) {
            return $amount;
        }

        if ($targetCurrency === $storeBaseCurrency && $fxRate > 0) {
            return round($amount / $fxRate, 2);
        }

        if ($orderCurrency === $storeBaseCurrency) {
            return $this->currencyConverter->convert($amount, $storeBaseCurrency, $targetCurrency, 2);
        }

        if ($fxRate > 0) {
            $amountInStoreBase = round($amount / $fxRate, 6);

            if ($targetCurrency === $storeBaseCurrency) {
                return round($amountInStoreBase, 2);
            }

            return $this->currencyConverter->convert($amountInStoreBase, $storeBaseCurrency, $targetCurrency, 2);
        }

        return $this->currencyConverter->convert($amount, $orderCurrency, $targetCurrency, 2);
    }

    protected function profileModelClass(): string
    {
        return (string) config('backpack.profile.profile_model', config('profile.profile_model', Profile::class));
    }

    protected function orderModelClass(): string
    {
        return (string) config('backpack.profile.order_model', config('profile.order_model', \Backpack\Store\app\Models\Order::class));
    }

    /**
     * @return array<int, string>
     */
    protected function userModelCandidates(): array
    {
        return array_values(array_unique(array_filter([
            config('dress.store.user_model'),
            config('backpack.profile.user_model'),
            config('profile.user_model'),
            \App\Models\User::class,
        ])));
    }
}
