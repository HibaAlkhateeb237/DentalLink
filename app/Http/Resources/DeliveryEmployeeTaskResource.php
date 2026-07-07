<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryEmployeeTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $array = [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'serial_number' => $this->order?->serial_number,
            'status' => $this->status,
            'direction' => $this->direction,
            'assigned_at' => $this->created_at?->toDateTimeString(),
            'picked_at' => $this->picked_at?->toDateTimeString(),
            'delivered_at' => $this->delivered_at?->toDateTimeString(),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'serial_number' => $this->order->serial_number,
                'patient_name' => $this->order->patient_name,
                'case_type' => $this->order->case_type,
                'priority' => $this->order->priority,
                'status' => $this->order->status,
                'notes' => $this->order->notes,
                'price' => $this->order->price,
                'created_at' => $this->order->created_at?->toISOString(),

            ]),
            /*  'delivery_user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),*/
        ];

        return $array;
    }
}
