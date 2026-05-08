<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_manager_can_create_department(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $response = $this->postJson('/api/auth/lab/departments', [
            'name' => 'Design',
            'description' => 'Design team',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('message', __('departments.created_successfully'))
            ->assertJsonPath('data.department.name', 'Design')
            ->assertJsonPath('data.department.lab_id', $lab->id);

        $this->assertDatabaseHas('departments', [
            'lab_id' => $lab->id,
            'name' => 'Design',
        ]);
    }

    public function test_lab_manager_cannot_create_duplicate_department(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $response = $this->postJson('/api/auth/lab/departments', [
            'name' => 'Design',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_lab_manager_can_bulk_create_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $response = $this->postJson('/api/auth/lab/departments/bulk', [
            'departments' => [
                [
                    'name' => 'Design',
                    'description' => 'Design team',
                ],
                [
                    'name' => 'Ceramics',
                    'description' => 'Ceramics team',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('message', __('departments.bulk_created_successfully'))
            ->assertJsonPath('data.departments.0.lab_id', $lab->id)
            ->assertJsonPath('data.departments.1.lab_id', $lab->id);

        $this->assertDatabaseHas('departments', [
            'lab_id' => $lab->id,
            'name' => 'Design',
        ]);

        $this->assertDatabaseHas('departments', [
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
        ]);
    }

    public function test_bulk_create_rejects_existing_department_name(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $response = $this->postJson('/api/auth/lab/departments/bulk', [
            'departments' => [
                ['name' => 'Design'],
                ['name' => 'Ceramics'],
            ],
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonValidationErrors(['departments.0.name']);
    }

    public function test_lab_manager_can_delete_department(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $response = $this->deleteJson('/api/auth/lab/departments/' . $department->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('departments.deleted_successfully'));

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_lab_manager_can_update_department(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $response = $this->putJson('/api/auth/lab/departments/' . $department->id, [
            'name' => 'Design Updated',
            'description' => 'Updated description',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('departments.updated_successfully'))
            ->assertJsonPath('data.department.name', 'Design Updated');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Design Updated',
        ]);
    }

    public function test_lab_manager_cannot_update_department_with_duplicate_name(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'description' => null,
            'is_management' => false,
        ]);

        $response = $this->putJson('/api/auth/lab/departments/' . $department->id, [
            'name' => 'Ceramics',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_non_lab_manager_cannot_update_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::query()->create([
            'lab_id' => Lab::query()->create([
                'name' => 'Lab A',
                'phone' => '0111111111',
                'address' => 'Damascus',
                'latitude' => 33.5138070,
                'longitude' => 36.2765279,
                'rating' => 4.20,
            ])->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/lab/departments/' . $department->id, [
            'name' => 'Design Updated',
        ]);

        $response->assertForbidden();
    }

    public function test_non_lab_manager_cannot_delete_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::query()->create([
            'lab_id' => Lab::query()->create([
                'name' => 'Lab A',
                'phone' => '0111111111',
                'address' => 'Damascus',
                'latitude' => 33.5138070,
                'longitude' => 36.2765279,
                'rating' => 4.20,
            ])->id,
            'name' => 'Design',
            'description' => null,
            'is_management' => false,
        ]);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/lab/departments/' . $department->id);

        $response->assertForbidden();
    }

    public function test_non_lab_manager_cannot_create_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/lab/departments', [
            'name' => 'Forbidden Department',
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0:User,1:Lab}
     */
    private function authenticateLabManager(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Lab Manager Lab',
            'phone' => '0111111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.20,
        ]);

        $managementDepartment = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
        ]);

        $manager = User::factory()->create([
            'email' => 'manager@example.com',
        ]);

        $labManagerRole = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $manager->roles()->syncWithoutDetaching([$labManagerRole->id]);

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $labManagerRole->id,
            'department_id' => $managementDepartment->id,
        ]);

        Sanctum::actingAs($manager);

        return [$manager, $lab];
    }
}
