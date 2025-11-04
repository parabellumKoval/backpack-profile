@php
    /** @var array{profile:\Backpack\Profile\app\Models\Profile,children:array} $node */
    /** @var \Backpack\Profile\app\Models\Profile $nodeProfile */
    $nodeProfile = $node['profile'];
    $children = $node['children'] ?? [];
    $levelIndex = $level ?? 1;
    $user = $nodeProfile->user;
    $wallet = optional($user)->walletBalance;

    $balanceValue = optional($wallet)->balance;
    $balanceCode = optional($wallet)->currency;
    $balanceFormatted = $balanceValue !== null
        ? number_format((float) $balanceValue, 2, '.', ' ')
        : '0.00';

    $balanceLabel = $balanceCode ? currency_label($balanceCode) : null;
    $balanceCode = $balanceCode ? strtoupper($balanceCode) : null;

    $avatarPath = $nodeProfile->photo;
    $avatarUrl = null;

    if ($avatarPath) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : asset($avatarPath);
    }

    $initials = trim(collect([$nodeProfile->firstname, $nodeProfile->lastname])
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode(''));

    if ($initials === '') {
        $fallback = $nodeProfile->login ?? optional($user)->name ?? optional($user)->email ?? 'U';
        $initials = mb_strtoupper(mb_substr($fallback, 0, 1));
    }

    $childrenCount = count($children);
    $email = $nodeProfile->email ?? optional($user)->email;
    $phone = $nodeProfile->phone ?? optional($user)->phone;
    $location = collect([$nodeProfile->country ?? null, $nodeProfile->city ?? null])
        ->filter()
        ->implode(', ');

    $profileUrl = url(config('backpack.base.route_prefix', 'admin') . '/profile/' . $nodeProfile->id . '/show');
@endphp

<li class="referral-tree__item">
    <div class="referral-card referral-card--compact">
        <div class="referral-card__avatar">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $nodeProfile->fullname ?: $nodeProfile->firstname }}" loading="lazy">
            @else
                <span class="referral-card__initials">{{ $initials }}</span>
            @endif
        </div>
        <div class="referral-card__body">
            <div class="referral-card__header">
                <a href="{{ $profileUrl }}" class="referral-card__name" target="_blank" rel="noopener">
                    {{ $nodeProfile->fullname ?: ($nodeProfile->firstname ?: __('Без имени')) }}
                </a>
                <span class="referral-card__badge">Уровень {{ $levelIndex }}</span>
            </div>
            <div class="referral-card__meta">
                @if($email)
                    <span class="referral-card__meta-item">
                        <i class="la la-envelope"></i>
                        <a href="mailto:{{ $email }}">{{ $email }}</a>
                    </span>
                @endif
                @if($phone)
                    <span class="referral-card__meta-item">
                        <i class="la la-phone"></i>
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                    </span>
                @endif
                @if($nodeProfile->referral_code)
                    <span class="referral-card__meta-item">
                        <i class="la la-qrcode"></i>
                        {{ $nodeProfile->referral_code }}
                    </span>
                @endif
                @if($location)
                    <span class="referral-card__meta-item">
                        <i class="la la-map-marker"></i>
                        {{ $location }}
                    </span>
                @endif
            </div>
        </div>
        <div class="referral-card__stats">
            <div class="referral-card__stat">
                <span class="referral-card__stat-label">Баланс</span>
                <span class="referral-card__stat-value">
                    {{ $balanceFormatted }}
                    @if($balanceCode)
                        <span class="referral-card__stat-currency">{{ $balanceCode }}</span>
                    @endif
                </span>
                @if($balanceLabel)
                    <span class="referral-card__stat-hint">{{ $balanceLabel }}</span>
                @endif
            </div>
            <div class="referral-card__stat">
                <span class="referral-card__stat-label">Партнеры</span>
                <span class="referral-card__stat-value">{{ $childrenCount }}</span>
            </div>
        </div>
    </div>

    @if($childrenCount)
        <ul class="referral-children">
            @foreach($children as $child)
                @include('crud::partials.referral-node', ['node' => $child, 'level' => $levelIndex + 1])
            @endforeach
        </ul>
    @endif
</li>
