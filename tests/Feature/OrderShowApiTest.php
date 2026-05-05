<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
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
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/auth/orders/qr/{$order->qr_code}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.qr_code', $order->qr_code)
            ->assertJsonPath('data.lab_id', $lab->id)
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.price', '15.00');
    }

    public function test_returns_404_for_invalid_qr_code(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/orders/qr/invalid-uuid-xyz');

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
            'price' => 15.00,
            'remaining_amount' => 15.00,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/auth/orders/qr/{$order->qr_code}");

        $qrCode = $response->json('data.qr_code');
        $qrUrl = $response->json('data.qr_url');

        $this->assertStringContainsString($qrCode, $qrUrl);
        $this->assertStringContainsString('/orders/qr/', $qrUrl);
    }
}
