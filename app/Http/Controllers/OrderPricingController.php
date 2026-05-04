<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderPricingCalculateRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OrderPricingService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderPricingController extends Controller
{
    public function __construct(
        private OrderPricingService $orderPricingService,
        private ApiResponse $apiResponse,
    ) {}

    public function calculate(OrderPricingCalculateRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('price', $order);

        $validated = $request->validated();
        $persist = (bool) ($validated['persist'] ?? false);

        if ($persist) {
            $updatedOrder = $this->orderPricingService->apply($order, $validated);
            $breakdown = $this->orderPricingService->calculate($updatedOrder, $validated);

            return $this->apiResponse->success(
                [
                    'order_id' => $updatedOrder->id,
                    'price' => $updatedOrder->price,
                    'remaining_amount' => $updatedOrder->remaining_amount,
                    'breakdown' => $breakdown,
                ],
                __('pricing.applied_successfully'),
                200,
            );
        }

        $breakdown = $this->orderPricingService->calculate($order, $validated);

        return $this->apiResponse->success(
            [
                'order_id' => $order->id,
                'breakdown' => $breakdown,
            ],
            __('pricing.calculated_successfully'),
            200,
        );
    }
}
