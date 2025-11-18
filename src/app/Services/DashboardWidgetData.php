<?php

namespace Backpack\Profile\app\Services;

use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardWidgetData
{
    protected int $cacheMinutes = 5;

    public function get(): array
    {
        $ttl = now()->addMinutes($this->cacheMinutes);

        $data = Cache::remember('bp:dashboard:profile-widgets:v4:base', $ttl, function () {
            return [
                'summaryCards'    => $this->summaryCards(),
                'referralLeaders' => $this->referralLeaders(),
                'rewardStats'     => $this->rewardStats(),
                'withdrawals'     => $this->recentWithdrawals(),
                'transactions'    => $this->recentTransactions(),
            ];
        });

        $data['topUsers'] = $this->topUsers($ttl);

        return $data;
    }

    protected function summaryCards(): array
    {
        $weekAgo  = now()->copy()->subDays(7);
        $monthAgo = now()->copy()->subDays(30);
        $dayAgo   = now()->copy()->subDay();

        $usersTotal    = (int) DB::table('ak_profiles')->count();
        $newUsersWeek  = (int) DB::table('ak_profiles')->where('created_at', '>=', $weekAgo)->count();
        $referralTotal = (int) DB::table('ak_profiles')->whereNotNull('sponsor_profile_id')->count();
        $refPercentage = $usersTotal > 0 ? round($referralTotal / $usersTotal * 100, 1) : 0;

        $rewardCount  = (int) DB::table('ak_rewards')->where('created_at', '>=', $monthAgo)->count();
        $rewardVolume = DB::table('ak_rewards')
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->where('created_at', '>=', $monthAgo)
            ->groupBy('currency')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get()
            ->map(fn ($row) => $this->formatAmount($row->total, $row->currency))
            ->take(3)
            ->implode(', ');

        $pendingWithdrawals = (int) DB::table('ak_withdrawal_requests')
            ->where('status', 'pending')
            ->count();

        $pendingAmounts = DB::table('ak_withdrawal_requests')
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->where('status', 'pending')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => $this->formatAmount($row->total, $row->currency))
            ->implode(', ');

        $transactionsDay = (int) DB::table('ak_wallet_ledger')
            ->where('created_at', '>=', $dayAgo)
            ->count();

        $usersSeries     = $this->monthlySeries('ak_profiles', 'created_at');
        $referralSeries  = $this->monthlySeries('ak_profiles', 'created_at', fn ($q) => $q->whereNotNull('sponsor_profile_id'));
        $rewardSeries    = $this->monthlySeries('ak_rewards', 'created_at', null, 'sum', 'amount');
        $withdrawSeries  = $this->monthlySeries('ak_withdrawal_requests', 'created_at');
        $walletSeries    = $this->monthlySeries('ak_wallet_ledger', 'created_at');

        return [
            [
                'label'     => 'Пользователи',
                'value'     => $usersTotal,
                'muted'     => "+{$newUsersWeek} за 7 дней",
                'icon'      => 'las la-users',
                'accent'    => 'primary',
                'route'     => 'profile',
                'routeText' => 'К профилям',
                'chart'     => $usersSeries,
                'chartColor'=> $this->chartColor('primary'),
                'chartBackground' => $this->chartBackground('primary'),
            ],
            [
                'label'     => 'Реферальная сеть',
                'value'     => $referralTotal,
                'muted'     => "{$refPercentage}% имеют спонсора",
                'icon'      => 'las la-sitemap',
                'accent'    => 'success',
                'route'     => 'profile',
                'routeText' => 'Управлять сетью',
                'chart'     => $referralSeries,
                'chartColor'=> $this->chartColor('success'),
                'chartBackground' => $this->chartBackground('success'),
            ],
            [
                'label'     => 'Награды',
                'value'     => $rewardCount,
                'muted'     => $rewardVolume ?: 'нет начислений за 30 дней',
                'icon'      => 'las la-gift',
                'accent'    => 'warning',
                'route'     => 'rewards',
                'routeText' => 'К вознаграждениям',
                'chart'     => $rewardSeries,
                'chartColor'=> $this->chartColor('warning'),
                'chartBackground' => $this->chartBackground('warning'),
            ],
            [
                'label'     => 'Заявки на вывод',
                'value'     => $pendingWithdrawals,
                'muted'     => $pendingAmounts ?: 'без сумм ожидания',
                'icon'      => 'las la-wallet',
                'accent'    => 'dark',
                'route'     => 'withdrawals',
                'routeText' => 'К заявкам',
                'chart'     => $withdrawSeries,
                'chartColor'=> $this->chartColor('dark'),
                'chartBackground' => $this->chartBackground('dark'),
            ],
            [
                'label'     => 'Транзакции кошелька',
                'value'     => $transactionsDay,
                'muted'     => 'за последние 24 часа',
                'icon'      => 'las la-exchange-alt',
                'accent'    => 'danger',
                'route'     => 'wallet-ledger',
                'routeText' => 'Все транзакции',
                'chart'     => $walletSeries,
                'chartColor'=> $this->chartColor('danger'),
                'chartBackground' => $this->chartBackground('danger'),
            ],
        ];
    }

    protected function topUsers($ttl = null)
    {
        $sort = $this->resolveTopUsersSort();
        $ttl = $ttl ?? now()->addMinutes($this->cacheMinutes);
        $cacheKey = "bp:dashboard:profile-widgets:v4:top-users:{$sort}";

        return Cache::remember($cacheKey, $ttl, function () use ($sort) {
            $referralStats = $this->referralStats();
            $limit = 10;

            return match ($sort) {
                'orders'    => $this->topUsersByOrders($referralStats, $limit),
                'referrals' => $this->topUsersByReferrals($referralStats, $limit),
                default     => $this->topUsersByCreated($referralStats, $limit),
            };
        });
    }

    protected function resolveTopUsersSort(): string
    {
        $sort = request()->query('top_users_sort');
        $allowed = ['created', 'referrals', 'orders'];

        return in_array($sort, $allowed, true) ? $sort : 'created';
    }

    protected function topUsersByCreated(array $referralStats, int $limit)
    {
        $rows = $this->baseTopUsersQuery()
            ->orderByDesc('p.created_at')
            ->limit($limit)
            ->get();

        return $this->mapTopUsersRows($rows, $referralStats);
    }

    protected function topUsersByOrders(array $referralStats, int $limit)
    {
        $rows = $this->baseTopUsersQuery()
            ->orderByDesc(DB::raw('COALESCE(orders.total_amount, 0)'))
            ->orderByDesc(DB::raw('COALESCE(orders.total_orders, 0)'))
            ->limit($limit)
            ->get();

        return $this->mapTopUsersRows($rows, $referralStats);
    }

    protected function topUsersByReferrals(array $referralStats, int $limit)
    {
        $ids = collect($referralStats)
            ->sortByDesc(fn ($stats) => $stats['total'] ?? 0)
            ->keys()
            ->take($limit);

        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = $this->baseTopUsersQuery()
            ->whereIn('p.id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn ($id) => $rows->get($id))->filter();

        return $this->mapTopUsersRows($ordered, $referralStats);
    }

    protected function baseTopUsersQuery()
    {
        $userTable    = $this->userTable();
        $userModel    = ltrim(config('profile.user_model', \App\Models\User::class), '\\');
        $profileModel = ltrim(config('profile.profile_model', \Backpack\Profile\app\Models\Profile::class), '\\');
        $pointsKey    = config('profile.points.key', 'point');

        $profileOrders = DB::table('ak_orders')
            ->select(
                'orderable_id as profile_id',
                DB::raw('SUM(COALESCE(grand_total, price, 0)) as total_amount'),
                DB::raw('COUNT(*) as total_orders')
            )
            ->where('orderable_type', $profileModel)
            ->groupBy('orderable_id');

        $orderTotalsQuery = clone $profileOrders;

        if ($userModel !== $profileModel) {
            $userOrders = DB::table('ak_orders as orders_for_users')
                ->select(
                    'p.id as profile_id',
                    DB::raw('SUM(COALESCE(orders_for_users.grand_total, orders_for_users.price, 0)) as total_amount'),
                    DB::raw('COUNT(*) as total_orders')
                )
                ->join('ak_profiles as p', 'p.user_id', '=', 'orders_for_users.orderable_id')
                ->where('orders_for_users.orderable_type', $userModel)
                ->groupBy('p.id');

            $orderTotalsQuery = $profileOrders->unionAll($userOrders);
        }

        $orderSub = DB::query()
            ->fromSub($orderTotalsQuery, 'order_totals')
            ->select(
                'profile_id',
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(total_orders) as total_orders')
            )
            ->groupBy('profile_id');

        $walletSub = DB::table('ak_wallet_balances')
            ->select('user_id', DB::raw('SUM(balance) as balance'))
            ->where('currency', $pointsKey)
            ->groupBy('user_id');

        return DB::table('ak_profiles as p')
            ->select(
                'p.id',
                'p.user_id',
                'p.first_name',
                'p.last_name',
                'p.phone',
                'p.country_code',
                'p.created_at',
                'p.avatar_url',
                'u.email',
                'orders.total_amount',
                'orders.total_orders',
                'wallet.balance as wallet_balance',
                's.first_name as sponsor_first_name',
                's.last_name as sponsor_last_name'
            )
            ->leftJoin($userTable.' as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('ak_profiles as s', 's.id', '=', 'p.sponsor_profile_id')
            ->leftJoinSub($orderSub, 'orders', 'orders.profile_id', '=', 'p.id')
            ->leftJoinSub($walletSub, 'wallet', 'wallet.user_id', '=', 'p.user_id');
    }

    protected function mapTopUsersRows($rows, array $referralStats)
    {
        $levelsTemplate = $this->referralLevelsTemplate();

        return collect($rows)->filter()->map(function ($row) use ($referralStats, $levelsTemplate) {
            $row->full_name          = trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ($row->email ?? 'User #'.$row->id);
            $row->order_total_amount = (float) ($row->total_amount ?? 0);
            $row->order_total_orders = (int) ($row->total_orders ?? 0);
            $row->wallet_balance     = (float) ($row->wallet_balance ?? 0);

            $stats = $referralStats[$row->id] ?? ['total' => 0, 'levels' => $levelsTemplate];
            $row->referrals_total  = (int) ($stats['total'] ?? 0);
            $row->referrals_levels = array_replace($levelsTemplate, $stats['levels'] ?? []);

            $row->sponsor_name = trim(($row->sponsor_first_name ?? '').' '.($row->sponsor_last_name ?? '')) ?: null;

            unset($row->total_amount, $row->total_orders, $row->sponsor_first_name, $row->sponsor_last_name);

            return $row;
        })->values();
    }

    protected function referralLevelsTemplate(): array
    {
        $levels = max(1, (int) config('profile.referral_levels', 1));
        $template = [];

        for ($level = 1; $level <= $levels; $level++) {
            $template[$level] = 0;
        }

        return $template;
    }

    protected function referralLeaders()
    {
        $userTable = $this->userTable();

        return DB::table('ak_profiles as child')
            ->select(
                's.id',
                's.first_name',
                's.last_name',
                'su.email',
                DB::raw('COUNT(child.id) as total')
            )
            ->join('ak_profiles as s', 's.id', '=', 'child.sponsor_profile_id')
            ->leftJoin($userTable.' as su', 'su.id', '=', 's.user_id')
            ->groupBy('s.id', 's.first_name', 's.last_name', 'su.email')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    protected function rewardStats(): array
    {
        $monthAgo = now()->copy()->subDays(30);

        $perCurrency = DB::table('ak_rewards')
            ->select('currency', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $monthAgo)
            ->groupBy('currency')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get();

        $latestRewards = DB::table('ak_rewards as r')
            ->select('r.id', 'r.amount', 'r.currency', 'r.created_at', 'p.first_name', 'p.last_name')
            ->leftJoin('ak_profiles as p', 'p.user_id', '=', 'r.beneficiary_user_id')
            ->orderByDesc('r.created_at')
            ->limit(5)
            ->get();

        return [
            'perCurrency'   => $perCurrency,
            'latestRewards' => $latestRewards,
        ];
    }

    protected function recentWithdrawals()
    {
        return DB::table('ak_withdrawal_requests')
            ->select('id', 'amount', 'currency', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    protected function recentTransactions()
    {
        return DB::table('ak_wallet_ledger')
            ->select('id', 'type', 'amount', 'currency', 'created_at', 'reference_type', 'reference_id', 'meta')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    protected function formatAmount($value, ?string $currency): string
    {
        $formatted = number_format((float) $value, 2, '.', ' ');
        $currencyLabel = $currency ?? '—';

        return trim("{$formatted} {$currencyLabel}");
    }

    protected function userTable(): string
    {
        static $table = null;

        if ($table) {
            return $table;
        }

        $class = config('backpack.profile.user_model', \App\Models\User::class);
        $table = (new $class)->getTable();

        return $table;
    }

    protected function monthlySeries(string $table, string $dateColumn, ?callable $modifier = null, string $mode = 'count', string $valueColumn = 'id', int $months = 6): array
    {
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();
        $end   = now()->copy()->startOfMonth();

        $aggregate = $mode === 'sum'
            ? "COALESCE(SUM({$valueColumn}), 0)"
            : "COUNT(*)";

        $query = DB::table($table)
            ->select(
                DB::raw("DATE_FORMAT({$dateColumn}, '%Y-%m-01') as bucket"),
                DB::raw("{$aggregate} as total")
            )
            ->where($dateColumn, '>=', $start);

        if ($modifier) {
            $query = $modifier($query) ?? $query;
        }

        $rows = $query->groupBy('bucket')->orderBy('bucket')->get()->pluck('total', 'bucket');

        $labels = [];
        $values = [];
        foreach (CarbonPeriod::create($start, '1 month', $end) as $point) {
            $bucket = $point->format('Y-m-01');
            $labels[] = $point->format('M');
            $values[] = (float) ($rows[$bucket] ?? 0);
        }

        return [
            'labels' => $labels,
            'data'   => $values,
        ];
    }

    protected function chartColor(string $accent): string
    {
        return [
            'primary' => 'rgba(99, 102, 241, 1)',
            'success' => 'rgba(34, 197, 94, 1)',
            'warning' => 'rgba(245, 158, 11, 1)',
            'info'    => 'rgba(14, 165, 233, 1)',
            'danger'  => 'rgba(239, 68, 68, 1)',
        ][$accent] ?? 'rgba(59, 130, 246, 1)';
    }

    protected function chartBackground(string $accent): string
    {
        return [
            'primary' => 'rgba(99, 102, 241, 0.15)',
            'success' => 'rgba(34, 197, 94, 0.2)',
            'warning' => 'rgba(245, 158, 11, 0.2)',
            'info'    => 'rgba(14, 165, 233, 0.2)',
            'danger'  => 'rgba(239, 68, 68, 0.2)',
        ][$accent] ?? 'rgba(59, 130, 246, 0.2)';
    }

    protected function referralStats(): array
    {
        $levels = max(1, (int) config('profile.referral_levels', 1));
        $levelSelects = [];

        for ($level = 1; $level <= $levels; $level++) {
            $levelSelects[] = "SUM(CASE WHEN depth = {$level} THEN 1 ELSE 0 END) AS level_{$level}";
        }

        $levelSelectSql = implode(",\n                   ", $levelSelects);

        $sql = "
            WITH RECURSIVE referral_tree AS (
                SELECT id, sponsor_profile_id, id AS root, 0 AS depth
                FROM ak_profiles
                UNION ALL
                SELECT p.id, p.sponsor_profile_id, referral_tree.root, referral_tree.depth + 1
                FROM ak_profiles p
                INNER JOIN referral_tree ON p.sponsor_profile_id = referral_tree.id
                WHERE referral_tree.depth < {$levels}
            )
            SELECT root AS profile_id,
                   {$levelSelectSql},
                   SUM(CASE WHEN depth > 0 THEN 1 ELSE 0 END) AS total_referrals
            FROM referral_tree
            WHERE depth > 0
            GROUP BY root
        ";

        $rows = DB::select($sql);
        $template = $this->referralLevelsTemplate();

        return collect($rows)->mapWithKeys(function ($row) use ($levels, $template) {
            $levelsData = $template;

            for ($level = 1; $level <= $levels; $level++) {
                $key = "level_{$level}";
                $levelsData[$level] = (int) ($row->$key ?? 0);
            }

            return [
                (int) $row->profile_id => [
                    'total'  => (int) ($row->total_referrals ?? 0),
                    'levels' => $levelsData,
                ],
            ];
        })->all();
    }
}
