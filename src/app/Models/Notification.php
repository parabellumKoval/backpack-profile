<?php

namespace Backpack\Profile\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Profile\app\Models\NotificationArchive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use CrudTrait;
    use HasTranslations;

    public const KIND_MANUAL = 'manual';
    public const KIND_EVENT = 'event';
    public const KIND_SYSTEM = 'system';

    public const TARGET_BROADCAST = 'broadcast';
    public const TARGET_PERSONAL = 'personal';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_AUTHENTICATED = 'authenticated';
    public const AUDIENCE_GUEST = 'guest';

    public const VARIANT_INFO = 'info';
    public const VARIANT_SUCCESS = 'success';
    public const VARIANT_WARNING = 'warning';
    public const VARIANT_ERROR = 'error';

    protected $table = 'ak_notifications';

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'is_pinned' => 'bool',
        'is_active' => 'bool',
        'is_archived' => 'bool',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    protected array $translatable = ['title', 'excerpt', 'body'];

    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            if ($notification->is_active && is_null($notification->published_at)) {
                $notification->published_at = now();
            }

            if ($notification->target_type === self::TARGET_BROADCAST) {
                $notification->user_id = null;
            }

            if ($notification->target_type === self::TARGET_PERSONAL && $notification->user_id === null) {
                $notification->target_type = self::TARGET_BROADCAST;
            }
        });

        static::created(function (self $notification) {
            if (app()->bound(\Backpack\Profile\app\Services\NotificationService::class)) {
                app(\Backpack\Profile\app\Services\NotificationService::class)->broadcast($notification);
            }
        });
    }

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

    public static function kinds(): array
    {
        return [
            self::KIND_MANUAL,
            self::KIND_EVENT,
            self::KIND_SYSTEM,
        ];
    }

    public function event()
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public function user()
    {
        $userModel = config('backpack.profile.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }

    public function reads()
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function archives()
    {
        return $this->hasMany(NotificationArchive::class, 'notification_id');
    }

    public function archiveForUser()
    {
        return $this->hasOne(NotificationArchive::class, 'notification_id');
    }

    public function scopeVisibleFor(Builder $query, $user = null): Builder
    {
        $now = now();

        $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($user) {
            $userId = is_object($user) ? (int) $user->getKey() : (int) $user;

            $query->where(function ($q) use ($userId) {
                $q->where(function ($broadcast) {
                    $broadcast
                        ->whereNull('user_id')
                        ->where('target_type', self::TARGET_BROADCAST)
                        ->whereIn('audience', [self::AUDIENCE_ALL, self::AUDIENCE_AUTHENTICATED]);
                })->orWhere(function ($personal) use ($userId) {
                    $personal
                        ->where('target_type', self::TARGET_PERSONAL)
                        ->where('user_id', $userId);
                });
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('user_id')
                    ->where('target_type', self::TARGET_BROADCAST)
                    ->whereIn('audience', [self::AUDIENCE_ALL, self::AUDIENCE_GUEST]);
            });
        }

        return $query;
    }

    public function scopeWithReadState(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->withAggregate(['reads as read_at' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }], 'read_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            });
    }

    public function getHasDetailsAttribute(): bool
    {
        $bodyTranslations = $this->getTranslations('body') ?? [];

        $hasBody = collect($bodyTranslations)->filter(fn ($text) => $this->hasMeaningfulText($text))->isNotEmpty();
        $hasAction = filled($this->meta['action_url'] ?? null) || filled($this->meta['action_label'] ?? null);

        return $hasBody || $hasAction;
    }

    protected function hasMeaningfulText($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $stripped = str_replace("\xc2\xa0", ' ', $stripped);
        $stripped = str_replace('&nbsp;', ' ', $stripped);
        $clean = trim(preg_replace('/\s+/', ' ', $stripped) ?? '');

        return $clean !== '';
    }

    public function translateField(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        $value = $this->getTranslation($field, $locale, false);

        if (! filled($value) && $fallback) {
            $value = $this->getTranslation($field, $fallback, false);
        }

        if (! filled($value)) {
            foreach (($this->getTranslations($field) ?? []) as $candidate) {
                if (filled($candidate)) {
                    $value = $candidate;
                    break;
                }
            }
        }

        return $value;
    }

    public function toPayload(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $readAt = $this->read_at;
        $readAtValue = $readAt instanceof \DateTimeInterface
            ? $readAt->toIso8601String()
            : (is_string($readAt) ? $readAt : null);
        $archiveRecord = $this->relationLoaded('archiveForUser') ? $this->archiveForUser : null;
        $isArchivedForUser = $archiveRecord instanceof NotificationArchive;

        $meta = $this->localizeMeta($this->meta ?? [], $locale);

        return [
            'id' => $this->id,
            'event_key' => $this->event?->key,
            'kind' => $this->kind,
            'target_type' => $this->target_type,
            'audience' => $this->audience,
            'variant' => $this->variant,
            'icon' => $this->icon,
            'is_pinned' => (bool) $this->is_pinned,
            'is_archived' => $this->is_archived || $isArchivedForUser,
            'title' => $this->translateField('title', $locale),
            'excerpt' => $this->translateField('excerpt', $locale),
            'body' => $this->translateField('body', $locale),
            'meta' => $meta,
            'has_details' => $this->has_details,
            'read_at' => $readAtValue,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'archived_at' => optional($archiveRecord?->archived_at)->toIso8601String(),
        ];
    }

    protected function localizeMeta(array $meta, ?string $locale = null): array
    {
        if (! array_key_exists('action_label', $meta)) {
            return $meta;
        }

        if (! is_array($meta['action_label'])) {
            return $meta;
        }

        $meta['action_label'] = $this->pickLocalizedValue($meta['action_label'], $locale);

        return $meta;
    }

    protected function pickLocalizedValue(array $values, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        $value = $values[$locale] ?? null;

        if (! filled($value) && $fallback) {
            $value = $values[$fallback] ?? null;
        }

        if (! filled($value)) {
            foreach ($values as $candidate) {
                if (filled($candidate)) {
                    $value = $candidate;
                    break;
                }
            }
        }

        return is_string($value) ? $value : null;
    }
}
