<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            LabSeeder::class,
            RolesAndPermissionsSeeder::class,

        ]);


        // User::factory(10)->create();


        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
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
