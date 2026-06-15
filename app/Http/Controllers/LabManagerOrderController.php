<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabManagerOrderDepartmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class LabManagerOrderController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
    ) {}

    public function setDepartmentRoute(LabManagerOrderDepartmentRequest $request): JsonResponse
    {
        $labId = $request->getManagerLabId();
        $departmentIds = $request->validated('department_ids');

        Department::query()
            ->where('lab_id', $labId)
            ->whereIn('id', $departmentIds)
            ->get()
            ->each(function (Department $department) use ($departmentIds): void {
                $newSortOrder = array_search($department->id, $departmentIds, true) + 1;
                $department->update(['sort_order' => $newSortOrder]);
            });

        return $this->apiResponse->success(
            [
                'total_departments_updated' => count($departmentIds),
                'department_route' => $departmentIds,
            ],
            __('orders.department_route_set_successfully'),
            200,
        );
    }
}
