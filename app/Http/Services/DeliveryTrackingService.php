<?php

namespace App\Http\Services;

use App\Events\DeliveryLocationUpdated;
use App\Models\DeliveryTask;
use App\Models\DeliveryTrack;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Tracking\TrackingStateNotification;
use App\Support\DeliveryStatus;
use App\Support\DeliveryTrackStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTrackingService
{
    /**
     * Start a delivery trip: create or update the tracking record to 'started'.
     */
    public function startTrip(int $orderId, User $deliveryPerson): DeliveryTrack
    {
        return DB::transaction(function () use ($orderId, $deliveryPerson): DeliveryTrack {
            $order = $this->findOrder($orderId);
            $this->validateDeliveryAssignment($order, $deliveryPerson);

            $existingTrack = DeliveryTrack::where('order_id', $orderId)->first();

            // Validate state transition
            if ($existingTrack !== null) {
                if (! DeliveryTrackStatus::canTransition($existingTrack->status, DeliveryTrackStatus::STARTED)) {
                    $nextAllowed = DeliveryTrackStatus::getNextAllowedStates($existingTrack->status);
                    throw ValidationException::withMessages([
                        'order_id' => "Cannot start trip. Invalid status transition from '{$existingTrack->status}' to 'started'. Can only start from: ".(empty($nextAllowed) ? "'pending'" : "'".implode("', '", $nextAllowed)."'"),
                    ]);
                }
            }

            $track = DeliveryTrack::updateOrCreate(
                ['order_id' => $orderId],
                [
                    'delivery_person_id' => $deliveryPerson->id,
                    'status' => DeliveryTrackStatus::STARTED,
                ],
            );

            DB::afterCommit(function () use ($order): void {
                $order->user->notify(new TrackingStateNotification($order, 'started'));
            });

            return $track;
        });
    }

    /**
     * Update the delivery person's latest location and broadcast via Pusher.
     */
    public function updateLocation(
        int $orderId,
        User $deliveryPerson,
        float $latitude,
        float $longitude,
        ?string $locationRecordedAt = null,
    ): DeliveryTrack {
        return DB::transaction(function () use ($orderId, $deliveryPerson, $latitude, $longitude, $locationRecordedAt): DeliveryTrack {
            $order = $this->findOrder($orderId);
            $this->validateDeliveryAssignment($order, $deliveryPerson);
            $this->validateActiveTrip($orderId);

            $track = DeliveryTrack::updateOrCreate(
                ['order_id' => $orderId],
                [
                    'delivery_person_id' => $deliveryPerson->id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_recorded_at' => $locationRecordedAt ?? now(),
                ],
            );

            DB::afterCommit(function () use ($track): void {
                broadcast(new DeliveryLocationUpdated(
                    orderId: $track->order_id,
                    latitude: $track->latitude,
                    longitude: $track->longitude,
                    deliveryPersonId: $track->delivery_person_id,
                    status: $track->status,
                    locationRecordedAt: $track->location_recorded_at?->toIso8601String(),
                ));
            });

            return $track;
        });
    }

    /**
     * End the delivery trip: mark as 'arrived'.
     */
    public function endTrip(int $orderId, User $deliveryPerson): DeliveryTrack
    {
        return DB::transaction(function () use ($orderId, $deliveryPerson): DeliveryTrack {
            $order = $this->findOrder($orderId);
            $this->validateDeliveryAssignment($order, $deliveryPerson);

            $track = DeliveryTrack::where('order_id', $orderId)
                ->where('delivery_person_id', $deliveryPerson->id)
                ->firstOrFail();

            if (! DeliveryTrackStatus::canTransition($track->status, DeliveryTrackStatus::ARRIVED)) {
                $nextAllowed = DeliveryTrackStatus::getNextAllowedStates($track->status);
                throw ValidationException::withMessages([
                    'order_id' => "Cannot end trip. Invalid status transition from '{$track->status}' to 'arrived'. Can only end from: ".(empty($nextAllowed) ? "'started'" : "'".implode("', '", $nextAllowed)."'"),
                ]);
            }

            $track->update(['status' => DeliveryTrackStatus::ARRIVED]);

            DB::afterCommit(function () use ($track, $order): void {
                broadcast(new DeliveryLocationUpdated(
                    orderId: $track->order_id,
                    latitude: (float) $track->latitude,
                    longitude: (float) $track->longitude,
                    deliveryPersonId: $track->delivery_person_id,
                    status: DeliveryTrackStatus::ARRIVED,
                    locationRecordedAt: now()->toIso8601String(),
                ));

                $order->user->notify(new TrackingStateNotification($order, 'arrived'));
            });

            return $track->refresh();
        });
    }

    private function findOrder(int $orderId): Order
    {
        $order = Order::find($orderId);

        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => __('validation.exists', ['attribute' => 'order']),
            ]);
        }

        return $order;
    }

    private function validateDeliveryAssignment(Order $order, User $deliveryPerson): void
    {
        $hasActiveAssignment = DeliveryTask::where('order_id', $order->id)
            ->where('user_id', $deliveryPerson->id)
            ->whereIn('status', DeliveryStatus::ASSIGNED_STATUSES)
            ->exists();

        if (! $hasActiveAssignment) {
            throw ValidationException::withMessages([
                'order_id' => 'You are not assigned to deliver this order.',
            ]);
        }
    }

    private function validateActiveTrip(int $orderId): void
    {
        $track = DeliveryTrack::where('order_id', $orderId)->first();

        if ($track !== null && in_array($track->status, [DeliveryTrackStatus::ARRIVED, DeliveryTrackStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'order_id' => "Cannot update location for a trip with status '{$track->status}'.",
            ]);
        }
    }
}
