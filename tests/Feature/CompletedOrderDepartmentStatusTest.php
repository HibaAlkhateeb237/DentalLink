<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompletedOrderDepartmentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_order_shows_all_departments_as_completed_in_receptionist_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'Status Lab',
            'phone' => '1111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $this->attachReceptionistToLab($receptionist, $lab);

        $deptA = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'is_management' => false,
            'sort_order' => 1,
            'time_allowed' => 8,
        ]);

        $deptB = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'CAD/CAM',
            'is_management' => false,
            'sort_order' => 2,
            'time_allowed' => 6,
        ]);

        $deptC = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Finishing',
            'is_management' => false,
            'sort_order' => 3,
            'time_allowed' => 4,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-COMPLETED-001',
            'priority' => 'normal',
            'status' => 'completed',
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/orders?status=completed&per_page=15');

        $response->assertOk()
            ->assertJsonPath('data.data.0.departments.0.status', 'completed')
            ->assertJsonPath('data.data.0.departments.1.status', 'completed')
            ->assertJsonPath('data.data.0.departments.2.status', 'completed')
            ->assertJsonPath('data.data.0.departments.0.is_current', false)
            ->assertJsonPath('data.data.0.departments.1.is_current', false)
            ->assertJsonPath('data.data.0.departments.2.is_current', false)
            ->assertJsonCount(3, 'data.data.0.departments');
    }

    public function test_completed_order_shows_all_departments_completed_in_lab_manager_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        [$manager, $lab] = $this->authenticateLabManager();
        $doctor = User::factory()->create();

        $deptA = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'is_management' => false,
            'sort_order' => 1,
            'time_allowed' => 8,
        ]);

        $deptB = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'CAD/CAM',
            'is_management' => false,
            'sort_order' => 2,
            'time_allowed' => 6,
        ]);

        $deptC = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Finishing',
            'is_management' => false,
            'sort_order' => 3,
            'time_allowed' => 4,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-COMPLETED-002',
            'priority' => 'urgent',
            'status' => 'completed',
            'order_type' => 'physical',
            'price' => 300,
            'remaining_amount' => 0,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/auth/lab/orders/{$order->id}/departments");

        $response->assertOk()
            ->assertJsonPath('data.departments.0.is_completed', true)
            ->assertJsonPath('data.departments.1.is_completed', true)
            ->assertJsonPath('data.departments.2.is_completed', true)
            ->assertJsonPath('data.departments.0.is_current', false)
            ->assertJsonPath('data.departments.1.is_current', false)
            ->assertJsonPath('data.departments.2.is_current', false)
            ->assertJsonPath('data.departments.0.task.status', 'completed')
            ->assertJsonPath('data.departments.1.task', null)
            ->assertJsonPath('data.departments.2.task', null);
    }

    public function test_completed_order_shows_all_steps_completed_in_doctor_tracking(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $doctor = $this->actingAsRole('doctor');

        $lab = Lab::query()->create([
            'name' => 'Track Lab',
            'phone' => '2222222',
            'address' => 'Aleppo',
            'latitude' => 33.5104140,
            'longitude' => 36.2783360,
        ]);

        $deptA = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'is_management' => false,
            'sort_order' => 1,
            'time_allowed' => 8,
        ]);

        $deptB = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'CAD/CAM',
            'is_management' => false,
            'sort_order' => 2,
            'time_allowed' => 6,
        ]);

        $deptC = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Finishing',
            'is_management' => false,
            'sort_order' => 3,
            'time_allowed' => 4,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-COMPLETED-003',
            'priority' => 'normal',
            'status' => 'completed',
            'order_type' => 'digital',
            'price' => 150,
            'remaining_amount' => 0,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/auth/doctor/orders/{$order->id}/track");

        $response->assertOk()
            ->assertJsonPath('data.steps.0.step_status', 'completed')
            ->assertJsonPath('data.steps.1.step_status', 'completed')
            ->assertJsonPath('data.steps.2.step_status', 'completed')
            ->assertJsonPath('data.steps.0.remaining_minutes', 0)
            ->assertJsonPath('data.steps.1.remaining_minutes', 0)
            ->assertJsonPath('data.steps.2.remaining_minutes', 0)
            ->assertJsonPath('data.order_status', 'completed')
            ->assertJsonCount(3, 'data.steps');
    }

    public function test_in_progress_order_shows_null_for_departments_without_tasks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'Progress Lab',
            'phone' => '3333333',
            'address' => 'Homs',
            'latitude' => 34.7318100,
            'longitude' => 36.7099460,
        ]);

        $this->attachReceptionistToLab($receptionist, $lab);

        $deptA = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'is_management' => false,
            'sort_order' => 1,
            'time_allowed' => 8,
        ]);

        $deptB = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'CAD/CAM',
            'is_management' => false,
            'sort_order' => 2,
            'time_allowed' => 6,
        ]);

        $deptC = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Finishing',
            'is_management' => false,
            'sort_order' => 3,
            'time_allowed' => 4,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-PROGRESS-001',
            'priority' => 'normal',
            'status' => 'in_progress',
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 200,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => 'in_progress',
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/orders?status=in_progress&per_page=15');

        $response->assertOk()
            ->assertJsonPath('data.data.0.departments.0.status', 'completed')
            ->assertJsonPath('data.data.0.departments.1.status', 'in_progress')
            ->assertJsonPath('data.data.0.departments.2.status', null)
            ->assertJsonPath('data.data.0.departments.1.is_current', true)
            ->assertJsonPath('data.data.0.departments.2.is_current', false);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();

        $roleId = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($roleId !== null) {
            $user->roles()->syncWithoutDetaching([$roleId]);
        }

        return $user;
    }

    private function attachReceptionistToLab(User $receptionist, Lab $lab): void
    {
        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Front Desk '.$receptionist->id,
            'description' => null,
            'is_management' => true,
        ]);

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id'),
            'department_id' => $department->id,
        ]);
    }

    /**
     * @return array{0:User,1:Lab}
     */
    private function authenticateLabManager(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Manager Lab',
            'phone' => '0111111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management Dept',
            'is_management' => false,
            'sort_order' => 1,
        ]);

        $manager = User::factory()->create([
            'email' => 'completed.order.manager@example.com',
        ]);

        $role = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        return [$manager, $lab];
    }
}
