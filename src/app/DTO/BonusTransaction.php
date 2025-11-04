<?php

namespace Backpack\Profile\app\DTO;

class BonusTransaction
{
    public function __construct(
        public readonly float $points,
        public readonly float $fiatAmount,
        public readonly string $fiatCurrency,
        public readonly string $walletCurrency,
        public readonly ?array $meta = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'fiat_amount' => $this->fiatAmount,
            'fiat_currency' => $this->fiatCurrency,
            'wallet_currency' => $this->walletCurrency,
            'meta' => $this->meta,
        ];
    }
}

