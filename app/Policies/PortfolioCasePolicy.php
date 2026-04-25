<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\PortfolioCase;
use App\Models\User;

class PortfolioCasePolicy
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
        return true;
    }

    public function view(User $user, PortfolioCase $portfolioCase): bool
    {
        return true;
    }

    public function create(User $user, Lab $lab): bool
    {
        if (! $user->hasRole('lab_manager')) {
            return false;
        }

        return $user->lab_name !== null && $user->lab_name === $lab->name;
    }

    public function delete(User $user, PortfolioCase $portfolioCase): bool
    {
        if (! $user->hasRole('lab_manager')) {
            return false;
        }

        return $user->lab_name !== null && $user->lab_name === $portfolioCase->order?->lab?->name;
    }
}
