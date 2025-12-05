<?php

namespace Backpack\Profile\app\Services;

use Ozh\Phpass\PasswordHash;

class WpPasswordChecker
{
    protected PasswordHash $phpass;

    public function __construct()
    {
        // те же параметры, что в WP (8 итераций, portable = true)
        $this->phpass = new PasswordHash(8, true);
    }

    public function check(string $plain, string $hash): bool
    {
        // Новый формат WordPress 6.8+: $wp$2y$10$...
        if (str_starts_with($hash, '$wp$2y')) {
            $passwordToHash = base64_encode(
                hash_hmac('sha384', trim($plain), 'wp-sha384', true)
            );

            // отрезаем префикс `$wp`, остаётся обычный bcrypt: $2y$10$...
            $bcryptHash = substr($hash, 3);

            return password_verify($passwordToHash, $bcryptHash);
        }

        // Старый формат $P$.../$H$... (portable phpass)
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return $this->phpass->CheckPassword($plain, $hash);
        }

        return false;
    }
}
