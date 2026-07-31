<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryTripRequest;
use App\Http\Requests\UpdateDeliveryLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DeliveryTrackingService;
use App\Models\DeliveryTrack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function getActiveTrip(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        // Determine the doctor_id: if user is doctor, use their own ID; if system_admin, require doctor_id param
        if ($user->hasRole('doctor')) {
            $doctorId = $user->id;
        } elseif ($user->hasRole('system_admin')) {
            $doctorId = $request->integer('doctor_id');
            if ($doctorId <= 0) {
                return $this->apiResponse->error(__('orders.tracking_doctor_id_required'), 400);
            }
        } else {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $activeTrip = $this->trackingService->getActiveTripForDoctor($doctorId);

        if ($activeTrip === null) {
            return $this->apiResponse->success([
                'active' => false,
                'message' => __('orders.tracking_no_active_trip'),
            ], __('orders.tracking_no_active_trip'));
        }

        return $this->apiResponse->success(
            array_merge($this->tripPayload($activeTrip['doctor_id'], $activeTrip['task_ids'], $activeTrip['tracks']), [
                'active' => true,
                'delivery_person' => $activeTrip['delivery_person'],
            ]),
            __('orders.tracking_active_trip_retrieved'),
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
