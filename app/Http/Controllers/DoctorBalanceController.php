<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\LabDoctorBalanceService;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorBalanceController extends Controller
{
    public function __construct(
        private LabDoctorBalanceService $balanceService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $labId = $this->balanceService->resolveManagerLabId($request->user());

        $search = $request->query('search');
        $page = $this->resolvePage($request->query('page'));
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $paginator = $this->balanceService->getDoctorBalancesPaginated($labId, $search, $page, $perPage);

        $data = $paginator->getCollection()->map(fn ($doctor): array => [
            'doctor_id' => $doctor->id,
            'name' => $doctor->name,
            'email' => $doctor->email,
            'phone' => $doctor->phone,
            'orders_count' => (int) $doctor->orders_count,
            'total_billed' => (float) $doctor->total_billed,
            'total_paid' => (float) $doctor->total_paid,
            'total_owed' => (float) $doctor->total_remaining,
        ]);

        $allDoctors = $this->balanceService->getDoctorBalances($labId, $search);
        $totalsBilled = (float) $allDoctors->sum('total_billed');
        $totalsPaid = (float) $allDoctors->sum('total_paid');

        $totals = [
            'repayment_percentage' => $totalsBilled > 0 ? round($totalsPaid / $totalsBilled * 100, 2) : 0,
            'total_billed' => $totalsBilled,
            'total_paid' => $totalsPaid,
            'total_owed' => (float) $allDoctors->sum('total_remaining'),
        ];

        return $this->apiResponse->success(
            [
                'doctors' => $data->values(),
                'totals' => $totals,
                'pagination' => $this->buildPaginationMeta($paginator),
            ],
            __('orders.retrieved_successfully'),
            200,
        );
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
     * @param  LengthAwarePaginator<int, User>  $paginator
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
