@php
    $rewardStats = $rewardStats ?? ['perCurrency' => collect(), 'latestRewards' => collect()];
    $perCurrency = $rewardStats['perCurrency'] ?? collect();
    $latestRewards = $rewardStats['latestRewards'] ?? collect();
    $currencyMax = max(($perCurrency->max('total') ?? 0), 1);
@endphp

<div class="card shadow-sm h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Награды</strong>
        <a href="{{ backpack_url('rewards') }}" class="btn btn-sm btn-outline-warning text-warning">Полный раздел</a>
    </div>
    <div class="card-body">
        <div class="mb-3">
            @forelse($perCurrency as $row)
                @php
                    $pct = $currencyMax ? round(($row->total / $currencyMax) * 100) : 0;
                @endphp
                <div class="d-flex justify-content-between">
                    <div>{{ $row->currency ?? '—' }}</div>
                    <div><strong>{{ number_format($row->total, 2, '.', ' ') }}</strong></div>
                </div>
                <div class="progress progress-xs mb-2">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pct }}%"></div>
                </div>
            @empty
                <div class="text-muted">Нет данных по валютам</div>
            @endforelse
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Сумма</th>
                        <th>Когда</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestRewards as $reward)
                        <tr>
                            <td>{{ trim(($reward->first_name ?? '').' '.($reward->last_name ?? '')) ?: 'ID #'.$reward->id }}</td>
                            <td>
                                <span class="badge badge-light">
                                    {{ number_format($reward->amount, 2, '.', ' ') }} {{ $reward->currency }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ \Carbon\Carbon::parse($reward->created_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Нет начислений</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
