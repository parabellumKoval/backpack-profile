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
}
