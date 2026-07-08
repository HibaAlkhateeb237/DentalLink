<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionistOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentTask = $this->currentTaskForDisplay();
        $tasksByDepartmentId = $this->relationLoaded('tasks')
            ? $this->tasks->keyBy('department_id')
            : collect();

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
            'departments' => $this->lab?->relationLoaded('departments')
                ? $this->lab->departments
                    ->filter(fn ($department) => ! $department->is_management && ! in_array($department->name, ['Reception', 'Delivery', 'Management'], true))
                    ->sortBy(fn ($department) => sprintf('%d-%010d', $department->is_management ? 0 : 1, (int) ($department->sort_order ?? PHP_INT_MAX)))
                    ->values()
                    ->map(function ($department) use ($tasksByDepartmentId, $currentTask): array {
                        $task = $tasksByDepartmentId->get($department->id);

                        return [
                            'id' => $department->id,
                            'name' => $department->name,
                            'sort_order' => $department->sort_order,
                            'is_management' => (bool) $department->is_management,
                            'status' => $task?->status,
                            'task_id' => $task?->id,
                            'approved_at' => $task?->approved_at?->toISOString(),
                            'is_current' => $currentTask?->id === $task?->id,
                        ];
                    })
                    ->all()
                : [],
            'current_department' => $currentTask === null || $currentTask->department === null
                ? null
                : [
                    'id' => $currentTask->department->id,
                    'name' => $currentTask->department->name,
                    'sort_order' => $currentTask->department->sort_order,
                    'task_id' => $currentTask->id,
                    'status' => $currentTask->status,
                    'approved_at' => $currentTask->approved_at?->toISOString(),
                    'time_allowed_hours' => (int) ($currentTask->department->time_allowed ?? 0),
                ],
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

    private function currentTaskForDisplay()
    {
        if (! $this->relationLoaded('tasks')) {
            return null;
        }

        return $this->tasks
            ->filter(fn ($task): bool => in_array($task->status, ['assigned', 'in_progress', 'pending_review'], true))
            ->sortByDesc('id')
            ->first();
    }
}
