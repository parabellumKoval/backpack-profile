<?php

namespace Backpack\Profile\app\Contracts;

use Backpack\Profile\app\DTO\BonusTransaction;

interface BonusAccount
{
    public function canSpend(int $userId, float $points): bool;

    public function spend(int $userId, float $points, array $context = []): BonusTransaction;

    public function refund(int $userId, float $points, array $context = []): void;
}

