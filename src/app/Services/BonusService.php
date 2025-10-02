<?php
namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Models\ProfileBonus;

use Backpack\Profile\app\Contracts\PointsConverter;
use Backpack\Profile\app\Services\Facades\Settings;

class BonusService
{
    protected ?PointsConverter $pointsConverter;

    public function __construct(?PointsConverter $pointsConverter = null)
    {
        $this->pointsConverter = $pointsConverter;
    }

    public function giveBonus(Profile $profile, float $amount, string $currency, string $reason, array $meta = [])
    {
        if (Settings::get('use_points') && $this->pointsConverter) {
            $points = $this->pointsConverter->currencyToPoints($amount, $currency);
            $finalCurrency = Settings::get('bonus_currency');
            $finalAmount = $points;
        } else {
            $finalCurrency = $currency;
            $finalAmount = $amount;
        }

        return $profile->bonuses()->create([
            'amount' => $finalAmount,
            'currency' => $finalCurrency,
            'reason' => $reason,
            'meta' => $meta
        ]);


        // return ProfileBonus::create([
        //     'profile_id' => $profile->id,
        //     'amount' => $amount,
        //     'currency' => $currency,
        //     'reason' => $reason,
        //     'meta' => $meta,
        // ]);
    }

    public function giveBonusByEvent(Profile $profile, BonusableEvent $event)
    {
        return $this->giveBonus(
            $profile,
            $event->getBonusAmount(),
            $event->getCurrency(),
            $event->getReason(),
            $event->getMeta()
        );
    }


    /**
     * Ступенчатое начисление бонусов по уровням
     *
     * @param Profile $buyer Профиль покупателя (реферал)
     * @param float $purchaseAmount Сумма покупки
     * @param string|null $reason Причина бонуса
     */
    public function distributeReferralBonus(Profile $buyer, float $purchaseAmount, ?string $reason = null)
    {
        $currentReferrer = $buyer->referrer;
        $levels = Settings::get('referral_levels', 1);
        $commissions = Settings::get('referral_commissions', []);
        $currency = Settings::get('bonus_currency', 'USD');

        for ($level = 1; $level <= $levels; $level++) {
            if (!$currentReferrer) {
                break; // если нет вышестоящего реферала, останавливаем цикл
            }

            $commissionPercent = $commissions[$level] ?? 0;

            if ($commissionPercent <= 0) {
                $currentReferrer = $currentReferrer->referrer;
                continue; // если нет процента на уровне, пропускаем
            }

            $bonusAmount = round($purchaseAmount * ($commissionPercent / 100), 2);

            $this->giveBonus(
                $currentReferrer,
                $bonusAmount,
                $currency,
                $reason ?: "Referral Level {$level} commission",
                [
                    'referral_level' => $level,
                    'referred_user_id' => $buyer->id,
                    'original_purchase_amount' => $purchaseAmount,
                ]
            );

            $currentReferrer = $currentReferrer->referrer; // переходим на уровень выше
        }
    }
}