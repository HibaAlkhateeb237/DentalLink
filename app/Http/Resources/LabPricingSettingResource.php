<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabPricingSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->toDateString(),
            'implant_addon' => $this->implant_addon,
            'long_bridge_or_high_addon' => $this->long_bridge_or_high_addon,
            'lisi_connect_etching_addon' => $this->lisi_connect_etching_addon,
            'intraoral_print_fee' => $this->intraoral_print_fee,
            'vip_urgent_multiplier' => $this->vip_urgent_multiplier,
            'student_discount_note' => $this->student_discount_note,
        ];
    }
}
