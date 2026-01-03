<?php

namespace Backpack\Profile\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use CrudTrait;
    use HasTranslations;

    public const VARIANT_INFO = 'info';
    public const VARIANT_SUCCESS = 'success';
    public const VARIANT_WARNING = 'warning';
    public const VARIANT_ERROR = 'error';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_AUTHENTICATED = 'authenticated';
    public const AUDIENCE_GUEST = 'guest';

    public const TARGET_BROADCAST = 'broadcast';
    public const TARGET_PERSONAL = 'personal';

    protected $table = 'ak_notification_events';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'bool',
        'is_pinned' => 'bool',
        'meta' => 'array',
        'options' => 'array',
    ];

    protected array $translatable = ['name', 'title', 'excerpt', 'body'];

    public static function variants(): array
    {
        return [
            self::VARIANT_INFO,
            self::VARIANT_SUCCESS,
            self::VARIANT_WARNING,
            self::VARIANT_ERROR,
        ];
    }

    public static function audiences(): array
    {
        return [
            self::AUDIENCE_ALL,
            self::AUDIENCE_AUTHENTICATED,
            self::AUDIENCE_GUEST,
        ];
    }

    public static function targetTypes(): array
    {
        return [
            self::TARGET_BROADCAST,
            self::TARGET_PERSONAL,
        ];
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
