<?php

namespace Tests\Feature;

use App\Events\DeliveryLocationUpdated;
use App\Models\DeliveryTask;
use App\Models\DeliveryTrack;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryTrackStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    private User $deliveryEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->lab = Lab::query()->create([
            'name' => 'Tracking Lab',
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
            'email' => 'delivery.tracking@example.com',
        ]);

        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $this->deliveryEmployee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);
    }

    public function test_delivery_employee_can_start_trip_for_multiple_tasks(): void
    {
        $doctor = User::factory()->create();
        $tasks = [
            $this->createAssignedTask($doctor),
            $this->createAssignedTask($doctor),
        ];
        $taskIds = array_map(static fn (DeliveryTask $task): int => $task->id, $tasks);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => $taskIds,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.task_ids', $taskIds)
            ->assertJsonCount(2, 'data.tracks');

        foreach ($tasks as $task) {
            $this->assertDatabaseHas('delivery_tracks', [
                'order_id' => $task->order_id,
                'delivery_person_id' => $this->deliveryEmployee->id,
                'status' => DeliveryTrackStatus::STARTED,
            ]);
        }
    }

    public function test_start_trip_requires_task_ids(): void
    {
        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson('/api/auth/delivery/tracking/start')
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_start_trip_rejects_tasks_belonging_to_different_doctors(): void
    {
        $taskIds = [
            $this->createAssignedTask(User::factory()->create())->id,
            $this->createAssignedTask(User::factory()->create())->id,
        ];

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => $taskIds,
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids'])
            ->assertJsonPath('errors.task_ids.0', __('orders.tracking_tasks_same_doctor'));
    }

    public function test_start_trip_rejects_tasks_not_assigned_to_delivery_person(): void
    {
        $otherEmployee = $this->createOtherDeliveryEmployee();
        $task = $this->createAssignedTask(User::factory()->create(), $otherEmployee);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => [$task->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_start_trip_rejects_invalid_state_transition(): void
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);
        $task = $this->createAssignedTask($doctor, $this->deliveryEmployee, $order);

        DeliveryTrack::query()->create([
            'order_id' => $order->id,
            'delivery_person_id' => $this->deliveryEmployee->id,
            'status' => DeliveryTrackStatus::ARRIVED,
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => [$task->id],
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_delivery_employee_can_update_location(): void
    {
        $doctor = User::factory()->create();
        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => [$task->id],
        ])->assertStatus(201);

        $response = $this->postJson('/api/auth/delivery/tracking/location', [
            'task_ids' => [$task->id],
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.tracks.0.order_id', $task->order_id)
            ->assertJsonPath('data.tracks.0.status', DeliveryTrackStatus::STARTED);

        $this->assertDatabaseHas('delivery_tracks', [
            'order_id' => $task->order_id,
            'delivery_person_id' => $this->deliveryEmployee->id,
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'status' => DeliveryTrackStatus::STARTED,
        ]);
    }

    public function test_cannot_update_location_after_trip_ended(): void
    {
        $doctor = User::factory()->create();
        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => [$task->id],
        ])->assertStatus(201);

        $this->postJson('/api/auth/delivery/tracking/end', [
            'task_ids' => [$task->id],
        ])->assertOk();

        $this->postJson('/api/auth/delivery/tracking/location', [
            'task_ids' => [$task->id],
            'latitude' => 33.5,
            'longitude' => 36.2,
        ])
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_delivery_employee_can_end_trip_for_multiple_tasks(): void
    {
        $doctor = User::factory()->create();
        $tasks = [
            $this->createAssignedTask($doctor),
            $this->createAssignedTask($doctor),
        ];
        $taskIds = array_map(static fn (DeliveryTask $task): int => $task->id, $tasks);

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => $taskIds,
        ])->assertStatus(201);

        $response = $this->postJson('/api/auth/delivery/tracking/end', [
            'task_ids' => $taskIds,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonCount(2, 'data.tracks');

        foreach ($tasks as $task) {
            $this->assertDatabaseHas('delivery_tracks', [
                'order_id' => $task->order_id,
                'delivery_person_id' => $this->deliveryEmployee->id,
                'status' => DeliveryTrackStatus::ARRIVED,
            ]);
        }
    }

    public function test_cannot_end_trip_that_was_never_started(): void
    {
        $doctor = User::factory()->create();
        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson('/api/auth/delivery/tracking/end', [
            'task_ids' => [$task->id],
        ])
            ->assertStatus(400)
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_non_delivery_user_cannot_access_tracking_endpoints(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        Sanctum::actingAs($doctor);

        $this->postJson('/api/auth/delivery/tracking/start', [
            'task_ids' => [1],
        ])->assertForbidden();
    }

    public function test_location_updated_event_broadcasts_to_doctor_channel(): void
    {
        $event = new DeliveryLocationUpdated(
            doctorId: 5,
            taskIds: [1, 2],
            orderIds: [10, 11],
            latitude: 33.5,
            longitude: 36.2,
            deliveryPersonId: 9,
            status: DeliveryTrackStatus::STARTED,
        );

        $this->assertSame('private-tracking.doctor.5', $event->broadcastOn()->name);
        $this->assertSame('location.updated', $event->broadcastAs());
        $this->assertSame([
            'doctor_id' => 5,
            'task_ids' => [1, 2],
            'order_ids' => [10, 11],
            'delivery_person_id' => 9,
            'latitude' => 33.5,
            'longitude' => 36.2,
            'status' => DeliveryTrackStatus::STARTED,
            'location_recorded_at' => null,
        ], $event->broadcastWith());
    }

    public function test_doctor_can_get_active_trip(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);
        $this->postJson('/api/auth/delivery/tracking/start', ['task_ids' => [$task->id]])->assertStatus(201);
        $this->postJson('/api/auth/delivery/tracking/location', [
            'task_ids' => [$task->id],
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ])->assertOk();

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.task_ids', [$task->id])
            ->assertJsonPath('data.order_ids', [$task->order_id])
            ->assertJsonPath('data.tracks.0.status', DeliveryTrackStatus::STARTED)
            ->assertJsonPath('data.tracks.0.latitude', '33.5138070')
            ->assertJsonPath('data.tracks.0.longitude', '36.2765279')
            ->assertJsonPath('data.delivery_person.id', $this->deliveryEmployee->id);
    }

    public function test_doctor_gets_no_active_trip_when_none_exists(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.message', __('orders.tracking_no_active_trip'));
    }

    public function test_doctor_cannot_see_other_doctors_active_trip(): void
    {
        $doctor1 = User::factory()->create();
        $doctor2 = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor1->roles()->sync([$role->id]);
        $doctor2->roles()->sync([$role->id]);

        $task = $this->createAssignedTask($doctor1);

        Sanctum::actingAs($this->deliveryEmployee);
        $this->postJson('/api/auth/delivery/tracking/start', ['task_ids' => [$task->id]])->assertStatus(201);

        Sanctum::actingAs($doctor2);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_system_admin_can_get_active_trip_for_any_doctor(): void
    {
        $doctor = User::factory()->create();
        $admin = User::factory()->create();
        $adminRole = Role::query()->where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $admin->roles()->sync([$adminRole->id]);

        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);
        $this->postJson('/api/auth/delivery/tracking/start', ['task_ids' => [$task->id]])->assertStatus(201);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/auth/doctor/tracking/active?doctor_id='.$doctor->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.doctor_id', $doctor->id);
    }

    public function test_system_admin_requires_doctor_id_param(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::query()->where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $admin->roles()->sync([$adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('orders.tracking_doctor_id_required'));
    }

    public function test_non_doctor_non_admin_cannot_access_active_trip(): void
    {
        $receptionist = User::factory()->create();
        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();
        $receptionist->roles()->sync([$role->id]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response->assertForbidden();
    }

    public function test_active_trip_returns_correct_data_after_location_update(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        $tasks = [
            $this->createAssignedTask($doctor),
            $this->createAssignedTask($doctor),
        ];
        $taskIds = array_map(static fn (DeliveryTask $task): int => $task->id, $tasks);

        Sanctum::actingAs($this->deliveryEmployee);
        $this->postJson('/api/auth/delivery/tracking/start', ['task_ids' => $taskIds])->assertStatus(201);
        $this->postJson('/api/auth/delivery/tracking/location', [
            'task_ids' => $taskIds,
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ])->assertOk();

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonCount(2, 'data.tracks')
            ->assertJsonPath('data.tracks.0.latitude', '33.5138070')
            ->assertJsonPath('data.tracks.0.longitude', '36.2765279')
            ->assertJsonPath('data.tracks.1.latitude', '33.5138070')
            ->assertJsonPath('data.tracks.1.longitude', '36.2765279');
    }

    public function test_active_trip_returns_false_after_trip_ended(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        $task = $this->createAssignedTask($doctor);

        Sanctum::actingAs($this->deliveryEmployee);
        $this->postJson('/api/auth/delivery/tracking/start', ['task_ids' => [$task->id]])->assertStatus(201);
        $this->postJson('/api/auth/delivery/tracking/end', ['task_ids' => [$task->id]])->assertOk();

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/tracking/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    private function createOrder(User $doctor): Order
    {
        return Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $this->lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'completed',
            'order_type' => 'digital',
            'notes' => 'Tracking order',
            'price' => 250,
            'remaining_amount' => 250,
        ]);
    }

    private function createAssignedTask(User $doctor, ?User $deliveryPerson = null, ?Order $order = null): DeliveryTask
    {
        $order = $order ?? $this->createOrder($doctor);

        return DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => ($deliveryPerson ?? $this->deliveryEmployee)->id,
            'status' => 'empty',
            'direction' => 'to_doctor',
        ]);
    }

    private function createOtherDeliveryEmployee(): User
    {
        $department = Department::query()->create([
            'lab_id' => $this->lab->id,
            'name' => 'Other Delivery',
            'is_management' => false,
        ]);

        $employee = User::factory()->create();

        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $employee->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        return $employee;
    }
}
