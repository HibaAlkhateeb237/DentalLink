<?php

namespace App\Policies;

use App\Models\DentalCompensationType;
use App\Models\User;

class DentalCompensationTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('lab_manager');
    }

    public function view(User $user, DentalCompensationType $type): bool
    {
        return $user->hasRole('lab_manager') && $user->lab_id === $type->lab_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('lab_manager');
    }

    public function update(User $user, DentalCompensationType $type): bool
    {
        return $user->hasRole('lab_manager') && $user->lab_id === $type->lab_id;
    }

    public function delete(User $user, DentalCompensationType $type): bool
    {
        return $user->hasRole('lab_manager') && $user->lab_id === $type->lab_id;
    }
}
