<?php
if (!function_exists('currency_label')) {
    function currency_label(string|null $code): string {
      if(!$code) return '';

      return app(\Backpack\Profile\app\Contracts\CurrencyNameResolver::class)->label($code);
    }
}