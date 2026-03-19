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

  'bot_generation' => [
    'default_password' => env('PROFILE_BOT_PASSWORD', 'bot228vivadzen'),
    'generate_avatars_by_default' => (bool) env('PROFILE_BOT_GENERATE_AVATARS', true),
    'avatar_driver' => env('PROFILE_BOT_AVATAR_DRIVER', 'gemini'),
    'avatar_model' => env('PROFILE_BOT_AVATAR_MODEL', 'gemini-2.5-flash-image'),
    'avatar_folder' => env('PROFILE_BOT_AVATAR_FOLDER', 'avatars'),
    'avatar_face_ratio' => (float) env('PROFILE_BOT_AVATAR_FACE_RATIO', 0.4),
    'avatar_prompt_planner_enabled' => (bool) env('PROFILE_BOT_AVATAR_PROMPT_PLANNER_ENABLED', true),
    'avatar_prompt_driver' => env('PROFILE_BOT_AVATAR_PROMPT_DRIVER', 'openai'),
    'avatar_prompt_model' => env('PROFILE_BOT_AVATAR_PROMPT_MODEL', 'gpt-5.1'),
    'avatar_prompt_temperature' => (float) env('PROFILE_BOT_AVATAR_PROMPT_TEMPERATURE', 1.1),
    'avatar_prompt' => [
      'templates' => [
        'common_intro' => 'Generate a realistic social avatar image for a regular user profile.',
        'common_no_text' => 'No logos, no watermark, no text overlays, no brand marks.',
        'face_template' => "Show one real-looking person (not a model, not studio).\nCamera angle: :face_camera_angle.\nFraming: :face_framing.\nCapture quality: :face_quality.\nFilter/edit style: :face_filter.\nAccessory hint: :face_accessory.\nSubject vibe: :face_subject.\nBackground: :face_background.\nKeep it candid, imperfect, and natural.",
        'non_face_template' => "Do not show a clear human face.\nMain concept: :non_face_concept.\nVisual style: :non_face_style.\nKeep it like a real user-picked avatar, not polished commercial art.",
      ],
      'variants' => [
        'face_camera_angle' => [
          ['text' => 'frontal selfie, eye-level', 'weight' => 40],
          ['text' => 'slightly above eye level', 'weight' => 20],
          ['text' => 'slightly below eye level', 'weight' => 10],
          ['text' => 'small head turn under 15 degrees', 'weight' => 20],
          ['text' => 'near-frontal mirror selfie', 'weight' => 6],
          ['text' => 'around 45-degree side angle', 'weight' => 3],
          ['text' => 'strong side profile', 'weight' => 1],
        ],
        'face_framing' => [
          ['text' => 'off-center with asymmetric crop', 'weight' => 28],
          ['text' => 'slightly tilted handheld framing', 'weight' => 20],
          ['text' => 'face partially cropped by frame edge', 'weight' => 16],
          ['text' => 'casual centered framing but not perfect', 'weight' => 18],
          ['text' => 'mirror shot with phone partly visible', 'weight' => 10],
          ['text' => 'candid close crop with background clutter', 'weight' => 8],
        ],
        'face_quality' => [
          ['text' => 'compressed smartphone quality with mild noise', 'weight' => 42],
          ['text' => 'normal smartphone quality, acceptable sharpness', 'weight' => 33],
          ['text' => 'mixed light, slight blur and low dynamic range', 'weight' => 15],
          ['text' => 'high clarity and clean focus', 'weight' => 10],
        ],
        'face_filter' => [
          ['text' => 'no filter, natural colors', 'weight' => 55],
          ['text' => 'subtle warm amateur filter', 'weight' => 10],
          ['text' => 'black and white filter with grain', 'weight' => 10],
          ['text' => 'oversaturated acid-like color edit', 'weight' => 8],
          ['text' => 'vintage faded filter', 'weight' => 7],
          ['text' => 'harsh high-contrast filter', 'weight' => 5],
          ['text' => 'cheap beauty-app artifacts', 'weight' => 5],
        ],
        'face_accessory' => [
          ['text' => 'no notable accessories', 'weight' => 70],
          ['text' => 'clear frame glasses', 'weight' => 8],
          ['text' => 'dark sunglasses', 'weight' => 5],
          ['text' => 'funny novelty glasses', 'weight' => 3],
          ['text' => 'beanie or knit hat', 'weight' => 5],
          ['text' => 'hoodie and scarf', 'weight' => 4],
          ['text' => 'baseball cap', 'weight' => 3],
          ['text' => 'headphones visible', 'weight' => 2],
        ],
        'face_subject' => [
          ['text' => 'ordinary person, everyday look, non-model features', 'weight' => 45],
          ['text' => 'average working-day appearance, simple clothes', 'weight' => 30],
          ['text' => 'slightly tired but natural expression', 'weight' => 12],
          ['text' => 'friendly casual expression, realistic skin texture', 'weight' => 13],
        ],
        'face_background' => [
          ['text' => 'apartment room with real clutter', 'weight' => 26],
          ['text' => 'hallway or elevator area', 'weight' => 14],
          ['text' => 'street fragment in natural light', 'weight' => 18],
          ['text' => 'public transport or station-like scene', 'weight' => 12],
          ['text' => 'car interior or parking area', 'weight' => 10],
          ['text' => 'kitchen or bathroom mirror context', 'weight' => 20],
        ],
        'non_face_concept' => [
          ['text' => 'abstract color textures and gradients', 'weight' => 16],
          ['text' => 'urban detail snapshot (sign fragment, wall texture)', 'weight' => 14],
          ['text' => 'nature detail (leaves, sky, water reflection, stone)', 'weight' => 16],
          ['text' => 'pet-focused candid avatar concept', 'weight' => 14],
          ['text' => 'everyday object close-up from desk or room', 'weight' => 14],
          ['text' => 'stylized non-human character doodle', 'weight' => 12],
          ['text' => 'retro low-fi random room detail', 'weight' => 14],
        ],
        'non_face_style' => [
          ['text' => 'casual smartphone capture look', 'weight' => 40],
          ['text' => 'slightly edited with amateur filter', 'weight' => 20],
          ['text' => 'soft noise and imperfect focus', 'weight' => 20],
          ['text' => 'high contrast playful edit', 'weight' => 10],
          ['text' => 'grainy vintage-like look', 'weight' => 10],
        ],
      ],
      'prevent_immediate_repeat' => true,
      'repeat_penalty_factor' => 0.35,
      'distribution' => [
        'face_side_45_max_percent' => 4,
        'face_high_quality_max_percent' => 15,
        'face_filtered_target_percent' => 40,
        'face_accessories_target_percent' => 30,
      ],
    ],
    'avatar_size' => [
      'width' => (int) env('PROFILE_BOT_AVATAR_WIDTH', 200),
      'height' => (int) env('PROFILE_BOT_AVATAR_HEIGHT', 200),
    ],
    'avatar_jpeg_quality' => (int) env('PROFILE_BOT_AVATAR_JPEG_QUALITY', 84),
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
