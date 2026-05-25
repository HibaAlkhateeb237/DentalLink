<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionistOrderDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qr_code' => $this->qr_code,
            'priority' => $this->priority,
            'status' => $this->status,
            'order_type' => $this->order_type,
            'notes' => $this->notes,
            'price' => $this->price,
            'remaining_amount' => $this->remaining_amount,
            'paid_amount' => number_format((float) ($this->paid_amount ?? 0), 2, '.', ''),
            'requires_resubmission' => (bool) $this->requires_resubmission,
            'resubmission_reason' => $this->resubmission_reason,
            'resubmission_requested_at' => $this->resubmission_requested_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
            'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),
            'files' => OrderFileResource::collection($this->whenLoaded('orderFiles')),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task): array => [
                'id' => $task->id,
                'status' => $task->status,
                'approved_at' => $task->approved_at,
                'department' => $task->department === null
                    ? null
                    : [
                        'id' => $task->department->id,
                        'name' => $task->department->name,
                    ],
                'employee' => $task->user === null
                    ? null
                    : [
                        'id' => $task->user->id,
                        'name' => $task->user->name,
                        'email' => $task->user->email,
                    ],
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => $payment->pivot?->amount,
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at,
                'doctor' => $payment->user === null
                    ? null
                    : [
                        'id' => $payment->user->id,
                        'name' => $payment->user->name,
                        'email' => $payment->user->email,
                    ],
            ])->values()),
        ];
    }
}
