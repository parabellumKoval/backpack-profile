<?php
// src/app/Models/WalletLedger.php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class WalletLedger extends Model
{
    use CrudTrait, HasFactory;
    use FormatsUniqAttribute;

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
      return view('crud::columns.price', [
        'price' => $this->amount,
        'currency' => currency_label($this->currency),
        'muted' => false
      ]);
    }


    public function getTypeHtmlAttribute() {
      return view('crud::columns.ledger_type', ['ledger' => $this]);
    }

    public function getUniqStringAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->type,
            sprintf('%s %s', $this->amount ?? 0, $this->currency ?? ''),
            sprintf('%s #%s', $this->reference_type, $this->reference_id ?? '?'),
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('%s %s', $this->amount ?? 0, $this->currency ?? ''),
        ]);

        return $this->formatUniqHtml($headline, [
            $this->type,
            sprintf('%s #%s', $this->reference_type, $this->reference_id ?? '?'),
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }
}
