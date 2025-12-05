<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ReferralPartner extends Model
{
    use FormatsUniqAttribute;

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

    public function getUniqStringAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $parent = $this->relationLoaded('parent') ? $this->getRelation('parent') : null;
        $depth = is_array($this->tree_path) ? count($this->tree_path) : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $user?->name ?? sprintf('user #%s', $this->user_id ?? '?'),
            'ref: '.$this->ref_code,
            $parent ? 'parent #'.$parent->id : ($this->parent_id ? 'parent #'.$this->parent_id : null),
            $depth !== null ? 'depth '.$depth : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $parent = $this->relationLoaded('parent') ? $this->getRelation('parent') : null;
        $depth = is_array($this->tree_path) ? count($this->tree_path) : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $user?->name ?? sprintf('user #%s', $this->user_id ?? '?'),
        ]);

        return $this->formatUniqHtml($headline, [
            'ref: '.$this->ref_code,
            $parent ? 'parent #'.$parent->id : ($this->parent_id ? 'parent #'.$this->parent_id : null),
            $depth !== null ? 'depth '.$depth : null,
        ]);
    }
}
