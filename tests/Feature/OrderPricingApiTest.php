<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\LabPricingSetting;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPricingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_order_price_using_lab_price_list_and_modifiers(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Pricing Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        LabPricingSetting::query()->create([
            'lab_id' => $lab->id,
            'currency' => 'USD',
            'effective_from' => '2026-04-15',
            'implant_addon' => 2.50,
            'long_bridge_or_high_addon' => 3.50,
            'lisi_connect_etching_addon' => 2.00,
            'intraoral_print_fee' => 8.00,
            'vip_urgent_multiplier' => 1.25,
            'student_discount_note' => null,
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

        $receptionist = User::factory()->create();
        $receptionistRoleId = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id');
        if ($receptionistRoleId !== null) {
            $receptionist->roles()->syncWithoutDetaching([$receptionistRoleId]);
        }

        Sanctum::actingAs($receptionist);

        $order = Order::query()->create([
            'user_id' => $receptionist->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'urgent',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 0,
            'remaining_amount' => 0,
        ]);

        // units=2, base=21.00, implant add=5.00, intraoral fee=8.00 => 34.00 then urgent multiplier 1.25 => 42.50
        $response = $this->postJson("/api/auth/orders/{$order->id}/pricing/calculate", [
            'compensation_code' => 'full_zircon_standard',
            'units' => 2,
            'case_type' => 'implant',
            'include_intraoral_print_examples' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.breakdown.total', '42.50');
    }

    public function test_it_calculates_bridge_case_type_addon_using_case_type(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Pricing Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        LabPricingSetting::query()->create([
            'lab_id' => $lab->id,
            'currency' => 'USD',
            'effective_from' => '2026-04-15',
            'implant_addon' => 2.50,
            'long_bridge_or_high_addon' => 3.50,
            'lisi_connect_etching_addon' => 2.00,
            'intraoral_print_fee' => 8.00,
            'vip_urgent_multiplier' => 1.25,
            'student_discount_note' => null,
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

        $receptionist = User::factory()->create();
        $receptionistRoleId = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id');
        if ($receptionistRoleId !== null) {
            $receptionist->roles()->syncWithoutDetaching([$receptionistRoleId]);
        }

        Sanctum::actingAs($receptionist);

        $order = Order::query()->create([
            'user_id' => $receptionist->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'urgent',
            'status' => 'pending',
            'order_type' => 'digital',
            'case_type' => 'bridge',
            'notes' => null,
            'price' => 0,
            'remaining_amount' => 0,
        ]);

        $response = $this->postJson("/api/auth/orders/{$order->id}/pricing/calculate", [
            'compensation_code' => 'full_zircon_standard',
            'units' => 2,
            'case_type' => 'bridge',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.breakdown.total', '35.00');
    }

    public function test_it_can_persist_calculated_price_on_order_when_requested(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Pricing Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        LabPricingSetting::query()->create([
            'lab_id' => $lab->id,
            'currency' => 'USD',
            'effective_from' => '2026-04-15',
            'implant_addon' => 2.50,
            'long_bridge_or_high_addon' => 3.50,
            'lisi_connect_etching_addon' => 2.00,
            'intraoral_print_fee' => 8.00,
            'vip_urgent_multiplier' => 1.25,
            'student_discount_note' => null,
            'is_active' => true,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'التعويض المؤقت: طباعة ريزين',
            'description' => null,
            'code' => 'temporary_resin_print',
            'category' => 'temporary',
        ]);

        DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 2.00,
            'effective_from' => '2026-04-15',
            'is_active' => true,
        ]);

        $receptionist = User::factory()->create();
        $receptionistRoleId = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id');
        if ($receptionistRoleId !== null) {
            $receptionist->roles()->syncWithoutDetaching([$receptionistRoleId]);
        }

        Sanctum::actingAs($receptionist);

        $order = Order::query()->create([
            'user_id' => $receptionist->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 0,
            'remaining_amount' => 0,
        ]);

        $response = $this->postJson("/api/auth/orders/{$order->id}/pricing/calculate", [
            'compensation_code' => 'temporary_resin_print',
            'units' => 3,
            'persist' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.price', '6.00')
            ->assertJsonPath('data.remaining_amount', '6.00');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'price' => 6.00,
            'remaining_amount' => 6.00,
        ]);
    }
}
