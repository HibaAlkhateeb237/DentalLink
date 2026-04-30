<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Requests\EmployeeStoreRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\EmployeeService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabEmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function index(EmployeeIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated()['per_page'] ?? 15);

        $employees = $this->employeeService->listEmployees($request->user(), $perPage);
        $payload = EmployeeResource::collection($employees)->response()->getData(true);

        return $this->apiResponse->success(
            $payload,
            __('employees.retrieved_successfully')
        );
    }

    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $assignment = $this->employeeService->createEmployee(
            $request->user(),
            $request->validated(),
            $request->file('profile_image'),
        );

        return $this->apiResponse->success(
            [
                'employee' => EmployeeResource::make($assignment)->resolve(),
            ],
            __('employees.created_successfully'),
            201,
        );
    }

    public function show(Request $request, User $employee): JsonResponse
    {
        if (! $request->user()?->can('view', $employee)) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $assignment = $this->employeeService->getEmployeeAssignment($request->user(), $employee);

        return $this->apiResponse->success(
            [
                'employee' => EmployeeResource::make($assignment)->resolve(),
            ],
            __('employees.details_retrieved_successfully')
        );
    }

    public function update(EmployeeUpdateRequest $request, User $employee): JsonResponse
    {
        $assignment = $this->employeeService->updateEmployee(
            $request->user(),
            $employee,
            $request->validated(),
            $request->file('profile_image'),
        );

        return $this->apiResponse->success(
            [
                'employee' => EmployeeResource::make($assignment)->resolve(),
            ],
            __('employees.updated_successfully')
        );
    }

    public function destroy(Request $request, User $employee): JsonResponse
    {
        if (! $request->user()?->can('delete', $employee)) {
            return $this->apiResponse->error(__('auth.forbidden'), 403);
        }

        $this->employeeService->deleteEmployee($request->user(), $employee);

        return $this->apiResponse->success(null, __('employees.deleted_successfully'));
    }
}
