<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
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
}
