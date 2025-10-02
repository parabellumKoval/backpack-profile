<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralPartner extends Model
{
    protected $table = 'ak_referral_partners';

    protected $fillable = [
        'user_id', 'ref_code', 'parent_id', 'tree_path',
    ];

    protected $casts = [
        'tree_path' => 'array',
    ];

    /** Владелец партнёрки (пользователь) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Profile::userModel(), 'user_id');
    }

    /** Аплайн (родитель) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Даунлайн (дети) */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Scope: по пользователю */
    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }
}
