<?php
namespace Backpack\Profile\app\Services;

use Illuminate\Support\Facades\DB;
use Backpack\Profile\app\Services\CurrencyConverter;

class ReferralService
{
    public function __construct(
        protected TriggerRegistry $registry,
        protected CurrencyConverter $fx,
        protected UplineResolver $upline,
    ) {}

    public function record(string $triggerAlias, string $externalId, array $payload, ?int $actorUserId=null, ?string $subjectType=null, ?string $subjectId=null): void
    {
        // уже есть такое событие по ключу? (как и было)
        $existsKey = DB::table('ak_reward_events')->where(['trigger'=>$triggerAlias,'external_id'=>$externalId])->exists();
        if ($existsKey) return;

        // NEW: защита от повторной выплаты по тому же субъекту для exclusive-триггеров
        if ($subjectType && $subjectId) {
            $cls = $this->registry->make($triggerAlias);
            if ($cls) {
                $cap = $cls::capabilities();
                if (!empty($cap['exclusive_by_subject'])) {
                    // есть ли «открытое» положительное событие (processed, не сторнировано) по (alias, subject)?
                    $open = DB::table('ak_reward_events as e')
                        ->where('e.trigger', $triggerAlias)
                        ->where('e.subject_type', $subjectType)
                        ->where('e.subject_id', $subjectId)
                        ->where('e.status', 'processed')
                        ->where('e.is_reversal', false)
                        ->whereNotExists(function($q){
                            $q->select(DB::raw(1))
                            ->from('ak_reward_events as r')
                            ->whereColumn('r.parent_event_id','e.id')
                            ->where('r.is_reversal', true);
                        })
                        ->exists();

                    if ($open) {
                        // уже выплачено и не откатано — не повторяем выплату
                        return;
                    }
                }
            }
        }

        // как было
        $id = DB::table('ak_reward_events')->insertGetId([
            'trigger'=>$triggerAlias,
            'external_id'=>$externalId,
            'actor_user_id'=>$actorUserId,
            'subject_type'=>$subjectType,
            'subject_id'=>$subjectId,
            'payload'=>json_encode($payload),
            'happened_at'=>now(),
            'status'=>'pending', // не забудь статус
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $this->process((int)$id);
    }

    public function process(int $eventId): void
    {
        $event = DB::table('ak_reward_events')
            ->where('id', $eventId)
            ->whereIn('status', ['pending','failed'])
            ->lockForUpdate()
            ->first();

        if (!$event) return;

        DB::table('ak_reward_events')->where('id', $eventId)->update([
            'status'    => 'processing',
            'attempts'  => DB::raw('attempts+1'),
            'updated_at'=> now(),
        ]);

        try {
            $cfg  = \Settings::get("profile.referrals.triggers.{$event->trigger}", []);
            if (empty($cfg['enabled'])) {
                DB::table('ak_reward_events')->where('id',$eventId)
                    ->update(['status'=>'processed','processed_at'=>now()]);
                return;
            }

            $trigger = $this->registry->make($event->trigger);
            if (!$trigger) {
                DB::table('ak_reward_events')->where('id',$eventId)
                    ->update(['status'=>'failed','last_error'=>'Trigger not found']);
                return;
            }

            $payload = $event->payload ? json_decode($event->payload, true) : [];
            $base = $trigger->baseAmount($payload);

            $baseAmount   = (float)($base['amount'] ?? 0);
            $baseCurrency = (string)($base['currency'] ?? (\Settings::get('profile.referrals.default_currency','VIVAPOINTS')));
            $payoutCurrency = (string)($cfg['payout_currency'] ?? \Settings::get('profile.referrals.default_currency','VIVAPOINTS'));

            DB::transaction(function () use ($event,$cfg,$trigger,$baseAmount,$baseCurrency,$payoutCurrency) {
                $cap = $trigger::capabilities();

                // 1) Автор
                $actorAwardAmount = $this->processActorAward($event, $cfg, $cap, $payoutCurrency);

                // 2) Уровни
                $this->processLevels($event, $cfg, $cap, $actorAwardAmount, $baseAmount, $baseCurrency, $payoutCurrency);
            });

            DB::table('ak_reward_events')->where('id',$eventId)->update([
                'status'=>'processed',
                'processed_at'=>now(),
                'last_error'=>null,
                'updated_at'=>now(),
            ]);

        } catch (\Throwable $e) {
            DB::table('ak_reward_events')->where('id',$eventId)->update([
                'status'=>'failed',
                'last_error'=>substr($e->getMessage(),0,65535),
                'updated_at'=>now(),
            ]);
            report($e);
        }
    }

    protected function processActorAward($event, array $cfg, array $cap, string $payoutCurrency): float
    {
        $actorAwardAmount = 0.0;

        if (!empty($cap['supports_actor']) && !empty($cfg['actor_award']['amount'])) {
            $aa = (float)$cfg['actor_award']['amount'];
            $aaCur = (string)($cfg['actor_award']['currency'] ?? $payoutCurrency);

            $payout = $this->fx->convert($aa, $aaCur, $payoutCurrency);
            $actorAwardAmount = round($payout, 6);

            if ($event->actor_user_id) {
                $this->storeRewardAndBalance(
                    (int)$event->id,
                    (int)$event->actor_user_id,
                    null,
                    'actor',
                    $actorAwardAmount,
                    $payoutCurrency,
                    $aa,
                    $aaCur,
                    ['kind'=>'actor_award']
                );
            }
        }

        return $actorAwardAmount;
    }

    protected function processLevels($event, array $cfg, array $cap, float $actorAwardAmount, float $baseAmount, string $baseCurrency, string $payoutCurrency): void
    {
        if (empty($cap['supports_levels'])) return;

        $levels = (array)($cfg['levels'] ?? []);
        $norm = $this->normalizeLevels($levels);

        $basisType = $cfg['levels_percent_of'] ?? ($cap['levels_percent_of'] ?? 'base');
        $basis = $basisType === 'actor' ? $actorAwardAmount : $baseAmount;
        $basisCur = $basisType === 'actor' ? $payoutCurrency : $baseCurrency;

        $maxLevel = empty($norm) ? 0 : max(array_keys($norm));
        $beneficiaries = $this->upline->forUser($event->actor_user_id, $maxLevel);

        foreach ($norm as $level => $percent) {
            $userId = $beneficiaries[$level] ?? null;
            if (!$userId) continue;

            $orig = round($basis * $percent / 100, 6);
            if ($orig <= 0) continue;

            $payout = $this->fx->convert($orig, $basisCur, $payoutCurrency);

            $this->storeRewardAndBalance(
                (int)$event->id,
                (int)$userId,
                (int)$level,
                'upline',
                $payout,
                $payoutCurrency,
                $orig,
                $basisCur,
                ['percent'=>$percent,'basis'=>$basisCur]
            );
        }
    }

    public function reverseEvent(int $originalEventId, string $reason='reversal'): ?int
    {
        // 1) если откат уже есть — выходим
        $exists = DB::table('ak_reward_events')
            ->where('parent_event_id', $originalEventId)
            ->where('is_reversal', true)
            ->value('id');
        if ($exists) return (int)$exists;

        // 2) найдём оригинал, проверим что он processed
        $orig = DB::table('ak_reward_events')->where('id',$originalEventId)->first();
        if (!$orig || $orig->status !== 'processed') return null;

        // 3) на всякий случай: если суммарно по оригиналу уже «ноль» (все сторнировано) — выходим
        $sum = DB::table('ak_rewards')->where('event_id',$originalEventId)->sum('amount');
        $revSum = DB::table('ak_reward_events')
            ->where('parent_event_id',$originalEventId)
            ->where('is_reversal',true)
            ->join('ak_rewards','ak_rewards.event_id','=','ak_reward_events.id')
            ->sum('ak_rewards.amount');
        if (bccomp((string)$sum, (string)abs($revSum), 6) <= 0) {
            return null;
        }

        // 4) создаём reversal event (is_reversal=true)
        $reversalId = DB::table('ak_reward_events')->insertGetId([
            'trigger'         => $orig->trigger.'.reversal',
            'external_id'     => $orig->external_id.'#reversal:'.now()->timestamp,
            'actor_user_id'   => $orig->actor_user_id,
            'subject_type'    => $orig->subject_type,
            'subject_id'      => $orig->subject_id,
            'payload'         => $orig->payload,
            'parent_event_id' => $originalEventId,
            'is_reversal'     => true,
            'status'          => 'pending',
            'happened_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 5) переносим зеркальные записи
        DB::transaction(function () use ($reversalId,$originalEventId,$reason) {
            $rewards = DB::table('ak_rewards')->where('event_id',$originalEventId)->get();
            foreach ($rewards as $rw) {
                $neg = bcmul((string)$rw->amount, '-1', 6);

                // защита от дубля той же строки-сторно (на случай гонок)
                $dupe = DB::table('ak_rewards')->where([
                    'event_id'             => $reversalId,
                    'beneficiary_user_id'  => $rw->beneficiary_user_id,
                    'beneficiary_type'     => $rw->beneficiary_type,
                    'level'                => $rw->level,
                    'currency'             => $rw->currency,
                ])->exists();
                if ($dupe) continue;

                DB::table('ak_rewards')->insert([
                    'event_id'             => $reversalId,
                    'beneficiary_user_id'  => $rw->beneficiary_user_id,
                    'level'                => $rw->level,
                    'beneficiary_type'     => $rw->beneficiary_type,
                    'amount'               => $neg,
                    'currency'             => $rw->currency,
                    'base_amount'          => $rw->base_amount,
                    'base_currency'        => $rw->base_currency,
                    'meta'                 => json_encode(['reversal_of'=>$rw->id,'reason'=>$reason]),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                // баланс + ledger
                DB::table('ak_wallet_balances')
                ->where(['user_id'=>$rw->beneficiary_user_id,'currency'=>$rw->currency])
                ->lockForUpdate()
                ->update(['balance'=>DB::raw("balance + ".(float)$neg),'updated_at'=>now()]);

                DB::table('ak_wallet_ledger')->insert([
                    'user_id'        => $rw->beneficiary_user_id,
                    'type'           => 'debit',
                    'amount'         => $neg,
                    'currency'       => $rw->currency,
                    'reference_type' => 'reversal',
                    'reference_id'   => (string)$reversalId,
                    'meta'           => json_encode(['reversal_of_reward_id'=>$rw->id,'reason'=>$reason]),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            DB::table('ak_reward_events')->where('id',$reversalId)
                ->update(['status'=>'processed','processed_at'=>now()]);
        });

        return $reversalId;
    }



    protected function normalizeLevels(array $levels): array
    {
        // принимаем либо [{level, value}, ...] либо [level=>value,...]
        $out = [];
        foreach ($levels as $k=>$v) {
            if (is_array($v) && isset($v['level']) && (isset($v['value']) || isset($v['amount']))) {
                // поддержим будущие fixed для уровней (если понадобятся) — сейчас берем value%
                $out[(int)$v['level']] = (float)($v['value'] ?? 0);
            } else {
                $out[(int)$k] = (float)$v;
            }
        }
        ksort($out);
        return $out;
    }

    protected function storeRewardAndBalance(int $eventId, int $userId, ?int $level, string $bType,
                                            float $amount, string $currency,
                                            ?float $baseAmount, ?string $baseCurrency, array $meta): void
    {
        $exists = DB::table('ak_rewards')->where([
            'event_id'            => $eventId,
            'beneficiary_user_id' => $userId,
            'beneficiary_type'    => $bType,
            'level'               => $level,
            'currency'            => $currency,
        ])->exists();
        if ($exists) return;

        DB::table('ak_rewards')->insert([
            'event_id'=>$eventId,
            'beneficiary_user_id'=>$userId,
            'level'=>$level,
            'beneficiary_type'=>$bType,
            'amount'=>$amount,
            'currency'=>$currency,
            'base_amount'=>$baseAmount,
            'base_currency'=>$baseCurrency,
            'meta'=>json_encode($meta),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        // апдейт баланса
        $exists = DB::table('ak_wallet_balances')->where([
            'user_id'=>$userId,'currency'=>$currency
        ])->lockForUpdate()->first();

        if ($exists) {
            DB::table('ak_wallet_balances')->where('id',$exists->id)
                ->update(['balance'=>DB::raw("balance + ".(float)$amount),'updated_at'=>now()]);
        } else {
            DB::table('ak_wallet_balances')->insert([
                'user_id'=>$userId,'currency'=>$currency,'balance'=>$amount,'created_at'=>now(),'updated_at'=>now()
            ]);
        }

        // ledger: credit
        DB::table('ak_wallet_ledger')->insert([
            'user_id'=>$userId,
            'type'=>'credit',
            'amount'=>$amount,
            'currency'=>$currency,
            'reference_type'=>'referral_reward',
            'reference_id'=>(string)$eventId,
            'meta'=>json_encode(['level'=>$level,'beneficiary_type'=>$bType]),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }
}
