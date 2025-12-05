<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class SocialAccount extends Model
{
    use FormatsUniqAttribute;

    protected $table = 'ak_social_accounts';

    protected $fillable = [
        'user_id', 'provider', 'provider_id', 'email', 'avatar', 'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Profile::userModel(), 'user_id');
    }

    public function scopeProvider($q, string $provider)
    {
        return $q->where('provider', $provider);
    }

    public static function findByProvider(string $provider, string $providerId): ?self
    {
        return static::where(['provider'=>$provider,'provider_id'=>$providerId])->first();
    }

    public function getUniqStringAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->provider,
            $this->provider_id,
            $this->email,
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            strtoupper((string) $this->provider),
        ]);

        return $this->formatUniqHtml($headline, [
            $this->provider_id,
            $this->email,
            $user?->email ?? sprintf('user #%s', $this->user_id ?? '?'),
        ]);
    }
}
