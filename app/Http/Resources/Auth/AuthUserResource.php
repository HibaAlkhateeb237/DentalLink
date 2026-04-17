<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $this->profile_image,
            'birthdate' => $this->birthdate,
            'location' => $this->location,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'lab_name' => $this->lab_name,
        ];
    }
}
