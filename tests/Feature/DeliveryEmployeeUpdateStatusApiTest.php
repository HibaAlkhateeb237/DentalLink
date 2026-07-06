<?php

namespace Tests\Feature;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryEmployeeUpdateStatusApiTest extends TestCase
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

    public function test_can_update_from_empty_to_received(): void
    {
        $task = $this->createTask('empty', 'to_doctor');

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'received',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.delivery_task.status', 'received');

        $this->assertNotNull($task->fresh()->picked_at);
    }

    public function test_picked_at_is_set_when_received(): void
    {
        $task = $this->createTask('empty', 'to_doctor');

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'received',
        ]);

        $this->assertNotNull($task->fresh()->picked_at);
    }

    public function test_can_update_from_received_to_on_the_way_to_doctor(): void
    {
        $task = $this->createTask('received', 'to_doctor', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'on_the_way_to_the_doctor',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.delivery_task.status', 'on_the_way_to_the_doctor');
    }

    public function test_can_update_from_received_to_on_the_way_to_lab(): void
    {
        $task = $this->createTask('received', 'to_lab', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'on_the_way_to_the_lab',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.delivery_task.status', 'on_the_way_to_the_lab');
    }

    public function test_can_update_from_on_the_way_to_doctor_to_delivered(): void
    {
        $task = $this->createTask('on_the_way_to_the_doctor', 'to_doctor', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'delivered',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.delivery_task.status', 'delivered');

        $this->assertNotNull($task->fresh()->delivered_at);
    }

    public function test_can_update_from_on_the_way_to_lab_to_delivered(): void
    {
        $task = $this->createTask('on_the_way_to_the_lab', 'to_lab', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'delivered',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.delivery_task.status', 'delivered');

        $this->assertNotNull($task->fresh()->delivered_at);
    }

    public function test_second_try_on_return_to_lab_reopens_original_execution_department_task(): void
    {
        $executionDepartment = Department::query()
            ->where('lab_id', $this->lab->id)
            ->where('sort_order', '>', 0)
            ->orderBy('sort_order', 'asc')
            ->firstOrFail();

        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);
        $order->update(['status' => 'try_on']);

        $existingTask = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $executionDepartment->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'completed',
            'approved_at' => now()->subDay(),
        ]);

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => 'on_the_way_to_the_lab',
            'direction' => 'to_lab',
            'picked_at' => now(),
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson('/api/auth/delivery/tasks/status/bulk', [
            'delivery_task_ids' => [$deliveryTask->id],
            'status' => 'delivered',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.updated_tasks.0.status', 'delivered');

        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertSame('completed', $existingTask->fresh()->status);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', [
            'order_id' => $order->id,
            'department_id' => $executionDepartment->id,
            'status' => 'pending_assignment',
            'user_id' => null,
        ]);
    }

    public function test_delivered_at_is_set_when_delivered(): void
    {
        $task = $this->createTask('on_the_way_to_the_lab', 'to_lab', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'delivered',
        ]);

        $this->assertNotNull($task->fresh()->delivered_at);
    }

    public function test_cannot_skip_statuses(): void
    {
        $task = $this->createTask('empty', 'to_doctor');

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_make_invalid_transition(): void
    {
        $task = $this->createTask('received', 'to_doctor', ['picked_at' => now()]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_update_another_employees_task(): void
    {
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

        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);
        $task = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $otherEmployee->id,
            'status' => 'empty',
        ]);

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'received',
        ]);

        $response->assertForbidden();
    }

    public function test_non_delivery_user_cannot_update_status(): void
    {
        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->sync([$role->id]);

        $task = $this->createTask('empty', 'to_doctor');

        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'received',
        ]);

        $response->assertForbidden();
    }

    public function test_response_includes_delivery_task_resource(): void
    {
        $task = $this->createTask('empty', 'to_doctor');

        Sanctum::actingAs($this->deliveryEmployee);

        $response = $this->postJson("/api/auth/delivery/tasks/{$task->id}/status", [
            'status' => 'received',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'status',
                'message',
                'data' => [
                    'delivery_task' => [
                        'id',
                        'order_id',
                        'status',
                        'direction',
                        'assigned_at',
                        'picked_at',
                        'delivered_at',
                        'order',
                    ],
                ],
                'errors',
            ]);
    }

    private function createTask(string $status, string $direction, array $extra = []): DeliveryTask
    {
        $doctor = User::factory()->create();
        $order = $this->createOrder($doctor);

        return DeliveryTask::query()->create(array_merge([
            'order_id' => $order->id,
            'user_id' => $this->deliveryEmployee->id,
            'status' => $status,
            'direction' => $direction,
        ], $extra));
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
