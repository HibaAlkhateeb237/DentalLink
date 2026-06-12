<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'changed_by' => $this->changedByUser ? [
                'id' => $this->changedByUser->id,
                'name' => $this->changedByUser->name,
                'email' => $this->changedByUser->email,
            ] : null,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
