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
