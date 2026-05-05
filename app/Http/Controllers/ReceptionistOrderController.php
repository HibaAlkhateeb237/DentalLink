<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionistOrderIndexRequest;
use App\Http\Requests\ReceptionistOrderResubmissionRequest;
use App\Http\Resources\ReceptionistOrderDetailsResource;
use App\Http\Resources\ReceptionistOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\ReceptionistOrderService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReceptionistOrderController extends Controller
{
    public function __construct(
        private readonly ReceptionistOrderService $receptionistOrderService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function index(ReceptionistOrderIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $orders = $this->receptionistOrderService->listOrders($request->validated());

        return $this->apiResponse->success(
            $orders->through(fn (Order $order): array => ReceptionistOrderListResource::make($order)->resolve()),
            __('orders.retrieved_successfully'),
            200,
        );
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order = $this->receptionistOrderService->getOrderDetails($order);

        return $this->apiResponse->success(
            [
                'order' => ReceptionistOrderDetailsResource::make($order)->resolve(),
            ],
            __('orders.details_retrieved_successfully'),
            200,
        );
    }

    public function markForResubmission(ReceptionistOrderResubmissionRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('markForResubmission', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $updatedOrder = $this->receptionistOrderService->markForResubmission(
            $order,
            $request->string('reason')->toString(),
            $user,
        );

        return $this->apiResponse->success(
            [
                'order' => ReceptionistOrderDetailsResource::make($updatedOrder)->resolve(),
            ],
            __('orders.resubmission_marked_successfully'),
            200,
        );
    }
}
