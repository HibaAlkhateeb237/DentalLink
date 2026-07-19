<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'balance_after' => number_format((float) $this->balance_after, 2, '.', ''),
            'currency' => $this->currency,
            'description' => $this->description,
            'payable' => $this->when($this->payable_type !== null, fn () => [
                'type' => class_basename($this->payable_type),
                'id' => $this->payable_id,
            ]),
            'reference' => $this->when($this->reference_type !== null, fn () => [
                'type' => class_basename($this->reference_type),
                'id' => $this->reference_id,
            ]),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
