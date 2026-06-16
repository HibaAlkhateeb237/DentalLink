<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryEmployeeTaskIndexRequest;
use App\Http\Resources\DeliveryEmployeeTaskResource;
use App\Http\Resources\DeliveryTaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DeliveryEmployeeTaskService;
use Illuminate\Http\JsonResponse;

class DeliveryEmployeeTaskController extends Controller
{
    public function __construct(
        private readonly DeliveryEmployeeTaskService $deliveryEmployeeTaskService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function index(DeliveryEmployeeTaskIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $tasks = $this->deliveryEmployeeTaskService->listTasks($user, $request->validated());
        $resourceData = DeliveryEmployeeTaskResource::collection($tasks)->response()->getData(true);
        $payload = array_merge(
            ['tasks' => $resourceData['data']], // غيري الاسم هنا إلى tasks لتكون واضحة للفرونت
            ['links' => $resourceData['links'] ?? null],
            ['meta' => $resourceData['meta'] ?? null]
        );

        return $this->apiResponse->success(
            $payload,
            __('orders.delivery_tasks_retrieved'),
            200,
        );
    }
}
