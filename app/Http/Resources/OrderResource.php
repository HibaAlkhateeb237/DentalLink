<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            //   'user_id' => $this->user_id,
            // 'lab_id' => $this->lab_id,
            // 'qr_code' => $this->qr_code,
            // 'qr_url' => route('orders.show-qr', ['qr' => $this->qr_code]),
            'priority' => $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,
            'price' => $this->price,
            // 'tooth_shade' => ToothShadeResource::make($this->whenLoaded('toothShade')),
            // 'dental_compensation_type_price' => DentalCompensationTypePriceResource::make($this->whenLoaded('dentalCompensationTypePrice')),
            // 'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),
        ];
    }
}
