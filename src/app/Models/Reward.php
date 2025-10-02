<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Reward extends Model
{
    use CrudTrait;

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
      return view('crud::columns.price', ['price' => $this->amount, 'currency' => $this->currency]);
    }


    public function getBaseAmountHtmlAttribute() {
      return view('crud::columns.price', ['price' => $this->base_amount, 'currency' => $this->base_currency, 'small' => true]);
    }
}
