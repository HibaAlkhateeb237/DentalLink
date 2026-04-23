<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemAdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'system.admin@dentalink.local',
        ], [
            'name' => 'System Admin',
            'password' => 'Admin@123456',
            'email_verified_at' => now(),
        ]);

        $systemAdminRoleId = Role::query()
            ->where('name', 'system_admin')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($systemAdminRoleId !== null) {
            $user->roles()->syncWithoutDetaching([$systemAdminRoleId]);
        }
    }
}
