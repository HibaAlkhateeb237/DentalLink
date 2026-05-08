<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LabSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabActiveInactiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_labs_index_returns_only_active_labs(): void
    {
        $user = User::query()->create([
            'name' => 'Doctor One',
            'email' => 'doctor.one@example.com',
            'password' => 'Secret1234',
        ]);

        Sanctum::actingAs($user);

        $activeLab = Lab::query()->create([
            'name' => 'Active Lab',
            'license_number' => 'LIC-ACTIVE-001',
            'phone' => '0930000001',
            'address' => 'Damascus',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'is_active' => true,
        ]);

        Lab::query()->create([
            'name' => 'Inactive Lab',
            'license_number' => 'LIC-INACTIVE-001',
            'phone' => '0930000002',
            'address' => 'Aleppo',
            'latitude' => 36.2021,
            'longitude' => 37.1343,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/auth/labs?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $activeLab->id)
            ->assertJsonPath('data.data.0.name', 'Active Lab');
    }

    public function test_inactive_labs_endpoint_returns_only_inactive_labs(): void
    {
        $user = User::query()->create([
            'name' => 'Doctor Two',
            'email' => 'doctor.two@example.com',
            'password' => 'Secret1234',
        ]);

        Sanctum::actingAs($user);

        Lab::query()->create([
            'name' => 'Active Lab Two',
            'license_number' => 'LIC-ACTIVE-002',
            'phone' => '0930000003',
            'address' => 'Homs',
            'latitude' => 34.7300,
            'longitude' => 36.7099,
            'is_active' => true,
        ]);

        $inactiveLab = Lab::query()->create([
            'name' => 'Inactive Lab Two',
            'license_number' => 'LIC-INACTIVE-002',
            'phone' => '0930000004',
            'address' => 'Latakia',
            'latitude' => 35.5317,
            'longitude' => 35.7901,
            'is_active' => false,
        ]);

        $labManagerRole = Role::query()->create([
            'name' => 'lab_manager',
            'guard_name' => 'sanctum',
        ]);

        $managementDepartment = Department::query()->create([
            'lab_id' => $inactiveLab->id,
            'name' => 'Management',
            'is_management' => true,
        ]);

        $manager = User::query()->create([
            'name' => 'Lab Manager',
            'email' => 'manager.inactive@example.com',
            'phone' => '0930000099',
            'password' => 'Secret1234',
        ]);

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $labManagerRole->id,
            'department_id' => $managementDepartment->id,
        ]);

        $response = $this->getJson('/api/auth/labs/inactive?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $inactiveLab->id)
            ->assertJsonPath('data.data.0.name', 'Inactive Lab Two')
            ->assertJsonPath('data.data.0.manager.id', $manager->id)
            ->assertJsonPath('data.data.0.manager.name', 'Lab Manager')
            ->assertJsonPath('data.data.0.manager.email', 'manager.inactive@example.com')
            ->assertJsonPath('data.data.0.manager.phone', '0930000099');
    }

    public function test_new_labs_default_to_inactive(): void
    {
        $lab = Lab::query()->create([
            'name' => 'Default State Lab',
            'license_number' => 'LIC-DEFAULT-001',
            'phone' => '0930000005',
            'address' => 'Tartus',
            'latitude' => 34.8890,
            'longitude' => 35.8866,
        ]);

        $this->assertFalse((bool) $lab->is_active);
    }

    public function test_seeded_inactive_labs_include_managers(): void
    {
        $this->seed([
            RolesAndPermissionsSeeder::class,
            LabSeeder::class,
            DepartmentSeeder::class,
        ]);

        $user = User::query()->create([
            'name' => 'Seeder Observer',
            'email' => 'seeder.observer@example.com',
            'password' => 'Secret1234',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/labs/inactive?per_page=15');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.total', 100);

        foreach ($response->json('data.data') as $lab) {
            $this->assertNotNull($lab['manager']);
            $this->assertArrayHasKey('id', $lab['manager']);
            $this->assertArrayHasKey('name', $lab['manager']);
            $this->assertArrayHasKey('email', $lab['manager']);
            $this->assertArrayHasKey('phone', $lab['manager']);
        }
    }
}
