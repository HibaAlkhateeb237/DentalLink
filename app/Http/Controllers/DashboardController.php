<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->apiResponse->error(__('auth.unauthenticated'), 401);
        }

        $labId = $this->resolveLabId($user);

        $from = $this->parseFrom($request);
        $to = $this->parseTo($request, $from);

        $data = $this->dashboardService->getDashboardData($labId, $from, $to);

        $resource = new DashboardResource($data);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('dashboard.retrieved_successfully'),
        );
    }

    private function resolveLabId($user): ?int
    {
        if ($user->hasRole('system_admin')) {
            return null;
        }

        $labId = $user->departmentUserRoles()
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->value('departments.lab_id');

        return $labId ? (int) $labId : null;
    }

    private function parseFrom(Request $request): ?Carbon
    {
        if ($request->filled('from')) {
            return Carbon::parse($request->input('from'))->startOfDay();
        }

        if ($request->filled('month') && $request->filled('year')) {
            return Carbon::create($request->integer('year'), $request->integer('month'), 1)->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }

    private function parseTo(Request $request, ?Carbon $from): Carbon
    {
        if ($request->filled('to')) {
            return Carbon::parse($request->input('to'))->endOfDay();
        }

        if ($request->filled('month') && $request->filled('year')) {
            return Carbon::create($request->integer('year'), $request->integer('month'), 1)->endOfMonth();
        }

        return $from->copy()->endOfMonth();
    }
}
