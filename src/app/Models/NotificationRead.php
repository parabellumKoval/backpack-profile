<?php

namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    protected $table = 'ak_notification_reads';

    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function user()
    {
        $userModel = config('backpack.profile.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }
}
