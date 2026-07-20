<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorOrderListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalPayments = $this->relationLoaded('payments')
            ? $this->payments->sum('pivot.amount')
            : 0;

        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'case_type' => $this->case_type,
            'status' => $this->status,
            'date' => $this->created_at?->toISOString(),
            'cost' => $this->price,
            'total_payments' => number_format((float) $totalPayments, 2, '.', ''),
            'amount_due' => number_format((float) $this->remaining_amount, 2, '.', ''),
        ];
    }
}
