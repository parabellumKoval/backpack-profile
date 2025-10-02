<?php
namespace Backpack\Profile\app\Contracts;

interface ReferralTrigger
{
    /** Уникальный алиас триггера, используется в Settings/DB и в ak_reward_events.trigger */
    public static function alias(): string;

    /** Заголовок/описание для UI (Settings) */
    public static function label(): string;
    public static function description(): ?string;

    /** Что умеет триггер — чтобы UI знал какие поля показывать */
    public static function capabilities(): array; 
    // пример возвращаемого:
    // [
    //   'supports_fixed'   => true,
    //   'supports_percent' => false,
    //   'supports_levels'  => true,   // можно делить аплайну
    //   'supports_actor'   => true,   // можно платить самому актору
    //   'levels_percent_of'=> 'base|actor', // проценты уровней от базы или от выплаты актору
    // ]
    
    /** Какие поля нужны из payload (для валидации/документации в UI) */
    public static function payloadSchema(): array;

    /**
     * Возвращает "базовую величину" и валюту для расчёта процентов/фиксов.
     * Например, для заказа — сумма оплаты и валюта заказа.
     */
    public function baseAmount(array $payload): ?array; // ['amount' => float, 'currency' => 'CZK']
}