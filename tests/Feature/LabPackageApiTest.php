<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabPackageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function labManager(): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function systemAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createLab(array $attributes = []): Lab
    {
        return Lab::create(array_merge([
            'name' => 'Test Lab',
            'description' => 'Test Description',
            'license_number' => 'LAB-'.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
        ], $attributes));
    }

    private function createDepartment(int $labId): Department
    {
        return Department::create([
            'lab_id' => $labId,
            'name' => 'Test Department',
            'sort_order' => 1,
            'is_management' => false,
        ]);
    }

    private function createDepartmentUserRole(int $userId, int $departmentId): void
    {
        $roleId = Role::where('name', 'lab_manager')->where('guard_name', 'sanctum')->value('id');
        DepartmentUserRole::create([
            'user_id' => $userId,
            'department_id' => $departmentId,
            'role_id' => $roleId,
        ]);
    }

    public function test_lab_manager_can_view_current_package(): void
    {
        $manager = $this->labManager();
        $package = Package::factory()->create(['name' => 'Premium Plan']);
        $lab = $this->createLab(['package_id' => $package->id]);

        // Link manager to lab via department
        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/package');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Premium Plan');
    }

    public function test_lab_manager_returns_null_when_no_package(): void
    {
        $manager = $this->labManager();
        $lab = $this->createLab(['package_id' => null]);

        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/package');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_lab_manager_can_view_package_history(): void
    {
        $manager = $this->labManager();
        $package = Package::factory()->create(['name' => 'Current Plan']);
        $lab = $this->createLab(['package_id' => $package->id]);

        // Link manager to lab
        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/package/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.current_page', 1);
    }

    public function test_non_lab_manager_cannot_access_package(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/lab/package')->assertForbidden();
        $this->getJson('/api/auth/lab/package/history')->assertForbidden();
    }
}
