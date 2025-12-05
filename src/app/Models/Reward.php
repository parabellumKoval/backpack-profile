<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class Reward extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    protected $table = 'ak_rewards';

    protected $fillable = [
        'event_id',
        'beneficiary_user_id',
        'level',
        'beneficiary_type', // actor|upline
        'amount',
        'currency',
        'base_amount',
        'base_currency',
        'meta',
    ];

    protected $casts = [
        'amount'        => 'decimal:6',
        'base_amount'   => 'decimal:6',
        'meta'          => 'array',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(\Profile::userModel(), 'beneficiary_user_id');
    }

    public function getAmountHtmlAttribute() {
      return view('crud::columns.price', [
        'price' => $this->amount,
        'currency' => currency_label($this->currency)
      ]);
    }


    public function getBaseAmountHtmlAttribute() {
      return view('crud::columns.price', [
        'price' => $this->base_amount,
        'currency' => currency_label($this->base_currency),
        'small' => true
      ]);
    }

    public function getUniqStringAttribute(): string
    {
        $beneficiary = $this->relationLoaded('beneficiary') ? $this->getRelation('beneficiary') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            sprintf('event #%s', $this->event_id ?? '?'),
            $beneficiary?->name ?? sprintf('user #%s', $this->beneficiary_user_id ?? '?'),
            sprintf('amount: %s %s', $this->amount ?? 0, $this->currency ?? ''),
            'level: '.$this->level,
            'type: '.$this->beneficiary_type,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $beneficiary = $this->relationLoaded('beneficiary') ? $this->getRelation('beneficiary') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('%s %s', $this->amount ?? 0, $this->currency ?? ''),
        ]);

        return $this->formatUniqHtml($headline, [
            sprintf('event #%s', $this->event_id ?? '?'),
            $beneficiary?->name ?? sprintf('user #%s', $this->beneficiary_user_id ?? '?'),
            'level: '.$this->level,
            'type: '.$this->beneficiary_type,
        ]);
    }
}
