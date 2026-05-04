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

    public function viewAny(User $user, Lab $lab): bool
    {
        if (! $user->hasPermission(['orders.price', 'labs.manage', 'labs.view'])) {
            return false;
        }

        return $user->lab_id === null || $user->lab_id === $lab->id;
    }

    public function update(User $user, Lab $lab): bool
    {
        if (! ($user->hasPermission('labs.manage') || $user->hasPermission('orders.price'))) {
            return false;
        }

        return $user->lab_id !== null && $user->lab_id === $lab->id;
    }

    public function view(User $user, LabPricingSetting $labPricingSetting): bool
    {
        return $this->viewAny($user, $labPricingSetting->lab);
    }
}
