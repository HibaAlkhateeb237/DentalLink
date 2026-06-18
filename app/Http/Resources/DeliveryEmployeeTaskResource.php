<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryEmployeeTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
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
                'doctor' => $this->order->relationLoaded('user')
                    ? [
                        'id' => $this->order->user->id,
                        'name' => $this->order->user->name,
                        'phone' => $this->order->user->phone,
                        'location' => $this->order->user->location,
                        'location_lat' => $this->order->user->location_lat,
                        'location_lng' => $this->order->user->location_lng,
                    ]
                    : null,
            ]),
            /*  'delivery_user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),*/
        ];
    }
}
