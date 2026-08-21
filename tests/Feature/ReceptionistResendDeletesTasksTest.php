<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskWorkSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceptionistResendDeletesTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_status_deletes_all_tasks_across_all_departments(): void
    {
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'Resend Lab',
            'phone' => '5555555',
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

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-RESEND-001',
            'priority' => 'normal',
            'status' => 'in_progress',
            'order_type' => 'digital',
            'price' => 200,
            'remaining_amount' => 200,
        ]);

        $taskA = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        $taskB = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptB->id,
            'status' => 'in_progress',
        ]);

        TaskWorkSession::query()->create([
            'task_id' => $taskA->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->subMinutes(30),
            'status' => 'completed',
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson("/api/auth/orders/{$order->id}/status", [
            'status' => 'resend_wrong_impression',
            'notes' => 'Wrong impression taken',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', 'resend_wrong_impression');

        $this->assertDatabaseMissing('tasks', ['id' => $taskA->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $taskB->id]);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('task_work_sessions', 0);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'resend_wrong_impression',
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'in_progress',
            'to_status' => 'resend_wrong_impression',
        ]);
    }

    public function test_other_status_changes_do_not_delete_tasks(): void
    {
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'TryOn Lab',
            'phone' => '6666666',
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

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-TRYON-001',
            'priority' => 'normal',
            'status' => 'in_progress',
            'order_type' => 'digital',
            'price' => 150,
            'remaining_amount' => 150,
        ]);

        $taskA = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $deptA->id,
            'status' => 'in_progress',
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson("/api/auth/orders/{$order->id}/status", [
            'status' => 'try_on',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status', 'try_on');

        $this->assertDatabaseHas('tasks', ['id' => $taskA->id]);
        $this->assertDatabaseCount('tasks', 1);
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
}
