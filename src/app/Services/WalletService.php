<?php
// src/Services/WalletService.php
namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Contracts\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService implements Wallet
{
    public function balance(int $userId, string $currency): string
    {
        $row = DB::table('ak_wallet_balances')
            ->where(['user_id'=>$userId,'currency'=>$currency])
            ->lockForUpdate(false)->first();

        return $row ? (string)$row->balance : '0';
    }

    public function authorize(string $orderId, int $userId, string $amount, string $currency): bool
    {
        return DB::transaction(function () use ($orderId,$userId,$amount,$currency) {
            $bal = $this->balanceForUpdate($userId, $currency);
            if (bccomp($bal, $amount, 6) < 0) {
                return false;
            }
            // hold в ledger
            DB::table('ak_wallet_ledger')->insert([
                'user_id' => $userId,
                'type' => 'hold',
                'amount' => $amount,
                'currency' => $currency,
                'reference_type' => 'order',
                'reference_id' => (string)$orderId,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // ничего не списываем с баланса до capture
            return true;
        });
    }

    public function capture(string $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $holds = DB::table('ak_wallet_ledger')
                ->where(['reference_type'=>'order','reference_id'=>$orderId,'type'=>'hold'])
                ->lockForUpdate()->get();

            foreach ($holds as $h) {
                // списать
                $this->debitBalance($h->user_id, $h->currency, $h->amount);

                // запись capture
                DB::table('ak_wallet_ledger')->insert([
                    'user_id' => $h->user_id,
                    'type' => 'capture',
                    'amount' => $h->amount,
                    'currency' => $h->currency,
                    'reference_type' => 'order',
                    'reference_id' => (string)$orderId,
                    'meta' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // отметить hold как обработанный? Можно оставить как есть, это журнал.
            }
        });
    }

    public function void(string $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $holds = DB::table('ak_wallet_ledger')
                ->where(['reference_type'=>'order','reference_id'=>$orderId,'type'=>'hold'])
                ->lockForUpdate()->get();

            foreach ($holds as $h) {
                // релиз (ничего не списывали — просто журналируем возврат резерва)
                DB::table('ak_wallet_ledger')->insert([
                    'user_id' => $h->user_id,
                    'type' => 'release',
                    'amount' => $h->amount,
                    'currency' => $h->currency,
                    'reference_type' => 'order',
                    'reference_id' => (string)$orderId,
                    'meta' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    // --- helpers ---

    protected function balanceForUpdate(int $userId, string $currency): string
    {
        $row = DB::table('ak_wallet_balances')
            ->where(['user_id'=>$userId,'currency'=>$currency])
            ->lockForUpdate()->first();

        if (!$row) {
            DB::table('ak_wallet_balances')->insert([
                'user_id'=>$userId,'currency'=>$currency,'balance'=>0,'created_at'=>now(),'updated_at'=>now()
            ]);
            return '0';
        }
        return (string)$row->balance;
    }

    protected function debitBalance(int $userId, string $currency, string $amount): void
    {
        $this->balanceForUpdate($userId, $currency);
        DB::table('ak_wallet_balances')
            ->where(['user_id'=>$userId,'currency'=>$currency])
            ->update([
                'balance' => DB::raw("balance - ".(float)$amount),
                'updated_at' => now()
            ]);

        DB::table('ak_wallet_ledger')->insert([
            'user_id'=>$userId,
            'type'=>'debit',
            'amount'=>$amount,
            'currency'=>$currency,
            'reference_type'=>'order',
            'reference_id'=>null,
            'meta'=>json_encode(['reason'=>'order_capture']),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }
}
