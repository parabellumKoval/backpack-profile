@php
    /** @var \Backpack\Profile\app\Models\Profile $profile */
    $user = $profile->user;
    $wallet = optional($user)->walletBalance;

    $balanceValue = optional($wallet)->balance;
    $balanceCode = optional($wallet)->currency;
    $balanceFormatted = $balanceValue !== null
        ? number_format((float) $balanceValue, 2, '.', ' ')
        : '0.00';
    $balanceLabel = $balanceCode ? currency_label($balanceCode) : null;
    $balanceCode = $balanceCode ? strtoupper($balanceCode) : null;

    $avatarPath = $profile->photo;
    $avatarUrl = null;

    if ($avatarPath) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : asset($avatarPath);
    }

    $initials = trim(collect([$profile->firstname, $profile->lastname])
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode(''));

    if ($initials === '') {
        $fallback = $profile->login ?? optional($user)->name ?? optional($user)->email ?? 'U';
        $initials = mb_strtoupper(mb_substr($fallback, 0, 1));
    }

    $summaryTotal = $summary['total'] ?? 0;
    $levelBreakdown = $summary['levels'] ?? [];
    $directReferrals = $profile->referrals ? $profile->referrals->count() : 0;
    $profileUrl = url(config('backpack.base.route_prefix', 'admin') . '/profile/' . $profile->id . '/show');

    $email = $profile->email ?? optional($user)->email;
    $phone = $profile->phone ?? optional($user)->phone;
    $login = $profile->login ?? optional($user)->name;

    $referrer = $profile->referrer;

    $referrerData = null;
    if ($referrer) {
        $refUser = $referrer->user;
        $refWallet = optional($refUser)->walletBalance;

        $refBalanceValue = optional($refWallet)->balance;
        $refBalanceCode = optional($refWallet)->currency;
        $refBalanceFormatted = $refBalanceValue !== null
            ? number_format((float) $refBalanceValue, 2, '.', ' ')
            : '0.00';
        $refBalanceLabel = $refBalanceCode ? currency_label($refBalanceCode) : null;
        $refBalanceCode = $refBalanceCode ? strtoupper($refBalanceCode) : null;

        $refAvatarPath = $referrer->photo;
        $refAvatarUrl = null;
        if ($refAvatarPath) {
            $refAvatarUrl = \Illuminate\Support\Str::startsWith($refAvatarPath, ['http://', 'https://'])
                ? $refAvatarPath
                : asset($refAvatarPath);
        }

        $refInitials = trim(collect([$referrer->firstname, $referrer->lastname])
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode(''));

        if ($refInitials === '') {
            $refFallback = $referrer->login ?? optional($refUser)->name ?? optional($refUser)->email ?? 'U';
            $refInitials = mb_strtoupper(mb_substr($refFallback, 0, 1));
        }

        $refEmail = $referrer->email ?? optional($refUser)->email;
        $refPhone = $referrer->phone ?? optional($refUser)->phone;
        $refProfileUrl = url(config('backpack.base.route_prefix', 'admin') . '/profile/' . $referrer->id . '/show');

        $referrerData = [
            'avatar' => $refAvatarUrl,
            'initials' => $refInitials,
            'name' => $referrer->fullname ?: ($referrer->firstname ?: __('Без имени')),
            'email' => $refEmail,
            'phone' => $refPhone,
            'code' => $referrer->referral_code,
            'balance_formatted' => $refBalanceFormatted,
            'balance_code' => $refBalanceCode,
            'balance_label' => $refBalanceLabel,
            'url' => $refProfileUrl,
        ];
    }
@endphp

<div class="referral-details">
    <style>
        .referral-details {
            padding: 24px 32px;
            background: #f9fafc;
            color: #1f2937;
            font-size: 14px;
        }

        .referral-card {
            background: #ffffff;
            border: 1px solid #e6e9ef;
            border-radius: 14px;
            padding: 22px 24px;
            display: flex;
            gap: 18px;
            align-items: center;
            box-shadow: 0 12px 32px rgba(15, 34, 58, 0.08);
            margin-bottom: 24px;
            position: relative;
        }

        .referral-card--highlight {
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.8), rgba(255, 255, 255, 0.96));
            border-color: rgba(99, 102, 241, 0.28);
            box-shadow: 0 18px 40px rgba(79, 70, 229, 0.12);
        }

        .referral-card--compact {
            box-shadow: none;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px;
            background: #ffffff;
        }

        .referral-card__avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef1f8;
            font-weight: 600;
            color: #4b5563;
            font-size: 18px;
            text-transform: uppercase;
        }

        .referral-card--compact .referral-card__avatar {
            width: 48px;
            height: 48px;
            font-size: 16px;
        }

        .referral-card__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .referral-card__body {
            flex: 1;
        }

        .referral-card__header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .referral-card__name {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            text-decoration: none;
        }

        .referral-card__badge {
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .referral-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: #6b7280;
            font-size: 13px;
        }

        .referral-card__meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .referral-card__meta-item i {
            color: #9ca3af;
        }

        .referral-card__stats {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            font-size: 13px;
            color: #4b5563;
        }

        .referral-card__stat {
            display: flex;
            flex-direction: column;
            min-width: 130px;
        }

        .referral-card__stat-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 4px;
            letter-spacing: 0.04em;
        }

        .referral-card__stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .referral-card__stat-currency {
            margin-left: 4px;
            font-size: 12px;
            color: #6b7280;
        }

        .referral-card__stat-hint {
            font-size: 12px;
            color: #9ca3af;
        }

        .referral-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .referral-summary__item {
            background: #ffffff;
            border: 1px solid #e6e9ef;
            border-radius: 12px;
            padding: 14px 18px;
            min-width: 130px;
            box-shadow: 0 8px 20px rgba(15, 34, 58, 0.06);
        }

        .referral-summary__label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            display: block;
            margin-bottom: 6px;
            letter-spacing: 0.04em;
        }

        .referral-summary__value {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .referral-tree__list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .referral-tree__item {
            margin-bottom: 18px;
        }

        .referral-children {
            list-style: none;
            margin: 14px 0 0 26px;
            padding-left: 14px;
            border-left: 2px dashed #d1d5db;
        }

        .referral-empty {
            background: #ffffff;
            border: 1px dashed #cbd5f5;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .referral-details {
                padding: 20px;
            }

            .referral-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .referral-card__stats {
                width: 100%;
                justify-content: space-between;
            }

            .referral-card__stat {
                min-width: 100px;
            }
        }
    </style>

    <div class="referral-card referral-card--highlight">
        <div class="referral-card__avatar">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $profile->fullname ?: $profile->firstname }}" loading="lazy">
            @else
                <span class="referral-card__initials">{{ $initials }}</span>
            @endif
        </div>
        <div class="referral-card__body">
            <div class="referral-card__header">
                <a href="{{ $profileUrl }}" class="referral-card__name" target="_blank" rel="noopener">
                    {{ $profile->fullname ?: ($profile->firstname ?: __('Без имени')) }}
                </a>
                <span class="referral-card__badge">ID #{{ $profile->id }}</span>
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
                @if($profile->referral_code)
                    <span class="referral-card__meta-item">
                        <i class="la la-qrcode"></i>
                        {{ $profile->referral_code }}
                    </span>
                @endif
                @if($login)
                    <span class="referral-card__meta-item">
                        <i class="la la-user"></i>
                        {{ $login }}
                    </span>
                @endif
                @if($profile->created_at)
                    <span class="referral-card__meta-item">
                        <i class="la la-clock"></i>
                        {{ $profile->created_at->format('d.m.Y H:i') }}
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
                <span class="referral-card__stat-label">Всего партнеров</span>
                <span class="referral-card__stat-value">{{ $summaryTotal }}</span>
            </div>
            <div class="referral-card__stat">
                <span class="referral-card__stat-label">Прямых партнеров</span>
                <span class="referral-card__stat-value">{{ $directReferrals }}</span>
            </div>
        </div>
    </div>

    @if($referrerData)
        <div class="referral-card referral-card--compact">
            <div class="referral-card__avatar">
                @if($referrerData['avatar'])
                    <img src="{{ $referrerData['avatar'] }}" alt="{{ $referrerData['name'] }}" loading="lazy">
                @else
                    <span class="referral-card__initials">{{ $referrerData['initials'] }}</span>
                @endif
            </div>
            <div class="referral-card__body">
                <div class="referral-card__header">
                    <a href="{{ $referrerData['url'] }}" class="referral-card__name" target="_blank" rel="noopener">
                        {{ $referrerData['name'] }}
                    </a>
                    <span class="referral-card__badge">Спонсор</span>
                </div>
                <div class="referral-card__meta">
                    @if($referrerData['email'])
                        <span class="referral-card__meta-item">
                            <i class="la la-envelope"></i>
                            <a href="mailto:{{ $referrerData['email'] }}">{{ $referrerData['email'] }}</a>
                        </span>
                    @endif
                    @if($referrerData['phone'])
                        <span class="referral-card__meta-item">
                            <i class="la la-phone"></i>
                            <a href="tel:{{ preg_replace('/\s+/', '', $referrerData['phone']) }}">{{ $referrerData['phone'] }}</a>
                        </span>
                    @endif
                    @if($referrerData['code'])
                        <span class="referral-card__meta-item">
                            <i class="la la-qrcode"></i>
                            {{ $referrerData['code'] }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="referral-card__stats">
                <div class="referral-card__stat">
                    <span class="referral-card__stat-label">Баланс</span>
                    <span class="referral-card__stat-value">
                        {{ $referrerData['balance_formatted'] }}
                        @if($referrerData['balance_code'])
                            <span class="referral-card__stat-currency">{{ $referrerData['balance_code'] }}</span>
                        @endif
                    </span>
                    @if($referrerData['balance_label'])
                        <span class="referral-card__stat-hint">{{ $referrerData['balance_label'] }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if(!empty($levelBreakdown))
        <div class="referral-summary">
            @foreach($levelBreakdown as $levelNumber => $count)
                <div class="referral-summary__item">
                    <span class="referral-summary__label">Уровень {{ $levelNumber }}</span>
                    <span class="referral-summary__value">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="referral-tree">
        @if(empty($referralTree))
            <div class="referral-empty">
                У пользователя пока нет партнеров. Как только появятся новые участники сети, они отобразятся здесь.
            </div>
        @else
            <ul class="referral-tree__list">
                @foreach($referralTree as $node)
                    @include('crud::partials.referral-node', ['node' => $node, 'level' => 1])
                @endforeach
            </ul>
        @endif
    </div>
</div>
