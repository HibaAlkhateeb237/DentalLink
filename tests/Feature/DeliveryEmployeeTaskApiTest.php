<?php

namespace Tests\Feature;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryEmployeeTaskApiTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    private User $deliveryEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '0111111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.20,
        ]);

        $department = Department::query()->create([
            'lab_id' => $this->lab->id,
            'name' => 'Delivery Department',
            'is_management' => false,
        ]);

        $this->deliveryEmployee = User::factory()->create([
            'email' => 'delivery.employee@example.com',
        ]);

        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $this->deliveryEmployee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);
    }

    public function test_delivery_employee_can_list_assigned_tasks(): void
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);

        DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks?tab=assigned');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'empty')
            ->assertJsonPath('data.0.tasks_count', 1);
    }

    public function test_delivery_employee_can_list_completed_tasks(): void
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);

        DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks?tab=completed');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'delivered')
            ->assertJsonPath('data.0.tasks_count', 1);
    }

    public function test_default_tab_is_assigned(): void
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);

        DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
        ]);

        DeliveryTask::query()->create([
            'order_id' => $this->createOrder($doctor)->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'empty')
            ->assertJsonPath('data.0.tasks_count', 1)
            ->assertJsonPath('data.1.status', 'delivered')
            ->assertJsonPath('data.1.tasks_count', 1);
    }

    public function test_delivery_employee_can_filter_by_direction(): void
    {
        $doctor = User::factory()->create();
        $order1 = $this->createOrder($doctor);
        $order2 = $this->createOrder($doctor);

        DeliveryTask::query()->create([
            'order_id' => $order1->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
            'direction' => 'to_doctor',
        ]);

        DeliveryTask::query()->create([
            'order_id' => $order2->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
            'direction' => 'to_lab',
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks?direction=to_doctor');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'empty')
            ->assertJsonPath('data.0.direction', 'to_doctor')
            ->assertJsonPath('data.0.tasks_count', 1);
    }

    public function test_delivery_employee_can_filter_by_tab_and_direction(): void
    {
        $doctor = User::factory()->create();
        $order1 = $this->createOrder($doctor);
        $order2 = $this->createOrder($doctor);

        DeliveryTask::query()->create([
            'order_id' => $order1->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
            'direction' => 'to_doctor',
        ]);

        DeliveryTask::query()->create([
            'order_id' => $order2->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'delivered',
            'direction' => 'to_lab',
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks?tab=assigned&direction=to_doctor');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'empty')
            ->assertJsonPath('data.0.direction', 'to_doctor')
            ->assertJsonPath('data.0.tasks_count', 1);
    }

    public function test_delivery_employee_cannot_see_other_users_tasks(): void
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);

        $otherEmployee = User::factory()->create();
        $otherLab = Lab::query()->create([
            'name' => 'Other Lab',
            'phone' => '0222222222',
            'address' => 'Aleppo',
            'latitude' => 36.2028,
            'longitude' => 37.1583,
            'rating' => 3.50,
        ]);
        $otherDepartment = Department::query()->create([
            'lab_id' => $otherLab->id,
            'name' => 'Other Delivery',
            'is_management' => false,
        ]);
        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();
        DepartmentUserRole::query()->create([
            'user_id' => $otherEmployee->id,
            'role_id' => $role->id,
            'department_id' => $otherDepartment->id,
        ]);

        DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $otherEmployee->id,
            'status' => 'empty',
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_delivery_user_cannot_access_tasks(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/delivery/tasks');

        $response->assertForbidden();
    }

    public function test_resource_includes_order_and_doctor_details(): void
    {
        $doctor = User::factory()->create([
            'location' => 'Doctor Location',
            'location_lat' => 33.5,
            'location_lng' => 36.2,
        ]);
        $order = $this->createOrder($doctor);

        $task = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'empty',
            'direction' => 'to_doctor',
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->getJson('/api/auth/delivery/tasks');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'doctor' => [
                            'id',
                            'name',
                            'phone',
                            'location',
                            'location_lat',
                            'location_lng',
                        ],
                        'status',
                        'tasks_count',
                        'tasks' => [
                            '*' => [
                                'id',
                                'order_id',
                                'serial_number',
                                'status',
                                'direction',
                                'assigned_at',
                                'picked_at',
                                'delivered_at',
                                'order' => [
                                    'id',
                                    'serial_number',
                                    'patient_name',
                                    'case_type',
                                    'priority',
                                    'status',
                                    'notes',
                                    'price',
                                    'created_at',
                                    'doctor' => [
                                        'id',
                                        'name',
                                        'phone',
                                        'location',
                                        'location_lat',
                                        'location_lng',
                                    ],
                                ],
                                'delivery_user',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.tasks.0.tasks.0.id', $task->id)
            ->assertJsonPath('data.0.tasks.0.tasks.0.order.id', $order->id)
            ->assertJsonPath('data.0.tasks.0.tasks.0.serial_number', $order->serial_number)
            ->assertJsonPath('data.0.tasks.0.tasks.0.order.doctor.id', $doctor->id)
            ->assertJsonPath('data.0.tasks.0.tasks.0.order.doctor.location', 'Doctor Location');
    }

    private function createOrder(User $doctor): Order
    {
        return Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $this->lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => 'Delivery order',
            'price' => 250,
            'remaining_amount' => 250,
        ]);
    }
}
