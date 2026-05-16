<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
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
            'joined_at' => $this->joined_at,
        ];
    }
}
