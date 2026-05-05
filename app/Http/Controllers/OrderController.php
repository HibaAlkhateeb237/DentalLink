<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderStoreRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OrderService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private ApiResponse $apiResponse,
    ) {}

    public function store(OrderStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['files'] = $request->file('files', []);

        $order = $this->orderService->createForDoctor($request->user(), $validated);

        return $this->apiResponse->success(
            OrderResource::make($order),
            __('orders.created_successfully'),
            201,
        );

    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['toothShade', 'dentalCompensationTypePrice.dentalCompensationType', 'orderTeeth']);

        return $this->apiResponse->success(
            OrderResource::make($order),
            __('orders.retrieved_successfully')
        );
    }
}
