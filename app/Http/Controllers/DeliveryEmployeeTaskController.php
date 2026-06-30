<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryEmployeeBulkUpdateStatusRequest;
use App\Http\Requests\DeliveryEmployeeTaskIndexRequest;
use App\Http\Requests\DeliveryEmployeeUpdateStatusRequest;
use App\Http\Resources\DeliveryEmployeeTaskResource;
use App\Http\Resources\DeliveryTaskResource;
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


    public function bulkUpdateStatus(DeliveryEmployeeBulkUpdateStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $deliveryTaskIds = $validated['delivery_task_ids'];
        $newStatus = $validated['status'];

        $tasks = $this->deliveryEmployeeTaskService->bulkUpdateStatus(
            $deliveryTaskIds,
            $newStatus,
            $request->user(),
        );

        return $this->apiResponse->success(
            [
                'updated_tasks' => DeliveryTaskResource::collection($tasks)->resolve(),
            ],
            __('orders.delivery_tasks_updated'),
            200,
        );
    }
}
