<?php
namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Contracts\CurrencyNameResolver as Contract;

class CurrencyNameResolver implements Contract
{
    public function label(string $code): string
    {
        $code = $this->normalize($code);

        // Настройки points
        $pointsKey  = (string)\Settings::get('profile.points.key', 'POINTS'); // внутренний ключ
        $pointsName = (string)\Settings::get('profile.points.name', 'Points'); // отображаемое имя

        if (strcasecmp($code, $pointsKey) === 0 || strcasecmp($code, 'points') === 0) {
            return $pointsName ?: $pointsKey;
        }

        // Кастомные лейблы из настроек: profile.currencies.labels = ['UAH'=>'₴', 'USD'=>'USD', 'CZK'=>'Kč', ...]
        $map = (array)\Settings::get('profile.currencies.labels', []);

        // Найдём с учётом регистра
        foreach ($map as $k => $v) {
            if (strcasecmp($code, (string)$k) === 0) {
                return (string)$v ?: strtoupper($code);
            }
        }

        // Фолбэк — сам код в верхнем регистре
        return strtoupper($code);
    }

    public function isPoints(string $code): bool
    {
        $code = $this->normalize($code);
        $pointsKey = (string)\Settings::get('profile.points.key', 'POINTS');
        return strcasecmp($code, $pointsKey) === 0 || strcasecmp($code, 'points') === 0;
    }

    public function normalize(string $code): string
    {
        return trim($code);
    }
}
