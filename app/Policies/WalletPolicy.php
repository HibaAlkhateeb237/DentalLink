<?php

namespace App\Policies;

use App\Models\DepartmentUserRole;
use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, Wallet $wallet): bool
    {
        if (! $user->hasRole('lab_manager')) {
            return false;
        }

        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        return $managerLabId !== null && $managerLabId === $wallet->lab_id;
    }
}
