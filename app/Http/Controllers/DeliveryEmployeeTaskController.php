<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryEmployeeTaskIndexRequest;
use App\Http\Requests\DeliveryEmployeeUpdateStatusRequest;
use App\Http\Resources\DeliveryEmployeeTaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DeliveryEmployeeTaskService;
use App\Models\DeliveryTask;
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

    public function updateStatus(DeliveryEmployeeUpdateStatusRequest $request, DeliveryTask $deliveryTask): JsonResponse
    {
        $task = $this->deliveryEmployeeTaskService->updateStatus(
            $deliveryTask,
            $request->validated('status'),
        );

        return $this->apiResponse->success(
            [
                'delivery_task' => DeliveryEmployeeTaskResource::make($task)->resolve(),
            ],
            __('orders.delivery_status_updated'),
            200,
        );
    }
}
