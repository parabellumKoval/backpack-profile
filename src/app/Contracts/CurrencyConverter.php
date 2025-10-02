<?php

namespace Backpack\Profile\app\Contracts;

interface CurrencyConverter {
    public function convert(float $amount, string $from, string $to, int $fixTo): float;
}
