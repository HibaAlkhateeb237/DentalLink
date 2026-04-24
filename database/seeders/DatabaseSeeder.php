<?php

namespace Database\Seeders;

use App\Models\DentalCompensationType;
use App\Models\Department;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Review;
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

        Review::query()->delete();
        Order::query()->delete();
        Favorite::query()->delete();
        DentalCompensationType::query()->delete();
        Department::query()->delete();

        $this->call([
            LabSeeder::class,
            RolesAndPermissionsSeeder::class,
            OrderSeeder::class,
            ReviewSeeder::class,

        ]);

        // User::factory(10)->create();

        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => bcrypt('password'),
        ]);

        $systemAdminRoleId = Role::query()
            ->where('name', 'system_admin')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($systemAdminRoleId !== null) {
            $user->roles()->syncWithoutDetaching([$systemAdminRoleId]);
        }

        $this->call([
            LabSeeder::class,
            RolesAndPermissionsSeeder::class,
            SystemAdminUserSeeder::class,
        ]);

    }
}
