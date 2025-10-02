<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

use Backpack\Profile\app\Models\Concerns\ResolvesUserModel;

class WithdrawalRequest extends Model
{
    use CrudTrait;
    use ResolvesUserModel;

    protected $table = 'ak_withdrawal_requests';

    protected $fillable = [
        'user_id','amount','currency','status',
        'payout_method','payout_details',
        'fx_rate','fx_from','fx_to',
        'approved_at','paid_at','approved_by','paid_by',
    ];

    protected $casts = [
        'amount'         => 'decimal:6',
        'fx_rate'        => 'decimal:10',
        'payout_details' => 'array',
        'approved_at'    => 'datetime',
        'paid_at'        => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo($this->userModelFqn(), 'user_id');
    }

        // Все ledger-записи, относящиеся к этой заявке
    public function ledger()
    {
        return $this->hasMany(\Backpack\Profile\app\Models\WalletLedger::class, 'reference_id', 'id')
            ->where('reference_type', 'withdrawal_request')
            ->orderByDesc('id');
    }

    public function ledgerHold()
    {
        return $this->ledger()->where('type', 'hold');
    }

    public function ledgerRelease()
    {
        return $this->ledger()->where('type', 'release');
    }

    public function ledgerCapture()
    {
        return $this->ledger()->where('type', 'capture');
    }

    public function getStatusHtmlAttribute(){
      return view('crud::columns.status', ['status' => $this->status, 'context' => 'withdrawal', 'type' => 'badge', 'namespace' => 'profile::base']);
    }

    public function getPayoutHtmlAttribute() {
      return view('crud::columns.price', ['price' => $this->amount, 'currency' => \currency_label($this->currency), 'muted' => false]);
    }

    public function getWalletHtmlAttribute() {
      return view('crud::columns.withdrawal_wallet_operation', [
        'price' => $this->wallet_amount,
        'currency' => \currency_label($this->wallet_currency),
        'small' => true,
        'rate' => $this->fx_rate,
        'rate_from' => \currency_label($this->fx_from),
        'rate_to' => \currency_label($this->fx_to),
        //
        'ledger' => $this->ledger()->first()
      ]);
    }
}
