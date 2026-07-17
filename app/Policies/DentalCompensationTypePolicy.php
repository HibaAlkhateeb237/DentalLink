<?php

namespace App\Policies;

use App\Models\DentalCompensationType;
use App\Models\DepartmentUserRole;
use App\Models\User;

class DentalCompensationTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['lab_manager', 'receptionist']);
    }

    public function view(User $user, DentalCompensationType $type): bool
    {
        if ($user->hasRole('receptionist')) {
            return true;
        }

        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        return $user->hasRole('lab_manager') && $managerLabId !== null && $managerLabId === $type->lab_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('lab_manager');
    }

    public function update(User $user, DentalCompensationType $type): bool
    {
        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        return $user->hasRole('lab_manager') && $managerLabId !== null && $managerLabId === $type->lab_id;
    }

    public function delete(User $user, DentalCompensationType $type): bool
    {
        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        return $user->hasRole('lab_manager') && $managerLabId !== null && $managerLabId === $type->lab_id;
    }
}
