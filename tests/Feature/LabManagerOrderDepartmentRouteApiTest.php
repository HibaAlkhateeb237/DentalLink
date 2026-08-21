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
        $deptD = $this->createDepartment($lab, 'Veneers', 4);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptC->id, $deptA->id, $deptB->id, $deptD->id],
            'department_time_allowed_hours' => [8, 4, 6, 10],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('orders.department_route_set_successfully'))
            ->assertJsonPath('data.total_departments_updated', 4)
            ->assertJsonPath('data.department_route', [$deptC->id, $deptA->id, $deptB->id, $deptD->id])
            ->assertJsonPath('data.total_estimated_time_hours', 28)
            ->assertJsonPath('data.departments.0.id', $deptC->id)
            ->assertJsonPath('data.departments.0.time_allowed_hours', 8)
            ->assertJsonPath('data.departments.1.id', $deptA->id)
            ->assertJsonPath('data.departments.1.time_allowed_hours', 4)
            ->assertJsonPath('data.departments.2.id', $deptB->id)
            ->assertJsonPath('data.departments.2.time_allowed_hours', 6)
            ->assertJsonPath('data.departments.3.id', $deptD->id)
            ->assertJsonPath('data.departments.3.time_allowed_hours', 10);

        $this->assertDatabaseHas('departments', ['id' => $deptC->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('departments', ['id' => $deptA->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'sort_order' => 3]);
        $this->assertDatabaseHas('departments', ['id' => $deptD->id, 'sort_order' => 4]);
        $this->assertDatabaseHas('departments', ['id' => $deptC->id, 'time_allowed' => 8]);
        $this->assertDatabaseHas('departments', ['id' => $deptA->id, 'time_allowed' => 4]);
        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'time_allowed' => 6]);
        $this->assertDatabaseHas('departments', ['id' => $deptD->id, 'time_allowed' => 10]);
    }

    public function test_updates_only_specified_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);
        $deptD = $this->createDepartment($lab, 'Veneers', 4);

        Sanctum::actingAs($manager);

        $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptB->id, $deptA->id, $deptC->id, $deptD->id],
            'department_time_allowed_hours' => [5, 4, 6, 3],
        ]);

        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('departments', ['id' => $deptA->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('departments', ['id' => $deptC->id, 'sort_order' => 3]);
        $this->assertDatabaseHas('departments', ['id' => $deptD->id, 'sort_order' => 4]);
        $this->assertDatabaseHas('departments', ['id' => $deptB->id, 'time_allowed' => 5]);
    }

    public function test_returns_400_when_department_from_another_lab(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

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
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id, $otherDept->id],
            'department_time_allowed_hours' => [2, 3, 4, 5],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.3']);
    }

    public function test_returns_400_when_department_is_management(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        $management = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id, $management->id],
            'department_time_allowed_hours' => [2, 3, 4, 5],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.3']);
    }

    public function test_can_assign_inactive_department_to_route(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        // A department with sort_order = 0 is not yet part of the workflow
        $inactive = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Inactive',
            'is_management' => false,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id, $inactive->id],
            'department_time_allowed_hours' => [2, 3, 4, 5],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.total_departments_updated', 4);

        // The previously inactive department is now activated (sort_order > 0)
        $this->assertDatabaseHas('departments', [
            'id' => $inactive->id,
            'sort_order' => 4,
        ]);
    }

    public function test_returns_400_when_department_ids_empty(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $this->createDepartment($lab, 'Ceramics', 1);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [],
            'department_time_allowed_hours' => [],
        ]);

        $response->assertStatus(400);
    }

    public function test_returns_400_when_fewer_than_four_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id],
            'department_time_allowed_hours' => [4, 6, 8],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids']);
    }

    public function test_returns_400_when_more_than_six_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $departments = collect(range(1, 7))->map(
            fn (int $i): Department => $this->createDepartment($lab, 'Dept '.$i, $i)
        );

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => $departments->pluck('id')->all(),
            'department_time_allowed_hours' => [1, 2, 3, 4, 5, 6, 7],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids']);
    }

    public function test_accepts_exactly_six_departments(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $departments = collect(range(1, 6))->map(
            fn (int $i): Department => $this->createDepartment($lab, 'Dept '.$i, $i)
        );

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => $departments->pluck('id')->all(),
            'department_time_allowed_hours' => [1, 2, 3, 4, 5, 6],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.total_departments_updated', 6);
    }

    public function test_returns_400_when_department_ids_contain_duplicates(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id, $deptA->id],
            'department_time_allowed_hours' => [2, 3, 4, 5],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_ids.3']);
    }

    public function test_returns_400_when_department_times_count_does_not_match_department_ids_count(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptA->id, $deptB->id, $deptC->id, $deptA->id],
            'department_time_allowed_hours' => [3],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['department_time_allowed_hours']);
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
            'department_time_allowed_hours' => [2],
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_set_department_route(): void
    {
        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [1],
            'department_time_allowed_hours' => [2],
        ]);

        $response->assertUnauthorized();
    }

    public function test_newly_created_department_not_in_route_until_assigned(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'Implants', 2);
        $deptC = $this->createDepartment($lab, 'Orthodontics', 3);
        $deptD = $this->createDepartment($lab, 'Veneers', 4);

        Sanctum::actingAs($manager);

        // Create a department via the department store endpoint (should default to sort_order = 0)
        $created = $this->postJson('/api/auth/lab/departments', [
            'name' => 'Extra Dept',
        ]);
        $created->assertCreated();
        $extraDeptId = $created->json('data.department.id');

        $this->assertDatabaseHas('departments', [
            'id' => $extraDeptId,
            'sort_order' => 0,
        ]);

        // Retrieve the lab department route: the extra department must NOT appear
        $route = $this->getJson('/api/auth/lab/department-route');
        $route->assertOk();
        $routeIds = $route->json('data.departments.*.id');
        $this->assertNotContains($extraDeptId, $routeIds);

        // Now explicitly set the route including the extra department
        $response = $this->postJson('/api/auth/lab/orders/departments', [
            'department_ids' => [$deptC->id, $deptA->id, $deptB->id, $deptD->id, $extraDeptId],
            'department_time_allowed_hours' => [8, 4, 6, 10, 2],
        ]);
        $response->assertOk();

        // The extra department should now appear in the lab department route
        $route = $this->getJson('/api/auth/lab/department-route');
        $route->assertOk();
        $routeIds = $route->json('data.departments.*.id');
        $this->assertContains($extraDeptId, $routeIds);
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
