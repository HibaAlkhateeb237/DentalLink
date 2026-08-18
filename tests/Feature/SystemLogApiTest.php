<?php

namespace Tests\Feature;

use App\Http\Services\ReceptionistOrderService;
use App\Http\Services\SystemLogService;
use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\SystemLog;
use App\Models\ToothShade;
use App\Models\User;
use App\Support\OrderStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemLogApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createLabManagerWithLab(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $managementDept = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
        ]);

        $manager = User::factory()->create();
        $role = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'department_id' => $managementDept->id,
        ]);

        return ['lab' => $lab, 'manager' => $manager];
    }

    private function createLabOnly(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Other Lab',
            'phone' => '2222222',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        return ['lab' => $lab];
    }

    public function test_lab_manager_can_list_own_lab_logs_only(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();
        ['lab' => $otherLab] = $this->createLabOnly();

        SystemLog::factory()->count(2)->create(['lab_id' => $lab->id]);
        SystemLog::factory()->create(['lab_id' => $otherLab->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/system-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonMissing(['lab_id' => $otherLab->id]);
    }

    public function test_system_logs_are_paginated(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        SystemLog::factory()->count(20)->create(['lab_id' => $lab->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/system-logs?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_system_logs_can_be_filtered_by_level(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        SystemLog::factory()->count(3)->create(['lab_id' => $lab->id, 'level' => 'info']);
        SystemLog::factory()->count(2)->create(['lab_id' => $lab->id, 'level' => 'error']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/system-logs?level=error');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertTrue(
            collect($response->json('data'))->every(fn (array $log): bool => $log['level'] === 'error')
        );
    }

    public function test_lab_manager_can_view_single_log(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        $log = SystemLog::factory()->create(['lab_id' => $lab->id]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/auth/system-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.event', $log->event);
    }

    public function test_lab_manager_cannot_view_other_lab_log(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();
        ['lab' => $otherLab] = $this->createLabOnly();

        $log = SystemLog::factory()->create(['lab_id' => $otherLab->id]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/auth/system-logs/{$log->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_lab_manager_without_lab_returns_403(): void
    {
        $this->seedRoles();

        $manager = User::factory()->create();

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/system-logs')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_doctor_cannot_view_system_logs(): void
    {
        $this->seedRoles();
        ['lab' => $lab] = $this->createLabOnly();

        SystemLog::factory()->create(['lab_id' => $lab->id]);

        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->syncWithoutDetaching([$role->id]);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/auth/system-logs')->assertForbidden();
    }

    public function test_guest_cannot_view_system_logs(): void
    {
        $this->getJson('/api/auth/system-logs')->assertUnauthorized();
    }

    public function test_invalid_level_returns_400(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        SystemLog::factory()->create(['lab_id' => $lab->id]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/system-logs?level=critical')
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_service_records_system_log(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        $log = app(SystemLogService::class)->record(
            event: 'order.created',
            message: 'Order #123 was created',
            level: 'info',
            context: ['order_id' => 123],
            labId: $lab->id,
            userId: $manager->id,
        );

        $this->assertDatabaseHas('system_logs', [
            'id' => $log->id,
            'lab_id' => $lab->id,
            'user_id' => $manager->id,
            'level' => 'info',
            'event' => 'order.created',
        ]);
        $this->assertSame(['order_id' => 123], $log->metadata);
    }

    public function test_login_failure_records_system_log(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('system_logs', [
            'event' => 'auth.login.failed',
            'level' => 'warning',
        ]);
    }

    public function test_login_success_records_system_log(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('system_logs', [
            'event' => 'auth.login.success',
            'user_id' => $user->id,
            'level' => 'info',
        ]);
    }

    public function test_change_password_records_system_log(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'password' => 'new-pass-123',
            'password_confirmation' => 'new-pass-123',
        ])->assertOk();

        $this->assertDatabaseHas('system_logs', [
            'event' => 'auth.password.changed',
            'user_id' => $user->id,
            'level' => 'warning',
        ]);
    }

    public function test_assign_role_records_system_log(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $systemAdminRole = Role::query()->where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$systemAdminRole->id]);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/auth/assign-role', [
            'user_id' => $target->id,
            'role' => 'doctor',
            'department_id' => null,
        ])->assertOk();

        $this->assertDatabaseHas('system_logs', [
            'event' => 'admin.role.assigned',
            'user_id' => $admin->id,
            'level' => 'warning',
        ]);
    }

    public function test_order_status_change_records_system_log(): void
    {
        $toothShade = ToothShade::query()->create([
            'code' => 'A2',
            'name' => 'A2',
            'color_hex' => '#ffffff',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $compensationType = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'code' => 'CROWN',
            'name' => 'Crown',
        ]);

        $compensationPrice = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $compensationType->id,
            'base_price' => 50.00,
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'lab_id' => $lab->id,
            'status' => OrderStatus::NEW,
            'serial_number' => 'ORD-000001',
            'tooth_shade_id' => $toothShade->id,
            'dental_compensation_type_price_id' => $compensationPrice->id,
        ]);
        $actor = User::factory()->create();

        app(ReceptionistOrderService::class)->updateStatus($order, OrderStatus::IN_PROGRESS, null, $actor);

        $log = SystemLog::query()->where('event', 'order.status.changed')->firstOrFail();

        $this->assertSame($order->id, $log->metadata['order_id']);
        $this->assertSame(OrderStatus::NEW, $log->metadata['from_status']);
        $this->assertSame(OrderStatus::IN_PROGRESS, $log->metadata['to_status']);
        $this->assertSame($order->lab_id, $log->lab_id);
        $this->assertSame($actor->id, $log->user_id);
    }
}
