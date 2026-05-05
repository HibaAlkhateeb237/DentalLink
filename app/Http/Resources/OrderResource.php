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
            'user_id' => $this->user_id,
            'lab_id' => $this->lab_id,
            'qr_code' => $this->qr_code,
            'qr_url' => route('orders.show-qr', ['order' => $this->qr_code]),
            'qr_image_path' => $this->qr_image_path,
            'priority' => $this->priority,
            'status' => $this->status,
            'order_type' => $this->order_type,
            'notes' => $this->notes,
            'price' => $this->price,
            'remaining_amount' => $this->remaining_amount,
            'tooth_shade' => ToothShadeResource::make($this->whenLoaded('toothShade')),
            'dental_compensation_type_price' => DentalCompensationTypePriceResource::make($this->whenLoaded('dentalCompensationTypePrice')),
            'created_at' => $this->created_at?->toISOString(),
            'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),
        ];
    }
}
