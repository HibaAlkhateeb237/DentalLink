<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Department;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\ToothShade;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ToothShadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_order_with_valid_qr_code(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        $price = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 15.00,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $technician = User::factory()->create();
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');

        if ($technicianRoleId !== null) {
            $technician->roles()->sync([$technicianRoleId]);
        }

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $technician->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'description' => 'Ceramics department',
            'time_allowed' => 2,
        ]);

        $task = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $department->id,
            'user_id' => $technician->id,
            'status' => 'assigned',
        ]);

        Sanctum::actingAs($technician);

        $response = $this->getJson("/api/auth/lab/technician/orders/qr/{$order->qr_code}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.qr_code', $order->qr_code)
            ->assertJsonPath('data.order.lab_id', $lab->id)
            ->assertJsonPath('data.order.priority', 'normal')
            ->assertJsonPath('data.order.price', '15.00')
            ->assertJsonPath('data.task.id', $task->id)
            ->assertJsonPath('data.task.status', 'assigned')
            ->assertJsonPath('data.task.order_id', $order->id)
            ->assertJsonPath('data.task.user_id', $technician->id);
    }

    public function test_returns_404_for_invalid_qr_code(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $technician = User::factory()->create();
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');

        if ($technicianRoleId !== null) {
            $technician->roles()->sync([$technicianRoleId]);
        }

        Sanctum::actingAs($technician);

        $response = $this->getJson('/api/auth/lab/technician/orders/qr/invalid-uuid-xyz');

        $response->assertNotFound();
    }

    public function test_qr_url_field_contains_qr_code_value(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        $price = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 15.00,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $technician = User::factory()->create();
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');

        if ($technicianRoleId !== null) {
            $technician->roles()->sync([$technicianRoleId]);
        }

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $technician->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'description' => 'Ceramics department',
            'time_allowed' => 2,
        ]);

        Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $department->id,
            'user_id' => $technician->id,
            'status' => 'assigned',
        ]);

        Sanctum::actingAs($technician);

        $response = $this->getJson("/api/auth/lab/technician/orders/qr/{$order->qr_code}");

        $qrCode = $response->json('data.order.qr_code');
        $qrUrl = $response->json('data.order.qr_url');

        $this->assertStringContainsString($qrCode, $qrUrl);
        $this->assertStringContainsString('/orders/qr/', $qrUrl);
    }

    public function test_returns_404_when_technician_has_no_task_for_order(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        $price = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 15.00,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $technician = User::factory()->create();
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');

        if ($technicianRoleId !== null) {
            $technician->roles()->sync([$technicianRoleId]);
        }

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $technician->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        // No task created for this technician + order

        Sanctum::actingAs($technician);

        $response = $this->getJson("/api/auth/lab/technician/orders/qr/{$order->qr_code}");

        $response->assertNotFound();
    }
}
