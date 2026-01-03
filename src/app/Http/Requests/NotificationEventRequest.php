<?php

namespace Backpack\Profile\app\Http\Requests;

use Backpack\Profile\app\Models\NotificationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $currentId = $this->route('notification_event') ?? $this->route('id');

        return [
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ak_notification_events', 'key')->ignore($currentId),
            ],
            'variant' => ['required', 'string', Rule::in(NotificationEvent::variants())],
            'audience' => ['required', 'string', Rule::in(NotificationEvent::audiences())],
            'target_type' => ['required', 'string', Rule::in(NotificationEvent::targetTypes())],
            'is_pinned' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
