<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemAdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_seeder_creates_profile_with_expected_personal_data_and_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SystemAdminUserSeeder::class);

        $user = User::query()->where('email', 'system.admin@dentalink.local')->firstOrFail();

        $this->assertSame('د. أحمد محمد المنصوري', $user->name);
        $this->assertSame('+971 50 123 4567', $user->phone);
        $this->assertNotNull($user->birthdate);
        $this->assertSame('1985-05-12', $user->birthdate?->toDateString());
        $this->assertSame('2020-01-01', $user->created_at?->toDateString());
        $this->assertTrue(Hash::check('Admin@123456', (string) $user->password));

        $systemAdminRole = Role::query()
            ->where('name', 'system_admin')
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $systemAdminRole->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }
}
