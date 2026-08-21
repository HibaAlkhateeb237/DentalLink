<?php

namespace App\Http\Services;

use App\Events\DeliveryLocationUpdated;
use App\Models\DeliveryTask;
use App\Models\DeliveryTrack;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Tracking\TrackingStateNotification;
use App\Support\DeliveryTrackStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTrackingService
{
    public function __construct(private readonly SystemLogService $systemLogs) {}

    /**
     * Start a delivery trip for a group of tasks (single doctor).
     *
     * @param  int[]  $taskIds
     * @return array{doctor_id:int, tracks:Collection<int, DeliveryTrack>}
     */
    public function startTrip(array $taskIds, User $deliveryPerson): array
    {
        return DB::transaction(function () use ($taskIds, $deliveryPerson): array {
            $tasks = $this->resolveTasks($taskIds, $deliveryPerson);
            $this->assertSameDoctor($tasks);

            $doctorId = $this->doctorIdFromTasks($tasks);
            $orderIds = $tasks->pluck('order_id')->all();

            $this->assertCanTransitionTo($tasks, DeliveryTrackStatus::STARTED);

            $tracks = $this->upsertTracks($tasks, $deliveryPerson, [
                'status' => DeliveryTrackStatus::STARTED,
            ]);

            $this->systemLogs->info(
                'delivery.trip.started',
                "Delivery trip started by {$deliveryPerson->name}",
                [
                    'doctor_id' => $doctorId,
                    'order_ids' => $orderIds,
                    'task_ids' => $taskIds,
                ],
                $this->resolveLabIdFromTasks($tasks),
                $deliveryPerson->id,
            );

            DB::afterCommit(function () use ($tasks, $deliveryPerson): void {
                $orders = $tasks->pluck('order');

                $tasks->first()->order->user->notify(new TrackingStateNotification(
                    orders: $orders,
                    state: 'started',
                    deliveryPersonId: $deliveryPerson->id,
                    deliveryPersonName: $deliveryPerson->name,
                    deliveryPersonPhone: $deliveryPerson->phone ?? '',
                ));
            });

            return ['doctor_id' => $doctorId, 'tracks' => $tracks];
        });
    }

    /**
     * Update the delivery person's latest location for a group of tasks and broadcast via Pusher.
     *
     * @param  int[]  $taskIds
     * @return array{doctor_id:int, tracks:Collection<int, DeliveryTrack>}
     */
    public function updateLocation(
        array $taskIds,
        User $deliveryPerson,
        float $latitude,
        float $longitude,
        ?string $locationRecordedAt = null,
    ): array {
        return DB::transaction(function () use ($taskIds, $deliveryPerson, $latitude, $longitude, $locationRecordedAt): array {
            $tasks = $this->resolveTasks($taskIds, $deliveryPerson);
            $this->assertSameDoctor($tasks);

            $doctorId = $this->doctorIdFromTasks($tasks);
            $orderIds = $tasks->pluck('order_id')->all();

            $this->assertTripNotTerminal($tasks);

            $tracks = $this->upsertTracks($tasks, $deliveryPerson, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_recorded_at' => $locationRecordedAt ?? now(),
            ]);

            DB::afterCommit(function () use ($doctorId, $taskIds, $orderIds, $deliveryPerson, $latitude, $longitude, $tracks): void {
                broadcast(new DeliveryLocationUpdated(
                    doctorId: $doctorId,
                    taskIds: $taskIds,
                    orderIds: $orderIds,
                    latitude: $latitude,
                    longitude: $longitude,
                    deliveryPersonId: $deliveryPerson->id,
                    status: $tracks->first()->status,
                    locationRecordedAt: $tracks->first()->location_recorded_at?->toIso8601String(),
                ));
            });

            return ['doctor_id' => $doctorId, 'tracks' => $tracks];
        });
    }

    /**
     * End the delivery trip for a group of tasks: mark all as 'arrived'.
     *
     * @param  int[]  $taskIds
     * @return array{doctor_id:int, tracks:Collection<int, DeliveryTrack>}
     */
    public function endTrip(array $taskIds, User $deliveryPerson): array
    {
        return DB::transaction(function () use ($taskIds, $deliveryPerson): array {
            $tasks = $this->resolveTasks($taskIds, $deliveryPerson);
            $this->assertSameDoctor($tasks);

            $doctorId = $this->doctorIdFromTasks($tasks);
            $orderIds = $tasks->pluck('order_id')->all();

            $this->assertCanTransitionTo($tasks, DeliveryTrackStatus::ARRIVED);

            $tracks = DeliveryTrack::whereIn('delivery_task_id', $tasks->pluck('id')->all())
                ->get();

            foreach ($tracks as $track) {
                $track->update(['status' => DeliveryTrackStatus::ARRIVED]);
            }

            $this->systemLogs->info(
                'delivery.trip.ended',
                "Delivery trip ended by {$deliveryPerson->name}",
                [
                    'doctor_id' => $doctorId,
                    'order_ids' => $orderIds,
                    'task_ids' => $taskIds,
                ],
                $this->resolveLabIdFromTasks($tasks),
                $deliveryPerson->id,
            );

            DB::afterCommit(function () use ($doctorId, $taskIds, $orderIds, $deliveryPerson, $tracks, $tasks): void {
                $lastTrack = $tracks->first();

                broadcast(new DeliveryLocationUpdated(
                    doctorId: $doctorId,
                    taskIds: $taskIds,
                    orderIds: $orderIds,
                    latitude: (float) $lastTrack->latitude,
                    longitude: (float) $lastTrack->longitude,
                    deliveryPersonId: $deliveryPerson->id,
                    status: DeliveryTrackStatus::ARRIVED,
                    locationRecordedAt: now()->toIso8601String(),
                ));

                $orders = $tasks->pluck('order');

                $tasks->first()->order->user->notify(new TrackingStateNotification(
                    orders: $orders,
                    state: 'arrived',
                    deliveryPersonId: $deliveryPerson->id,
                    deliveryPersonName: $deliveryPerson->name,
                    deliveryPersonPhone: $deliveryPerson->phone ?? '',
                ));
            });

            return ['doctor_id' => $doctorId, 'tracks' => $tracks];
        });
    }

    /**
     * Resolve the given task ids to delivery tasks assigned to the delivery person.
     *
     * @param  int[]  $taskIds
     * @return Collection<int, DeliveryTask>
     */
    private function resolveTasks(array $taskIds, User $deliveryPerson): Collection
    {
        $tasks = DeliveryTask::query()
            ->whereIn('id', $taskIds)
            ->where('user_id', $deliveryPerson->id)
            ->with(['order.user'])
            ->get();

        if ($tasks->count() !== count($taskIds)) {
            throw ValidationException::withMessages([
                'task_ids' => __('orders.tracking_task_not_found'),
            ]);
        }

        return $tasks;
    }

    /**
     * @param  Collection<int, DeliveryTask>  $tasks
     */
    private function assertSameDoctor(Collection $tasks): void
    {
        if ($tasks->pluck('order.user_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'task_ids' => __('orders.tracking_tasks_same_doctor'),
            ]);
        }
    }

    /**
     * @param  Collection<int, DeliveryTask>  $tasks
     */
    private function doctorIdFromTasks(Collection $tasks): int
    {
        return (int) $tasks->first()->order->user_id;
    }

    /**
     * Enforce the state machine for the track of every given task.
     *
     * @param  Collection<int, DeliveryTask>  $tasks
     */
    private function assertCanTransitionTo(Collection $tasks, string $targetStatus): void
    {
        foreach ($tasks as $task) {
            $track = DeliveryTrack::where('delivery_task_id', $task->id)->first();

            $currentStatus = $track?->status ?? DeliveryTrackStatus::PENDING;

            if (! DeliveryTrackStatus::canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'task_ids' => __(
                        'orders.tracking_invalid_transition',
                        [
                            'order_id' => $task->order_id,
                            'from' => $currentStatus,
                            'to' => $targetStatus,
                            'allowed' => implode(', ', DeliveryTrackStatus::getNextAllowedStates($currentStatus)),
                        ],
                    ),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, DeliveryTask>  $tasks
     */
    private function assertTripNotTerminal(Collection $tasks): void
    {
        foreach ($tasks as $task) {
            $track = DeliveryTrack::where('delivery_task_id', $task->id)->first();

            if ($track !== null && in_array($track->status, [DeliveryTrackStatus::ARRIVED, DeliveryTrackStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'task_ids' => __('orders.tracking_location_after_terminal', [
                        'order_id' => $task->order_id,
                        'status' => $track->status,
                    ]),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, DeliveryTask>  $tasks
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, DeliveryTrack>
     */
    private function upsertTracks(Collection $tasks, User $deliveryPerson, array $attributes): Collection
    {
        $tracks = new Collection;

        foreach ($tasks as $task) {
            $tracks->push(DeliveryTrack::updateOrCreate(
                ['delivery_task_id' => $task->id],
                [
                    'order_id' => $task->order_id,
                    'delivery_person_id' => $deliveryPerson->id,
                    ...$attributes,
                ],
            ));
        }

        return $tracks;
    }

    /**
     * @param  Collection<int, DeliveryTask>  $tasks
     */
    private function resolveLabIdFromTasks(Collection $tasks): ?int
    {
        $orderId = $tasks->first()?->order_id;

        if ($orderId === null) {
            return null;
        }

        return Order::query()->whereKey($orderId)->value('lab_id');
    }

    /**
     * Get the active delivery trip for a doctor (if any).
     *
     * @return array{delivery_person_id:int, task_ids:int[], order_ids:int[], tracks:Collection<int, DeliveryTrack>, delivery_person:array}|null
     */
    public function getActiveTripForDoctor(array $orderIds): ?array
    {
        // Find all tracks with STARTED status whose task belongs to these order IDs
        $tracks = DeliveryTrack::query()
            ->whereHas('deliveryTask', fn ($query) => $query->whereIn('order_id', $orderIds))
            ->where('status', DeliveryTrackStatus::STARTED)
            ->with(['order', 'deliveryPerson'])
            ->get();

        if ($tracks->isEmpty()) {
            return null;
        }

        // Validate all matching tracks belong to the same delivery person
        $deliveryPersonIds = $tracks->pluck('delivery_person_id')->unique()->toArray();

        if (count($deliveryPersonIds) > 1) {
            throw new InvalidArgumentException('Tracks belong to multiple delivery persons');
        }

        $deliveryPersonId = $deliveryPersonIds[0];

        // Sort tracks by location_recorded_at descending
        $tracks = $tracks->sortByDesc('location_recorded_at');

        $taskIds = $tracks->pluck('delivery_task_id')->all();

        return [
            'delivery_person_id' => $deliveryPersonId,
            'task_ids' => array_values($taskIds),
            'order_ids' => array_values($orderIds),
            'tracks' => $tracks,
            'delivery_person' => [
                'id' => $tracks[0]->deliveryPerson->id,
                'name' => $tracks[0]->deliveryPerson->name,
                'phone' => $tracks[0]->deliveryPerson->phone,
            ],
        ];
    }
}
