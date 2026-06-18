<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lab_id' => $this->when($this->lab_id !== null, $this->lab_id),
            'permissions' => $this->when($this->relationLoaded('permissions'), function (): array {
                return $this->permissions->map(fn ($permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ])->values()->toArray();
            }),
        ];
    }
}
