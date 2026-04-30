<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabPricingRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'code' => $this->code,
            'name' => $this->name,
            'effective_from' => $this->effective_from?->toDateString(),
            'applies_to' => $this->applies_to,
            'kind' => $this->kind,
            'value' => $this->value,
            'per_unit' => $this->per_unit,
            'condition' => $this->condition,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
