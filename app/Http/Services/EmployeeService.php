<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Role;
use App\Models\User;
use App\Support\EmployeeRoles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function listEmployees(User $manager, int $perPage = 15): LengthAwarePaginator
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return User::query()
            ->whereHas('departmentUserRoles', function ($query) use ($managerLabId): void {
                $query
                    ->whereHas('department', function ($departmentQuery) use ($managerLabId): void {
                        $departmentQuery->where('lab_id', $managerLabId);
                    })
                    ->whereHas('role', function ($roleQuery) use ($managerLabId): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->where(function ($q) use ($managerLabId): void {
                                $q->whereNull('lab_id')->whereIn('name', EmployeeRoles::system())
                                    ->orWhere('lab_id', $managerLabId);
                            });
                    });
            })
            ->with([
                'departmentUserRoles' => function ($query) use ($managerLabId): void {
                    $query
                        ->whereHas('department', function ($departmentQuery) use ($managerLabId): void {
                            $departmentQuery->where('lab_id', $managerLabId);
                        })
                        ->whereHas('role', function ($roleQuery) use ($managerLabId): void {
                            $roleQuery
                                ->where('guard_name', 'sanctum')
                                ->where(function ($q) use ($managerLabId): void {
                                    $q->whereNull('lab_id')->whereIn('name', EmployeeRoles::system())
                                        ->orWhere('lab_id', $managerLabId);
                                });
                        })
                        ->orderBy('department_id');
                },
                'departmentUserRoles.department:id,name,lab_id',
                'departmentUserRoles.department.lab:id,name',
                'departmentUserRoles.role:id,name',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createEmployee(User $manager, array $validated, ?UploadedFile $profileImage): User
    {
        $managerLabId = $this->resolveManagerLabId($manager);
        $profileImagePath = $profileImage?->store('users/profile-images', 'public');

        try {
            return DB::transaction(function () use ($validated, $managerLabId, $profileImagePath): User {
                $employee = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'],
                    'birthdate' => $validated['birthdate'],
                    'joined_at' => $validated['joined_at'],
                    'profile_image' => $profileImagePath,
                ]);

                $role = Role::query()
                    ->where('id', $validated['role_id'])
                    ->where('guard_name', 'sanctum')
                    ->firstOrFail();

                if ($role->name === 'department_manager') {
                    $departmentIds = collect($validated['departments_ids'] ?? [])
                        ->map(static fn ($value): int => (int) $value)
                        ->unique()
                        ->values();

                    $this->ensureDepartmentsHaveNoManager($departmentIds->all());

                    $departments = Department::query()
                        ->whereIn('id', $departmentIds)
                        ->where('lab_id', $managerLabId)
                        ->pluck('id');

                    foreach ($departments as $departmentId) {
                        DepartmentUserRole::query()->create([
                            'user_id' => $employee->id,
                            'role_id' => $role->id,
                            'department_id' => $departmentId,
                        ]);
                    }
                } else {
                    $departmentId = (int) collect($validated['departments_ids'] ?? [])->first();

                    $department = Department::query()
                        ->where('id', $departmentId)
                        ->where('lab_id', $managerLabId)
                        ->firstOrFail();

                    DepartmentUserRole::query()->create([
                        'user_id' => $employee->id,
                        'role_id' => $role->id,
                        'department_id' => $department->id,
                    ]);
                }

                return $this->loadEmployeeForManager($employee, $managerLabId);
            });
        } catch (\Throwable $exception) {
            if ($profileImagePath !== null) {
                Storage::disk('public')->delete($profileImagePath);
            }

            throw $exception;
        }
    }

    public function getEmployeeAssignment(User $manager, User $employee): User
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return $this->loadEmployeeForManager($employee, $managerLabId);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateEmployee(User $manager, User $employee, array $validated, ?UploadedFile $profileImage): User
    {
        $managerLabId = $this->resolveManagerLabId($manager);
        $currentProfileImage = $employee->profile_image;
        $newProfileImagePath = $profileImage?->store('users/profile-images', 'public');

        try {
            $employee = DB::transaction(function () use ($validated, $employee, $managerLabId, $newProfileImagePath): User {
                $updates = collect($validated)
                    ->only(['name', 'email', 'phone', 'birthdate', 'joined_at'])
                    ->all();

                if (array_key_exists('password', $validated)) {
                    $updates['password'] = $validated['password'];
                }

                if ($newProfileImagePath !== null) {
                    $updates['profile_image'] = $newProfileImagePath;
                }

                if ($updates !== []) {
                    $employee->fill($updates);
                    $employee->save();
                }

                $targetRole = $this->resolveTargetRole($employee, $validated, $managerLabId);

                if ($targetRole !== null) {
                    if ($targetRole->name === 'department_manager') {
                        if (array_key_exists('departments_ids', $validated)) {
                            $departmentIds = collect($validated['departments_ids'] ?? [])
                                ->map(static fn ($value): int => (int) $value)
                                ->unique()
                                ->values();

                            $this->ensureDepartmentsHaveNoManager($departmentIds->all(), $employee->id);

                            $departments = Department::query()
                                ->whereIn('id', $departmentIds)
                                ->where('lab_id', $managerLabId)
                                ->pluck('id');

                            $this->removeEmployeeAssignments($employee, $managerLabId);

                            foreach ($departments as $departmentId) {
                                DepartmentUserRole::query()->create([
                                    'user_id' => $employee->id,
                                    'role_id' => $targetRole->id,
                                    'department_id' => $departmentId,
                                ]);
                            }
                        }
                    } else {
                        if (array_key_exists('departments_ids', $validated) || array_key_exists('role_id', $validated)) {
                            $departmentId = (int) collect($validated['departments_ids'] ?? [])->first();

                            $department = Department::query()
                                ->where('id', $departmentId)
                                ->where('lab_id', $managerLabId)
                                ->firstOrFail();

                            $this->removeEmployeeAssignments($employee, $managerLabId);

                            DepartmentUserRole::query()->create([
                                'user_id' => $employee->id,
                                'role_id' => $targetRole->id,
                                'department_id' => $department->id,
                            ]);
                        }
                    }
                }

                return $this->loadEmployeeForManager($employee, $managerLabId);
            });
        } catch (\Throwable $exception) {
            if ($newProfileImagePath !== null) {
                Storage::disk('public')->delete($newProfileImagePath);
            }

            throw $exception;
        }

        if ($newProfileImagePath !== null && $currentProfileImage !== null && $currentProfileImage !== $newProfileImagePath) {
            Storage::disk('public')->delete($currentProfileImage);
        }

        return $employee;
    }

    public function deleteEmployee(User $manager, User $employee): void
    {
        $this->resolveManagerLabId($manager);

        $employee = $this->getEmployeeAssignment($manager, $employee);
        $profileImage = $employee->profile_image;

        DB::transaction(function () use ($employee): void {
            $employee->delete();
        });

        if ($profileImage !== null) {
            Storage::disk('public')->delete($profileImage);
        }
    }

    private function loadEmployeeForManager(User $employee, int $managerLabId): User
    {
        $employee->load([
            'departmentUserRoles' => function ($query) use ($managerLabId): void {
                $query
                    ->whereHas('department', function ($departmentQuery) use ($managerLabId): void {
                        $departmentQuery->where('lab_id', $managerLabId);
                    })
                    ->whereHas('role', function ($roleQuery) use ($managerLabId): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->where(function ($q) use ($managerLabId): void {
                                $q->whereNull('lab_id')->whereIn('name', EmployeeRoles::system())
                                    ->orWhere('lab_id', $managerLabId);
                            });
                    })
                    ->orderBy('department_id');
            },
            'departmentUserRoles.department:id,name,lab_id',
            'departmentUserRoles.department.lab:id,name',
            'departmentUserRoles.role:id,name',
        ]);

        if ($employee->departmentUserRoles->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_id' => [__('messages.not_found')],
            ]);
        }

        return $employee;
    }

    private function resolveTargetRole(User $employee, array $validated, int $managerLabId): ?Role
    {
        if (array_key_exists('role_id', $validated)) {
            return Role::query()
                ->where('id', $validated['role_id'])
                ->where('guard_name', 'sanctum')
                ->first();
        }

        $roleId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $employee->id)
            ->where('roles.guard_name', 'sanctum')
            ->where(function ($q) use ($managerLabId): void {
                $q->whereNull('roles.lab_id')->whereIn('roles.name', EmployeeRoles::system())
                    ->orWhere('roles.lab_id', $managerLabId);
            })
            ->value('roles.id');

        if ($roleId === null) {
            return null;
        }

        return Role::query()->where('id', $roleId)->where('guard_name', 'sanctum')->first();
    }

    private function removeEmployeeAssignments(User $employee, int $managerLabId): void
    {
        DepartmentUserRole::query()
            ->where('user_id', $employee->id)
            ->whereHas('role', function ($query) use ($managerLabId): void {
                $query
                    ->where('guard_name', 'sanctum')
                    ->where(function ($q) use ($managerLabId): void {
                        $q->whereNull('lab_id')->whereIn('name', EmployeeRoles::system())
                            ->orWhere('lab_id', $managerLabId);
                    });
            })
            ->delete();
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

    /**
     * @param  array<int, int>  $departmentIds
     */
    private function ensureDepartmentsHaveNoManager(array $departmentIds, ?int $excludeUserId = null): void
    {
        $departmentIds = collect($departmentIds)
            ->filter(static fn ($value): bool => (int) $value > 0)
            ->unique()
            ->values();

        if ($departmentIds->isEmpty()) {
            return;
        }

        $query = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('roles.name', 'department_manager')
            ->where('roles.guard_name', 'sanctum')
            ->whereIn('department_user_roles.department_id', $departmentIds->all());

        if ($excludeUserId !== null) {
            $query->where('department_user_roles.user_id', '!=', $excludeUserId);
        }

        $conflicts = $query
            ->pluck('department_user_roles.department_id')
            ->unique()
            ->values()
            ->all();

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'departments_ids' => [__('validation.unique', ['attribute' => 'departments_ids'])],
            ]);
        }
    }
}
