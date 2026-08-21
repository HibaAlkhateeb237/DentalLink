<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionistOrderIndexRequest;
use App\Http\Requests\ReceptionistOrderResubmissionRequest;
use App\Http\Requests\ReceptionistOrderStatusUpdateRequest;
use App\Http\Resources\ReceptionistOrderDetailsResource;
use App\Http\Resources\ReceptionistOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\OrderLockService;
use App\Http\Services\OrderNotificationService;
use App\Http\Services\ReceptionistOrderService;
use App\Models\Department;
use App\Models\Order;
use App\Models\Task;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
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
        private OrderLockService $orderLockService,
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

        $owner = $this->orderLockService->getOwner($order);

        return $this->apiResponse->success(
            [
                'order' => ReceptionistOrderDetailsResource::make($order)->resolve(),
                'lock' => [
                    'is_locked' => $owner !== null,
                    'locked_by' => $owner['user_id'] ?? null,
                    'locked_by_name' => $owner['name'] ?? null,
                ],
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
        Gate::authorize('price', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        if ($this->orderLockService->isLockedByOther($order, $user)) {
            $owner = $this->orderLockService->getOwner($order);

            return $this->apiResponse->error(
                __('orders.order_locked_by_another', ['name' => $owner['name'] ?? 'another user']),
                423
            );
        }

        if ($order->is_status_finalized) {
            return $this->apiResponse->error(__('orders.status_finalized'), 409);
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

    public function lock(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('price', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $result = $this->orderLockService->acquire($order, $user);

        if (! $result['success']) {
            return $this->apiResponse->error($result['message'], 423, [
                'locked_by' => $result['locked_by'],
                'locked_by_name' => $result['locked_by_name'],
            ]);
        }

        return $this->apiResponse->success(
            [
                'locked_by' => $result['locked_by'],
                'locked_by_name' => $result['locked_by_name'],
                'expires_at' => $result['expires_at'],
            ],
            __('orders.order_locked'),
            200,
        );
    }

    public function unlock(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('price', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $result = $this->orderLockService->release($order, $user);

        if (! $result['success']) {
            return $this->apiResponse->error($result['message'], 423);
        }

        return $this->apiResponse->success(null, __('orders.order_unlocked'));
    }

    public function qrImage(Request $request, Order $order): Response|JsonResponse
    {
        Gate::authorize('price', $order);

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
            $regenerated = $this->ensureQrImageExists($order);

            if ($regenerated === null) {
                return $this->apiResponse->error(__('messages.file_not_found'), 404);
            }

            $path = $regenerated;
        }

        if ($order->qr_printed_at !== null) {
            return $this->apiResponse->error(__('orders.qr_already_printed'), 409);
        }

        return DB::transaction(function () use ($order, $path) {

            $order->qr_printed_at = now();
            $order->save();

            /*    $this->receptionistOrderService->updateStatus(
                    $order,
                    OrderStatus::IN_PROGRESS,
                    null,
                    $user,
                );*/

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

              //  $this->orderNotificationService->notifyDepartmentManagerAboutUrgentCase($task);

            }

            return Storage::disk('public')->response($path, 'qr.png', [
                'Content-Type' => 'image/png',
            ]);
        });
    }

    /**
     * (Re)generate the order's QR image on disk if it is missing.
     *
     * @return string|null The stored path when generation succeeds, otherwise null.
     */
    private function ensureQrImageExists(Order $order): ?string
    {
        if (! filled($order->qr_code)) {
            return null;
        }

        $path = 'orders/'.$order->qr_code.'/qr.png';

        try {
            $result = Builder::create()
                ->writer(new PngWriter)
                ->data(route('orders.show-qr', ['qr' => $order->qr_code]))
                ->size(300)
                ->build();

            Storage::disk('public')->put($path, $result->getString());

            $order->qr_image_path = $path;
            $order->save();

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
