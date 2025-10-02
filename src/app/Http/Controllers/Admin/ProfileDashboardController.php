<?php
// src/app/Http/Controllers/Admin/ProfileDashboardController.php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;

class ProfileDashboardController extends Controller
{
    public function index(Request $request)
    {
        // период агрегации: day|week|month
        $range = $request->query('range', '30d'); // 7d|30d|90d|12m
        [$from, $group] = $this->resolveRange($range);

        $cacheTtl = now()->addMinutes(5);
        $cacheKey = fn(string $key) => "bp:dash:{$key}:{$range}";

        // Верхние карточки (минимум запросов)
        $top = Cache::remember($cacheKey('top'), $cacheTtl, function () use ($from) {
            $usersTotal = DB::table('ak_profiles')->count(); // или ваша таблица пользователей
            $newUsers   = DB::table('ak_profiles')->where('created_at','>=',$from)->count();
            $refTotal   = DB::table('ak_profiles')->whereNotNull('sponsor_profile_id')->count();

            $pendingWd  = DB::table('ak_withdrawal_requests')->where('status','pending')->count();

            $rewardsByCurrency = DB::table('ak_rewards')
                ->select('currency', DB::raw('SUM(amount) as total'))
                ->where('created_at','>=',$from)
                ->groupBy('currency')
                ->orderByDesc(DB::raw('SUM(amount)'))
                ->get();

            return compact('usersTotal','newUsers','refTotal','pendingWd','rewardsByCurrency');
        });

        // Движение по кошельку (по дням/неделям/месяцам)
        $walletSeries = Cache::remember($cacheKey('walletSeries'), $cacheTtl, function () use ($from, $group) {
            $rows = DB::table('ak_wallet_ledger')
                ->select(
                    DB::raw($this->dateBucket($group).' as bucket'),
                    'type',
                    DB::raw('SUM(amount) as sum')
                )
                ->where('created_at','>=',$from)
                ->groupBy('bucket','type')
                ->orderBy('bucket')
                ->get();

            return $this->pivotSeries($rows, ['credit','debit','hold','release','capture']);
        });

        // Награды (суммы по валютам)
        $rewardsSeries = Cache::remember($cacheKey('rewardsSeries'), $cacheTtl, function () use ($from, $group) {
            $rows = DB::table('ak_rewards')
                ->select(
                    DB::raw($this->dateBucket($group).' as bucket'),
                    'currency',
                    DB::raw('SUM(amount) as sum')
                )
                ->where('created_at','>=',$from)
                ->groupBy('bucket','currency')
                ->orderBy('bucket')
                ->get();

            // серии по валютам
            $currencies = $rows->pluck('currency')->unique()->values()->all();
            return $this->pivotSeries($rows, $currencies, 'currency');
        });

        // Топ-триггеры по событиям
        $topTriggers = Cache::remember($cacheKey('topTriggers'), $cacheTtl, function () use ($from) {
            return DB::table('ak_reward_events')
                ->select('trigger', DB::raw('COUNT(*) as cnt'))
                ->where('created_at','>=',$from)
                ->where('is_reversal', false)
                ->groupBy('trigger')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get();
        });

        // Свежие рефералы (последние 10)
        $recentRefs = Cache::remember($cacheKey('recentRefs'), $cacheTtl, function () {
            return DB::table('ak_profiles as p')
                ->select('p.id','p.user_id','p.first_name','p.last_name','p.created_at','s.user_id as sponsor_user_id')
                ->leftJoin('ak_profiles as s','s.id','=','p.sponsor_profile_id')
                ->orderByDesc('p.id')
                ->limit(10)->get();
        });

        // Последние выводы
        $recentWithdrawals = Cache::remember($cacheKey('recentWd'), $cacheTtl, function () {
            return DB::table('ak_withdrawal_requests')
                ->orderByDesc('id')->limit(10)->get();
        });

        // передаём в blade
        return view('crud::profile_dashboard', [
            'range'            => $range,
            'from'             => $from,
            'group'            => $group,
            'top'              => $top,
            'walletSeries'     => $walletSeries,
            'rewardsSeries'    => $rewardsSeries,
            'topTriggers'      => $topTriggers,
            'recentRefs'       => $recentRefs,
            'recentWithdrawals'=> $recentWithdrawals,
        ]);
    }

    private function resolveRange(string $range): array
    {
        switch ($range) {
            case '7d':  return [now()->subDays(7)->startOfDay(), 'day'];
            case '30d': return [now()->subDays(30)->startOfDay(), 'day'];
            case '90d': return [now()->subDays(90)->startOfDay(), 'day'];
            case '12m': return [now()->subMonths(12)->startOfMonth(), 'month'];
            default:    return [now()->subDays(30)->startOfDay(), 'day'];
        }
    }

    private function dateBucket(string $group): string
    {
        return $group === 'month'
            ? "DATE_FORMAT(created_at, '%Y-%m-01')" // первый день месяца
            : "DATE(created_at)";
    }

    private function pivotSeries($rows, array $seriesKeys, string $dim = 'type'): array
    {
        // собрать полный список бакетов
        $buckets = $rows->pluck('bucket')->unique()->sort()->values()->all();

        // инициализировать серию нулями
        $series = [];
        foreach ($seriesKeys as $key) {
            $series[$key] = array_fill(0, count($buckets), 0.0);
        }

        // индекс бакетов
        $index = array_flip($buckets);

        foreach ($rows as $r) {
            $key = $r->{$dim};
            if (!array_key_exists($key, $series)) continue;
            $i = $index[$r->bucket] ?? null;
            if ($i !== null) {
                $series[$key][$i] = (float)$r->sum;
            }
        }

        return [
            'labels' => $buckets,
            'series' => $series,
        ];
    }
}
