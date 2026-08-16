<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Factories\PackageFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'Secret1234',
        ]);

        $adminRole = Role::query()
            ->where('name', 'system_admin')
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $user->roles()->sync([$adminRole->id]);

        return $user;
    }

    private function nonAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'Doctor',
            'email' => 'doctor@example.com',
            'password' => 'Secret1234',
        ]);

        $role = Role::query()
            ->where('name', 'doctor')
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_admin_can_list_packages(): void
    {
        PackageFactory::new()->count(3)->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/packages');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 3);
    }

    public function test_non_admin_cannot_list_packages(): void
    {
        Sanctum::actingAs($this->nonAdmin());

        $this->getJson('/api/admin/packages')->assertForbidden();
    }

    public function test_admin_can_create_package(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/admin/packages', [
            'name' => 'Premium Plan',
            'description' => 'Full year plan',
            'duration_days' => 365,
            'price' => 1200.50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Premium Plan')
            ->assertJsonPath('data.duration_days', 365)
            ->assertJsonPath('data.price', '1200.50');

        $this->assertDatabaseHas('packages', ['name' => 'Premium Plan', 'duration_days' => 365]);
    }

    public function test_create_package_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/packages', [])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'duration_days', 'price']);
    }

    public function test_create_package_rejects_duplicate_name(): void
    {
        PackageFactory::new()->create(['name' => 'Dup Plan']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/packages', [
            'name' => 'Dup Plan',
            'duration_days' => 30,
            'price' => 100,
        ])->assertStatus(400)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_show_package(): void
    {
        $package = PackageFactory::new()->create();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/packages/'.$package->id)
            ->assertOk()
            ->assertJsonPath('data.id', $package->id)
            ->assertJsonPath('data.duration_days', $package->duration_days);
    }

    public function test_admin_can_update_package(): void
    {
        $package = PackageFactory::new()->create();

        Sanctum::actingAs($this->admin());

        $response = $this->putJson('/api/admin/packages/'.$package->id, [
            'name' => 'Updated Plan',
            'duration_days' => 45,
            'price' => 99.99,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Plan')
            ->assertJsonPath('data.duration_days', 45)
            ->assertJsonPath('data.price', '99.99');

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'name' => 'Updated Plan']);
    }

    public function test_admin_cannot_delete_assigned_package(): void
    {
        $package = PackageFactory::new()->create();

        Lab::query()->create([
            'name' => 'Assigned Lab',
            'license_number' => 'LAB-'.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson('/api/admin/packages/'.$package->id)
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['package']);

        $this->assertDatabaseHas('packages', ['id' => $package->id]);
    }

    public function test_admin_can_delete_package(): void
    {
        $package = PackageFactory::new()->create();

        Sanctum::actingAs($this->admin());

        $this->deleteJson('/api/admin/packages/'.$package->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_non_admin_cannot_delete_package(): void
    {
        $package = PackageFactory::new()->create();

        Sanctum::actingAs($this->nonAdmin());

        $this->deleteJson('/api/admin/packages/'.$package->id)->assertForbidden();
    }
}
