<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\Rbac;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roleRows = collect(Rbac::roles())
            ->map(static fn (string $role): array => [
                'name' => $role,
                'guard_name' => 'sanctum',
                'lab_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Role::query()->upsert($roleRows, ['name', 'guard_name', 'lab_id'], ['updated_at']);

        $permissionRows = collect(Rbac::permissions())
            ->map(static fn (string $permission): array => [
                'name' => $permission,
                'guard_name' => 'sanctum',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Permission::query()->upsert($permissionRows, ['name', 'guard_name'], ['updated_at']);

        $roles = Role::query()
            ->where('guard_name', 'sanctum')
            ->get()
            ->keyBy('name');

        $permissions = Permission::query()
            ->where('guard_name', 'sanctum')
            ->get()
            ->keyBy('name');

        foreach (Rbac::permissionsByRole() as $roleName => $permissionNames) {
            $role = $roles->get($roleName);

            if ($role === null) {
                continue;
            }

            $permissionIds = collect($permissionNames)
                ->map(static fn (string $permissionName): ?int => $permissions->get($permissionName)?->id)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
