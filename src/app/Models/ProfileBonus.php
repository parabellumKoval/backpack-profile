<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ProfileBonus extends Model
{
    use FormatsUniqAttribute;

    protected $fillable = [
        'profile_id', 'amount', 'currency', 'reason', 'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function getUniqStringAttribute(): string
    {
        $profile = $this->relationLoaded('profile') ? $this->getRelation('profile') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $profile?->fullname ?? sprintf('profile #%s', $this->profile_id ?? '?'),
            sprintf('amount: %s %s', $this->amount ?? 0, $this->currency ?? ''),
            $this->reason,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $profile = $this->relationLoaded('profile') ? $this->getRelation('profile') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('%s %s', $this->amount ?? 0, $this->currency ?? ''),
        ]);

        return $this->formatUniqHtml($headline, [
            $profile?->fullname ?? sprintf('profile #%s', $this->profile_id ?? '?'),
            $this->reason,
        ]);
    }
}
