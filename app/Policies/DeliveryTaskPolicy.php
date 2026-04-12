<?php

namespace App\Policies;

use App\Models\DeliveryTask;
use App\Models\User;

class DeliveryTaskPolicy
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
        return $user->hasPermission('delivery.view-assigned');
    }

    public function view(User $user, DeliveryTask $deliveryTask): bool
    {
        if ($user->hasPermission('delivery.view')) {
            return true;
        }

        return $user->hasPermission('delivery.view-assigned') && $deliveryTask->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('delivery.assign');
    }

    public function update(User $user, DeliveryTask $deliveryTask): bool
    {
        if ($user->hasPermission('delivery.update-any')) {
            return true;
        }

        return $user->hasPermission('delivery.update-status') && $deliveryTask->user_id === $user->id;
    }

    public function delete(User $user, DeliveryTask $deliveryTask): bool
    {
        return $user->hasPermission('delivery.cancel');
    }
}
