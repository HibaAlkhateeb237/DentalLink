<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    /**
     * @param  array{name:string,description?:string|null}  $validated
     */
    public function createDepartment(User $manager, array $validated): Department
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return DB::transaction(function () use ($validated, $managerLabId): Department {
            $department = Department::query()->create([
                'lab_id' => $managerLabId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_management' => false,
            ]);

            return $department->load('lab:id,name');
        });
    }

    public function listDepartments(User $manager, int $perPage = 15): LengthAwarePaginator
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return Department::query()
            ->where('lab_id', $managerLabId)
            ->with('lab:id,name')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getDepartment(User $manager, Department $department): Department
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        if ($department->lab_id !== $managerLabId) {
            throw ValidationException::withMessages([
                'department_id' => [__('messages.not_found')],
            ]);
        }

        return $department->load('lab:id,name');
    }

    /**
     * @param  array{departments:array<int, array{name:string,description?:string|null}>}  $validated
     * @return Collection<int, Department>
     */
    public function createDepartmentsBulk(User $manager, array $validated): Collection
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return DB::transaction(function () use ($validated, $managerLabId) {
            $departmentIds = [];

            foreach ($validated['departments'] as $departmentData) {
                $department = Department::query()->create([
                    'lab_id' => $managerLabId,
                    'name' => $departmentData['name'],
                    'description' => $departmentData['description'] ?? null,
                    'is_management' => false,
                ]);

                $departmentIds[] = $department->id;
            }

            return Department::query()
                ->with('lab:id,name')
                ->whereIn('id', $departmentIds)
                ->orderBy('id')
                ->get();
        });
    }

    public function deleteDepartment(User $manager, Department $department): void
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        if ($department->lab_id !== $managerLabId) {
            throw ValidationException::withMessages([
                'department_id' => [__('messages.not_found')],
            ]);
        }

        DB::transaction(function () use ($department): void {
            $department->delete();
        });
    }

    /**
     * @param  array{name:string,description?:string|null}  $validated
     */
    public function updateDepartment(User $manager, Department $department, array $validated): Department
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        if ($department->lab_id !== $managerLabId) {
            throw ValidationException::withMessages([
                'department_id' => [__('messages.not_found')],
            ]);
        }

        return DB::transaction(function () use ($department, $validated): Department {
            $department->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);
            $department->save();

            return $department->load('lab:id,name');
        });
    }

    public function listDepartmentsWithEmployees(User $manager, int $perPage = 15): LengthAwarePaginator
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return Department::query()
            ->where('lab_id', $managerLabId)
            ->where('is_management', false)
            ->with([
                'lab:id,name',
                'departmentUserRoles.user:id,name,email,phone,birthdate,joined_at,profile_image',
                'departmentUserRoles.role:id,name',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getDepartmentWithEmployees(User $manager, Department $department): Department
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        if ($department->lab_id !== $managerLabId || $department->is_management) {
            throw ValidationException::withMessages([
                'department_id' => [__('messages.not_found')],
            ]);
        }

        return $department->load([
            'lab:id,name',
            'departmentUserRoles.user:id,name,email,phone,birthdate,joined_at,profile_image',
            'departmentUserRoles.role:id,name',
        ]);
    }

    private function resolveManagerLabId(User $manager): int
    {
        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $manager->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        if ($managerLabId === null) {
            throw ValidationException::withMessages([
                'lab_id' => [__('messages.not_found')],
            ]);
        }

        return (int) $managerLabId;
    }
}
