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

class LabManagerOrderDepartmentRouteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_manager_can_set_department_route(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptC->id, $deptA->id, $deptB->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('orders.department_route_set_successfully'))
            ->assertJsonPath('data.total_departments_updated', 3)
            ->assertJsonPath('data.department_route', [$deptC->id, $deptA->id, $deptB->id]);

        $this->assertDatabaseHas('departments', ['id' => $deptC->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('departments', ['id' => $deptA->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'sort_order' => 3]);
    }

    public function test_updates_only_specified_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        Sanctum::actingAs($manager);

        $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptB->id],
        ]);

        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('departments', ['id' => $deptA->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('departments', ['id' => $deptC->id, 'sort_order' => 3]);
    }

    public function test_returns_400_when_department_from_another_lab(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $this->createDepartment($lab, 'Ceramics', 1);

        $otherLab = Lab::query()->create([
            'name' => 'Other Lab',
            'phone' => '0222222222',
            'address' => 'Homs',
            'latitude' => 34.7311,
            'longitude' => 36.7145,
        ]);
        $otherDept = $this->createDepartment($otherLab, 'Other', 1);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$otherDept->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.0']);
    }

    public function test_returns_400_when_department_is_management(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $management = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$management->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.0']);
    }

    public function test_returns_400_when_department_sort_order_is_zero(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $inactive = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Inactive',
            'is_management' => false,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$inactive->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.0']);
    }

    public function test_returns_400_when_department_ids_empty(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $this->createDepartment($lab, 'Ceramics', 1);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [],
        ]);

        $response->assertStatus(400);
    }

    public function test_returns_400_when_department_ids_contain_duplicates(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $dept = $this->createDepartment($lab, 'Ceramics', 1);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$dept->id, $dept->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.1']);
    }

    public function test_non_lab_manager_cannot_set_department_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('Blocked Lab');
        $dept = $this->createDepartment($lab, 'Ceramics', 1);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$dept->id],
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_set_department_route(): void
    {
        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [1],
        ]);

        $response->assertUnauthorized();
    }

    /**
     * @return array{0:User,1:Lab}
     */
    private function authenticateLabManager(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('Manager Lab');

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management Dept',
            'is_management' => false,
            'sort_order' => 1,
        ]);

        $manager = User::factory()->create([
            'email' => 'lab.manager@example.com',
        ]);

        $role = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        return [$manager, $lab];
    }

    private function createDepartment(Lab $lab, string $name, int $sortOrder): Department
    {
        return Department::query()->create([
            'lab_id' => $lab->id,
            'name' => $name,
            'is_management' => false,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createLab(string $name = 'Test Lab'): Lab
    {
        return Lab::query()->create([
            'name' => $name,
            'phone' => '0111111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.20,
        ]);
    }
}
