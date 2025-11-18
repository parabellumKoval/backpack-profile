@php
    $topUsers = collect($topUsers ?? []);
    $storeCurrency = config('backpack.store.base_currency', 'USD');
    $pointsCurrency = strtoupper(config('profile.points.key', 'POINT'));
    $maxOrders = max($topUsers->max('order_total_orders') ?? 0, 1);
    $sortOptions = [
        'created'   => 'Новые пользователи',
        'referrals' => 'Самые активные',
        'orders'    => 'По заказам',
    ];
    $requestedSort = request()->query('top_users_sort');
    $activeSort = array_key_exists($requestedSort, $sortOptions) ? $requestedSort : 'created';

    $statusBadge = function ($user) {
        $score = ($user->referrals_total ?? 0) + ($user->order_total_orders ?? 0);
        if ($score >= 20) return 'success';
        if ($score >= 5) return 'warning';
        return 'secondary';
    };

    $countryFlagEmoji = function (?string $code): ?string {
        $code = strtoupper(trim($code ?? ''));
        if (strlen($code) !== 2) {
            return null;
        }

        $offset = 127397;
        return implode('', array_map(function ($char) use ($offset) {
            return html_entity_decode('&#'.(ord($char) + $offset).';', ENT_NOQUOTES, 'UTF-8');
        }, str_split($code)));
    };
@endphp

<div class="card shadow-sm h-100">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
            <strong>Users & Referrals</strong>
            <div class="text-muted small">Топ активных пользователей по заказам и рефералам</div>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Sorting toggles">
            @foreach($sortOptions as $sortKey => $label)
                <a
                    href="{{ request()->fullUrlWithQuery(['top_users_sort' => $sortKey]) }}"
                    class="btn {{ $activeSort === $sortKey ? 'btn-primary' : 'btn-outline-secondary' }}"
                    aria-pressed="{{ $activeSort === $sortKey ? 'true' : 'false' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-responsive-sm table-striped mb-0">
            <thead class="thead-light">
                <tr>
                    <th class="text-center"><i class="la la-user"></i></th>
                    <th>Пользователь</th>
                    <th class="text-center">Страна</th>
                    <th>Заказы</th>
                    <th>Баланс ({{ $pointsCurrency }})</th>
                    <th>Рефералы</th>
                    <th>Спонсор</th>
                    <th>Регистрация</th>
                </tr>
            </thead>
            <tbody id="profile-top-users-body">
                @forelse($topUsers as $user)
                    @php
                        $createdAt = \Carbon\Carbon::parse($user->created_at);
                        $ordersPercent = $maxOrders ? min(100, round(($user->order_total_orders ?? 0) / $maxOrders * 100)) : 0;
                        $avatarUrl = $user->avatar_url ?: null;
                        $initials = collect(explode(' ', $user->full_name))->filter()->map(fn($part) => mb_substr($part, 0, 1))->take(2)->implode('');
                        $statusClass = $statusBadge($user);
                        $flagCode = strtoupper($user->country_code ?? '');
                        $flagEmoji = $flagCode ? $countryFlagEmoji($flagCode) : null;
                    @endphp
                    <tr>
                        <td class="text-center align-middle">
                            <div class="avatar">
                                @if($avatarUrl)
                                    <img class="img-avatar" src="{{ $avatarUrl }}" alt="{{ $user->full_name }}">
                                @else
                                    <div class="avatar-initial">{{ $initials ?: 'U' }}</div>
                                @endif
                                <span class="avatar-status badge-{{ $statusClass }}"></span>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div>{{ $user->full_name }}</div>
                            <div class="small text-muted">
                                {{ $user->email ?? '—' }}
                                @if($user->phone)
                                    <span class="mx-1">|</span>{{ $user->phone }}
                                @endif
                            </div>
                        </td>
                        <td class="text-center align-middle">
                            @if($flagCode)
                                <div class="flag-wrapper" title="{{ $flagCode }}">
                                    @if($flagEmoji)
                                        <span class="flag-emoji" role="img" aria-label="{{ $flagCode }}">{{ $flagEmoji }}</span>
                                    @endif
                                    <small class="text-muted">{{ $flagCode }}</small>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="align-middle" style="min-width: 200px;">
                            <div class="clearfix">
                                <div class="float-left"><strong>{{ number_format($user->order_total_amount ?? 0, 2, '.', ' ') }} {{ $storeCurrency }}</strong></div>
                                <div class="float-right"><small class="text-muted">{{ $user->order_total_orders ?? 0 }} заказ(ов)</small></div>
                            </div>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $ordersPercent }}%"></div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <strong>{{ number_format($user->wallet_balance ?? 0, 2, '.', ' ') }}</strong>
                        </td>
                        <td class="align-middle">
                            @php
                                $referralLevels = $user->referrals_levels ?? [];
                            @endphp
                            @if(!empty($referralLevels))
                                <div class="referral-levels">
                                    @foreach($referralLevels as $level => $count)
                                        <span class="badge badge-light" title="Уровень {{ $level }}">L{{ $level }}: {{ $count }}</span>
                                    @endforeach
                                </div>
                                <div class="badge badge-info mt-2">Всего: {{ $user->referrals_total ?? 0 }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $user->sponsor_name ?? '—' }}
                        </td>
                        <td class="align-middle">
                            <div>{{ $createdAt->format('d.m.Y') }}</div>
                            <div class="small text-muted">{{ $createdAt->diffForHumans() }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Нет данных для отображения</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('after_styles')
    <style>
        .avatar {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-initial {
            font-weight: 600;
            color: #4b5563;
        }
        .avatar-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 2px solid #fff;
        }
        .flag-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 48px;
        }
        .flag-emoji {
            font-size: 1.5rem;
            line-height: 1;
        }
        .referral-levels {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .referral-levels .badge {
            font-size: 0.75rem;
        }
    </style>
@endpush
