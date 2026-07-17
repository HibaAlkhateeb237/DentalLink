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
            //   'qr_url' => url('/api/auth/lab/technician/orders/qr/'.$this->qr_code),

            'lab_name' => $this->whenLoaded('lab', function () {
                return $this->lab->name;
            }),
            'case_type' => $this->case_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'patient_name' => $this->patient_name,
            'serial_number' => $this->serial_number,
            'received_at' => $this->received_at?->toISOString(),
            'expected_delivery_at' => $this->expected_delivery_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),

            'notes' => $this->notes,
            'price' => $this->price,
            // 'tooth_shade' => ToothShadeResource::make($this->whenLoaded('toothShade')),
            // 'dental_compensation_type_price' => DentalCompensationTypePriceResource::make($this->whenLoaded('dentalCompensationTypePrice')),
            // 'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),

            'created_at' => $this->created_at?->toISOString(),

        ];
    }
}
