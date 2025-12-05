<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
    use Illuminate\Database\Eloquent\Relations\MorphTo;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class RewardEvent extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    protected $table = 'ak_reward_events';

    protected $fillable = [
        'trigger','external_id','actor_user_id',
        'subject_type','subject_id','payload',
        'status','attempts','processed_at','last_error','parent_event_id',
        'happened_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
        'happened_at'  => 'datetime',
    ];

    // статусы
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED  = 'processed';
    public const STATUS_FAILED     = 'failed';

    public function rewards(): HasMany
    {
        return $this->hasMany(\Backpack\Profile\app\Models\Reward::class, 'event_id');
    }

    public function subject(): MorphTo
    {
      return $this->morphTo();
    }

    /** Удобные скоупы */
    public function scopeKey($q, string $trigger, string $externalId)
    {
        return $q->where('trigger',$trigger)->where('external_id',$externalId);
    }
    public function scopeStatus($q, string $status) { return $q->where('status',$status); }


    public function getStatusHtmlAttribute(){
    //   return $this->status;
      return view('crud::columns.status', ['status' => $this->status, 'context' => 'reward', 'type' => 'badge', 'namespace' => 'profile::base']);
    }

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            $this->trigger,
            $this->external_id,
            sprintf('status: %s', $this->status ?? '-'),
            'attempts: '.$this->attempts,
            $this->happened_at ? 'at '.$this->happened_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->trigger,
        ]);

        return $this->formatUniqHtml($headline, [
            $this->external_id,
            sprintf('status: %s', $this->status ?? '-'),
            'attempts: '.$this->attempts,
            $this->happened_at ? 'at '.$this->happened_at->format('Y-m-d H:i') : null,
        ]);
    }
}
