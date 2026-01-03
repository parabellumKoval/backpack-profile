<?php

namespace Backpack\Profile\app\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationArchive extends Model
{
    protected $table = 'ak_notification_archives';

    protected $guarded = ['id'];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function user()
    {
        $userModel = config('backpack.profile.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }
}
