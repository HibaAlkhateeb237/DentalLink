<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
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
        return $user->hasPermission(['payments.view', 'payments.view-own']);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasPermission('payments.view')) {
            return true;
        }

        return $user->hasPermission('payments.view-own') && $payment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.manage');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.manage');
    }
}
