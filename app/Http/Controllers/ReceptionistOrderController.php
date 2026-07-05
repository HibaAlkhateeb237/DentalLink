<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionistOrderIndexRequest;
use App\Http\Requests\ReceptionistOrderResubmissionRequest;
use App\Http\Requests\ReceptionistOrderStatusUpdateRequest;
use App\Http\Resources\ReceptionistOrderDetailsResource;
use App\Http\Resources\ReceptionistOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OrderNotificationService;
use App\Http\Services\ReceptionistOrderService;
use App\Models\Department;
use App\Models\Order;
use App\Models\Task;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReceptionistOrderController extends Controller
{
    public function __construct(
        private ReceptionistOrderService $receptionistOrderService,
        private OrderNotificationService $orderNotificationService,
        private ApiResponse $apiResponse,
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

    public function updateStatus(ReceptionistOrderStatusUpdateRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $updatedOrder = $this->receptionistOrderService->updateStatusAndDetails(
            $order,
            $request->validated(),
            $user,
        );

        return $this->apiResponse->success(
            [
                'order' => ReceptionistOrderDetailsResource::make($updatedOrder)->resolve(),
            ],
            __('orders.status_updated_successfully'),
            200,
        );
    }

    public function qrImage(Request $request, Order $order): Response|JsonResponse
    {
        Gate::authorize('view', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $user->loadMissing('departmentUserRoles.department');

        $labIds = $user->departmentUserRoles
            ->map(static fn ($departmentUserRole): ?int => $departmentUserRole->department?->lab_id)
            ->filter()
            ->unique();

        if ($labIds->isEmpty() || ! $labIds->contains($order->lab_id)) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $path = $order->qr_image_path;

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            return $this->apiResponse->error(__('messages.file_not_found'), 404);
        }

        return DB::transaction(function () use ($order, $user, $path) {

            $this->receptionistOrderService->updateStatus(
                $order,
                OrderStatus::IN_PROGRESS,
                null,
                $user,
            );

            $firstDepartment = Department::query()
                ->where('lab_id', $order->lab_id)
                ->where('sort_order', '>', 0)
                ->orderBy('sort_order', 'asc')
                ->first();

            if ($firstDepartment) {

                $task = Task::query()->firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'department_id' => $firstDepartment->id,
                    ],
                    [
                        'status' => TaskStatus::PENDING_ASSIGNMENT,
                        'user_id' => null,
                        'approved_at' => null,
                    ]
                );

                $this->orderNotificationService->notifyDepartmentManagerAboutUrgentCase($task);

            }

            return Storage::disk('public')->response($path, 'qr.png', [
                'Content-Type' => 'image/png',
            ]);
        });
    }
}
