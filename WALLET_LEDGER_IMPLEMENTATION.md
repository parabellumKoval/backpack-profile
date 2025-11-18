# Wallet Ledger API Implementation

## Обзор

Реализован API маршрут для получения истории движений по счету кошелька пользователя с подробной информацией об операциях, включая вознаграждения за отзывы, заказы рефералов, выводы средств и другие операции.

## Компоненты реализации

### 1. API Маршрут

**Файл**: `src/api/packages/profile/src/routes/api/profile.php`

Добавлен маршрут:
```php
Route::get('/wallet/ledger', 'walletLedger')->middleware(['api', 'auth:sanctum']);
```

### 2. Контроллер

**Файл**: `src/api/packages/profile/src/app/Http/Controllers/Api/ProfileController.php`

Добавлен метод `walletLedger()` с следующими возможностями:
- Пагинация (1-100 записей на страницу)
- Фильтрация по типу операции (credit/debit/hold/release/capture)
- Фильтрация по типу ссылки (referral_reward/withdrawal/order и т.д.)
- Обогащение данных подробной информацией об операциях
- Связь с моделями наград и событий

### 3. Request Validation

**Файл**: `src/api/packages/profile/src/app/Http/Requests/WalletLedgerRequest.php`

Валидация параметров запроса:
- `per_page`: 1-100
- `page`: минимум 1
- `type`: credit|debit|hold|release|capture
- `reference_type`: строка до 255 символов

### 4. API Resource

**Файл**: `src/api/packages/profile/src/app/Http/Resources/WalletLedgerResource.php`

Структурированный ответ с:
- Форматированными суммами
- Подробностями операций
- Метаданными
- Датами в читаемом формате

### 5. Обновленная модель

**Файл**: `src/api/packages/profile/src/app/Models/WalletLedger.php`

Добавлены скоупы для удобного использования:
- `forUser(int $userId)` - записи для пользователя
- `ofType(string $type)` - фильтр по типу
- `ofReferenceType(string $referenceType)` - фильтр по типу ссылки
- `recent()` - сортировка по дате создания (убывание)

### 6. Database Migration

**Файл**: `src/api/database/migrations/2025_11_08_000001_add_indexes_to_wallet_ledger_table.php`

Добавлены индексы для оптимизации производительности:
- Составной индекс `user_id + created_at`
- Составной индекс `user_id + type + created_at`
- Составной индекс `user_id + reference_type + created_at`
- Индекс для поиска по ссылкам `reference_type + reference_id`

### 7. Factory для тестов

**Файл**: `src/api/database/factories/Backpack/Profile/App/Models/WalletLedgerFactory.php`

Factory с методами для создания различных типов операций:
- `credit()` - пополнения
- `debit()` - списания
- `hold()` - блокировки
- `referralReward()` - реферальные награды
- `withdrawal()` - выводы средств

### 8. Unit Tests

**Файл**: `src/api/tests/Feature/Profile/WalletLedgerApiTest.php`

Тесты покрывают:
- Авторизацию
- Пагинацию
- Фильтрацию по типам
- Валидацию параметров
- Безопасность (только свои записи)
- Порядок сортировки

## API Документация

### Эндпоинт
```
GET /api/profile/wallet/ledger
```

### Параметры запроса (все опциональные)
- `per_page` (integer, 1-100) - записей на страницу
- `page` (integer, min:1) - номер страницы
- `type` (string) - тип операции (credit/debit/hold/release/capture)
- `reference_type` (string) - тип ссылки

### Пример запроса
```bash
curl -X GET "/api/profile/wallet/ledger?per_page=20&type=credit&reference_type=referral_reward" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Структура ответа
```json
{
  "data": [
    {
      "id": 123,
      "type": "credit",
      "type_label": "Пополнение",
      "amount": {
        "value": "50.00",
        "formatted": "50.00",
        "currency": "VIVAPOINTS"
      },
      "reference": {
        "type": "referral_reward",
        "id": "456"
      },
      "operation_details": {
        "description": "Вознаграждение по реферальной программе",
        "related_data": {
          "trigger": "review.published",
          "trigger_label": "Опубликованный отзыв",
          "event_payload": {...},
          "rewards_count": 1,
          "total_amount": "50.00",
          "currency": "VIVAPOINTS"
        }
      },
      "meta": {...},
      "created_at": "2025-11-08 12:30:45",
      "updated_at": "2025-11-08 12:30:45"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 145,
      "last_page": 8,
      "from": 1,
      "to": 20
    }
  }
}
```

## Типы операций и их описания

### Типы операций (type):
- **credit** - Пополнения счета
- **debit** - Списания со счета
- **hold** - Блокировка средств
- **release** - Разблокировка средств
- **capture** - Подтверждение операции

### Типы ссылок (reference_type):
- **referral_reward** - Вознаграждения по реферальной программе
- **review_reward** - Вознаграждения за отзывы
- **order_reward** - Вознаграждения за заказы
- **withdrawal** - Выводы средств
- **order** - Операции с заказами
- **bonus** - Бонусные начисления
- **refund** - Возвраты средств
- **fee** - Комиссии

## Связи с другими моделями

API автоматически обогащает данные информацией из связанных моделей:

1. **RewardEvent** - события, которые привели к начислению наград
2. **Reward** - детали наград (сумма, получатель, уровень)
3. **User** - информация о пользователе (через связь в модели)

## Триггеры реферальной системы

API поддерживает все зарегистрированные триггеры, включая:
- `review.published` - Опубликованный отзыв
- `store.order_paid` - Оплаченный заказ
- Другие триггеры, определенные в `App\Services\Referral\Triggers`

## Производительность

- Добавлены составные индексы для основных запросов
- Используется пагинация для больших объемов данных
- Оптимизированные скоупы в модели
- Ленивая загрузка связанных данных только при необходимости

## Безопасность

- Обязательная авторизация через Sanctum
- Пользователи видят только свои записи
- Валидация всех входных параметров
- Ограничение размера выборки (максимум 100 записей)

## Тестирование

```bash
# Запуск тестов API
php artisan test tests/Feature/Profile/WalletLedgerApiTest.php

# Запуск миграции с индексами
php artisan migrate
```

## Возможные расширения

1. **Экспорт данных** - добавление возможности экспорта истории в CSV/Excel
2. **Фильтрация по датам** - добавление фильтров по диапазону дат
3. **Группировка данных** - сводки по типам операций или периодам
4. **Уведомления** - интеграция с системой уведомлений о новых операциях
5. **Аналитика** - добавление статистических данных в ответ API