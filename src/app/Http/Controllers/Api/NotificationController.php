<?php

namespace Backpack\Profile\app\Http\Controllers\Api;

use Backpack\Profile\app\Http\Resources\NotificationResource;
use Backpack\Profile\app\Models\Notification;
use Backpack\Profile\app\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->resolveUser($request);
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min(50, $perPage));

        $onlyPinned = $request->boolean('pinned', false);
        $onlyUnread = $request->boolean('unread', false);
        $archivedOnly = $request->boolean('archived', false);

        $query = Notification::query()
            ->visibleFor($user)
            ->with(['event'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($user) {
            $query->withReadState($user->getKey());

            if ($onlyUnread) {
                $query->havingRaw('read_at IS NULL');
            }
            $query->with(['archiveForUser' => function ($q) use ($user) {
                $q->where('user_id', $user->getKey());
            }]);
        }

        if (! $user) {
            $query->with(['archiveForUser' => function ($q) {
                $q->whereRaw('0 = 1');
            }]);
        }

        if ($onlyPinned) {
            $query->where('is_pinned', true);
        }

        if ($archivedOnly) {
            $query->where(function ($q) use ($user) {
                $q->where('is_archived', true);

                if ($user) {
                    $q->orWhereHas('archives', function ($archive) use ($user) {
                        $archive->where('user_id', $user->getKey());
                    });
                }
            });
        } else {
            $query->where('is_archived', false);

            if ($user) {
                $query->whereDoesntHave('archives', function ($archive) use ($user) {
                    $archive->where('user_id', $user->getKey());
                });
            }
        }

        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    public function show(Request $request, int $id)
    {
        $user = $this->resolveUser($request);

        $notification = Notification::query()
            ->visibleFor($user)
            ->with(['event'])
            ->withReadState($user?->getKey())
            ->findOrFail($id);

        return new NotificationResource($notification);
    }

    public function markRead(Request $request, int $id, NotificationService $service)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $notification = Notification::query()
            ->visibleFor($user)
            ->findOrFail($id);

        $service->markAsRead($notification, $user);

        $notification->read_at = now();

        return new NotificationResource($notification);
    }

    public function markUnread(Request $request, int $id, NotificationService $service)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $notification = Notification::query()
            ->visibleFor($user)
            ->findOrFail($id);

        $service->markAsUnread($notification, $user);

        $notification->read_at = null;

        return new NotificationResource($notification);
    }

    public function toggleArchive(Request $request, int $id, NotificationService $service)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $archived = $request->boolean('archived', true);

        $notification = Notification::query()
            ->visibleFor($user)
            ->with(['event'])
            ->withReadState($user->getKey())
            ->with(['archiveForUser' => function ($q) use ($user) {
                $q->where('user_id', $user->getKey());
            }])
            ->findOrFail($id);

        if ($archived) {
            $service->archiveForUser($notification, $user);
        } else {
            $service->unarchiveForUser($notification, $user);
        }

        $notification->load(['event', 'archiveForUser' => function ($q) use ($user) {
            $q->where('user_id', $user->getKey());
        }]);

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request, NotificationService $service)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $count = $service->markAllAsRead($user);

        return response()->json([
            'status' => 'ok',
            'updated' => $count,
        ]);
    }

    protected function resolveUser(Request $request)
    {
        return $request->user('sanctum') ?? $request->user();
    }
}
