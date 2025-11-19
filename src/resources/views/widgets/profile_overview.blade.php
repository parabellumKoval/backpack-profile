@php
    $data = $widget['data'] ?? [];

    $summaryCards    = $data['summaryCards'] ?? [];
    $topUsers        = collect($data['topUsers'] ?? []);
    $topUsersSort    = $data['topUsersSort'] ?? 'created';
    $referralLeaders = $data['referralLeaders'] ?? collect();
    $rewardStats     = $data['rewardStats'] ?? ['perCurrency' => collect(), 'latestRewards' => collect()];
    $withdrawals     = $data['withdrawals'] ?? collect();
    $transactions    = $data['transactions'] ?? collect();

    $statusColor = function ($status) {
        return [
            'pending'   => 'warning',
            'approved'  => 'success',
            'completed' => 'success',
            'rejected'  => 'danger',
            'failed'    => 'danger',
        ][$status] ?? 'secondary';
    };

    $typeColor = function ($type) {
        return [
            'credit'  => 'success',
            'debit'   => 'danger',
            'hold'    => 'warning',
            'release' => 'info',
            'capture' => 'primary',
        ][$type] ?? 'secondary';
    };
@endphp

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

<div class="profile-dashboard-widgets">
    <div class="row">
        <div class="col-12 mb-4">
            @include('profile-backpack::widgets.profile.summary_cards', ['cards' => $summaryCards])
        </div>
    </div>

    <div class="row">
        <div class="col-xl-10 mb-4">
            @include('profile-backpack::widgets.profile.users_referrals', ['topUsers' => $topUsers, 'activeSort' => $topUsersSort])
        </div>
        <div class="col-xl-2 mb-4">
            @include('profile-backpack::widgets.profile.referral_leaders', ['leaders' => $referralLeaders])
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            @include('profile-backpack::widgets.profile.transactions_table', [
                'transactions' => $transactions,
                'typeColor'    => $typeColor,
            ])
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            @include('profile-backpack::widgets.profile.reward_stats', ['rewardStats' => $rewardStats])
        </div>
        <div class="col-xl-6 mb-4">
            @include('profile-backpack::widgets.profile.withdrawals_table', [
                'withdrawals' => $withdrawals,
                'statusColor' => $statusColor,
            ])
        </div>
    </div>
</div>
