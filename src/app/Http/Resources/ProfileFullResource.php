<?php

namespace Backpack\Profile\app\Http\Resources;

use Backpack\Profile\app\Models\Profile as ProfileModel;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileFullResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        /** @var \Backpack\Profile\app\Models\Profile $profile */
        $profile = $this->resource;

        $avatar = $profile->avatarUrl();

        return [
            'id' => $profile->id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'full_name' => $profile->fullname,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'avatar' => $avatar,
            'avatar_url' => $avatar,
            'country_code' => $profile->country_code,
            'locale' => $profile->locale,
            'timezone' => $profile->timezone,
            'referral_code' => $profile->referral_code,
            'discount_percent' => $profile->discount_percent,
            'personal_discount_percent' => $profile->personal_discount_percent,
            'role' => $profile->role,
            'role_label' => $profile->role_label,
            'role_data' => $profile->rolePayload(),
            'billing' => ProfileModel::fillAddress($profile->getMetaSection('billing')),
            'shipping' => ProfileModel::fillAddress($profile->getMetaSection('shipping')),
            'meta' => $profile->metaWithoutOther(),
            'created_at' => optional($profile->created_at)->toIso8601String(),
            'created_at_human' => optional($profile->created_at)->format('d.m.Y'),
        ];
    }
}
