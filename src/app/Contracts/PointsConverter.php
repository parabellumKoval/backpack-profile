<?php
namespace Backpack\Profile\app\Contracts;

interface PointsConverter
{
    public function currencyToPoints(float $amount, string $currency): float;
    public function pointsToCurrency(float $points, string $currency): float;
}
