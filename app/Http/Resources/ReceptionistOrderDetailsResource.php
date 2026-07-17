<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReceptionistOrderDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startDate = $this->received_at;
        $endDate = $this->delivered_at;

        if ($endDate === null && $startDate !== null) {
            $estimatedHours = $this->estimatedTotalHours();
            $endDate = $estimatedHours > 0
                ? $startDate->copy()->addHours($estimatedHours)
                : null;
        }

        $elapsedMinutes = null;
        $remainingMinutes = null;
        $isOverdue = false;

        if ($startDate !== null) {
            $now = Carbon::now();
            $elapsedMinutes = max(0, (int) $startDate->diffInMinutes($now, false));

            if ($endDate !== null) {
                $remainingMinutes = (int) $now->diffInMinutes($endDate, false);
                $isOverdue = $remainingMinutes < 0;
                $remainingMinutes = abs($remainingMinutes);
            }
        }

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
            'start_date' => $startDate?->toISOString(),
            'end_date' => $endDate?->toISOString(),
            'elapsed_time' => [
                'minutes' => $elapsedMinutes,
                'human' => $elapsedMinutes === null ? null : $this->humanizeMinutes($elapsedMinutes),
            ],
            'remaining_time' => [
                'minutes' => $remainingMinutes,
                'human' => $remainingMinutes === null ? null : $this->humanizeMinutes($remainingMinutes),
                'is_overdue' => $isOverdue,
            ],
            'estimated_total_hours' => $this->estimatedTotalHours(),
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

    private function estimatedTotalHours(): int
    {
        if (! $this->lab || ! $this->lab->relationLoaded('departments')) {
            return 0;
        }

        return (int) $this->lab->departments
            ->where('is_management', false)
            ->sum(fn ($department) => (int) ($department->time_allowed ?? 0));
    }

    private function humanizeMinutes(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($mins > 0 || $parts === []) {
            $parts[] = $mins.'m';
        }

        return implode(' ', $parts);
    }
}
