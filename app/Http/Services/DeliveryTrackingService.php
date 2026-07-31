<?php

namespace App\Http\Services;

use App\Events\DeliveryLocationUpdated;
use App\Models\DeliveryTask;
use App\Models\DeliveryTrack;
use App\Models\User;
use App\Notifications\Tracking\TrackingStateNotification;
use App\Support\DeliveryTrackStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTrackingService
{
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

            $this->assertCanTransitionTo($orderIds, $deliveryPerson, DeliveryTrackStatus::STARTED);

            $tracks = $this->upsertTracks($orderIds, $deliveryPerson, [
                'status' => DeliveryTrackStatus::STARTED,
            ]);

            DB::afterCommit(function () use ($tasks): void {
                $tasks->each(function (DeliveryTask $task): void {
                    $task->order->user->notify(new TrackingStateNotification($task->order, 'started'));
                });
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

            $this->assertTripNotTerminal($orderIds, $deliveryPerson);

            $tracks = $this->upsertTracks($orderIds, $deliveryPerson, [
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

            $this->assertCanTransitionTo($orderIds, $deliveryPerson, DeliveryTrackStatus::ARRIVED);

            $tracks = DeliveryTrack::whereIn('order_id', $orderIds)
                ->where('delivery_person_id', $deliveryPerson->id)
                ->get();

            foreach ($tracks as $track) {
                $track->update(['status' => DeliveryTrackStatus::ARRIVED]);
            }

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

                $tasks->each(function (DeliveryTask $task): void {
                    $task->order->user->notify(new TrackingStateNotification($task->order, 'arrived'));
                });
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
     * Enforce the state machine for every order of the trip.
     *
     * @param  int[]  $orderIds
     */
    private function assertCanTransitionTo(array $orderIds, User $deliveryPerson, string $targetStatus): void
    {
        foreach ($orderIds as $orderId) {
            $track = DeliveryTrack::where('order_id', $orderId)
                ->where('delivery_person_id', $deliveryPerson->id)
                ->first();

            $currentStatus = $track?->status ?? DeliveryTrackStatus::PENDING;

            if (! DeliveryTrackStatus::canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'task_ids' => __(
                        'orders.tracking_invalid_transition',
                        [
                            'order_id' => $orderId,
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
     * @param  int[]  $orderIds
     */
    private function assertTripNotTerminal(array $orderIds, User $deliveryPerson): void
    {
        foreach ($orderIds as $orderId) {
            $track = DeliveryTrack::where('order_id', $orderId)
                ->where('delivery_person_id', $deliveryPerson->id)
                ->first();

            if ($track !== null && in_array($track->status, [DeliveryTrackStatus::ARRIVED, DeliveryTrackStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'task_ids' => __('orders.tracking_location_after_terminal', [
                        'order_id' => $orderId,
                        'status' => $track->status,
                    ]),
                ]);
            }
        }
    }

    /**
     * @param  int[]  $orderIds
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, DeliveryTrack>
     */
    private function upsertTracks(array $orderIds, User $deliveryPerson, array $attributes): Collection
    {
        $tracks = new Collection;

        foreach ($orderIds as $orderId) {
            $tracks->push(DeliveryTrack::updateOrCreate(
                ['order_id' => $orderId],
                [
                    'delivery_person_id' => $deliveryPerson->id,
                    ...$attributes,
                ],
            ));
        }

        return $tracks;
    }

    /**
     * Get the active delivery trip for a doctor (if any).
     *
     * @return array{doctor_id:int, task_ids:int[], order_ids:int[], tracks:Collection<int, DeliveryTrack>, delivery_person:array}|null
     */
    public function getActiveTripForDoctor(int $doctorId): ?array
    {
        // Find all tracks with STARTED status for orders belonging to this doctor
        $tracks = DeliveryTrack::query()
            ->where('status', DeliveryTrackStatus::STARTED)
            ->whereHas('order', function ($query) use ($doctorId): void {
                $query->where('user_id', $doctorId);
            })
            ->with(['order', 'deliveryPerson'])
            ->get();

        if ($tracks->isEmpty()) {
            return null;
        }

        // Group by delivery person (in case multiple delivery people have active trips for this doctor)
        // We return the first active trip (most recent by location_recorded_at)
        $grouped = $tracks->groupBy('delivery_person_id');

        // For now, return the trip with the most recent location update
        $activeTrip = $grouped->map(function ($personTracks) {
            return $personTracks->sortByDesc('location_recorded_at')->first();
        })->sortByDesc('location_recorded_at')->first();

        if ($activeTrip === null) {
            return null;
        }

        // Get all tracks for this delivery person and doctor (the full trip)
        $tripTracks = DeliveryTrack::query()
            ->where('status', DeliveryTrackStatus::STARTED)
            ->where('delivery_person_id', $activeTrip->delivery_person_id)
            ->whereHas('order', function ($query) use ($doctorId): void {
                $query->where('user_id', $doctorId);
            })
            ->with(['order', 'deliveryPerson'])
            ->get();

        // Get the associated task_ids
        $orderIds = $tripTracks->pluck('order_id')->all();
        $taskIds = DeliveryTask::query()
            ->whereIn('order_id', $orderIds)
            ->where('user_id', $activeTrip->delivery_person_id)
            ->pluck('id')
            ->all();

        return [
            'doctor_id' => $doctorId,
            'task_ids' => array_values($taskIds),
            'order_ids' => array_values($orderIds),
            'tracks' => $tripTracks,
            'delivery_person' => [
                'id' => $activeTrip->deliveryPerson->id,
                'name' => $activeTrip->deliveryPerson->name,
                'phone' => $activeTrip->deliveryPerson->phone,
            ],
        ];
    }
}
