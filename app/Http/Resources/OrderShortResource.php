<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderShortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'priority' => $this->priority,
            'status' => $this->status,
            'case_type' => $this->case_type,
            'material_type' => $this->whenLoaded('dentalCompensationTypePrice.dentalCompensationType', function () {
                return $this->dentalCompensationTypePrice?->dentalCompensationType?->name;
            }),
            'notes' => $this->notes,
            'files' => OrderFileResource::collection($this->whenLoaded('orderFiles')),

        ];
    }
}
