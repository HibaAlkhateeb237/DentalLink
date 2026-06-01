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

class ReceptionistDeliveryAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_list_delivery_employees(): void
    {
        [$receptionist, $lab] = $this->authenticateReceptionist();

        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery1@example.com');
        $otherLab = $this->createLab('Other Delivery Lab');
        $this->createDeliveryEmployee($otherLab, 'delivery2@example.com');

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/orders/delivery-employees?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('message', __('orders.delivery_employees_retrieved'))
            ->assertJsonPath('data.data.0.id', $deliveryEmployee->id);
    }

    public function test_receptionist_can_assign_delivery_to_order(): void
    {
        [$receptionist, $lab] = $this->authenticateReceptionist();
        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery3@example.com');
        $order = $this->createOrder($lab);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/auth/orders/'.$order->id.'/delivery-assignments', [
            'user_id' => $deliveryEmployee->id,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('message', __('orders.delivery_assigned_successfully'))
            ->assertJsonPath('data.delivery_task.order_id', $order->id)
            ->assertJsonPath('data.delivery_task.delivery_user.id', $deliveryEmployee->id);

        $this->assertDatabaseHas('delivery_tasks', [
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => 'pending',
        ]);
    }

    public function test_receptionist_can_list_delivery_tasks(): void
    {
        [$receptionist, $lab] = $this->authenticateReceptionist();
        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.list@example.com');
        $order = $this->createOrder($lab);

        $task = DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/orders/delivery-tasks?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('message', __('orders.delivery_tasks_retrieved'))
            ->assertJsonPath('data.data.0.id', $task->id);
    }

    public function test_receptionist_cannot_assign_delivery_for_other_lab(): void
    {
        [$receptionist, $lab] = $this->authenticateReceptionist();
        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery4@example.com');
        $otherLab = $this->createLab('Foreign Orders Lab');
        $order = $this->createOrder($otherLab);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/auth/orders/'.$order->id.'/delivery-assignments', [
            'user_id' => $deliveryEmployee->id,
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_receptionist_cannot_assign_delivery_when_already_assigned(): void
    {
        [$receptionist, $lab] = $this->authenticateReceptionist();
        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery5@example.com');
        $order = $this->createOrder($lab);

        DeliveryTask::query()->create([
            'order_id' => $order->id,
            'user_id' => $deliveryEmployee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/auth/orders/'.$order->id.'/delivery-assignments', [
            'user_id' => $deliveryEmployee->id,
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_non_receptionist_cannot_assign_delivery(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('Blocked Lab');
        $deliveryEmployee = $this->createDeliveryEmployee($lab, 'delivery.blocked@example.com');
        $order = $this->createOrder($lab);

        $user = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/orders/'.$order->id.'/delivery-assignments', [
            'user_id' => $deliveryEmployee->id,
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0:User,1:Lab}
     */
    private function authenticateReceptionist(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab('Receptionist Lab');

        $receptionDepartment = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Reception',
            'is_management' => false,
        ]);

        $receptionist = User::factory()->create([
            'email' => 'receptionist@example.com',
        ]);

        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => $role->id,
            'department_id' => $receptionDepartment->id,
        ]);

        return [$receptionist, $lab];
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

    private function createOrder(Lab $lab): Order
    {
        $doctor = User::factory()->create([
            'email' => 'doctor.'.Str::uuid().'@example.com',
        ]);

        return Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
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
