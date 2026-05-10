<?php

namespace App\Http\Services;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function listRoles(): Collection
    {
        return Role::query()
            ->select(['id', 'name'])
            ->where('guard_name', 'sanctum')
            ->orderBy('name')
            ->get();
    }
}
