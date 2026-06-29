<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');

        // Doctor info from related order->user
        $doctor = optional(optional($this->order)->user);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'direction' => $this->direction,
            'assigned_at' => $this->created_at?->toDateTimeString(),
            'delivery_user' => $user === null
                ? null
                : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            'doctor_name' => $doctor->name,
            'doctor_phone' => $doctor->phone,
            'doctor_location' => $doctor->location,
            'doctor_location_lat' => $doctor->location_lat,
            'doctor_location_lng' => $doctor->location_lng,
        ];
    }
}
