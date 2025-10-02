<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    protected $table = 'ak_wallet_balances';

    public $timestamps = true;

    protected $fillable = [
        'user_id', 'currency', 'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Profile::userModel(), 'user_id');
    }

    public function scopeCurrency($q, string $currency)
    {
        return $q->where('currency', $currency);
    }
}
