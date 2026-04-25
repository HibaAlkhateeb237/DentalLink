<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class LabPortfolioCaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'case_name' => $this->case_name,
            'before_image' => $this->toPublicUrl($this->before_image_path),
            'after_image' => $this->toPublicUrl($this->after_image_path),
            'duration_minutes' => $this->duration_minutes,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
        ];
    }

    private function toPublicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith((string) $path, ['http://', 'https://'])) {
            return $path;
        }

        $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');

        return $publicDiskUrl !== ''
            ? $publicDiskUrl.'/'.ltrim((string) $path, '/')
            : '/storage/'.ltrim((string) $path, '/');
    }
}
