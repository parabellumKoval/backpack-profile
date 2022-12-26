<?php

namespace Backpack\Profile\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileFullResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
        'id' => $this->id,
        'login' => $this->login,
        'firstname' => $this->firstname,
        'lastname' => $this->lastname,
        'fullname' => $this->fullname,
        'phone' => $this->phone,
        'photo' => $this->photo? url($this->photo): null,
        'email' => $this->email,
        'referrer_id' => $this->referrer_id,
        'referral_code' => $this->referral_code,
        'addresses' => $this->addresses,
        'extras' => $this->extras,
        'created_at' => $this->created_at->format('d.m.Y'),
      ];
    }
}
