<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\LabPricingSetting;
use App\Models\User;

class LabPricingSettingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Check if user belongs to the lab through department roles
     */
    private function userBelongsToLab(User $user, Lab $lab): bool
    {
        return $user->departmentUserRoles()
            ->whereHas('department', function ($q) use ($lab) {
                $q->where('lab_id', $lab->id);
            })
            ->exists();
    }

    public function viewAny(User $user, Lab $lab): bool
    {
        if (! $user->hasPermission(['orders.price', 'labs.manage', 'labs.view'])) {
            return false;
        }

        // Allow if user has no department roles (admin/doctor) OR belongs to this lab
        return ! $user->departmentUserRoles()->exists() || $this->userBelongsToLab($user, $lab);
    }

    public function update(User $user, Lab $lab): bool
    {
        if (! ($user->hasPermission('labs.manage') || $user->hasPermission('orders.price'))) {
            return false;
        }

        return $this->userBelongsToLab($user, $lab);
    }

    public function view(User $user, LabPricingSetting $labPricingSetting): bool
    {
        return $this->viewAny($user, $labPricingSetting->lab);
    }
}
