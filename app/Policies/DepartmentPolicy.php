<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(['departments.view', 'departments.manage']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermission(['departments.view', 'departments.manage'], $department->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.manage', $department->id);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.manage', $department->id);
    }
}
