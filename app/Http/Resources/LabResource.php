<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class LabResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        // Use rating if available (calculated from reviews), default to null
        $rating = $this->rating ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'license_number' => $this->license_number,
            'phone' => $this->phone,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rating' => filled($rating) ? number_format((float) $rating, 2, '.', '') : null,
            'reviews_count' => $this->reviews_count ?? 0,
            'orders_count' => isset($this->orders_count) ? (int) $this->orders_count : null,
            'photo' => $this->toPublicUrl($this->photo ?? null),
            'package' => $this->whenLoaded('package', function () {
                return new PackageResource($this->package);
            }),
        ];
    }

    private function toPublicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');

        if ($publicDiskUrl !== '') {
            return $publicDiskUrl.'/'.ltrim($path, '/');
        }

        return '/storage/'.ltrim($path, '/');
    }
}
