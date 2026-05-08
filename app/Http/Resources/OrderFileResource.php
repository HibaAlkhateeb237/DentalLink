<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Illuminate\Support\Str;

class OrderFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $filePath = $this->file_path;

        if (filled($filePath) && ! Str::startsWith((string) $filePath, ['http://', 'https://'])) {
            $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
            $filePath = $publicDiskUrl !== ''
                ? $publicDiskUrl.'/'.ltrim((string) $filePath, '/')
                : '/storage/'.ltrim((string) $filePath, '/');
        }

        return [
            'id' => $this->id,
            'file_path' => $filePath,
            'file_type' => $this->file_type,
            'uploaded_at' => $this->uploaded_at,
        ];
    }
}
