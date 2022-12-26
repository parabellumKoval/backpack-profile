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
    public function toArray($request)
    {
      return [
        'id' => $this->id,
        'fullname' => $this->fullname,
        'email' => $this->email,
        'photo' => $this->photo? url($this->photo): null,
        'referrals' => $this->referrals? self::collection($this->referrals): null,
        'created_at' => $this->created_at
      ];
    }
}
