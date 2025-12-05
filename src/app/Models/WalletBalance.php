<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class WalletBalance extends Model
{
    use FormatsUniqAttribute;

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

    public function getUniqStringAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
            sprintf('balance: %s %s', $this->balance ?? 0, $this->currency ?? ''),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('%s %s', $this->balance ?? 0, $this->currency ?? ''),
        ]);

        return $this->formatUniqHtml($headline, [
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
        ]);
    }
}
