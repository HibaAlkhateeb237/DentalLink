<?php

namespace App\Http\Services;

use App\Http\Resources\DeliveryEmployeeTaskResource;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryStatus;
use App\Support\DeliveryTaskDirection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryEmployeeTaskService
{
    public function __construct(
        private readonly OrderNotificationService $orderNotificationService
    ) {}

    /**
     * @param  array{tab?:string,direction?:string,per_page?:int}  $validated
     */
    public function listTasks(User $deliveryUser, array $validated): LengthAwarePaginator
    {
        $query = $deliveryUser->deliveryTasks()
            ->with(['order.user', 'user'])
            ->orderByDesc('id');

        $tab = $validated['tab'] ?? 'assigned';

        if ($tab === 'completed') {
            $query->whereNotNull('delivered_at');
        } else {
            $query->whereNull('delivered_at');
        }

        if (! empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    public function getGroupedTasks(User $deliveryUser, array $validated): array
    {
        $tasks = $deliveryUser->deliveryTasks()
            ->with(['order.user', 'user'])
            ->orderByDesc('id');

        $tab = $validated['tab'] ?? 'assigned';

        if ($tab === 'completed') {
            $tasks->whereNotNull('delivered_at');
        } else {
            $tasks->whereNull('delivered_at');
        }

        if (! empty($validated['direction'])) {
            $tasks->where('direction', $validated['direction']);
        }

        $tasks = $tasks->get();

        $groupedTasks = [];
        foreach ($tasks as $task) {
            $doctorId = $task->order->user->id;
            $doctorName = $task->order->user->name;
            $doctorPhone = $task->order->user->phone;
            $doctorLocation = $task->order->user->location;
            $doctorLocationLat = $task->order->user->location_lat;
            $doctorLocationLng = $task->order->user->location_lng;
            $doctor = [
                'id' => $doctorId,
                'name' => $doctorName,
                'phone' => $doctorPhone,
                'location' => $doctorLocation,
                'location_lat' => $doctorLocationLat,
                'location_lng' => $doctorLocationLng,
            ];

            $groupKey = $doctorId.'_'.$task->status;

            if (! isset($groupedTasks[$groupKey])) {
                $groupedTasks[$groupKey] = [
                    'doctor' => $doctor,
                    'status' => $task->status,
                    'tasks_count' => 0,
                    'tasks' => [],
                ];
            }

            $groupedTasks[$groupKey]['tasks_count']++;
            $groupedTasks[$groupKey]['tasks'][] = DeliveryEmployeeTaskResource::make($task)->toArray($request = request());
        }

        return [
            'data' => array_values($groupedTasks),
        ];
    }

    public function bulkUpdateStatus(
        array $deliveryTaskIds,
        string $newStatus,
        User $deliveryUser
    ): Collection {
        $tasks = DeliveryTask::query()
            ->whereIn('id', $deliveryTaskIds)
            ->with(['order.user', 'user'])
            ->get();

        if ($tasks->count() !== count($deliveryTaskIds)) {
            throw ValidationException::withMessages([
                'delivery_task_ids' => ['Some delivery tasks were not found.'],
            ]);
        }

        $firstTask = $tasks->first();

        foreach ($tasks as $task) {
            if ($task->order->user_id !== $firstTask->order->user_id) {
                throw ValidationException::withMessages([
                    'delivery_task_ids' => ['All tasks must belong to the same customer.'],
                ]);
            }

            if ($task->status !== $firstTask->status) {
                throw ValidationException::withMessages([
                    'delivery_task_ids' => ['All tasks must have the same status.'],
                ]);
            }

            if ($task->direction !== $firstTask->direction) {
                throw ValidationException::withMessages([
                    'delivery_task_ids' => ['All tasks must have the same direction.'],
                ]);
            }
        }

        $allowedNextStatuses = match ($firstTask->direction) {
            DeliveryTaskDirection::TO_DOCTOR => DeliveryStatus::TRANSITIONS_To_Doctor[$firstTask->status] ?? [],

            DeliveryTaskDirection::TO_LAB => DeliveryStatus::TRANSITIONS_To_Lab[$firstTask->status] ?? [],

            default => [],
        };

        if (! in_array($newStatus, $allowedNextStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Invalid transition from {$firstTask->status} to {$newStatus}. Allowed: "
                    .implode(', ', $allowedNextStatuses),
                ],
            ]);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === DeliveryStatus::RECEIVED) {
            $updateData['picked_at'] = now();
        }

        if ($newStatus === DeliveryStatus::DELIVERED) {
            $updateData['delivered_at'] = now();
        }

        DB::transaction(function () use ($tasks, $updateData) {
            $tasks->each(fn ($task) => $task->update($updateData));
        });

        // Handle order status transitions when delivery is completed
        if ($newStatus === DeliveryStatus::DELIVERED) {
            $tasks->load('order');
            foreach ($tasks as $task) {
                if ($task->order) {
                    app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($task);
                }
            }

            if ($firstTask->direction === DeliveryTaskDirection::TO_DOCTOR) {
                $tasks
                    ->pluck('order')
                    ->filter()
                    ->unique('id')
                    ->each(function (Order $order): void {
                        $this->orderNotificationService->notifyReceptionistWhenDeliveryTaskDelivered($order);
                    });
            }

            // Reload tasks with fresh order data
            $tasks = $tasks->fresh()->load(['order.user', 'user','order']);
        }

        return $tasks->fresh()->load(['order.user', 'user']);
    }
}
