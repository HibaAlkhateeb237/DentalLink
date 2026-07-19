<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorOrderPaymentStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'price' => $this->price,
            'case_type' => $this->case_type,
            'lab_name' => $this->whenLoaded('lab', fn () => $this->lab->name),
            'order_date' => $this->created_at?->toISOString(),
            'payment_date' => $this->whenLoaded('payments', function () {
                $paidAt = $this->payments->first()?->paid_at;

                return $paidAt?->toISOString();
            }),
        ];
    }
}
