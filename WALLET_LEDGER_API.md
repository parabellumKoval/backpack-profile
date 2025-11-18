# Wallet Ledger API

API маршрут для получения истории движений по кошельку пользователя.

## Эндпоинт

```
GET /api/profile/wallet/ledger
```

## Аутентификация

Требуется авторизация через Sanctum токен.

```
Authorization: Bearer {token}
```

## Параметры запроса

Все параметры опциональны:

| Параметр | Тип | Описание | Пример |
|----------|-----|----------|--------|
| `per_page` | integer | Количество записей на страницу (1-100) | `15` |
| `page` | integer | Номер страницы | `1` |
| `type` | string | Фильтр по типу операции | `credit`, `debit`, `hold`, `release`, `capture` |
| `reference_type` | string | Фильтр по типу ссылки | `referral_reward`, `review_reward`, `withdrawal`, `order` |

## Пример запроса

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?per_page=20&type=credit" \
  -H "Authorization: Bearer your_token_here" \
  -H "Accept: application/json"
```

## Пример ответа

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
          "event_payload": {
            "review_id": 789,
            "user_id": 123,
            "rating": 5
          },
          "rewards_count": 1,
          "total_amount": "50.00",
          "currency": "VIVAPOINTS"
        }
      },
      "meta": {
        "reward_event_id": 456,
        "additional_info": "Review reward for 5-star rating"
      },
      "created_at": "2025-11-08 12:30:45",
      "updated_at": "2025-11-08 12:30:45"
    },
    {
      "id": 122,
      "type": "hold",
      "type_label": "Блокировка",
      "amount": {
        "value": "100.00",
        "formatted": "100.00",
        "currency": "VIVAPOINTS"
      },
      "reference": {
        "type": "withdrawal",
        "id": "789"
      },
      "operation_details": {
        "description": "Блокировка средств для вывода",
        "related_data": {
          "withdrawal_id": "789",
          "status": "hold",
          "meta": {
            "withdrawal_method": "bank_transfer"
          }
        }
      },
      "meta": {
        "withdrawal_method": "bank_transfer",
        "bank_account": "****1234"
      },
      "created_at": "2025-11-08 10:15:30",
      "updated_at": "2025-11-08 10:15:30"
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

## Типы операций

### type (Тип операции)

- `credit` - Пополнение счета
- `debit` - Списание со счета
- `hold` - Блокировка средств
- `release` - Разблокировка средств
- `capture` - Подтверждение операции

### reference_type (Тип ссылки)

- `referral_reward` - Вознаграждение по реферальной программе
- `review_reward` - Вознаграждение за отзыв
- `order_reward` - Вознаграждение за заказ
- `withdrawal` - Вывод средств
- `order` - Оплата заказа
- `bonus` - Бонусное начисление
- `refund` - Возврат средств
- `fee` - Комиссия

## Описания операций

### Для пополнений (credit):
- **referral_reward**: "Вознаграждение по реферальной программе"
- **review_reward**: "Вознаграждение за отзыв"
- **order_reward**: "Вознаграждение за заказ"
- **bonus**: "Бонусное начисление"
- **refund**: "Возврат средств"

### Для списаний (debit):
- **withdrawal**: "Вывод средств"
- **order**: "Оплата заказа"
- **fee**: "Комиссия"

### Для блокировок (hold):
- **withdrawal**: "Блокировка средств для вывода"
- **order**: "Блокировка средств для заказа"

### Для разблокировок (release):
- **withdrawal**: "Разблокировка средств (отмена вывода)"
- **order**: "Разблокировка средств (отмена заказа)"

### Для подтверждений (capture):
- **withdrawal**: "Подтверждение вывода средств"
- **order**: "Подтверждение оплаты заказа"

## Связанные данные

### Для наградных операций (referral_reward, review_reward, order_reward):

Включает информацию о триггере и связанном событии:

```json
"related_data": {
  "trigger": "review.published",
  "trigger_label": "Опубликованный отзыв",
  "event_payload": {
    "review_id": 789,
    "user_id": 123,
    "rating": 5
  },
  "rewards_count": 1,
  "total_amount": "50.00",
  "currency": "VIVAPOINTS"
}
```

### Для операций вывода (withdrawal):

```json
"related_data": {
  "withdrawal_id": "789",
  "status": "hold",
  "meta": {
    "withdrawal_method": "bank_transfer"
  }
}
```

### Для операций с заказами (order):

```json
"related_data": {
  "order_id": "123",
  "meta": {
    "order_total": "250.00",
    "order_currency": "RUB"
  }
}
```

## Коды ошибок

- `401 Unauthorized` - Не авторизован
- `422 Unprocessable Entity` - Неверные параметры запроса
- `500 Internal Server Error` - Внутренняя ошибка сервера