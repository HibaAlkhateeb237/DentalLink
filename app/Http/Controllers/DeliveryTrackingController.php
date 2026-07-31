<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryTripRequest;
use App\Http\Requests\UpdateDeliveryLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DeliveryTrackingService;
use App\Models\DeliveryTrack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class DeliveryTrackingController extends Controller
{
    public function __construct(
        private readonly DeliveryTrackingService $trackingService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function startTrip(DeliveryTripRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $taskIds = $request->validated('task_ids');

        ['doctor_id' => $doctorId, 'tracks' => $tracks] = $this->trackingService->startTrip($taskIds, $user);

        return $this->apiResponse->success(
            $this->tripPayload($doctorId, $taskIds, $tracks),
            __('orders.tracking_started'),
            201,
        );
    }

    public function updateLocation(UpdateDeliveryLocationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $validated = $request->validated();

        ['doctor_id' => $doctorId, 'tracks' => $tracks] = $this->trackingService->updateLocation(
            taskIds: $validated['task_ids'],
            deliveryPerson: $user,
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            locationRecordedAt: $validated['location_recorded_at'] ?? null,
        );

        return $this->apiResponse->success(
            $this->tripPayload($doctorId, $validated['task_ids'], $tracks),
            __('orders.tracking_location_updated'),
        );
    }

    public function endTrip(DeliveryTripRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $taskIds = $request->validated('task_ids');

        ['doctor_id' => $doctorId, 'tracks' => $tracks] = $this->trackingService->endTrip($taskIds, $user);

        return $this->apiResponse->success(
            $this->tripPayload($doctorId, $taskIds, $tracks),
            __('orders.tracking_ended'),
        );
    }

    /**
     * @param  int[]  $taskIds
     * @param  Collection<int, DeliveryTrack>  $tracks
     */
    private function tripPayload(int $doctorId, array $taskIds, Collection $tracks): array
    {
        return [
            'doctor_id' => $doctorId,
            'task_ids' => array_values($taskIds),
            'order_ids' => $tracks->pluck('order_id')->values()->all(),
            'tracks' => $tracks->map(function (DeliveryTrack $track): array {
                $payload = [
                    'track_id' => $track->id,
                    'order_id' => $track->order_id,
                    'status' => $track->status,
                ];

                if ($track->latitude !== null && $track->longitude !== null) {
                    $payload['latitude'] = $track->latitude;
                    $payload['longitude'] = $track->longitude;
                }

                return $payload;
            })->values()->all(),
        ];
    }
}
