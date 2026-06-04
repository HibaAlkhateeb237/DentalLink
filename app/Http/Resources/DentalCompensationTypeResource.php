<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DentalCompensationTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'name' => $this->name,
            'description' => $this->description,
            //'code' => $this->code,
            'category' => $this->category,
            'price' => optional($this->prices()->where('is_active', true)->first())->base_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
