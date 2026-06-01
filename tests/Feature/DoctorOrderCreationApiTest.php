<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\Role;
use App\Models\ToothShade;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ToothShadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorOrderCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_lab_materials_and_tooth_shades_for_the_order_form(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Order Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'is_active' => true,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/auth/labs/{$lab->id}/pricing");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.code', 'full_zircon_standard')
            ->assertJsonPath('data.tooth_shades.0.code', 'A1');
    }

    public function test_doctor_can_create_order_with_shades_and_material_type_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Order Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'is_active' => true,
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

        $shade = ToothShade::query()->where('code', 'A2')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        Sanctum::actingAs($doctor);

        $response = $this->postJson('/api/auth/doctor/orders', [
            'lab_id' => $lab->id,
            'patient_name' => 'محمد',
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_id' => $type->id,
            'priority' => 'urgent',
            'order_type' => 'digital',
            'case_type' => 'implant',
            'notes' => 'Doctor order',
            'teeth' => [
                [
                    'tooth_number' => 11,
                    'notes' => 'Main tooth',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lab_id', $lab->id)
            ->assertJsonPath('data.case_type', 'implant')
            ->assertJsonPath('data.patient_name', 'محمد')
            ->assertJsonPath('data.serial_number', 'ORD-000001')
            ->assertJsonPath('data.tooth_shade.code', 'A2')
            ->assertJsonPath('data.teeth.0.tooth_number', 11);

        $receivedAt = $response->json('data.received_at');
        $deliveredAt = $response->json('data.delivered_at');

        $this->assertNotNull($receivedAt);
        $this->assertNotNull($deliveredAt);
        $this->assertEquals(
            Carbon::parse($receivedAt)->addDays(2)->toISOString(),
            $deliveredAt
        );

        $this->assertDatabaseHas('orders', [
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'priority' => 'urgent',
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'patient_name' => 'محمد',
        ]);

        $this->assertDatabaseHas('order_teeth', [
            'tooth_number' => 11,
        ]);

        // Verify qr_url contains qr_code and /qr/ path
        $qrCode = $response->json('data.qr_code');
        $qrUrl = $response->json('data.qr_url');
        $this->assertStringContainsString($qrCode, $qrUrl);
        $this->assertStringContainsString('/lab/technician/orders/qr/', $qrUrl);

        // Test retrieval via show route with qr_code binding
        $technician = User::factory()->create();
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');

        if ($technicianRoleId !== null) {
            $technician->roles()->syncWithoutDetaching([$technicianRoleId]);
        }

        Sanctum::actingAs($technician);

        $getResponse = $this->getJson("/api/auth/lab/technician/orders/qr/{$qrCode}");
        $getResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qr_code', $qrCode)
            ->assertJsonPath('data.lab_id', $lab->id);
    }

    public function test_it_rejects_materials_from_other_labs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Primary Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'is_active' => true,
        ]);

        $otherLab = Lab::query()->create([
            'name' => 'Other Lab',
            'phone' => '2222222',
            'address' => 'Address 2',
            'latitude' => 33.6138070,
            'longitude' => 36.3765279,
            'is_active' => true,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $otherLab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        $otherLabPrice = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        Sanctum::actingAs($doctor);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $response = $this->postJson('/api/auth/doctor/orders', [
            'lab_id' => $lab->id,
            'patient_name' => 'أحمد',
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_id' => $type->id,
            'priority' => 'normal',
            'case_type' => 'bridge',
            'teeth' => [
                [
                    'tooth_number' => 11,
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString('dental_compensation_type_id', $response->getContent());

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_teeth', 0);
    }

    public function test_doctor_can_create_bridge_order_with_case_type_pricing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Order Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'is_active' => true,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        DentalCompensationTypePrice::query()->create([
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

        Sanctum::actingAs($doctor);

        $response = $this->postJson('/api/auth/doctor/orders', [
            'lab_id' => $lab->id,
            'patient_name' => 'سارة',
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_id' => $type->id,
            'priority' => 'normal',
            'case_type' => 'bridge',
            'teeth' => [
                [
                    'tooth_number' => 11,
                ],
                [
                    'tooth_number' => 12,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.case_type', 'bridge')
            ->assertJsonPath('data.price', '28.00');
    }

    public function test_patient_name_is_required_on_order_creation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Order Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'is_active' => true,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'فل زيركون عادي',
            'description' => null,
            'code' => 'full_zircon_standard',
            'category' => 'zircon',
        ]);

        DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 10.50,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A2')->firstOrFail();

        $doctor = User::factory()->create();
        $doctorRoleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');

        if ($doctorRoleId !== null) {
            $doctor->roles()->syncWithoutDetaching([$doctorRoleId]);
        }

        Sanctum::actingAs($doctor);

        $response = $this->postJson('/api/auth/doctor/orders', [
            'lab_id' => $lab->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_id' => $type->id,
            'priority' => 'urgent',
            'order_type' => 'digital',
            'case_type' => 'implant',
            'notes' => 'Doctor order',
            'teeth' => [
                [
                    'tooth_number' => 11,
                    'notes' => 'Main tooth',
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['patient_name']]);
    }
}
