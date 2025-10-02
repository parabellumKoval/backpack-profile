<?php
namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileBonus extends Model
{
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
}
