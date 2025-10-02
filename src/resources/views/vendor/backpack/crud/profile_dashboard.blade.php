@extends(backpack_view('blank'))
@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    __('translator::settings.settings_title') => false
  ];

  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
  <div class="container-fluid mb-5">
    <h2>
      <span class="text-capitalize">User & Referral Dashboard</span>
      <small id="datatable_info_stack">Сводная аналитика пользователей, рефералов, начислений и выводов</small>
    </h2>
  </div>
@endsection

@php
  // helper для красивых валют
  $currencyLabel = fn($code) => app(\Backpack\Profile\app\Contracts\CurrencyNameResolver::class)->label($code);
@endphp

@section('content')

  {{-- Фильтры периода --}}
  <div class="d-flex mb-3 align-items-center">
    <form method="get" class="form-inline">
      <label class="mr-2">Период:</label>
      <select name="range" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
        <option value="7d"  {{ $range=='7d'  ? 'selected':'' }}>7 дней</option>
        <option value="30d" {{ $range=='30d' ? 'selected':'' }}>30 дней</option>
        <option value="90d" {{ $range=='90d' ? 'selected':'' }}>90 дней</option>
        <option value="12m" {{ $range=='12m' ? 'selected':'' }}>12 месяцев</option>
      </select>
      <a href="{{ route('bp.profile.dashboard') }}" class="btn btn-sm btn-light">Сбросить</a>
    </form>
  </div>

  {{-- ТОП-карточки --}}
  <div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Всего пользователей</div>
          <div class="h3 mb-0">{{ number_format($top['usersTotal']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Новых за период</div>
          <div class="h3 mb-0">{{ number_format($top['newUsers']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Всего рефералов</div>
          <div class="h3 mb-0">{{ number_format($top['refTotal']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Заявок на вывод (pending)</div>
          <div class="h3 mb-0">{{ number_format($top['pendingWd']) }}</div>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/withdrawals') }}" class="btn btn-sm btn-outline-primary mt-2">Перейти</a>
        </div>
      </div>
    </div>
  </div>

  {{-- Ряды карточек: Rewards по валютам + Триггеры --}}
  <div class="row">
    <div class="col-xl-8 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Награды по валютам</strong>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/rewards') }}" class="btn btn-sm btn-outline-primary">Полный раздел</a>
        </div>
        <div class="card-body">
          <canvas id="rewardsChart" height="110"></canvas>
        </div>
      </div>
    </div>
    <div class="col-xl-4 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Топ триггеров</strong>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/reward-events') }}" class="btn btn-sm btn-outline-primary">Все события</a>
        </div>
        <div class="card-body">
          @forelse($topTriggers as $row)
            <div class="d-flex justify-content-between mb-2">
              <div>{{ $row->trigger }}</div>
              <div><span class="badge badge-secondary">{{ $row->cnt }}</span></div>
            </div>
          @empty
            <div class="text-muted">Нет данных</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Движение по кошельку --}}
  <div class="row">
    <div class="col-xl-12 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Движение по кошельку</strong>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/wallet-ledger') }}" class="btn btn-sm btn-outline-primary">Журнал кошелька</a>
        </div>
        <div class="card-body">
          <canvas id="walletChart" height="110"></canvas>
        </div>
      </div>
    </div>
  </div>

  {{-- Списки: новые рефералы, последние выводы --}}
  <div class="row">
    <div class="col-xl-6 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Новые рефералы</strong>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/profiles') }}" class="btn btn-sm btn-outline-primary">Все пользователи</a>
        </div>
        <div class="card-body">
          @forelse($recentRefs as $r)
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <div>
                <div><strong>{{ trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: 'User #'.$r->user_id }}</strong></div>
                <div class="text-muted small">{{ $r->email ?? 'email@gmail.com' }}</div>
              </div>
              <div class="text-muted small">{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}</div>
            </div>
          @empty
            <div class="text-muted">Нет данных</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-6 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Последние выводы</strong>
          <a href="{{ url(config('backpack.base.route_prefix','admin').'/withdrawals') }}" class="btn btn-sm btn-outline-primary">Все выводы</a>
        </div>
        <div class="card-body">
          @forelse($recentWithdrawals as $w)
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <div>
                <div><strong>{{ number_format($w->amount,2) }} {{ e($w->currency) }}</strong></div>
                <div class="text-muted small">status: {{ $w->status }}</div>
              </div>
              <div class="text-muted small">{{ \Carbon\Carbon::parse($w->created_at)->diffForHumans() }}</div>
            </div>
          @empty
            <div class="text-muted">Нет данных</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

@endsection

@push('after_styles')
  {{-- можно подключить дополнительные стили, если надо --}}
@endpush

@push('after_scripts')
  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      // данные из PHP → JS
      const rewardsLabels = @json($rewardsSeries['labels']);
      const rewardsSeries = @json($rewardsSeries['series']);

      const walletLabels  = @json($walletSeries['labels']);
      const walletSeries  = @json($walletSeries['series']);

      const palette = (i)=> {
        const colors = [
          'rgba(54, 162, 235, 0.7)',
          'rgba(255, 99, 132, 0.7)',
          'rgba(75, 192, 192, 0.7)',
          'rgba(255, 206, 86, 0.7)',
          'rgba(153, 102, 255, 0.7)',
          'rgba(255, 159, 64, 0.7)',
        ];
        return colors[i % colors.length];
      };

      // Rewards by currency (stacked bars)
      const rewardsCtx = document.getElementById('rewardsChart').getContext('2d');
      const rDatasets = Object.keys(rewardsSeries).map((cur, idx)=>({
        label: cur,
        data: rewardsSeries[cur],
        backgroundColor: palette(idx),
        borderWidth: 1,
        stack: 'rewards',
      }));
      new Chart(rewardsCtx, {
        type: 'bar',
        data: { labels: rewardsLabels, datasets: rDatasets },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { x: { stacked: true }, y: { stacked: true } }
        }
      });

      // Wallet flows (lines)
      const walletCtx = document.getElementById('walletChart').getContext('2d');
      const wKeys = Object.keys(walletSeries);
      const wDatasets = wKeys.map((k, i)=>({
        label: k,
        data: walletSeries[k],
        backgroundColor: palette(i),
        borderColor: palette(i),
        borderWidth: 2,
        fill: false,
        tension: 0.3,
      }));
      new Chart(walletCtx, {
        type: 'line',
        data: { labels: walletLabels, datasets: wDatasets },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { y: { beginAtZero: true } }
        }
      });
    })();
  </script>
@endpush
