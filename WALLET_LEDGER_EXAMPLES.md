# Примеры использования Wallet Ledger API

## Базовый запрос

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## Получение только пополнений счета

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?type=credit" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## Получение только реферальных наград

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?reference_type=referral_reward" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## Получение истории выводов средств

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?reference_type=withdrawal" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## Пагинация - вторая страница по 5 записей

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?per_page=5&page=2" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## Комбинированный запрос - только блокировки средств для выводов

```bash
curl -X GET "https://your-domain.com/api/profile/wallet/ledger?type=hold&reference_type=withdrawal" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

## JavaScript пример

```javascript
// Получение истории кошелька
async function getWalletHistory(token, options = {}) {
  const params = new URLSearchParams(options);
  
  try {
    const response = await fetch(`/api/profile/wallet/ledger?${params}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching wallet history:', error);
    throw error;
  }
}

// Использование
getWalletHistory('your_token_here', {
  per_page: 20,
  type: 'credit',
  reference_type: 'referral_reward'
}).then(data => {
  console.log('Wallet history:', data.data);
  console.log('Total entries:', data.meta.pagination.total);
});
```

## React компонент пример

```jsx
import React, { useState, useEffect } from 'react';

const WalletHistory = ({ token }) => {
  const [history, setHistory] = useState([]);
  const [pagination, setPagination] = useState({});
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    type: '',
    reference_type: '',
    per_page: 10,
    page: 1
  });

  useEffect(() => {
    fetchWalletHistory();
  }, [filters]);

  const fetchWalletHistory = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v))
      );
      
      const response = await fetch(`/api/profile/wallet/ledger?${params}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        }
      });
      
      const data = await response.json();
      setHistory(data.data);
      setPagination(data.meta.pagination);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value, page: 1 }));
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      {/* Фильтры */}
      <div style={{ marginBottom: '20px' }}>
        <select 
          value={filters.type} 
          onChange={(e) => handleFilterChange('type', e.target.value)}
        >
          <option value="">Все типы операций</option>
          <option value="credit">Пополнения</option>
          <option value="debit">Списания</option>
          <option value="hold">Блокировки</option>
          <option value="release">Разблокировки</option>
          <option value="capture">Подтверждения</option>
        </select>
        
        <select 
          value={filters.reference_type} 
          onChange={(e) => handleFilterChange('reference_type', e.target.value)}
        >
          <option value="">Все источники</option>
          <option value="referral_reward">Реферальные награды</option>
          <option value="review_reward">Награды за отзывы</option>
          <option value="withdrawal">Выводы средств</option>
          <option value="order">Заказы</option>
        </select>
      </div>

      {/* История транзакций */}
      <div>
        {history.map(entry => (
          <div key={entry.id} style={{ 
            border: '1px solid #ccc', 
            padding: '10px', 
            marginBottom: '10px' 
          }}>
            <div>
              <strong>{entry.type_label}</strong> - 
              {entry.amount.formatted} {entry.amount.currency}
            </div>
            <div>{entry.operation_details.description}</div>
            <div>
              <small>{entry.created_at}</small>
            </div>
            {entry.operation_details.related_data && (
              <div style={{ marginTop: '5px', fontSize: '0.9em', color: '#666' }}>
                {entry.operation_details.related_data.trigger_label}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Пагинация */}
      <div style={{ textAlign: 'center', marginTop: '20px' }}>
        <button 
          disabled={pagination.current_page === 1}
          onClick={() => handleFilterChange('page', pagination.current_page - 1)}
        >
          Предыдущая
        </button>
        <span style={{ margin: '0 10px' }}>
          Страница {pagination.current_page} из {pagination.last_page}
        </span>
        <button 
          disabled={pagination.current_page === pagination.last_page}
          onClick={() => handleFilterChange('page', pagination.current_page + 1)}
        >
          Следующая
        </button>
      </div>
      
      <div style={{ textAlign: 'center', marginTop: '10px' }}>
        <small>
          Показано {pagination.from}-{pagination.to} из {pagination.total} записей
        </small>
      </div>
    </div>
  );
};

export default WalletHistory;
```

## PHP пример (для интеграции в другие части приложения)

```php
<?php

use Illuminate\Support\Facades\Http;

class WalletService
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    public function getWalletHistory(array $filters = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/api/profile/wallet/ledger', $filters);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch wallet history: ' . $response->body());
        }

        return $response->json();
    }

    public function getCreditOperations(int $perPage = 15): array
    {
        return $this->getWalletHistory([
            'type' => 'credit',
            'per_page' => $perPage
        ]);
    }

    public function getReferralRewards(int $perPage = 15): array
    {
        return $this->getWalletHistory([
            'type' => 'credit',
            'reference_type' => 'referral_reward',
            'per_page' => $perPage
        ]);
    }

    public function getWithdrawals(int $perPage = 15): array
    {
        return $this->getWalletHistory([
            'reference_type' => 'withdrawal',
            'per_page' => $perPage
        ]);
    }
}

// Использование
$walletService = new WalletService('https://your-domain.com', 'user_token');

// Получить все пополнения
$credits = $walletService->getCreditOperations(20);

// Получить реферальные награды
$rewards = $walletService->getReferralRewards();

// Получить историю выводов
$withdrawals = $walletService->getWithdrawals();
```