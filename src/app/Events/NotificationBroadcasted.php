<?php

namespace Backpack\Profile\app\Events;

use Backpack\Profile\app\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NotificationBroadcasted implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->notification->target_type === Notification::TARGET_PERSONAL && $this->notification->user_id) {
            $channels[] = new PrivateChannel('notifications.user.' . $this->notification->user_id);
        } else {
            if (in_array($this->notification->audience, [Notification::AUDIENCE_ALL, Notification::AUDIENCE_GUEST], true)) {
                $channels[] = new Channel('notifications.public');
            }

            if (in_array($this->notification->audience, [Notification::AUDIENCE_ALL, Notification::AUDIENCE_AUTHENTICATED], true)) {
                $channels[] = new PrivateChannel('notifications.auth');
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return $this->notification->toPayload($this->resolveLocale());
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    protected function resolveLocale(): ?string
    {
        if (
            $this->notification->target_type !== Notification::TARGET_PERSONAL
            || ! $this->notification->user_id
        ) {
            return app()->getLocale();
        }

        $user = $this->resolveUser();

        if (! $user) {
            return app()->getLocale();
        }

        $locale = $user->locale ?? $user->profile?->locale ?? null;

        return $this->normalizeLocale($locale) ?? app()->getLocale();
    }

    protected function resolveUser(): ?object
    {
        if ($this->notification->relationLoaded('user')) {
            return $this->notification->user;
        }

        return $this->notification->user()->with('profile')->first();
    }

    protected function normalizeLocale(?string $locale): ?string
    {
        if (! is_string($locale) || $locale === '') {
            return null;
        }

        $normalized = strtolower(str_replace('_', '-', $locale));

        if (str_contains($normalized, '-')) {
            $normalized = explode('-', $normalized)[0];
        }

        $supported = (array) config('app.supported_locales', []);

        if (! empty($supported) && ! in_array($normalized, $supported, true)) {
            return null;
        }

        return $normalized;
    }
}
