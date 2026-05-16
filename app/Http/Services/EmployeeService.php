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

        return DepartmentUserRole::query()
            ->select('department_user_roles.*')
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('departments.lab_id', $managerLabId)
            ->where('roles.guard_name', 'sanctum')
            ->whereIn('roles.name', EmployeeRoles::allowed())
            ->with([
                'user:id,name,email,phone,profile_image,birthdate,joined_at',
                'department:id,name,lab_id',
                'department.lab:id,name',
                'role:id,name',
            ])
            ->orderByDesc('department_user_roles.id')
            ->paginate($perPage);
    }

    /**
     * @param  array{name:string,email:string,password:string,birthdate:string,joined_at:string,department_id:int,role_id:int}  $validated
     */
    public function createEmployee(User $manager, array $validated, ?UploadedFile $profileImage): DepartmentUserRole
    {
        $managerLabId = $this->resolveManagerLabId($manager);
        $profileImagePath = $profileImage?->store('users/profile-images', 'public');

        try {
            return DB::transaction(function () use ($validated, $managerLabId, $profileImagePath): DepartmentUserRole {
                $employee = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'],
                    'birthdate' => $validated['birthdate'],
                    'joined_at' => $validated['joined_at'],
                    'profile_image' => $profileImagePath,
                ]);

                $department = Department::query()
                    ->where('id', $validated['department_id'])
                    ->where('lab_id', $managerLabId)
                    ->firstOrFail();

                $role = Role::query()
                    ->where('id', $validated['role_id'])
                    ->where('guard_name', 'sanctum')
                    ->firstOrFail();

                $assignment = DepartmentUserRole::query()->create([
                    'user_id' => $employee->id,
                    'role_id' => $role->id,
                    'department_id' => $department->id,
                ]);

                return $assignment->load([
                    'user:id,name,email,phone,profile_image,birthdate,joined_at',
                    'department:id,name,lab_id',
                    'department.lab:id,name',
                    'role:id,name',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($profileImagePath !== null) {
                Storage::disk('public')->delete($profileImagePath);
            }

            throw $exception;
        }
    }

    public function getEmployeeAssignment(User $manager, User $employee): DepartmentUserRole
    {
        $managerLabId = $this->resolveManagerLabId($manager);

        return DepartmentUserRole::query()
            ->select('department_user_roles.*')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $employee->id)
            ->where('departments.lab_id', $managerLabId)
            ->where('roles.guard_name', 'sanctum')
            ->whereIn('roles.name', EmployeeRoles::allowed())
            ->with([
                'user:id,name,email,phone,profile_image,birthdate,joined_at',
                'department:id,name,lab_id',
                'department.lab:id,name',
                'role:id,name',
            ])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateEmployee(User $manager, User $employee, array $validated, ?UploadedFile $profileImage): DepartmentUserRole
    {
        $managerLabId = $this->resolveManagerLabId($manager);
        $currentProfileImage = $employee->profile_image;
        $newProfileImagePath = $profileImage?->store('users/profile-images', 'public');

        try {
            $assignment = DB::transaction(function () use ($validated, $employee, $manager, $managerLabId, $newProfileImagePath): DepartmentUserRole {
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

                if (array_key_exists('department_id', $validated) || array_key_exists('role_id', $validated)) {
                    $department = Department::query()
                        ->where('id', $validated['department_id'])
                        ->where('lab_id', $managerLabId)
                        ->firstOrFail();

                    $role = Role::query()
                        ->where('id', $validated['role_id'])
                        ->where('guard_name', 'sanctum')
                        ->firstOrFail();

                    $assignment = DepartmentUserRole::query()
                        ->select('department_user_roles.*')
                        ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
                        ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
                        ->where('department_user_roles.user_id', $employee->id)
                        ->where('departments.lab_id', $managerLabId)
                        ->where('roles.guard_name', 'sanctum')
                        ->whereIn('roles.name', EmployeeRoles::allowed())
                        ->first();

                    if ($assignment !== null) {
                        $assignment->update([
                            'department_id' => $department->id,
                            'role_id' => $role->id,
                        ]);
                    } else {
                        $assignment = DepartmentUserRole::query()->create([
                            'user_id' => $employee->id,
                            'role_id' => $role->id,
                            'department_id' => $department->id,
                        ]);
                    }

                    return $assignment->load([
                        'user:id,name,email,phone,profile_image,birthdate,joined_at',
                        'department:id,name,lab_id',
                        'department.lab:id,name',
                        'role:id,name',
                    ]);
                }

                return $this->getEmployeeAssignment($manager, $employee);
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

        return $assignment;
    }

    public function deleteEmployee(User $manager, User $employee): void
    {
        $this->resolveManagerLabId($manager);

        $assignment = $this->getEmployeeAssignment($manager, $employee);
        $profileImage = $assignment->user?->profile_image;

        DB::transaction(function () use ($employee): void {
            $employee->delete();
        });

        if ($profileImage !== null) {
            Storage::disk('public')->delete($profileImage);
        }
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
