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
        $joinedAt = now()->setDate(2020, 1, 1)->startOfDay();

        $user = User::query()->updateOrCreate([
            'email' => 'system.admin@dentalink.local',
        ], [
            'name' => 'د. أحمد محمد المنصوري',
            'phone' => '+971 50 123 4567',
            'birthdate' => '1985-05-12',
            'password' => 'Admin@123456',
            'email_verified_at' => now(),
            'created_at' => $joinedAt,
            'updated_at' => now(),
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
