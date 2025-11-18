<?php
// src/app/Models/WalletLedger.php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class WalletLedger extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'ak_wallet_ledger';

    protected $fillable = [
        'user_id',
        'type',           // credit|debit|hold|release|capture
        'amount',
        'currency',
        'reference_type', // order|withdrawal|referral_reward|...
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'meta'   => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Profile::userModel(), 'user_id');
    }

    public function scopeForReference($q, string $type, string|int $id)
    {
        return $q->where('reference_type', $type)->where('reference_id', (string)$id);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopeOfReferenceType($q, string $referenceType)
    {
        return $q->where('reference_type', $referenceType);
    }

    public function scopeRecent($q)
    {
        return $q->orderBy('created_at', 'desc');
    }

     // Человекочитаемая метка типа (через перевод)
    public function getTypeLabelAttribute(): string
    {
        $key = 'profile::wallet.types.'.$this->type;
        $label = trans($key);
        return ($label === $key) ? ucfirst($this->type) : $label;
    }

    public function getAmountHtmlAttribute() {
      return view('crud::columns.price', ['price' => $this->amount, 'currency' => $this->currency, 'muted' => false]);
    }


    public function getTypeHtmlAttribute() {
      return view('crud::columns.ledger_type', ['ledger' => $this]);
    }
}
