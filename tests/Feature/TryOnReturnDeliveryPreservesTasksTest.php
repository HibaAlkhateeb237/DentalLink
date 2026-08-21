<?php

namespace Tests\Feature;

use App\Http\Services\OrderDeliveryTransitionService;
use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\DeliveryStatus;
use App\Support\DeliveryTaskDirection;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TryOnReturnDeliveryPreservesTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_try_on_return_to_lab_transitions_order_to_in_progress(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('TryOn Lab');
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'CAD/CAM', 2);

        $doctor = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-TRYON-001',
            'priority' => 'normal',
            'status' => OrderStatus::TRY_ON,
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.tryon@example.com');

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => DeliveryStatus::ON_THE_WAY_TO_LAB,
            'direction' => DeliveryTaskDirection::TO_LAB,
            'original_order_status' => OrderStatus::TRY_ON,
        ]);

        app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($deliveryTask);

        $order->refresh();

        $this->assertEquals(OrderStatus::IN_PROGRESS, $order->status);
        $this->assertFalse($order->is_in_delivery);
    }

    public function test_try_on_return_preserves_existing_completed_tasks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('TryOn Preserve Lab');
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'CAD/CAM', 2);

        $doctor = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-TRYON-002',
            'priority' => 'normal',
            'status' => OrderStatus::TRY_ON,
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        $taskA = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        $taskB = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.tryon2@example.com');

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => DeliveryStatus::ON_THE_WAY_TO_LAB,
            'direction' => DeliveryTaskDirection::TO_LAB,
            'original_order_status' => OrderStatus::TRY_ON,
        ]);

        app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($deliveryTask);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskA->id,
            'status' => TaskStatus::COMPLETED,
            'department_id' => $deptA->id,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskB->id,
            'status' => TaskStatus::COMPLETED,
            'department_id' => $deptB->id,
        ]);

        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_try_on_return_does_not_create_new_department_tasks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('TryOn NoCreate Lab');
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'CAD/CAM', 2);

        $doctor = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-TRYON-003',
            'priority' => 'normal',
            'status' => OrderStatus::TRY_ON,
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.tryon3@example.com');

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => DeliveryStatus::ON_THE_WAY_TO_LAB,
            'direction' => DeliveryTaskDirection::TO_LAB,
            'original_order_status' => OrderStatus::TRY_ON,
        ]);

        app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($deliveryTask);

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseMissing('tasks', [
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::PENDING_ASSIGNMENT,
        ]);
    }

    public function test_try_on_return_preserves_in_progress_task_state(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('TryOn InProgress Lab');
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);
        $deptB = $this->createDepartment($lab, 'CAD/CAM', 2);

        $doctor = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-TRYON-004',
            'priority' => 'normal',
            'status' => OrderStatus::TRY_ON,
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        $taskA = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);

        $taskB = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.tryon4@example.com');

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => DeliveryStatus::ON_THE_WAY_TO_LAB,
            'direction' => DeliveryTaskDirection::TO_LAB,
            'original_order_status' => OrderStatus::TRY_ON,
        ]);

        app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($deliveryTask);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskA->id,
            'status' => TaskStatus::COMPLETED,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskB->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_resend_wrong_impression_still_creates_first_department_task(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('Resend Lab');
        $deptA = $this->createDepartment($lab, 'Ceramics', 1);

        $doctor = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-RESEND-001',
            'priority' => 'normal',
            'status' => OrderStatus::RESEND_WRONG_IMPRESSION,
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 0,
        ]);

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.resend@example.com');

        $deliveryTask = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => DeliveryStatus::ON_THE_WAY_TO_LAB,
            'direction' => DeliveryTaskDirection::TO_LAB,
            'original_order_status' => OrderStatus::RESEND_WRONG_IMPRESSION,
        ]);

        app(OrderDeliveryTransitionService::class)->handleDeliveryCompleted($deliveryTask);

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', [
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => TaskStatus::PENDING_ASSIGNMENT,
        ]);
    }

    private function createLab(string $name): Lab
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

    private function createDepartment(Lab $lab, string $name, int $sortOrder): Department
    {
        return Department::query()->create([
            'lab_id' => $lab->id,
            'name' => $name,
            'is_management' => false,
            'sort_order' => $sortOrder,
            'time_allowed' => 8,
        ]);
    }

    private function createDeliveryEmployee(Lab $lab, string $email): User
    {
        $deliveryDepartment = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Delivery',
            'is_management' => false,
        ]);

        $deliveryUser = User::factory()->create([
            'email' => $email,
        ]);

        $role = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $deliveryUser->id,
            'role_id' => $role->id,
            'department_id' => $deliveryDepartment->id,
        ]);

        return $deliveryUser;
    }
}
