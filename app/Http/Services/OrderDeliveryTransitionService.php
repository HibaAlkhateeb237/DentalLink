<?php

namespace App\Http\Services;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Task;
use App\Support\DeliveryTaskDirection;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderDeliveryTransitionService
{
    public function handleDeliveryCompleted(DeliveryTask $task): void
    {
        $order = $task->order;
        $originalStatus = $task->original_order_status ?? $order->status;
        $direction = $task->direction;

        $newStatus = $this->resolveNewStatus($originalStatus, $direction, $order);

        DB::transaction(function () use ($order, $newStatus, $originalStatus, $direction) {
            $updateData = [
                'status' => $newStatus,
                'is_in_delivery' => false,
            ];

            if ($newStatus === OrderStatus::COMPLETED) {
                $updateData['delivered_at'] = now();
            }

            $order->update($updateData);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $originalStatus,
                'to_status' => $newStatus,
                'changed_by' => Auth::id(),
                'reason' => "Auto-updated from delivery completion ({$direction})",
                'metadata' => ['triggered_via' => 'delivery_completion'],
            ]);

            if ($newStatus === OrderStatus::IN_PROGRESS && $originalStatus !== OrderStatus::TRY_ON) {
                $this->createFirstDepartmentTask($order);
            }
        });
    }

    private function resolveNewStatus(string $originalStatus, string $direction, Order $order): string
    {
        return match (true) {
            $originalStatus === OrderStatus::NEW && $direction === DeliveryTaskDirection::TO_LAB => OrderStatus::PENDING,

            $originalStatus === OrderStatus::RESEND_WRONG_IMPRESSION && $direction === DeliveryTaskDirection::TO_LAB => OrderStatus::PENDING,

            $originalStatus === OrderStatus::TRY_ON && $direction === DeliveryTaskDirection::TO_DOCTOR => OrderStatus::TRY_ON,

            $originalStatus === OrderStatus::TRY_ON && $direction === DeliveryTaskDirection::TO_LAB => OrderStatus::IN_PROGRESS,

            $originalStatus === OrderStatus::COMPLETED && $direction === DeliveryTaskDirection::TO_DOCTOR => OrderStatus::COMPLETED,

            default => $order->status,
        };
    }

    private function createFirstDepartmentTask(Order $order): void
    {
        $firstDepartment = Department::query()
            ->where('lab_id', $order->lab_id)
            ->where('sort_order', '>', 0)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($firstDepartment) {
            $hasActiveExecutionTask = Task::query()
                ->where('order_id', $order->id)
                ->where('department_id', $firstDepartment->id)
                ->whereIn('status', [
                    TaskStatus::PENDING_ASSIGNMENT,
                    TaskStatus::ASSIGNED,
                    TaskStatus::IN_PROGRESS,
                    TaskStatus::PENDING_REVIEW,
                ])
                ->first();

            if ($hasActiveExecutionTask === null) {
                Task::query()->create([
                    'order_id' => $order->id,
                    'department_id' => $firstDepartment->id,
                    'status' => TaskStatus::PENDING_ASSIGNMENT,
                    'user_id' => null,
                    'approved_at' => null,
                ]);
            }
        }
    }
}
