<?php
// packages/backpack-profile/src/app/Support/TriggerLabels.php

namespace Backpack\Profile\app\Support;

use Backpack\Profile\app\Services\TriggerRegistry;

class TriggerLabels
{
    /**
     * Резолвит метки и метаданные триггера.
     *
     * @return array{
     *   label:string,
     *   description:?string,
     *   base:string,
     *   reversal:bool
     * }
     */
    public static function resolve(string $triggerKey): array
    {
        $reversal = self::isReversal($triggerKey);
        $base = self::base($triggerKey);

        $label = null;
        $description = null;

        // 1) Через реестр триггеров
        /** @var TriggerRegistry|null $registry */
        $registry = app()->bound(TriggerRegistry::class) ? app(TriggerRegistry::class) : null;

        if ($registry) {
            // make() вернёт инстанс класса, а у тебя label()/description() — статические
            // потому спокойно обращаемся как к статике через имя класса инстанса
            if ($trigger = $registry->make($base)) {
                $class = get_class($trigger);

                if (is_callable([$class, 'label'])) {
                    $label = (string) $class::label();
                }
                if (is_callable([$class, 'description'])) {
                    $description = $class::description();
                }
            }
        }

        // 2) Фолбэк на настройки (если не зарегистрирован в реестре)
        if ($label === null) {
            $def = \Settings::get('profile.referrals.triggers.'.$base);
            if (is_array($def)) {
                $label = $def['label'] ?? null;
                $description = $description ?? ($def['description'] ?? null);
            }
        }

        // 3) Последний фолбэк — сам ключ
        $label = $label ?? $base;

        return [
            'label'       => (string) $label,
            'description' => $description ? (string) $description : null,
            'base'        => (string) $base,
            'reversal'    => $reversal,
        ];
    }

    public static function isReversal(string $triggerKey): bool
    {
        return str_ends_with($triggerKey, '.reversal');
    }

    public static function base(string $triggerKey): string
    {
        return self::isReversal($triggerKey)
            ? substr($triggerKey, 0, -9) // отрезаем ".reversal"
            : $triggerKey;
    }
}
