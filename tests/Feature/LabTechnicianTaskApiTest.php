<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabTechnicianTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_department_time_and_task_metrics_for_the_authenticated_technician(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 12:00:00'));

        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab();
        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'description' => 'Ceramics department',
            'time_allowed' => 2,
        ]);

        $technician = $this->createTechnician($department);
        Sanctum::actingAs($technician);

        $completedTask = $this->createTaskWithSessions($department, $technician, 'completed', [
            [Carbon::parse('2026-05-21 08:00:00'), Carbon::parse('2026-05-21 09:30:00')],
            [Carbon::parse('2026-05-21 09:45:00'), Carbon::parse('2026-05-21 10:45:00')],
        ]);

        $assignedTask = $this->createTaskWithSessions($department, $technician, 'assigned', [
            [Carbon::parse('2026-05-21 10:00:00'), Carbon::parse('2026-05-21 11:30:00')],
        ]);

        $response = $this->getJson('/api/auth/lab/technician/departments/'.$department->id.'/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('tasks.retrieved_successfully'))
            ->assertJsonPath('data.department.time_allowed_hours', 2)
            ->assertJsonCount(2, 'data.tasks');

        $tasks = $response->json('data.tasks');

        $this->assertIsArray($tasks);

        $tasksByStatus = collect($tasks)->keyBy('status');

        $assignedTaskData = $tasksByStatus->get('assigned');
        $completedTaskData = $tasksByStatus->get('completed');

        $this->assertIsArray($assignedTaskData);
        $this->assertSame('normal', $assignedTaskData['order']['priority']);
        $this->assertSame('Zircon Crown', $assignedTaskData['order']['material_type']);
        $this->assertSame(90, $assignedTaskData['worked_minutes']);
        $this->assertSame(30, $assignedTaskData['remaining_minutes']);
        $this->assertSame(0, $assignedTaskData['overdue_minutes']);

        $this->assertIsArray($completedTaskData);
        $this->assertSame('urgent', $completedTaskData['order']['priority']);
        $this->assertSame(150, $completedTaskData['worked_minutes']);
        $this->assertSame(-30, $completedTaskData['remaining_minutes']);
        $this->assertSame(-30, $completedTaskData['overdue_minutes']);

        Carbon::setTestNow();
    }

    public function test_it_filters_tasks_by_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-21 12:00:00'));

        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = $this->createLab();
        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Implants',
            'description' => 'Implants department',
            'time_allowed' => 3,
        ]);

        $technician = $this->createTechnician($department);
        Sanctum::actingAs($technician);

        $this->createTaskWithSessions($department, $technician, 'assigned', [
            [Carbon::parse('2026-05-21 08:00:00'), Carbon::parse('2026-05-21 08:45:00')],
        ]);

        $this->createTaskWithSessions($department, $technician, 'in_progress', [
            [Carbon::parse('2026-05-21 10:00:00'), Carbon::parse('2026-05-21 10:40:00')],
        ]);

        $response = $this->getJson('/api/auth/lab/technician/departments/'.$department->id.'/tasks?status=in_progress');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonCount(1, 'data.tasks');

        $tasks = $response->json('data.tasks');

        $this->assertIsArray($tasks);
        $this->assertSame('in_progress', $tasks[0]['status']);
        $this->assertSame(40, $tasks[0]['worked_minutes']);
        $this->assertSame(140, $tasks[0]['remaining_minutes']);
        $this->assertSame(0, $tasks[0]['overdue_minutes']);

        Carbon::setTestNow();
    }

    private function createLab(): Lab
    {
        return Lab::query()->create([
            'name' => 'Demo Lab',
            'phone' => '0999000000',
            'address' => 'Damascus',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);
    }

    private function createTechnician(Department $department): User
    {
        $technician = User::factory()->create();

        $roleId = Role::query()
            ->where('name', 'lab_technician')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($roleId !== null) {
            $technician->roles()->syncWithoutDetaching([$roleId]);
            DepartmentUserRole::query()->create([
                'user_id' => $technician->id,
                'role_id' => $roleId,
                'department_id' => $department->id,
            ]);
        }

        return $technician;
    }

    /**
     * @param  array<int, array{0:Carbon,1:Carbon|null}>  $sessions
     */
    private function createTaskWithSessions(Department $department, User $technician, string $status, array $sessions): Task
    {
        $compensationType = DentalCompensationType::query()->firstOrCreate([
            'lab_id' => $department->lab_id,
            'code' => Str::slug('Zircon Crown', '_'),
        ], [
            'name' => 'Zircon Crown',
            'category' => null,
            'description' => 'Material type',
        ]);

        $price = DentalCompensationTypePrice::query()
            ->where('dental_compensation_type_id', $compensationType->id)
            ->whereDate('effective_from', now()->toDateString())
            ->first();

        if ($price === null) {
            $price = DentalCompensationTypePrice::query()->create([
                'dental_compensation_type_id' => $compensationType->id,
                'base_price' => 100,
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]);
        }

        $order = Order::query()->create([
            'user_id' => $technician->id,
            'lab_id' => $department->lab_id,
            'qr_code' => (string) Str::uuid(),
            'priority' => $status === 'completed' ? 'urgent' : 'normal',
            'status' => $status === 'assigned' ? 'pending' : 'in_progress',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 100,
            'remaining_amount' => 0,
            'dental_compensation_type_price_id' => $price->id,
        ]);

        $task = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $department->id,
            'user_id' => $technician->id,
            'status' => $status,
            'approved_at' => now(),
        ]);

        foreach ($sessions as [$startTime, $endTime]) {
            TaskWorkSession::query()->create([
                'task_id' => $task->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $endTime === null ? 'active' : 'completed',
                'note' => null,
            ]);
        }

        return $task;
    }
}
