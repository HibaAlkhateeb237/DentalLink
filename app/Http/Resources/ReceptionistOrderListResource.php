<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionistOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qr_code' => $this->qr_code,
            'case_type' => $this->case_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'order_type' => $this->order_type,
            'patient_name' => $this->patient_name,
            'serial_number' => $this->serial_number,
            'received_at' => $this->received_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'remaining_days' => $this->delivered_at
                ? max(0, (int) now()->diffInDays($this->delivered_at, false))
                : null,
            'tooth_shade_name' => $this->toothShade?->name,
            'material_type' => $this->dentalCompensationTypePrice?->dentalCompensationType?->name,
            'price' => $this->price,
            'remaining_amount' => $this->remaining_amount,
            'paid_amount' => number_format((float) ($this->paid_amount ?? 0), 2, '.', ''),
            'order_teeth_count' => $this->order_teeth_count,
            'teeth' => $this->relationLoaded('orderTeeth')
                ? $this->orderTeeth->pluck('tooth_number')->values()
                : [],
            'files' => $this->relationLoaded('orderFiles')
                ? OrderFileResource::collection($this->orderFiles)
                : [],
            'requires_resubmission' => (bool) $this->requires_resubmission,
            'resubmission_reason' => $this->resubmission_reason,
            'resubmission_requested_at' => $this->resubmission_requested_at,
            'created_at' => $this->created_at,
            'doctor' => $this->user === null
                ? null
                : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'location' => $this->user->location,
                ],
            'lab' => $this->lab === null
                ? null
                : [
                    'id' => $this->lab->id,
                    'name' => $this->lab->name,
                    'phone' => $this->lab->phone,
                    'address' => $this->lab->address,
                ],
        ];
    }
}
