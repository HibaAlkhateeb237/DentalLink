<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryEmployeeBulkUpdateStatusRequest;
use App\Http\Requests\DeliveryEmployeeTaskIndexRequest;
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

        $groupedTasks = $this->deliveryEmployeeTaskService->getGroupedTasks($user, $request->validated());

        return $this->apiResponse->success(
            [
                'tasks' => $groupedTasks['data'],
            ],
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
