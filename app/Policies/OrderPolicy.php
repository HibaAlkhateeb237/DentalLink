<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
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
        return $user->hasPermission(['orders.view', 'orders.view-own']);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->hasPermission('orders.view')) {
            return true;
        }

        return $user->hasPermission('orders.view-own') && $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->hasPermission('orders.update')) {
            return true;
        }

        return $user->hasPermission('orders.update-own') && $order->user_id === $user->id;
    }

    public function price(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.price');
    }

    public function markForResubmission(User $user, Order $order): bool
    {
        if ($user->hasPermission(['orders.price', 'orders.update'])) {
            return true;
        }

        return $user->hasPermission('orders.update-own') && $order->user_id === $user->id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.delete') || ($user->hasPermission('orders.update-own') && $order->user_id === $user->id);
    }
}
