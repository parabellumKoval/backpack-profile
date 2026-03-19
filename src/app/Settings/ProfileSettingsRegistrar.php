<?php
namespace Backpack\Profile\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

use Backpack\Profile\app\Services\TriggerRegistry;
use Backpack\Profile\app\Contracts\ReferralTrigger;

class ProfileSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        /** @var TriggerRegistry $triggersRegistry */
        $triggersRegistry = app(TriggerRegistry::class);
        /** @var array<string, class-string<ReferralTrigger>> $triggers */
        $triggers = $triggersRegistry->all();

        // Валюты для select (VIVAPOINTS + ISO)
        $currencyOptions = \Profile::currencyOptions();
        $currencyOptionsFiat = \Profile::currencyOptions(true);

        $registry->group('profile', function ($group) use ($triggers, $currencyOptions, $currencyOptionsFiat) {
            $group->title('Настройки профиля')->icon('la la-user-cog')

                // -------------------- Страница "Пользователи"
                ->page('Пользователи', function ($page) {
                    $page
                        ->add(
                            Field::make('profile.users.allow_registration', 'checkbox')
                                ->label('Разрешить регистрацию')
                                ->default(true)
                                ->cast('bool')
                                ->hint('Включает/выключает возможность самостоятельной регистрации пользователей.')
                                ->tab('Общее')
                        )
                        ->add(
                            Field::make('profile.users.require_email_verification', 'checkbox')
                                ->label('Требовать подтверждение email')
                                ->default(true)
                                ->cast('bool')
                                ->hint('При включении новые пользователи должны подтвердить email перед входом.')
                                ->tab('Общее')
                        )
                        ->add(
                            Field::make('profile.users.default_role', 'text')
                                ->label('Роль по умолчанию')
                                ->default('customer')
                                ->cast('string')
                                ->hint('Ключ роли, назначаемой новому пользователю (например, "customer").')
                                ->tab('Общее')
                        )
                        ->add(
                            Field::make('profile.users.default_locale', 'text')
                                ->label('Локаль по умолчанию')
                                ->default('uk')
                                ->cast('string')
                                ->hint('Например: uk, ru, en. Используется при создании аккаунта.')
                                ->tab('Общее')
                        );
                    $page
                        ->add(
                            Field::make('profile.users.allow_personal_discount', 'checkbox')
                                ->label('Включить персональные скидки')
                                ->default(true)
                                ->cast('bool')
                                ->hint('Если включено при оформлении заказа будет учтена персональная скидка пользователя (если она имеется)')
                                ->tab('Другое')
                        );
                })

                // -------------------- Страница "Реферальная система"
                ->page('Реферальная система', function ($page) use ($currencyOptions, $currencyOptionsFiat) {
                    // Глобальные настройки рефералок
                    $page
                        ->add(
                            Field::make('profile.referrals.enabled', 'checkbox')
                                ->label('Включить реферальную систему')
                                ->default(true)
                                ->cast('bool')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.url_param', 'text')
                                ->label('Ключ параметра для реферального кода')
                                ->cast('string')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.cookie.name', 'text')
                                ->label('Ключ реферального кода в куках')
                                ->cast('string')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.link_ttl_days', 'number')
                                ->label('TTL в кол-ве дней')
                                ->cast('number')
                                ->hint('Какое колличество дней после первичного перехода по ссылке пользователь считается закрепленным за спонсором')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.log_clicks', 'checkbox')
                                ->label('Логирование переходов')
                                ->default(true)
                                ->cast('bool')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.allow_orders_without_registration_via_ref', 'checkbox')
                                ->label('Разрешить заказ по реферальной ссылке без регистрации')
                                ->default(true)
                                ->cast('bool')
                                ->tab('Глобальные')
                        );
                })

                ->page('Бонусный счет', function ($page) use ($currencyOptions, $currencyOptionsFiat) {
                    // Глобальные настройки рефералок
                    $page
                        ->add(
                            Field::make('profile.pay_for_order.enabled', 'checkbox')
                                ->label('Разрешить оплату заказа с бонусного счета')
                                ->default(true)
                                ->cast('bool')
                                ->tab('Глобальные')
                        )
                        ->add(
                            Field::make('profile.referrals.default_currency', 'select_from_array')
                                ->label('Валюта начислений по-умолчанию')
                                ->options($currencyOptions)
                                ->cast('string')
                                ->hint('Используется, если у правила/уровня не задана своя валюта.')
                                ->tab('Валюты')
                        )
                        ->add(
                            Field::make('profile.points.name', 'text')
                                ->label('Название “балльной” валюты')
                                ->cast('string')
                                ->tab('Валюты')
                        )
                        ->add(
                            Field::make('profile.points.base', 'select_from_array')
                                ->label('Базовая валюта для баллов')
                                ->options($currencyOptionsFiat)
                                ->default('CZK')
                                ->cast('string')
                                ->hint('1 балл = 1 единица выбранной базовой валюты (по умолчанию 1:1 к CZK).')
                                ->tab('Валюты')
                        );

                    $page
                        ->add(
                            Field::make('profile.withdrawal.enabled', 'checkbox')
                                ->label('Разрешить вывод средств')
                                ->default(true)
                                ->cast('bool')
                                ->tab('Вывод средств')
                        )
                        ->add(
                            Field::make('profile.withdrawal.minAmount', 'number')
                                ->label('Минимальная сумма вывод (в валюте по-умолчанию)')
                                ->cast('float')
                                ->tab('Вывод средств')
                        );
                })

                ->page('Генерация аватаров ботов', function ($page) {
                    $driverOptions = collect((array) config('ai-content-generator.drivers', []))
                        ->mapWithKeys(fn (array $cfg, string $key) => [$key => (string) ($cfg['name'] ?? strtoupper($key))])
                        ->all();

                    if ($driverOptions === []) {
                        $driverOptions = [
                            'gemini' => 'Gemini',
                            'openai' => 'OpenAI',
                            'grok' => 'Grok',
                        ];
                    }

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt._info', 'custom_html')
                            ->label('Как это работает')
                            ->default('<div class="alert alert-info mb-0">Настройка аватаров ботов: шаблоны + варианты с весами. Вес работает как вероятность. Для лиц боковой угол ~45° и "слишком качественные" кадры можно ограничивать в процентах. Генерация старается держать естественный UGC-стиль, а не студийные портреты.</div>')
                            ->tab('Справка')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.generate_avatars_by_default', 'checkbox')
                            ->label('Генерировать аватарки по умолчанию')
                            ->default((bool) config('backpack.profile.bot_generation.generate_avatars_by_default', true))
                            ->cast('bool')
                            ->tab('Общее')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_face_ratio', 'number')
                            ->label('Доля аватаров с лицами (0..1)')
                            ->default((float) config('backpack.profile.bot_generation.avatar_face_ratio', 0.4))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 1, 'step' => 0.01])
                            ->tab('Общее')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_size.width', 'number')
                            ->label('Ширина аватарки')
                            ->default((int) config('backpack.profile.bot_generation.avatar_size.width', 200))
                            ->cast('int')
                            ->attributes(['min' => 64, 'max' => 1024, 'step' => 1])
                            ->tab('Общее')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_size.height', 'number')
                            ->label('Высота аватарки')
                            ->default((int) config('backpack.profile.bot_generation.avatar_size.height', 200))
                            ->cast('int')
                            ->attributes(['min' => 64, 'max' => 1024, 'step' => 1])
                            ->tab('Общее')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_jpeg_quality', 'number')
                            ->label('JPEG quality (60..95)')
                            ->default((int) config('backpack.profile.bot_generation.avatar_jpeg_quality', 84))
                            ->cast('int')
                            ->attributes(['min' => 60, 'max' => 95, 'step' => 1])
                            ->tab('Общее')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_driver', 'select_from_array')
                            ->label('AI драйвер изображений')
                            ->options($driverOptions)
                            ->default((string) config('backpack.profile.bot_generation.avatar_driver', 'gemini'))
                            ->cast('string')
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_model', 'text')
                            ->label('AI модель изображений')
                            ->default((string) config('backpack.profile.bot_generation.avatar_model', 'gemini-2.5-flash-image'))
                            ->cast('string')
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt_planner_enabled', 'checkbox')
                            ->label('Включить AI-планировщик разнообразия (1 запрос на батч)')
                            ->default((bool) config('backpack.profile.bot_generation.avatar_prompt_planner_enabled', true))
                            ->cast('bool')
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt_driver', 'select_from_array')
                            ->label('AI драйвер планировщика промптов')
                            ->options($driverOptions)
                            ->default((string) config('backpack.profile.bot_generation.avatar_prompt_driver', 'openai'))
                            ->cast('string')
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt_model', 'text')
                            ->label('AI модель планировщика промптов')
                            ->default((string) config('backpack.profile.bot_generation.avatar_prompt_model', 'gpt-5.1'))
                            ->cast('string')
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt_temperature', 'number')
                            ->label('Температура планировщика промптов')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt_temperature', 1.1))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 2, 'step' => 0.05])
                            ->tab('AI')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.templates.common_intro', 'textarea')
                            ->label('Шаблон: общий intro')
                            ->default($this->avatarTemplateDefault('common_intro'))
                            ->cast('string')
                            ->attributes(['rows' => 2])
                            ->tab('Шаблон')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.templates.common_no_text', 'textarea')
                            ->label('Шаблон: ограничение без текста/логотипов')
                            ->default($this->avatarTemplateDefault('common_no_text'))
                            ->cast('string')
                            ->attributes(['rows' => 2])
                            ->tab('Шаблон')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.templates.face_template', 'textarea')
                            ->label('Шаблон для аватаров с лицами')
                            ->default($this->avatarTemplateDefault('face_template'))
                            ->cast('string')
                            ->attributes(['rows' => 7])
                            ->hint('Плейсхолдеры: :face_camera_angle, :face_framing, :face_quality, :face_filter, :face_accessory, :face_subject, :face_background')
                            ->tab('Шаблон')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.templates.non_face_template', 'textarea')
                            ->label('Шаблон для аватаров без лиц')
                            ->default($this->avatarTemplateDefault('non_face_template'))
                            ->cast('string')
                            ->attributes(['rows' => 5])
                            ->hint('Плейсхолдеры: :non_face_concept, :non_face_style')
                            ->tab('Шаблон')
                    );

                    foreach ([
                        'face_camera_angle' => 'Лица: угол камеры',
                        'face_framing' => 'Лица: кадрирование',
                        'face_quality' => 'Лица: качество',
                        'face_filter' => 'Лица: фильтр/обработка',
                        'face_accessory' => 'Лица: аксессуары',
                        'face_subject' => 'Лица: тип внешности',
                        'face_background' => 'Лица: фон',
                        'non_face_concept' => 'Без лиц: концепт',
                        'non_face_style' => 'Без лиц: стиль',
                    ] as $key => $label) {
                        $page->add(
                            Field::make("profile.bot_generation.avatar_prompt.variants.{$key}", 'repeatable_pure')
                                ->label($label)
                                ->fields($this->weightedVariantSubfields())
                                ->newItemLabel('Добавить вариант')
                                ->default($this->avatarVariantDefault($key))
                                ->cast('array')
                                ->tab('Варианты')
                        );
                    }

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.prevent_immediate_repeat', 'checkbox')
                            ->label('Снижать моментальный повтор одинаковых вариантов')
                            ->default((bool) config('backpack.profile.bot_generation.avatar_prompt.prevent_immediate_repeat', true))
                            ->cast('bool')
                            ->tab('Распределение')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.repeat_penalty_factor', 'number')
                            ->label('Коэффициент штрафа повтора подряд')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt.repeat_penalty_factor', 0.35))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 1, 'step' => 0.05])
                            ->hint('0 = почти исключить повтор подряд, 1 = без штрафа.')
                            ->tab('Распределение')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.distribution.face_side_45_max_percent', 'number')
                            ->label('Лица: максимум бокового угла ~45° (%)')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_side_45_max_percent', 4))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 100, 'step' => 0.5])
                            ->tab('Распределение')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.distribution.face_high_quality_max_percent', 'number')
                            ->label('Лица: максимум “слишком качественных” фото (%)')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_high_quality_max_percent', 15))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 100, 'step' => 0.5])
                            ->tab('Распределение')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.distribution.face_filtered_target_percent', 'number')
                            ->label('Лица: целевой процент фильтров/дилетантской обработки (%)')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_filtered_target_percent', 40))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 100, 'step' => 0.5])
                            ->tab('Распределение')
                    );

                    $page->add(
                        Field::make('profile.bot_generation.avatar_prompt.distribution.face_accessories_target_percent', 'number')
                            ->label('Лица: целевой процент заметных аксессуаров (%)')
                            ->default((float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_accessories_target_percent', 30))
                            ->cast('float')
                            ->attributes(['min' => 0, 'max' => 100, 'step' => 0.5])
                            ->tab('Распределение')
                    );
                })

                ->page('Тригеры', function ($page) use ($triggers, $currencyOptions, $currencyOptionsFiat) {
                    // Динамические вкладки по зарегистрированным триггерам
                    foreach ($triggers as $alias => $class) {
                        /** @var class-string<ReferralTrigger> $class */
                        $label = method_exists($class, 'label') ? $class::label() : $alias;
                        $desc  = method_exists($class, 'description') ? (string)$class::description() : '';

                        $tab = $label; // название вкладки = читаемое имя триггера
                        $baseKey = "profile.referrals.triggers.{$alias}";
                        $cap = $class::capabilities();

                        $page->add(
                                Field::make("{$baseKey}.enabled", 'checkbox')
                                    ->label('Включить')
                                    ->default(true)
                                    ->cast('bool')
                                    ->hint(trim("Триггер: {$alias}" . ($desc ? " — {$desc}" : '')))
                                    ->tab($tab)
                        );

                        // Тип начисления (процент допустим только если supports_percent)
                        $typeOptions = ['fixed' => 'Фикс'];
                        if (!empty($cap['supports_percent'])) $typeOptions['percent'] = 'Процент';

                        $page->add(
                                Field::make("{$baseKey}.type", 'radio')
                                    ->label('Тип начисления')
                                    ->options($typeOptions)
                                    ->default(array_key_first($typeOptions))
                                    ->inline(true)
                                    ->cast('string')
                                    ->tab($tab)
                        );

                        if (!empty($cap['supports_actor'])) {
                            $page->add(
                                Field::make("{$baseKey}.actor_award.amount",'number')
                                    ->label('Автору: сумма (фикс)')
                                    ->attributes(['step'=>'0.01'])
                                    ->cast('float')
                                    ->tab($tab)
                            );

                            // $page->add(
                            //     Field::make("{$baseKey}.actor_award.currency",'select_from_array')
                            //         ->label('Автору: валюта')
                            //         ->options($currencyOptions)
                            //         ->default('VIVAPOINTS')
                            //         ->cast('string')
                            //         ->tab($tab)
                            // );
                        }

                        // Уровни (если supports_levels)
                        if (!empty($cap['supports_levels'])) {
                            // от чего считать проценты уровней
                            $levelsPercentOf = $cap['levels_percent_of'] ?? 'base';
                            $page->add(
                                Field::make("{$baseKey}.levels_percent_of",'select_from_array')
                                    ->label('Проценты уровней считать от')
                                    ->options(['base'=>'Базы','actor'=>'Выплаты автору'])
                                    ->default($levelsPercentOf)
                                    ->cast('string')
                                    ->tab($tab)
                            );

                            // сами уровни (проценты)
                            $page->add(
                                Field::make("{$baseKey}.levels",'repeatable_pure')
                                    ->label('Уровни рефералов')
                                    ->cast('array')
                                    ->fields([
                                        ['name'=>'level','type'=>'number','label'=>'Уровень','default'=>1,'cast'=>'int','attributes'=>['min'=>1,'step'=>1],'wrapper'=>['class' => 'form-group col-md-4']],
                                        ['name'=>'value','type'=>'number','label'=>'Значение','cast'=>'float','attributes'=>['step'=>'0.01'],'wrapper'=>['class' => 'form-group col-md-4']],
                                    ])
                                    ->tab($tab)
                            );
                        }

                        // Общая валюта выплат (куда всё приводим)
                        $page->add(
                            Field::make("{$baseKey}.payout_currency",'select_from_array')
                                ->label('Итоговая валюта выплат')
                                ->options($currencyOptions)
                                ->default('VIVAPOINTS')
                                ->cast('string')
                                ->tab($tab)
                        );
                    }

                    // Если триггеров нет — покажем информативное поле
                    if (empty($triggers)) {
                        $page->add(
                            Field::make('profile.referrals._no_triggers_info', 'custom_html')
                                ->label(false)
                                ->value('<div class="alert alert-info m-0">Триггеры не зарегистрированы. Зарегистрируйте их через TriggerRegistry в вашем приложении.</div>')
                                ->tab('Триггеры')
                        );
                    }
                });
        });
    }

    protected function avatarTemplateDefault(string $key): string
    {
        return (string) config("backpack.profile.bot_generation.avatar_prompt.templates.{$key}", '');
    }

    protected function avatarVariantDefault(string $variant): array
    {
        $rows = config("backpack.profile.bot_generation.avatar_prompt.variants.{$variant}", []);

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $value = trim($row);
                if ($value !== '') {
                    $normalized[] = ['text' => $value, 'weight' => 1];
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $text = trim((string) ($row['text'] ?? $row['value'] ?? ''));
            if ($text === '') {
                continue;
            }

            $weight = (float) ($row['weight'] ?? 1);
            $normalized[] = [
                'text' => $text,
                'weight' => max(0, $weight),
            ];
        }

        return $normalized;
    }

    protected function weightedVariantSubfields(): array
    {
        return [
            [
                'name' => 'text',
                'type' => 'text',
                'label' => 'Вариант',
            ],
            [
                'name' => 'weight',
                'type' => 'number',
                'label' => 'Вес',
                'attributes' => ['min' => 0, 'step' => 1],
            ],
        ];
    }
}
