@php
    $topUsers = collect($topUsers ?? []);
    $storeCurrency = $storeCurrency ?? config('backpack.store.base_currency', 'USD');
    $maxOrders = max((int) ($maxOrders ?? 1), 1);
    $columnCount = (int) ($columnCount ?? 8);

    $statusBadge = $statusBadge ?? function ($user) {
        $score = ($user->referrals_total ?? 0) + ($user->order_total_orders ?? 0);
        if ($score >= 20) return 'success';
        if ($score >= 5) return 'warning';
        return 'secondary';
    };

    $countryFlagEmoji = $countryFlagEmoji ?? function (?string $code): ?string {
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

@forelse($topUsers as $user)
    @php
        $createdAt = $user->created_at ?? null;
        if ($createdAt && ! $createdAt instanceof \Carbon\Carbon) {
            try {
                $createdAt = \Carbon\Carbon::parse($createdAt);
            } catch (\Exception $exception) {
                $createdAt = null;
            }
        }

        $ordersPercent = $maxOrders ? min(100, round((($user->order_total_orders ?? 0) / $maxOrders) * 100)) : 0;
        $avatarUrl = $user->avatar_url ?? null;
        $initials = collect(explode(' ', (string) ($user->full_name ?? '')))
            ->filter()
            ->map(fn($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
        $statusClass = $statusBadge($user);
        $flagCode = strtoupper($user->country_code ?? '');
        $flagEmoji = $flagCode ? $countryFlagEmoji($flagCode) : null;
        $referralLevels = (array) ($user->referrals_levels ?? []);
        $createdDate = $createdAt ? $createdAt->format('d.m.Y') : '—';
        $createdHuman = $createdAt ? $createdAt->diffForHumans() : '—';
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
                $totalReferrals = $user->referrals_total ?? 0;
            @endphp
            @if(!empty($referralLevels))
                <div class="referral-levels">
                    @foreach($referralLevels as $level => $count)
                        <span class="badge badge-light" title="Уровень {{ $level }}">L{{ $level }}: {{ $count }}</span>
                    @endforeach
                </div>
                <div class="badge badge-info mt-2">Всего: {{ $totalReferrals }}</div>
            @else
                <span class="text-muted">—</span>
            @endif
        </td>
        <td class="align-middle">
            {{ $user->sponsor_name ?? '—' }}
        </td>
        <td class="align-middle">
            <div>{{ $createdDate }}</div>
            <div class="small text-muted">{{ $createdHuman }}</div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="{{ $columnCount }}" class="text-center text-muted py-4">Нет данных для отображения</td>
    </tr>
@endforelse
