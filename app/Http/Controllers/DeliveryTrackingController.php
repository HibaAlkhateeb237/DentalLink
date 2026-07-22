<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeliveryLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DeliveryTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryTrackingController extends Controller
{
    public function __construct(
        private readonly DeliveryTrackingService $trackingService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function startTrip(int $orderId, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $track = $this->trackingService->startTrip($orderId, $user);

        return $this->apiResponse->success(
            [
                'track_id' => $track->id,
                'order_id' => $track->order_id,
                'status' => $track->status,
            ],
            __('Tracking trip started successfully.'),
            201,
        );
    }

    public function updateLocation(UpdateDeliveryLocationRequest $request, int $orderId): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $validated = $request->validated();

        $track = $this->trackingService->updateLocation(
            orderId: $orderId,
            deliveryPerson: $user,
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            locationRecordedAt: $validated['location_recorded_at'] ?? null,
        );

        return $this->apiResponse->success(
            [
                'track_id' => $track->id,
                'order_id' => $track->order_id,
                'latitude' => $track->latitude,
                'longitude' => $track->longitude,
                'status' => $track->status,
            ],
            __('Location updated successfully.'),
        );
    }

    public function endTrip(int $orderId, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $track = $this->trackingService->endTrip($orderId, $user);

        return $this->apiResponse->success(
            [
                'track_id' => $track->id,
                'order_id' => $track->order_id,
                'status' => $track->status,
            ],
            __('Tracking trip ended successfully.'),
        );
    }
}
