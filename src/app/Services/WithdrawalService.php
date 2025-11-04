<?php
// src/app/Services/WithdrawalService.php
namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Events\WithdrawalApproved;
use Backpack\Profile\app\Events\WithdrawalPaid;
use Illuminate\Support\Facades\DB;
use Backpack\Profile\app\Models\WithdrawalRequest;

class WithdrawalService
{
    public function __construct(
        protected CurrencyConverter $fx // твой адаптер, который внутри дергает контракт
    ) {}

    /**
     * Создать заявку на вывод. Пользователь указывает сумму в валюте выплаты (напр. USD).
     * Мы конвертируем в валюту кошелька (points) и ставим hold.
     */
    public function create(int $userId, float $payoutAmount, string $payoutCurrency, ?string $method=null, array $details=[]): WithdrawalRequest
    {
        $pointsKey  = (string) \Settings::get('profile.points.key', 'POINTS');
        $pointsBase = (string) \Settings::get('profile.points.base', 'CZK');

        // минималка в базовой валюте баллов
        $minBase = (float) \Settings::get('profile.withdrawals.min_amount', 0);
        if ($minBase > 0) {
            $payoutInBase = $this->fx->convert($payoutAmount, $payoutCurrency, $pointsBase);
            if ($payoutInBase < $minBase) {
                throw new \RuntimeException("Minimal withdrawal is {$minBase} {$pointsBase}");
            }
        }

        // сколько удержать с кошелька (points)
        $debitPoints = $this->fx->convert($payoutAmount, $payoutCurrency, $pointsKey);
        $fxRate = $this->fx->convert(1.0, $pointsKey, $payoutCurrency, 10); // 1 point -> payoutCurrency

        return DB::transaction(function () use ($userId,$payoutAmount,$payoutCurrency,$debitPoints,$pointsKey,$method,$details,$fxRate) {
            $wb = DB::table('ak_wallet_balances')
                ->where(['user_id'=>$userId,'currency'=>$pointsKey])
                ->lockForUpdate()->first();

            if (!$wb || (float)$wb->balance < $debitPoints) {
                throw new \RuntimeException('Insufficient funds');
            }

            $id = DB::table('ak_withdrawal_requests')->insertGetId([
                // видимые админу поля — «что выводим»
                'user_id'        => $userId,
                'amount'         => $payoutAmount,
                'currency'       => $payoutCurrency,

                // технические — «что списываем с кошелька»
                'wallet_amount'  => $debitPoints,
                'wallet_currency'=> $pointsKey,

                'status'         => 'pending',
                'payout_method'  => $method,
                'payout_details' => json_encode($details),

                'fx_rate'        => $fxRate,
                'fx_from'        => $pointsKey,
                'fx_to'          => $payoutCurrency,

                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // HOLD в кошельке (points)
            DB::table('ak_wallet_balances')->where('id',$wb->id)
                ->update(['balance'=>DB::raw('balance - '.(float)$debitPoints),'updated_at'=>now()]);

            DB::table('ak_wallet_ledger')->insert([
                'user_id'        => $userId,
                'type'           => 'hold',
                'amount'         => -$debitPoints,
                'currency'       => $pointsKey,
                'reference_type' => 'withdrawal_request',
                'reference_id'   => (string)$id,
                'meta'           => json_encode(['fx_rate'=>$fxRate, 'target'=>$payoutCurrency, 'target_amount'=>$payoutAmount]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return \Backpack\Profile\app\Models\WithdrawalRequest::query()->findOrFail($id);
        });
    }


    public function approve(int $requestId, int $adminId, ?float $fxRate=null, ?string $fxFrom=null, ?string $fxTo=null): void
    {
        DB::transaction(function () use ($requestId,$adminId,$fxRate,$fxFrom,$fxTo) {
            $wr = DB::table('ak_withdrawal_requests')->lockForUpdate()->find($requestId);
            if (!$wr || $wr->status !== 'pending') return;

            $timestamp = now();

            // если админ переопределяет курс — фиксируем его
            DB::table('ak_withdrawal_requests')->where('id',$requestId)->update([
                'status'      => 'approved',
                'approved_at' => $timestamp,
                'approved_by' => $adminId,
                'fx_rate'     => $fxRate ?? $wr->fx_rate,
                'fx_from'     => $fxFrom ?? $wr->fx_from,
                'fx_to'       => $fxTo   ?? $wr->fx_to,
                'updated_at'  => $timestamp,
            ]);

            DB::afterCommit(function () use ($requestId) {
                $model = WithdrawalRequest::query()->find($requestId);
                if ($model) {
                    event(new WithdrawalApproved($model));
                }
            });
        });
    }

    public function reject(int $requestId, int $adminId, string $reason='rejected'): void
    {
        DB::transaction(function () use ($requestId,$adminId,$reason) {
            $wr = DB::table('ak_withdrawal_requests')->lockForUpdate()->find($requestId);
            if (!$wr || !in_array($wr->status, ['pending','approved'], true)) return;

            // вернуть в points на баланс
            $wb = DB::table('ak_wallet_balances')->where(['user_id'=>$wr->user_id,'currency'=>$wr->wallet_currency])
                ->lockForUpdate()->first();

            if ($wb) {
                DB::table('ak_wallet_balances')->where('id',$wb->id)
                    ->update(['balance'=>DB::raw('balance + '.(float)$wr->wallet_amount),'updated_at'=>now()]);
            } else {
                DB::table('ak_wallet_balances')->insert([
                    'user_id'=>$wr->user_id,'currency'=>$wr->wallet_currency,'balance'=>$wr->wallet_amount,'created_at'=>now(),'updated_at'=>now()
                ]);
            }

            DB::table('ak_wallet_ledger')->insert([
                'user_id'        => $wr->user_id,
                'type'           => 'release',
                'amount'         => $wr->wallet_amount,
                'currency'       => $wr->wallet_currency, // points
                'reference_type' => 'withdrawal_request',
                'reference_id'   => (string)$wr->id,
                'meta'           => json_encode(['reason'=>$reason]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::table('ak_withdrawal_requests')->where('id',$requestId)->update([
                'status'=>'rejected',
                'updated_at'=>now(),
            ]);
        });
    }

    public function markPaid(int $requestId, int $adminId): void
    {
        DB::transaction(function () use ($requestId,$adminId) {
            $wr = DB::table('ak_withdrawal_requests')->lockForUpdate()->find($requestId);
            if (!$wr || !in_array($wr->status, ['approved','pending'], true)) return;

            // фиксация выплаты (списание уже удержано в create())
            DB::table('ak_wallet_ledger')->insert([
                'user_id'        => $wr->user_id,
                'type'           => 'capture',
                'amount'         => 0,
                'currency'       => $wr->wallet_currency, // points
                'reference_type' => 'withdrawal_request',
                'reference_id'   => (string)$wr->id,
                'meta'           => json_encode(['paid'=>true]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $timestamp = now();

            DB::table('ak_withdrawal_requests')->where('id',$requestId)->update([
                'status'     => 'paid',
                'paid_at'    => $timestamp,
                'paid_by'    => $adminId,
                'updated_at' => $timestamp,
            ]);

            DB::afterCommit(function () use ($requestId) {
                $model = WithdrawalRequest::query()->find($requestId);
                if ($model) {
                    event(new WithdrawalPaid($model));
                }
            });
        });
    }
}
