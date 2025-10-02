<?php
namespace Backpack\Profile\app\Services;

class CurrencyConverter
{
    public function convert(float $amount, string $from, string $to, int $fixTo = 2): float
    {
        if ($from === $to) return $amount;
        
        $realFrom = $this->resolveCurrency($from);
        $realTo = $this->resolveCurrency($to);

        return app(\Backpack\Profile\app\Contracts\CurrencyConverter::class)->convert($amount, $realFrom, $realTo, $fixTo);
    }

    private function resolveCurrency($currency) {
        $pointCurrencyKey = \Settings::get('profile.points.key');
        $pointBaseKey = \Settings::get('profile.points.base');

        if($currency === $pointCurrencyKey) {
            return $pointBaseKey;
        }else {
            return $currency;
        }
    }
}
