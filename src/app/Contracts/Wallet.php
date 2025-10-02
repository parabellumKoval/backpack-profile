<?php

namespace Backpack\Profile\app\Contracts;

interface Wallet
{
    /** Доступный баланс в поинтах */
    public function balance(int $userId): string; // decimal string

    /** Авторизованная попытка зарезервировать списание под order */
    public function authorize(string $orderId, int $userId, string $amountPoints): bool;

    /** Захват списания по оплачиваемому заказу */
    public function capture(string $orderId): void;

    /** Отмена авторизации (возврат в баланс) */
    public function void(string $orderId): void;
}
