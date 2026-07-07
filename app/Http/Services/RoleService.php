<?php

namespace App\Http\Services;

use App\Models\DepartmentUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function listRoles(): Collection
    {
        return Role::query()
            ->select(['id', 'name'])
            ->where('guard_name', 'sanctum')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function listPermissions(): Collection
    {
        return Permission::query()
            ->select(['id', 'name'])
            ->where('guard_name', 'sanctum')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{id: int, name: string, permissions: list<string>}>
     */
    public function matrix(User $user): array
    {
        $isSystemAdmin = $user->hasRole('system_admin');

        $query = Role::query()
            ->where('guard_name', 'sanctum')
            ->with('permissions:id,name')
            ->orderBy('name');

        if (! $isSystemAdmin) {
            $query->whereNotIn('name', ['system_admin', 'lab_manager', 'doctor']);
        }

        return $query->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->toArray(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @param  array<int, array{role_id: int, permissions: list<int>}>  $matrix
     */
    public function updateMatrix(User $user, array $matrix): void
    {
        $isSystemAdmin = $user->hasRole('system_admin');

        DB::transaction(function () use ($matrix, $isSystemAdmin): void {
            foreach ($matrix as $item) {
                $role = Role::query()
                    ->where('id', $item['role_id'])
                    ->where('guard_name', 'sanctum')
                    ->firstOrFail();

                if (! $isSystemAdmin && ! $role->isCustom()) {
                    throw ValidationException::withMessages([
                        'matrix.*.role_id' => [__('roles.cannot_edit_system_role')],
                    ]);
                }

                $role->permissions()->sync($item['permissions']);
            }
        });
    }

    public function createRole(User $manager, string $name, ?array $permissionIds = null): Role
    {
        $labId = $this->resolveManagerLabId($manager);

        return DB::transaction(function () use ($name, $labId, $permissionIds): Role {
            $role = Role::query()->create([
                'name' => $name,
                'guard_name' => 'sanctum',
                'lab_id' => $labId,
            ]);

            if ($permissionIds !== null && $permissionIds !== []) {
                $role->permissions()->sync($permissionIds);
            }

            return $role->load('permissions:id,name');
        });
    }

    public function updateRole(Role $role, string $name, ?array $permissionIds = null): Role
    {
        if (! $role->isCustom()) {
            throw ValidationException::withMessages([
                'role_id' => [__('roles.cannot_edit_system_role')],
            ]);
        }

        return DB::transaction(function () use ($role, $name, $permissionIds): Role {
            $role->update(['name' => $name]);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            return $role->fresh()->load('permissions:id,name');
        });
    }

    public function deleteRole(Role $role): void
    {
        if (! $role->isCustom()) {
            throw ValidationException::withMessages([
                'role_id' => [__('roles.cannot_delete_system_role')],
            ]);
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->departmentUserRoles()->delete();
            $role->delete();
        });
    }

    /**
     * @return Collection<int, DepartmentUserRole>
     */
    public function employeeRoles(User $manager, User $employee): Collection
    {
        $labId = $this->resolveManagerLabId($manager);

        return DepartmentUserRole::query()
            ->where('user_id', $employee->id)
            ->whereHas('department', function ($query) use ($labId): void {
                $query->where('lab_id', $labId);
            })
            ->whereHas('role', function ($query): void {
                $query->where('guard_name', 'sanctum');
            })
            ->with(['role:id,name', 'department:id,name'])
            ->get();
    }

    public function assignEmployeeRole(User $manager, User $employee, int $roleId, int $departmentId): DepartmentUserRole
    {
        $labId = $this->resolveManagerLabId($manager);

        $role = Role::query()
            ->where('id', $roleId)
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $belongsToLab = DepartmentUserRole::query()
            ->where('user_id', $employee->id)
            ->whereHas('department', function ($query) use ($labId): void {
                $query->where('lab_id', $labId);
            })
            ->exists();

        if (! $belongsToLab) {
            throw ValidationException::withMessages([
                'employee_id' => [__('messages.not_found')],
            ]);
        }

        $existingRole = DepartmentUserRole::query()
            ->where('user_id', $employee->id)
            ->where('department_id', $departmentId)
            ->first();

        if ($existingRole !== null) {
            $existingRole->update(['role_id' => $role->id]);

            return $existingRole->fresh()->load(['role:id,name', 'department:id,name']);
        }

        $assignment = DepartmentUserRole::query()->create([
            'user_id' => $employee->id,
            'role_id' => $role->id,
            'department_id' => $departmentId,
        ]);

        return $assignment->load(['role:id,name', 'department:id,name']);
    }

    public function removeEmployeeRole(User $manager, User $employee, DepartmentUserRole $departmentRole): void
    {
        $labId = $this->resolveManagerLabId($manager);

        $exists = DepartmentUserRole::query()
            ->where('id', $departmentRole->id)
            ->where('user_id', $employee->id)
            ->whereHas('department', function ($query) use ($labId): void {
                $query->where('lab_id', $labId);
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'department_role_id' => [__('messages.not_found')],
            ]);
        }

        $departmentRole->delete();
    }

    private function resolveManagerLabId(User $manager): int
    {
        $labId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $manager->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        if ($labId === null) {
            throw ValidationException::withMessages([
                'lab_id' => [__('messages.not_found')],
            ]);
        }

        return (int) $labId;
    }
}
