<?php

namespace Backpack\Profile\app\Http\Requests;

use Backpack\Profile\app\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $userModel = config('backpack.profile.user_model', \App\Models\User::class);
        $userTable = (new $userModel)->getTable();

        return [
            'variant' => ['required', 'string', Rule::in(Notification::variants())],
            'audience' => ['required', 'string', Rule::in(Notification::audiences())],
            'target_type' => ['required', 'string', Rule::in(Notification::targetTypes())],
            'kind' => ['required', 'string', Rule::in(Notification::kinds())],
            'user_id' => [
                Rule::requiredIf(fn () => $this->input('target_type') === Notification::TARGET_PERSONAL),
                'nullable',
                'integer',
                "exists:{$userTable},id",
            ],
            'notification_event_id' => ['nullable', 'integer', 'exists:ak_notification_events,id'],
            'is_pinned' => ['boolean'],
            'is_active' => ['boolean'],
            'is_archived' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ];
    }
}
