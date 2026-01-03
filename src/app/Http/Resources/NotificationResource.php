<?php

namespace Backpack\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = method_exists($request, 'getLocale')
            ? $request->getLocale()
            : app()->getLocale();

        return $this->resource->toPayload($locale);
    }
}
