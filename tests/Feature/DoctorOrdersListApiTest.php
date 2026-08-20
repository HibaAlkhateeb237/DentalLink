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
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ToothShadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorOrdersListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_their_orders_list(): void
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
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        // Create multiple orders for this doctor
        Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 10.50,
            'remaining_amount' => 10.50,
        ]);

        Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'urgent',
            'status' => 'in_progress',
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.user_id', $doctor->id)
            ->assertJsonPath('data.1.user_id', $doctor->id);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_doctor_can_view_own_order_details(): void
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
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 10.50,
            'remaining_amount' => 10.50,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/auth/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.user_id', $doctor->id)
            ->assertJsonPath('data.lab_name', 'Test Lab')
            ->assertJsonPath('data.lab_phone', '1111111');
    }

    public function test_doctor_cannot_view_other_doctors_orders(): void
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
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $doctor1 = User::factory()->create();
        $doctor2 = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor1->roles()->syncWithoutDetaching([$doctorRoleId]);
            $doctor2->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor1->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'price' => 10.50,
            'remaining_amount' => 10.50,
        ]);

        Sanctum::actingAs($doctor2);

        $response = $this->getJson("/api/auth/orders/{$order->id}");

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 403);
    }

    public function test_order_list_is_paginated(): void
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
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        // Create 5 orders
        for ($i = 0; $i < 5; $i++) {
            Order::query()->create([
                'lab_id' => $lab->id,
                'user_id' => $doctor->id,
                'tooth_shade_id' => $shade->id,
                'dental_compensation_type_price_id' => $price->id,
                'qr_code' => (string) Str::uuid(),
                'priority' => 'normal',
                'status' => 'pending',
                'price' => 10.50,
                'remaining_amount' => 10.50,
            ]);
        }

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/orders');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verify pagination structure - should have data array with items
        $data = $response->json('data');
        $this->assertCount(5, $data);
    }

    public function test_in_progress_orders_are_split_between_pending_and_in_progress_tabs_by_first_department_task_status(): void
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

        $firstDepartment = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'First Department',
            'is_management' => false,
            'sort_order' => 1,
        ]);

        Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Second Department',
            'is_management' => false,
            'sort_order' => 2,
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
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        $pendingOrder = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => OrderStatus::PENDING,
            'price' => 10.50,
            'remaining_amount' => 10.50,
        ]);

        $waitingTabInProgressOrder = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => OrderStatus::IN_PROGRESS,
            'price' => 12.00,
            'remaining_amount' => 12.00,
        ]);

        Task::query()->create([
            'order_id' => $waitingTabInProgressOrder->id,
            'department_id' => $firstDepartment->id,
            'user_id' => null,
            'status' => TaskStatus::ASSIGNED,
        ]);

        $inProgressTabOrder = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => OrderStatus::IN_PROGRESS,
            'price' => 14.00,
            'remaining_amount' => 14.00,
        ]);

        Task::query()->create([
            'order_id' => $inProgressTabOrder->id,
            'department_id' => $firstDepartment->id,
            'user_id' => null,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        Sanctum::actingAs($doctor);

        $pendingResponse = $this->getJson('/api/auth/doctor/orders?status=pending');
        $pendingResponse->assertOk();

        $pendingPayload = $pendingResponse->json('data', []);
        $pendingOrders = collect(is_array($pendingPayload) && array_key_exists('data', $pendingPayload)
            ? $pendingPayload['data']
            : $pendingPayload);
        $pendingOrderIds = $pendingOrders->pluck('id')->all();

        $this->assertContains($pendingOrder->id, $pendingOrderIds);
        $this->assertContains($waitingTabInProgressOrder->id, $pendingOrderIds);
        $this->assertNotContains($inProgressTabOrder->id, $pendingOrderIds);

        $inProgressResponse = $this->getJson('/api/auth/doctor/orders?status=in_progress');
        $inProgressResponse->assertOk();

        $inProgressPayload = $inProgressResponse->json('data', []);
        $inProgressOrders = collect(is_array($inProgressPayload) && array_key_exists('data', $inProgressPayload)
            ? $inProgressPayload['data']
            : $inProgressPayload);
        $inProgressOrderIds = $inProgressOrders->pluck('id')->all();

        $this->assertContains($inProgressTabOrder->id, $inProgressOrderIds);
        $this->assertNotContains($waitingTabInProgressOrder->id, $inProgressOrderIds);
    }
}
