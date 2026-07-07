<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabManagerOrderDepartmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LabManagerOrderController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
    ) {}

    public function setDepartmentRoute(LabManagerOrderDepartmentRequest $request): JsonResponse
    {
        $labId = $request->getManagerLabId();
        $departmentIds = $request->validated('department_ids');
        $departmentTimes = $request->validated('department_time_allowed_hours');

        $timeAllowedByDepartmentId = collect($departmentIds)
            ->values()
            ->mapWithKeys(static fn (int $departmentId, int $index): array => [
                $departmentId => (int) ($departmentTimes[$index] ?? 0),
            ]);

        $departments = DB::transaction(function () use ($labId, $departmentIds, $timeAllowedByDepartmentId) {
            $matchedDepartments = Department::query()
                ->where('lab_id', $labId)
                ->whereIn('id', $departmentIds)
                ->get()
                ->keyBy('id');

            collect($departmentIds)->each(function (int $departmentId, int $index) use ($matchedDepartments, $timeAllowedByDepartmentId): void {
                $department = $matchedDepartments->get($departmentId);

                if ($department !== null) {
                    $department->update([
                        'sort_order' => $index + 1,
                        'time_allowed' => (int) ($timeAllowedByDepartmentId->get($departmentId) ?? 0),
                    ]);
                }
            });

            return Department::query()
                ->select(['id', 'name', 'sort_order', 'time_allowed'])
                ->where('lab_id', $labId)
                ->whereIn('id', $departmentIds)
                ->orderBy('sort_order')
                ->get();
        });

        $totalEstimatedTimeHours = $departments->sum(
            static fn (Department $department): int => max(0, (int) ($department->time_allowed ?? 0))
        );

        return $this->apiResponse->success(
            [
                'total_departments_updated' => count($departmentIds),
                'department_route' => $departmentIds,
                'total_estimated_time_hours' => $totalEstimatedTimeHours,
                'departments' => $departments->map(static fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'sort_order' => $department->sort_order,
                    'time_allowed_hours' => max(0, (int) ($department->time_allowed ?? 0)),
                ])->values(),
            ],
            __('orders.department_route_set_successfully'),
            200,
        );
    }
}
