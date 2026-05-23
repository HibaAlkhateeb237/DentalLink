<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionistOrderIndexRequest;
use App\Http\Requests\ReceptionistOrderResubmissionRequest;
use App\Http\Resources\ReceptionistOrderDetailsResource;
use App\Http\Resources\ReceptionistOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\ReceptionistOrderService;
use App\Models\Order;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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
            $orders->through(fn(Order $order): array => ReceptionistOrderListResource::make($order)->resolve()),
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

    public function qrImage(Request $request, Order $order): Response|JsonResponse
    {
        Gate::authorize('view', $order);

        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $user->loadMissing('departmentUserRoles.department');

        $labIds = $user->departmentUserRoles
            ->map(static fn($departmentUserRole): ?int => $departmentUserRole->department?->lab_id)
            ->filter()
            ->unique();

        if ($labIds->isEmpty() || ! $labIds->contains($order->lab_id)) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $path = $order->qr_image_path;

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            $generation = $this->generateQrImage($order);

            if ($generation['path'] === null) {
                $shouldExposeReason = app()->isLocal() || (bool) config('app.debug');

                return $this->apiResponse->error(
                    __('messages.error'),
                    500,
                    $shouldExposeReason ? ['reason' => $generation['error']] : null,
                );
            }

            $path = $generation['path'];
        }

        return Storage::disk('public')->response($path, 'qr.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    /**
     * @return array{path:?string,error:?string}
     */
    private function generateQrImage(Order $order): array
    {
        try {
            if (! filled($order->qr_code)) {
                $order->qr_code = (string) Str::uuid();
                $order->save();
            }

            $qrData = route('doctor.orders.show-qr', ['order' => $order->qr_code]);

            $result = Builder::create()
                ->writer(new PngWriter)
                ->data($qrData)
                ->size(300)
                ->build();

            $png = $result->getString();
            $path = 'orders/' . $order->qr_code . '/qr.png';

            Storage::disk('public')->put($path, $png);

            $order->qr_image_path = $path;
            $order->save();

            return ['path' => $path, 'error' => null];
        } catch (\Throwable $exception) {
            return ['path' => null, 'error' => $exception->getMessage()];
        }
    }
}
