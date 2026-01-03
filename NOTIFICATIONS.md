# Уведомления: поля и бродкастинг

## Поля уведомления (Admin → Notifications)

- `target_type`
  - `broadcast` — одно уведомление для всех (одна запись в БД).
  - `personal` — персональное уведомление (нужен `user_id`).
- `audience` (только для `broadcast`)
  - `all` — всем.
  - `authenticated` — только авторизованным.
  - `guest` — только гостям.
- `variant`
  - `info | success | warning | error` — визуальный тип (цвет/статус).
- `kind`
  - `manual | event | system` — источник (для учета/аналитики).
- `user_id`
  - обязателен при `target_type=personal`.
- `title`, `excerpt`, `body`
  - переводимые поля: заголовок / краткий текст / полный текст.
- `meta.action_url`, `meta.action_label`
  - ссылка/кнопка в подробном просмотре.
- `is_pinned`
  - закрепить вверху списка.
- `is_active`
  - показывать/скрыть уведомление.
- `published_at`
  - когда опубликовать (можно в будущем).
- `expires_at`
  - когда скрывать.

## Поля шаблона события (Admin → Notification Events)

- `key` — системный ключ события (например `order_status_changed`).
- `variant`, `audience`, `target_type`, `is_pinned`, `is_active` — дефолты.
- `title`, `excerpt`, `body` — шаблоны (поддерживают `{{placeholder}}`).
- `meta`, `options` — любые доп. параметры для шаблонов.

## Реальный бродкастинг (локально через soketi)

1) Поднять контейнер websocket сервера:

```bash
docker compose -f docker-compose.dev.yml up -d soketi
```

2) Настроить API (`src/api/.env` или общий `.env`):

```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=local
PUSHER_APP_KEY=local
PUSHER_APP_SECRET=local
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

Нужен пакет `pusher/pusher-php-server`:

```bash
cd src/api
composer require pusher/pusher-php-server
```

Если API работает в Docker-контейнере, укажи `PUSHER_HOST=soketi`.

3) Настроить фронтенд (`src/front/.env`):

```
PUSHER_APP_KEY=local
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
NOTIFICATIONS_POLLING_MS=15000
```

4) Перезапустить API и фронт.

## Примечания

- Если бродкастинг не доступен, фронт использует polling.
- Звуковой “пилик” включается при новых уведомлениях (фронт).

## Как отправлять уведомления по событиям

### Общий подход

1) Создай шаблон события в админке (Admin → Notification Events) и задай `key`.
2) В нужном event listener вызови сервис уведомлений:

```php
app(\Backpack\Profile\app\Services\NotificationService::class)
    ->createFromEvent('order.status.changed', [
        'order_id' => $order->id,
        'status' => $order->status,
    ], [
        'meta' => [
            'action_url' => '/account/orders/'.$order->id,
            'action_label' => 'Открыть заказ',
        ],
    ], $order->user_id);
```

В шаблонах (`title`, `excerpt`, `body`) можно использовать `{{order_id}}`, `{{status}}` — они подставятся из массива контекста.

### Где регистрировать listener

Есть несколько источников событий, к которым можно привязаться:

- **События отзывов**: `Backpack\Reviews\app\Events\ReviewPublished`  
  Диспатчится в `src/api/packages/reviews/src/app/Observers/ReviewObserver.php`.  
  Используется для пересинхронизации товара и уведомлений.

- **События рефералов/бонусов**:  
  `Backpack\Profile\app\Events\ReferralAttached`,  
  `Backpack\Profile\app\Events\RewardLedgerEntryCreated`,  
  `Backpack\Profile\app\Events\WithdrawalApproved`,  
  `Backpack\Profile\app\Events\WithdrawalPaid`.  
  Подписки видны в `src/api/app/Providers/EventServiceProvider.php`.

- **События магазина**:  
  `Backpack\Store\app\Events\OrderCreated`, `OrderRejected`, `OrderDeleted` и др.  
  Подписки в `src/api/packages/store/src/app/Providers/EventServiceProvider.php`.

- **Observer‑ы заказа**:  
  `src/api/app/Observers/OrderObserver.php` — уведомления при смене статуса.

### Что уже реализовано

Сейчас автоматическая отправка уведомлений уже включена для ключевых событий:

- `referral.attached` — новый реферал (`src/api/app/Listeners/SendReferralNotification.php`).
- `wallet.reward.created` — начисление награды/бонуса в Wallet Ledger (`src/api/app/Listeners/SendRewardLedgerNotification.php`).
- `withdrawal.approved` и `withdrawal.paid` — статус вывода средств (`src/api/app/Listeners/SendWithdrawalNotification.php`).
- `review.published` — публикация отзыва (`src/api/app/Listeners/SendReviewPublishedNotification.php`).
- `order.status.changed`, `order.payment.changed`, `order.delivery.changed` — смена статусов заказа (`src/api/app/Observers/OrderObserver.php`).

Если нужно добавить новое событие — создай `Notification Event` и добавь listener с `NotificationService::createFromEvent` или `NotificationService::create`.
