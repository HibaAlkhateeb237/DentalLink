<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profileImage = $this->profile_image;

        if (filled($profileImage) && ! Str::startsWith((string) $profileImage, ['http://', 'https://'])) {
            $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
            $profileImage = $publicDiskUrl !== ''
                ? $publicDiskUrl.'/'.ltrim((string) $profileImage, '/')
                : '/storage/'.ltrim((string) $profileImage, '/');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $profileImage,
            'birthdate' => $this->birthdate,
            'location' => $this->location,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'lab_id' => $this->lab_id,
        ];
    }
}
