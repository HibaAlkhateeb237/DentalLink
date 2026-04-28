<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabEmployeesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_manager_can_create_employee(): void
    {
        Storage::fake('public');

        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Reception',
            'is_management' => false,
        ]);

        $response = $this->post('/api/auth/lab/employees', [
            'name' => 'Employee One',
            'email' => 'employee1@example.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'birthdate' => '1995-06-10',
            'joined_at' => '2026-04-01',
            'department_id' => $department->id,
            'role' => 'receptionist',
            'profile_image' => UploadedFile::fake()->image('employee.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('message', __('employees.created_successfully'))
            ->assertJsonPath('data.employee.email', 'employee1@example.com')
            ->assertJsonPath('data.employee.department.id', $department->id)
            ->assertJsonPath('data.employee.role.name', 'receptionist');

        $employee = User::query()->where('email', 'employee1@example.com')->firstOrFail();

        $this->assertSame('2026-04-01', $employee->joined_at?->format('Y-m-d'));
        $this->assertNotNull($employee->profile_image);
        $this->assertTrue(Storage::disk('public')->exists((string) $employee->profile_image));

        $this->assertDatabaseHas('department_user_roles', [
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'role_id' => Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail()->id,
        ]);
    }

    public function test_lab_manager_can_list_employees(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Design',
            'is_management' => false,
        ]);

        $employee = User::query()->create([
            'name' => 'Employee Two',
            'email' => 'employee2@example.com',
            'password' => 'Secret1234',
            'birthdate' => '1993-01-05',
            'joined_at' => '2026-03-01',
            'profile_image' => null,
        ]);

        $role = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $employee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        $response = $this->getJson('/api/auth/lab/employees?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('message', __('employees.retrieved_successfully'))
            ->assertJsonPath('data.data.0.email', 'employee2@example.com')
            ->assertJsonPath('data.data.0.department.id', $department->id)
            ->assertJsonPath('data.data.0.role.name', 'lab_technician');
    }

    public function test_lab_manager_can_update_employee(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Plaster',
            'is_management' => false,
        ]);

        $departmentTwo = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'is_management' => false,
        ]);

        $employee = User::query()->create([
            'name' => 'Employee Three',
            'email' => 'employee3@example.com',
            'password' => 'Secret1234',
            'birthdate' => '1992-07-10',
            'joined_at' => '2026-02-01',
            'profile_image' => null,
        ]);

        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $employee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        $response = $this->putJson('/api/auth/lab/employees/'.$employee->id, [
            'name' => 'Employee Three Updated',
            'joined_at' => '2026-02-10',
            'department_id' => $departmentTwo->id,
            'role' => 'department_manager',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('employees.updated_successfully'))
            ->assertJsonPath('data.employee.name', 'Employee Three Updated')
            ->assertJsonPath('data.employee.department.id', $departmentTwo->id)
            ->assertJsonPath('data.employee.role.name', 'department_manager');

        $employee->refresh();

        $this->assertSame('2026-02-10', $employee->joined_at?->format('Y-m-d'));
    }

    public function test_lab_manager_can_delete_employee(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Margins',
            'is_management' => false,
        ]);

        $employee = User::query()->create([
            'name' => 'Employee Four',
            'email' => 'employee4@example.com',
            'password' => 'Secret1234',
            'birthdate' => '1990-05-10',
            'joined_at' => '2026-01-01',
            'profile_image' => null,
        ]);

        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $employee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        $response = $this->deleteJson('/api/auth/lab/employees/'.$employee->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', __('employees.deleted_successfully'));

        $this->assertDatabaseMissing('users', [
            'id' => $employee->id,
        ]);
    }

    public function test_non_lab_manager_cannot_create_employee(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/lab/employees', [
            'name' => 'Forbidden Employee',
            'email' => 'forbidden@example.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'birthdate' => '1995-06-10',
            'joined_at' => '2026-04-01',
            'department_id' => 1,
            'role' => 'receptionist',
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
