<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalCompensationTypePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->dentalCompensationType;

        return [
            'id' => $this->id,
            'lab_id' => $type?->lab_id,
            'dental_compensation_type_id' => $this->dental_compensation_type_id,
            'code' => $type?->code,
            'name' => $type?->name,
            'category' => $type?->category,
            'base_price' => $this->base_price,
            'effective_from' => $this->effective_from?->toDateString(),
            'description' => $type?->description,
        ];
    }
}
