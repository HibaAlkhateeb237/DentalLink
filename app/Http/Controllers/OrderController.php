<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderStoreRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OrderService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['toothShade', 'dentalCompensationTypePrice.dentalCompensationType', 'orderTeeth'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->apiResponse->success(
            OrderResource::collection($orders),
            __('orders.retrieved_successfully')
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        // Authorization: only the owner (doctor) can view their order
        if ($order->user_id !== $request->user()->id) {
            return $this->apiResponse->error(
                __('messages.unauthorized'),
                403
            );
        }

        $order->load(['toothShade', 'dentalCompensationTypePrice.dentalCompensationType', 'orderTeeth', 'orderFiles']);

        return $this->apiResponse->success(
            OrderDetailResource::make($order),
            __('orders.retrieved_successfully')
        );
    }
}
