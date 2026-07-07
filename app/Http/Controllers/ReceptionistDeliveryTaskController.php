<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionistDeliveryAssignRequest;
use App\Http\Requests\ReceptionistDeliveryEmployeesRequest;
use App\Http\Requests\ReceptionistDeliveryTasksRequest;
use App\Http\Resources\DeliveryEmployeeResource;
use App\Http\Resources\DeliveryTaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\ReceptionistDeliveryService;
use App\Models\DeliveryTask;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReceptionistDeliveryTaskController extends Controller
{
    public function __construct(
        private readonly ReceptionistDeliveryService $receptionistDeliveryService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function employees(ReceptionistDeliveryEmployeesRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        if (! $user->hasPermission('delivery.assign')) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $employees = $this->receptionistDeliveryService->listDeliveryEmployees($user, $request->validated());
        $payload = DeliveryEmployeeResource::collection($employees)->response()->getData(true);

        return $this->apiResponse->success(
            $payload,
            __('orders.delivery_employees_retrieved'),
            200,
        );
    }

    public function tasks(ReceptionistDeliveryTasksRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        if (! $user->hasPermission('delivery.assign')) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $tasks = $this->receptionistDeliveryService->listDeliveryTasks($user, $request->validated());
        $payload = DeliveryTaskResource::collection($tasks)->response()->getData(true);

        return $this->apiResponse->success(
            $payload,
            __('orders.delivery_tasks_retrieved'),
            200,
        );
    }

    public function assign(ReceptionistDeliveryAssignRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);
        Gate::authorize('create', DeliveryTask::class);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $deliveryTask = $this->receptionistDeliveryService->assignDelivery(
            $user,
            $order,
            $request->integer('user_id') ?: 0,
        );

        return $this->apiResponse->success(
            [
                'delivery_task' => DeliveryTaskResource::make($deliveryTask)->resolve(),
            ],
            __('orders.delivery_assigned_successfully'),
            201,
        );
    }
}
