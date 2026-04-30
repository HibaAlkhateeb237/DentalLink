<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\LabPricingRule;
use App\Models\User;

class LabPricingRulePolicy
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
    public function viewAny(User $user, Lab $lab): bool
    {
        if (! $user->hasPermission(['orders.price', 'labs.manage', 'labs.view'])) {
            return false;
        }

        return $user->lab_id === null || $user->lab_id === $lab->id;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LabPricingRule $labPricingRule): bool
    {
        return $this->viewAny($user, $labPricingRule->lab);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Lab $lab): bool
    {
        if (! ($user->hasPermission('labs.manage') || $user->hasPermission('orders.price'))) {
            return false;
        }

        return $user->lab_id !== null && $user->lab_id === $lab->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LabPricingRule $labPricingRule): bool
    {
        return $this->create($user, $labPricingRule->lab);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LabPricingRule $labPricingRule): bool
    {
        return $this->create($user, $labPricingRule->lab);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LabPricingRule $labPricingRule): bool
    {
        return $this->create($user, $labPricingRule->lab);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LabPricingRule $labPricingRule): bool
    {
        return false;
    }
}
