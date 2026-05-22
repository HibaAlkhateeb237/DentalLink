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
                    ->whereHas('role', function ($roleQuery): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->whereIn('name', EmployeeRoles::allowed());
                    });
            })
            ->with([
                'departmentUserRoles' => function ($query) use ($managerLabId): void {
                    $query
                        ->whereHas('department', function ($departmentQuery) use ($managerLabId): void {
                            $departmentQuery->where('lab_id', $managerLabId);
                        })
                        ->whereHas('role', function ($roleQuery): void {
                            $roleQuery
                                ->where('guard_name', 'sanctum')
                                ->whereIn('name', EmployeeRoles::allowed());
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
                    $department = Department::query()
                        ->where('id', $validated['department_id'])
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

                $targetRole = $this->resolveTargetRole($employee, $validated);

                if ($targetRole !== null) {
                    if ($targetRole->name === 'department_manager') {
                        if (array_key_exists('departments_ids', $validated)) {
                            $departmentIds = collect($validated['departments_ids'] ?? [])
                                ->map(static fn ($value): int => (int) $value)
                                ->unique()
                                ->values();

                            $departments = Department::query()
                                ->whereIn('id', $departmentIds)
                                ->where('lab_id', $managerLabId)
                                ->pluck('id');

                            $this->removeEmployeeAssignments($employee, EmployeeRoles::allowed());

                            foreach ($departments as $departmentId) {
                                DepartmentUserRole::query()->create([
                                    'user_id' => $employee->id,
                                    'role_id' => $targetRole->id,
                                    'department_id' => $departmentId,
                                ]);
                            }
                        }
                    } else {
                        if (array_key_exists('department_id', $validated) || array_key_exists('role_id', $validated)) {
                            $department = Department::query()
                                ->where('id', $validated['department_id'])
                                ->where('lab_id', $managerLabId)
                                ->firstOrFail();

                            $this->removeEmployeeAssignments($employee, EmployeeRoles::allowed());

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
                    ->whereHas('role', function ($roleQuery): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->whereIn('name', EmployeeRoles::allowed());
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

    private function resolveTargetRole(User $employee, array $validated): ?Role
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
            ->whereIn('roles.name', EmployeeRoles::allowed())
            ->value('roles.id');

        if ($roleId === null) {
            return null;
        }

        return Role::query()->where('id', $roleId)->where('guard_name', 'sanctum')->first();
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    private function removeEmployeeAssignments(User $employee, array $roleNames): void
    {
        DepartmentUserRole::query()
            ->where('user_id', $employee->id)
            ->whereHas('role', function ($query) use ($roleNames): void {
                $query
                    ->where('guard_name', 'sanctum')
                    ->whereIn('name', $roleNames);
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
}
