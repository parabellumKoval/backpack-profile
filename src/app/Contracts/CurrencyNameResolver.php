<?php
// src/app/Contracts/CurrencyNameResolver.php
namespace Backpack\Profile\app\Contracts;

interface CurrencyNameResolver
{
    /** Отдаёт человеко-читаемое имя валюты (например: 'USD' -> 'USD', 'points' -> 'VIVA') */
    public function label(string $code): string;

    /** Это код бонусных баллов? */
    public function isPoints(string $code): bool;

    /** Нормализация кода (регистр/пробелы) */
    public function normalize(string $code): string;
}
