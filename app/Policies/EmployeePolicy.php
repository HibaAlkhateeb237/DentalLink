<?php

namespace App\Policies;

use App\Models\DepartmentUserRole;
use App\Models\User;
use App\Support\EmployeeRoles;

class EmployeePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isLabManagerWithLab($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->canManageEmployee($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isLabManagerWithLab($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $this->canManageEmployee($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $this->canManageEmployee($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function isLabManagerWithLab(User $user): bool
    {
        return $user->hasRole('lab_manager') && $this->resolveManagerLabId($user) !== null;
    }

    private function canManageEmployee(User $user, User $employee): bool
    {
        $managerLabId = $this->resolveManagerLabId($user);

        if (! $user->hasRole('lab_manager') || $managerLabId === null) {
            return false;
        }

        return DepartmentUserRole::query()
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $employee->id)
            ->where('departments.lab_id', $managerLabId)
            ->where('roles.guard_name', 'sanctum')
            ->where(function ($q) use ($managerLabId): void {
                $q->whereNull('roles.lab_id')->whereIn('roles.name', EmployeeRoles::system())
                    ->orWhere('roles.lab_id', $managerLabId);
            })
            ->exists();
    }

    private function resolveManagerLabId(User $user): ?int
    {
        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');
    }
}
