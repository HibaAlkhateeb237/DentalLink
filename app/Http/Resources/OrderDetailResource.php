<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lab_id' => $this->lab_id,
            'qr_code' => $this->qr_code,
            'qr_url' => route('orders.show-qr', ['qr' => $this->qr_code]),
            'case_type' => $this->case_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'patient_name' => $this->patient_name,
            'serial_number' => $this->serial_number,
            'received_at' => $this->received_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'notes' => $this->notes,
            'price' => $this->price,
            'remaining_amount' => $this->remaining_amount,
            'tooth_shade_name' => $this->toothShade?->name,
            'compensation_type_name' => $this->dentalCompensationTypePrice?->dentalCompensationType?->name,
            'compensation_base_price' => $this->dentalCompensationTypePrice?->base_price,
            'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),
            'files' => OrderFileResource::collection($this->whenLoaded('orderFiles')),
            //  'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toISOString(),

        ];
    }
}
