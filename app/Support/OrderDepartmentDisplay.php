<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Order;
use App\Models\Task;
use Illuminate\Support\Collection;

final class OrderDepartmentDisplay
{
    private const EXCLUDED_DEPARTMENT_NAMES = ['الاستقبال', 'التوصيل', 'الإدارة'];

    public static function currentTask(Order $order): ?Task
    {
        if (! $order->relationLoaded('tasks')) {
            return null;
        }

        $activeTask = $order->tasks
            ->filter(fn (Task $task): bool => in_array($task->status, [
                TaskStatus::ASSIGNED,
                TaskStatus::IN_PROGRESS,
                TaskStatus::PENDING_REVIEW,
            ], true))
            ->sortByDesc('id')
            ->first();

        if ($activeTask !== null) {
            return $activeTask;
        }

        $processingStatuses = [OrderStatus::IN_PROGRESS, OrderStatus::TRY_ON, OrderStatus::RESEND_WRONG_IMPRESSION];

        if (! in_array($order->status, $processingStatuses, true)) {
            return null;
        }

        $departments = $order->lab?->departments;

        if (! $departments) {
            return null;
        }

        $firstDepartment = self::workflowDepartments($departments)->first();

        if (! $firstDepartment) {
            return null;
        }

        return $order->tasks
            ->filter(fn (Task $task): bool => $task->department_id === $firstDepartment->id && $task->status === TaskStatus::PENDING_ASSIGNMENT)
            ->first();
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     sort_order: ?int,
     *     is_management: bool,
     *     status: ?string,
     *     task_id: ?int,
     *     approved_at: ?string,
     *     is_current: bool
     * }>
     */
    public static function departments(Order $order): array
    {
        $departments = $order->lab?->departments;

        if (! $departments) {
            return [];
        }

        $currentTask = self::currentTask($order);
        $tasksByDepartmentId = $order->relationLoaded('tasks')
            ? $order->tasks->keyBy('department_id')
            : collect();

        $isOrderCompleted = $order->status === OrderStatus::COMPLETED;

        return self::workflowDepartments($departments)
            ->map(function (Department $department) use ($tasksByDepartmentId, $currentTask, $isOrderCompleted): array {
                $task = $tasksByDepartmentId->get($department->id);

                return [
                    'id' => $department->id,
                    'name' => $department->translated_name,
                    'sort_order' => $department->sort_order,
                    'is_management' => (bool) $department->is_management,
                    'status' => $isOrderCompleted
                        ? TaskStatus::COMPLETED
                        : $task?->status,
                    'task_id' => $task?->id,
                    'approved_at' => $task?->approved_at?->toISOString(),
                    'is_current' => ! $isOrderCompleted && $currentTask !== null && $currentTask->id === $task?->id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Department>  $departments
     * @return Collection<int, Department>
     */
    private static function workflowDepartments(Collection $departments): Collection
    {
        return $departments
            ->filter(fn (Department $department): bool => ! $department->is_management && ! in_array($department->name, self::EXCLUDED_DEPARTMENT_NAMES, true))
            ->sortBy(fn (Department $department): string => sprintf('%d-%010d', $department->is_management ? 0 : 1, (int) ($department->sort_order ?? PHP_INT_MAX)))
            ->values();
    }
}
