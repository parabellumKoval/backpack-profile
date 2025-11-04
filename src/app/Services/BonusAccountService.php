<?php

namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Contracts\BonusAccount;
// use Backpack\Profile\app\Contracts\CurrencyConverter;
use Backpack\Profile\app\Services\CurrencyConverter;
use Backpack\Profile\app\DTO\BonusTransaction;
use Backpack\Profile\app\Exceptions\InsufficientBonusesException;
use Illuminate\Support\Facades\DB;

class BonusAccountService implements BonusAccount
{
    protected string $walletCurrency;
    protected string $baseCurrency;

    public function __construct(
        protected CurrencyConverter $converter
    ) {
        $this->walletCurrency = config('profile.points.key', 'point');
        $this->baseCurrency = config('profile.points.base', 'USD');
    }

    public function canSpend(int $userId, float $points): bool
    {
        if ($points <= 0) {
            return true;
        }

        $balance = DB::table('ak_wallet_balances')
            ->where('user_id', $userId)
            ->where('currency', $this->walletCurrency)
            ->value('balance');

        $balance = $balance !== null ? (string)$balance : '0';

        return bccomp($balance, $this->formatPoints($points), 6) >= 0;
    }

    public function spend(int $userId, float $points, array $context = []): BonusTransaction
    {
        $points = max(0.0, $points);

        $currency = $context['currency'] ?? $this->baseCurrency;
        $referenceType = $context['reference_type'] ?? 'order';
        $referenceId = isset($context['reference_id']) ? (string)$context['reference_id'] : null;

        if ($points <= 0) {
            return new BonusTransaction(0.0, 0.0, $currency, $this->walletCurrency);
        }

        $formattedPoints = $this->formatPoints($points);

        return DB::transaction(function () use (
            $userId,
            $formattedPoints,
            $currency,
            $referenceType,
            $referenceId,
            $context
        ) {
            $currentBalance = $this->lockBalance($userId);

            if (bccomp($currentBalance, $formattedPoints, 6) < 0) {
                throw new InsufficientBonusesException('Недостаточно бонусов на счёте.');
            }

            DB::table('ak_wallet_balances')
                ->where('user_id', $userId)
                ->where('currency', $this->walletCurrency)
                ->update([
                    'balance' => DB::raw('balance - '.$formattedPoints),
                    'updated_at' => now(),
                ]);

            DB::table('ak_wallet_ledger')->insert([
                'user_id' => $userId,
                'type' => 'debit',
                'amount' => $formattedPoints,
                'currency' => $this->walletCurrency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => $this->encodeMeta($context, $currency),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $fiatAmount = $this->convertPoints($formattedPoints, $currency);

            return new BonusTransaction(
                round((float)$formattedPoints, 6),
                round($fiatAmount, 2),
                $currency,
                $this->walletCurrency,
                $context
            );
        });
    }

    public function refund(int $userId, float $points, array $context = []): void
    {
        $points = max(0.0, $points);

        if ($points <= 0) {
            return;
        }

        $currency = $context['currency'] ?? $this->baseCurrency;
        $referenceType = $context['reference_type'] ?? 'order';
        $referenceId = isset($context['reference_id']) ? (string)$context['reference_id'] : null;
        $formattedPoints = $this->formatPoints($points);

        DB::transaction(function () use ($userId, $formattedPoints, $referenceType, $referenceId, $context, $currency) {
            $this->lockBalance($userId);

            DB::table('ak_wallet_balances')
                ->where('user_id', $userId)
                ->where('currency', $this->walletCurrency)
                ->update([
                    'balance' => DB::raw('balance + '.$formattedPoints),
                    'updated_at' => now(),
                ]);

            DB::table('ak_wallet_ledger')->insert([
                'user_id' => $userId,
                'type' => 'credit',
                'amount' => $formattedPoints,
                'currency' => $this->walletCurrency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => $this->encodeMeta($context, $currency),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    protected function lockBalance(int $userId): string
    {
        $row = DB::table('ak_wallet_balances')
            ->where('user_id', $userId)
            ->where('currency', $this->walletCurrency)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            DB::table('ak_wallet_balances')->insert([
                'user_id' => $userId,
                'currency' => $this->walletCurrency,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return '0';
        }

        return (string)$row->balance;
    }

    protected function convertPoints(string $points, string $currency): float
    {
        $numericPoints = (float)$points;

        if ($currency === $this->baseCurrency) {
            return round($numericPoints, 2);
        }

        $result = $this->converter->convert(
            $numericPoints,
            $this->baseCurrency,
            $currency
        );

        return $result !== null ? (float)$result : 0.0;
    }

    protected function formatPoints(float|string $points): string
    {
        $value = is_string($points) ? (float)$points : $points;

        return number_format($value, 6, '.', '');
    }

    protected function encodeMeta(array $context, string $currency): ?string
    {
        $meta = array_filter([
            'currency' => $currency,
            'context' => $context ?: null,
        ]);

        return $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    }
}
