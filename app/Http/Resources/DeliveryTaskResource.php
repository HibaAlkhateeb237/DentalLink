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

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'assigned_at' => $this->created_at?->toDateTimeString(),
            'delivery_user' => $user === null
                ? null
                : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
        ];
    }
}
