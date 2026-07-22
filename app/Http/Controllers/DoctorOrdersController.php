<?php

namespace App\Http\Controllers;

use App\Http\Resources\DoctorOrdersResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DoctorOrdersService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorOrdersController extends Controller
{
    public function __construct(
        private DoctorOrdersService $doctorOrdersService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $doctors = $this->doctorOrdersService->getDoctorsWithOrders($search);

        return $this->apiResponse->success(
            DoctorOrdersResource::collection($doctors)->toArray($request),
            __('orders.retrieved_successfully'),
            200,
        );
    }

    public function show(Request $request, User $doctor): JsonResponse
    {
        $paymentFilter = $this->resolvePaymentFilter($request->query('status'));
        $page = $this->resolvePage($request->query('page'));
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $result = $this->doctorOrdersService->getDoctorOrdersPaginated($doctor, $paymentFilter, $page, $perPage);

        if ($result === null) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        $data = DoctorOrdersResource::make($result['doctor'])->toArray($request);
        $data['pagination'] = $this->buildPaginationMeta($result['paginator']);

        return $this->apiResponse->success(
            $data,
            __('orders.retrieved_successfully'),
            200,
        );
    }

    /**
     * Resolve the paid/unpaid filter from the `status` query param.
     *
     * @return 'paid'|'unpaid'|null
     */
    private function resolvePaymentFilter(mixed $value): ?string
    {
        return in_array($value, ['paid', 'unpaid'], true) ? $value : null;
    }

    private function resolvePage(mixed $value): int
    {
        $page = is_numeric($value) ? (int) $value : 1;

        return $page >= 1 ? $page : 1;
    }

    private function resolvePerPage(mixed $value): int
    {
        $perPage = is_numeric($value) ? (int) $value : 15;

        return $perPage >= 1 ? min($perPage, 100) : 15;
    }

    /**
     * @param  LengthAwarePaginator<int, Order>  $paginator
     * @return array<string, mixed>
     */
    private function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
