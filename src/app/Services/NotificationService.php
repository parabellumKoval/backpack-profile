<?php

namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Events\NotificationBroadcasted;
use Backpack\Profile\app\Models\Notification;
use Backpack\Profile\app\Models\NotificationArchive;
use Backpack\Profile\app\Models\NotificationEvent;
use Backpack\Profile\app\Models\NotificationRead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class NotificationService
{
    public function create(array $attributes): Notification
    {
        $notification = new Notification($attributes);
        $notification->save();

        return $notification;
    }

    public function createFromEvent(string|NotificationEvent $event, array $context = [], array $overrides = [], Model|int|null $user = null): Notification
    {
        $eventModel = is_string($event)
            ? NotificationEvent::query()->where('key', $event)->firstOrFail()
            : $event;

        $data = [
            'notification_event_id' => $eventModel->id,
            'kind' => Notification::KIND_EVENT,
            'target_type' => $overrides['target_type'] ?? $eventModel->target_type ?? Notification::TARGET_PERSONAL,
            'audience' => $overrides['audience'] ?? $eventModel->audience ?? Notification::AUDIENCE_AUTHENTICATED,
            'variant' => $overrides['variant'] ?? $eventModel->variant ?? Notification::VARIANT_INFO,
            'icon' => $overrides['icon'] ?? $eventModel->icon,
            'is_pinned' => (bool) ($overrides['is_pinned'] ?? $eventModel->is_pinned ?? false),
            'is_active' => (bool) ($overrides['is_active'] ?? $eventModel->is_active ?? true),
            'title' => $overrides['title'] ?? $this->renderTranslatable($eventModel->getTranslations('title'), $context),
            'excerpt' => $overrides['excerpt'] ?? $this->renderTranslatable($eventModel->getTranslations('excerpt'), $context),
            'body' => $overrides['body'] ?? $this->renderTranslatable($eventModel->getTranslations('body'), $context),
            'meta' => array_merge($eventModel->meta ?? [], $overrides['meta'] ?? []),
            'published_at' => $overrides['published_at'] ?? now(),
        ];

        if ($user) {
            $data['user_id'] = $user instanceof Model ? $user->getKey() : (int) $user;
            $data['target_type'] = Notification::TARGET_PERSONAL;
            $data['audience'] = Notification::AUDIENCE_AUTHENTICATED;
        }

        $data = array_merge($data, Arr::except($overrides, ['title', 'excerpt', 'body', 'meta']));

        return $this->create($data);
    }

    public function markAsRead(Notification $notification, Model|int $user): NotificationRead
    {
        $userId = $user instanceof Model ? $user->getKey() : (int) $user;

        return NotificationRead::query()->updateOrCreate(
            [
                'notification_id' => $notification->id,
                'user_id' => $userId,
            ],
            [
                'read_at' => now(),
            ]
        );
    }

    public function markAsUnread(Notification $notification, Model|int $user): void
    {
        $userId = $user instanceof Model ? $user->getKey() : (int) $user;

        NotificationRead::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $userId)
            ->delete();
    }

    public function markAllAsRead(Model|int $user): int
    {
        $userId = $user instanceof Model ? $user->getKey() : (int) $user;

        $ids = Notification::query()
            ->visibleFor($userId)
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return 0;
        }

        $now = now();

        $payload = array_map(function ($id) use ($userId, $now) {
            return [
                'notification_id' => $id,
                'user_id' => $userId,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $ids);

        NotificationRead::query()->upsert(
            $payload,
            ['notification_id', 'user_id'],
            ['read_at', 'updated_at']
        );

        return count($payload);
    }

    public function archiveForUser(Notification $notification, Model|int $user): NotificationArchive
    {
        $userId = $user instanceof Model ? $user->getKey() : (int) $user;

        return NotificationArchive::query()->updateOrCreate(
            [
                'notification_id' => $notification->id,
                'user_id' => $userId,
            ],
            [
                'archived_at' => now(),
            ]
        );
    }

    public function unarchiveForUser(Notification $notification, Model|int $user): void
    {
        $userId = $user instanceof Model ? $user->getKey() : (int) $user;

        NotificationArchive::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $userId)
            ->delete();
    }

    public function broadcast(Notification $notification): void
    {
        if (! $this->shouldBroadcast($notification)) {
            return;
        }

        $notification->loadMissing('event');

        broadcast(new NotificationBroadcasted($notification));
    }

    protected function shouldBroadcast(Notification $notification): bool
    {
        if (! $notification->is_active) {
            return false;
        }

        if ($notification->published_at && $notification->published_at->isFuture()) {
            return false;
        }

        if ($notification->expires_at && $notification->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    protected function renderTranslatable(?array $source, array $context = []): ?array
    {
        if (empty($source)) {
            return $source;
        }

        $rendered = [];

        foreach ($source as $locale => $value) {
            if ($value === null) {
                continue;
            }

            $rendered[$locale] = $this->replacePlaceholders((string) $value, $context);
        }

        return $rendered;
    }

    protected function replacePlaceholders(string $value, array $context = []): string
    {
        return preg_replace_callback('/{{\s*(\w+)\s*}}/', function ($matches) use ($context) {
            $key = $matches[1] ?? '';

            return array_key_exists($key, $context)
                ? (string) $context[$key]
                : $matches[0];
        }, $value) ?? $value;
    }
}
