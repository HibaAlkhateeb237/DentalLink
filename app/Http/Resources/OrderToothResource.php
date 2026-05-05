<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderToothResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tooth_number' => $this->tooth_number,
            'tooth_type' => $this->tooth_type,
            'tooth_color' => $this->tooth_color,
            'notes' => $this->notes,
        ];
    }
}
