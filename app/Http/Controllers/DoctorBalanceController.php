<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\LabDoctorBalanceService;
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

        $doctors = $this->balanceService->getDoctorBalances($labId, $search);

        $data = $doctors->map(fn ($doctor): array => [
            'doctor_id' => $doctor->id,
            'name' => $doctor->name,
            'email' => $doctor->email,
            'phone' => $doctor->phone,
            'orders_count' => (int) $doctor->orders_count,
            'total_billed' => (float) $doctor->total_billed,
            'total_paid' => (float) $doctor->total_paid,
            'total_owed' => (float) $doctor->total_remaining,
        ]);

        $totalsBilled = (float) $data->sum('total_billed');
        $totalsPaid = (float) $data->sum('total_paid');

        $totals = [
            'repayment_percentage' => $totalsBilled > 0 ? round($totalsPaid / $totalsBilled * 100, 2) : 0,
            'total_billed' => $totalsBilled,
            'total_paid' => $totalsPaid,
            'total_owed' => (float) $data->sum('total_owed'),
        ];

        return $this->apiResponse->success(
            [
                'doctors' => $data->values(),
                'totals' => $totals,
            ],
            __('orders.retrieved_successfully'),
            200,
        );
    }
}
