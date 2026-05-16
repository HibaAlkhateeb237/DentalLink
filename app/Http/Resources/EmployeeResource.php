<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profileImage = $this->user?->profile_image;

        if (filled($profileImage) && ! Str::startsWith((string) $profileImage, ['http://', 'https://'])) {
            $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
            $profileImage = $publicDiskUrl !== ''
                ? $publicDiskUrl.'/'.ltrim((string) $profileImage, '/')
                : '/storage/'.ltrim((string) $profileImage, '/');
        }

        return [
            'id' => $this->user?->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,
            'profile_image' => $profileImage,
            'birthdate' => $this->user?->birthdate?->format('Y-m-d'),
            'joined_at' => $this->user?->joined_at?->format('Y-m-d'),
            'department' => $this->department === null
                ? null
                : [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'lab' => $this->department->lab === null
                        ? null
                        : [
                            'id' => $this->department->lab->id,
                            'name' => $this->department->lab->name,
                        ],
                ],
            'role' => $this->role === null
                ? null
                : [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ],
        ];
    }
}
