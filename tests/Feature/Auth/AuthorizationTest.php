<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permission_middleware_allows_user_with_permission(): void
    {
        $doctor = User::factory()->create();
        $doctorRole = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$doctorRole->id]);

        Sanctum::actingAs($doctor);

        $this->app['router']->get('/api/test-permission', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'permission:orders.create']);

        $response = $this->getJson('/api/test-permission');

        $response->assertOk();
    }

    public function test_permission_middleware_denies_user_without_permission(): void
    {
        $delivery = User::factory()->create();
        $deliveryRole = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();
        $delivery->roles()->sync([$deliveryRole->id]);

        Sanctum::actingAs($delivery);

        $this->app['router']->get('/api/test-permission-denied', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'permission:orders.create']);

        $response = $this->getJson('/api/test-permission-denied');

        $response->assertForbidden();
    }

    public function test_role_middleware_allows_specific_role(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::query()->where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $admin->roles()->sync([$adminRole->id]);

        Sanctum::actingAs($admin);

        $this->app['router']->get('/api/test-role', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'role:system_admin']);

        $response = $this->getJson('/api/test-role');

        $response->assertOk();
    }
}
