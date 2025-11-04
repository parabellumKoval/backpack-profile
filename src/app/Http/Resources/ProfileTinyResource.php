<?php

namespace Backpack\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileTinyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $avatar = $this->avatarUrl();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullname,
            'email' => $this->email,
            'avatar' => $avatar,
            'avatar_url' => $avatar,
            'referral_code' => $this->referral_code,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
