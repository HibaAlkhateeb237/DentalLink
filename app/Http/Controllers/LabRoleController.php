<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleAssignEmployeeRequest;
use App\Http\Requests\RoleMatrixUpdateRequest;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\RoleService;
use App\Models\DepartmentUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabRoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly ApiResponse $apiResponse,
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->roleService->listRoles();

        return $this->apiResponse->success(
            ['roles' => $roles],
            __('auth.roles_retrieved_successfully')
        );
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
        $role = $this->roleService->createRole(
            $request->user(),
            $request->validated()['name'],
            $request->validated()['permissions'] ?? null,
        );

        return $this->apiResponse->success(
            ['role' => RoleResource::make($role)->resolve()],
            __('roles.created_successfully'),
            201,
        );
    }

    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        $role = $this->roleService->updateRole(
            $role,
            $validated['name'] ?? $role->name,
            $validated['permissions'] ?? null,
        );

        return $this->apiResponse->success(
            ['role' => RoleResource::make($role)->resolve()],
            __('roles.updated_successfully')
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->roleService->deleteRole($role);

        return $this->apiResponse->success(null, __('roles.deleted_successfully'));
    }

    public function permissions(): JsonResponse
    {
        $permissions = $this->roleService->listPermissions();

        return $this->apiResponse->success(
            ['permissions' => $permissions],
            __('roles.permissions_retrieved_successfully')
        );
    }

    public function matrix(): JsonResponse
    {
        $matrix = $this->roleService->matrix();

        return $this->apiResponse->success(
            ['matrix' => $matrix],
            __('roles.matrix_retrieved_successfully')
        );
    }

    public function updateMatrix(RoleMatrixUpdateRequest $request): JsonResponse
    {
        $this->roleService->updateMatrix($request->validated()['matrix']);

        return $this->apiResponse->success(null, __('roles.matrix_updated_successfully'));
    }

    public function employeeRoles(Request $request, User $employee): JsonResponse
    {
        $roles = $this->roleService->employeeRoles($request->user(), $employee);

        return $this->apiResponse->success(
            ['roles' => $roles],
            __('roles.employee_roles_retrieved_successfully')
        );
    }

    public function assignEmployeeRole(RoleAssignEmployeeRequest $request, User $employee): JsonResponse
    {
        $validated = $request->validated();

        $assignment = $this->roleService->assignEmployeeRole(
            $request->user(),
            $employee,
            (int) $validated['role_id'],
            (int) $validated['department_id'],
        );

        return $this->apiResponse->success(
            ['role' => $assignment],
            __('auth.role_assigned_successfully'),
            201,
        );
    }

    public function removeEmployeeRole(Request $request, User $employee, DepartmentUserRole $departmentRole): JsonResponse
    {
        $this->roleService->removeEmployeeRole($request->user(), $employee, $departmentRole);

        return $this->apiResponse->success(null, __('roles.employee_role_removed_successfully'));
    }
}
