<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentBulkStoreRequest;
use App\Http\Requests\DepartmentStoreRequest;
use App\Http\Requests\DepartmentUpdateRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DepartmentService;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function store(DepartmentStoreRequest $request): JsonResponse
    {
        $department = $this->departmentService->createDepartment(
            $request->user(),
            $request->validated(),
        );

        return $this->apiResponse->success(
            [
                'department' => DepartmentResource::make($department)->resolve(),
            ],
            __('departments.created_successfully'),
            201,
        );
    }

    public function bulkStore(DepartmentBulkStoreRequest $request): JsonResponse
    {
        $departments = $this->departmentService->createDepartmentsBulk(
            $request->user(),
            $request->validated(),
        );

        return $this->apiResponse->success(
            [
                'departments' => DepartmentResource::collection($departments)->resolve(),
            ],
            __('departments.bulk_created_successfully'),
            201,
        );
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        if (! $request->user()?->can('delete', $department)) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $this->departmentService->deleteDepartment($request->user(), $department);

        return $this->apiResponse->success(null, __('departments.deleted_successfully'));
    }

    public function update(DepartmentUpdateRequest $request, Department $department): JsonResponse
    {
        $department = $this->departmentService->updateDepartment(
            $request->user(),
            $department,
            $request->validated(),
        );

        return $this->apiResponse->success(
            [
                'department' => DepartmentResource::make($department)->resolve(),
            ],
            __('departments.updated_successfully'),
        );
    }
}
