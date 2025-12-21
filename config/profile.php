<?php

return [
  'user_model' => '\App\Models\User',

  // PROFILE MODEL
  'profile_model' => 'Backpack\Profile\app\Models\Profile',

  // ORDER
  'order_model' => 'Backpack\Store\app\Models\Order',

  // REVIEW
  'review_model' => 'Backpack\Reviews\app\Models\Review',

  // RESOURCES
  'full_resource' => 'Backpack\Profile\app\Http\Resources\ProfileFullResource',

  'tiny_resource' => 'Backpack\Profile\app\Http\Resources\ProfileTinyResource',

  'reset_password_redirect' => env('FRONT_URL') . 'new-password',
  'email_verify_redirect' => env('FRONT_URL'),

  //
  'private_middlewares' => [
    'api', 
    'auth.api:sanctum' // auth:sanctum
  ],

  'roles' => [
    'customer' => [
      'label' => 'Покупатель',
      'badge_class' => 'badge-success',
      'color' => '#198754',
    ],
    'bot' => [
      'label' => 'Бот',
      'badge_class' => 'badge-info',
      'color' => '#0ea5e9',
    ],
    'influencer' => [
      'label' => 'Инфлюенсер',
      'badge_class' => 'badge-warning',
      'color' => '#f59e0b',
    ],
    'manager' => [
      'label' => 'Менеджер',
      'badge_class' => 'badge-primary',
      'color' => '#2563eb',
    ],
  ],

  'default_role' => 'customer',

  'role_fields' => [],

  // REFERRALS & BONUSES
  'referral_enabled' => true,
  'referral_levels' => 3,
  'referral_commissions' => [
      1 => 10, // 1-й уровень - 10%
      2 => 5,  // 2-й уровень - 5%
      3 => 2,  // 3-й уровень - 2%
  ],

  'currency_converter' => \Backpack\Store\app\Services\Currency\CurrencyConverter::class,

  'points' => [
    'enabled' => true,
    'key' => 'point',
    'name' => 'VIVA',
    'base' => 'CZK'
  ],

  'bonus' => [
    'account_service' => \Backpack\Profile\app\Services\BonusAccountService::class,
  ],

  'currencies' => [
    'usd' => [
      'code' => 'USD',
      'name' => 'Доллар (США)'
    ],
    'eur' => [
      'code' => 'EUR',
      'name' => 'Евро'
    ],
    'czk' => [
      'code' => 'CZK',
      'name' => 'Чешская крона'
    ],
    'uah' => [
      'code' => 'UAH',
      'name' => 'Гривна'
    ]
  ]
];
