<?php

namespace Tests\Feature\Database;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_system_admin_user_with_expected_credentials_and_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'system.admin@dentalink.local')->first();
        $this->assertNotNull($user);

        $this->assertSame('System Admin', $user->name);
        $this->assertTrue(Hash::check('Admin@123456', $user->password));

        $systemAdminRole = Role::query()
            ->where('name', 'system_admin')
            ->where('guard_name', 'sanctum')
            ->first();

        $this->assertNotNull($systemAdminRole);
        $this->assertTrue($user->roles()->whereKey($systemAdminRole->id)->exists());
    }

    public function test_it_does_not_duplicate_system_admin_user_on_reseed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $count = User::query()
            ->where('email', 'system.admin@dentalink.local')
            ->count();

        $this->assertSame(1, $count);
    }
}
