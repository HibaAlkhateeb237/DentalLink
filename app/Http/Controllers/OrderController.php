<?php

namespace App\Http\Controllers;

use App\Http\Requests\DoctorOrderIndexRequest;
use App\Http\Requests\DoctorOrderPrintStatusRequest;
use App\Http\Requests\DoctorOrderTrackRequest;
use App\Http\Requests\OrderStoreRequest;
use App\Http\Resources\DoctorOrderPaymentStatusResource;
use App\Http\Resources\DoctorOrderTrackingResource;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderShortResource;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DoctorOrderTrackingService;
use App\Http\Services\OrderNotificationService;
use App\Http\Services\OrderService;
use App\Models\Order;
use App\Models\Task;
use App\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderNotificationService $notificationService,
        private ApiResponse $apiResponse,
        private DoctorOrderTrackingService $trackingService
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

    public function index(DoctorOrderIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->when($request->status, function ($query, $status) {
                if ($status === OrderStatus::PENDING) {

                    $query->whereIn('status', [OrderStatus::PENDING, OrderStatus::NEW]);
                } elseif ($status === OrderStatus::RESEND_WRONG_IMPRESSION) {
                    $query->whereIn('status', [OrderStatus::RESEND_WRONG_IMPRESSION, OrderStatus::TRY_ON]);
                } else {

                    $query->where('status', $status);
                }
            })
            ->with(['lab', 'toothShade', 'dentalCompensationTypePrice.dentalCompensationType', 'orderTeeth'])
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

    public function showByQr(Request $request, string $qr): JsonResponse
    {
        $isDepartmentManagerRoute = $request->routeIs('department.manager.orders.show-qr');

        $order = Order::query()
            ->where('qr_code', $qr)
            ->firstOrFail();

        $task = Task::query()
            ->where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $task && ! $isDepartmentManagerRoute) {
            return $this->apiResponse->error(__('You have no task associated with this order.'), 404);
        }

        $order->load(['toothShade', 'dentalCompensationTypePrice.dentalCompensationType', 'orderTeeth', 'orderFiles']);

        return $this->apiResponse->success([
            'order' => OrderShortResource::make($order),
            'task' => $task ? TaskResource::make($task) : null,
        ], __('orders.retrieved_successfully'));
    }

    public function track(DoctorOrderTrackRequest $request, Order $order): DoctorOrderTrackingResource
    {
        $trackingData = $this->trackingService->getTrackingDetails($order);

        return new DoctorOrderTrackingResource($trackingData);
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['lab', 'payments'])
            ->get();

        $filtered = match ($status) {
            'paid' => $orders->filter(fn (Order $order) => $order->payments->isNotEmpty()),
            'unpaid' => $orders->filter(fn (Order $order) => $order->payments->isEmpty()),
            default => $orders,
        };

        return $this->apiResponse->success(
            DoctorOrderPaymentStatusResource::collection($filtered),
            __('orders.retrieved_successfully')
        );
    }

    public function printStatus(DoctorOrderPrintStatusRequest $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->apiResponse->error(
                __('messages.unauthorized'),
                403
            );
        }

        $validated = $request->validated();

        $this->notificationService->notifyReceptionistPrintStatus(
            $order,
            $validated['status'],
            $validated['doctor_notes'] ?? null,
        );

        return $this->apiResponse->success(
            null,
            __('orders.print_status_notified_successfully'),
            200,
        );
    }
}
