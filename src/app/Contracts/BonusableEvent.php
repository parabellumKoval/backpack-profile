<?php

namespace Backpack\Profile\app\Contracts;

interface BonusableEvent
{
    public function getBonusAmount(): float;
    public function getCurrency(): string;
    public function getReason(): string;
    public function getMeta(): array;
}