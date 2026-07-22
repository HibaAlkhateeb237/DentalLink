<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('system_admin');
    }

    public function view(User $user, Package $package): bool
    {
        return $user->hasRole('system_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('system_admin');
    }

    public function update(User $user, Package $package): bool
    {
        return $user->hasRole('system_admin');
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->hasRole('system_admin');
    }
}
