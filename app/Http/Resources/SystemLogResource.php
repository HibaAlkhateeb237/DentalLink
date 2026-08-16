<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'event' => $this->event,
            'message' => $this->message,
            'user' => $this->when($this->user_id !== null, fn () => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
            ]),
            'lab_id' => $this->lab_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
