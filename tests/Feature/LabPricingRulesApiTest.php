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

class LabPricingRulesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_creating_rule_and_it_affects_order_pricing_calculation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Rules Lab',
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

        $labManager = User::factory()->create([
            'lab_id' => $lab->id,
        ]);

        $labManagerRoleId = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($labManagerRoleId !== null) {
            $labManager->roles()->syncWithoutDetaching([$labManagerRoleId]);
        }

        Sanctum::actingAs($labManager);

        $createRuleResponse = $this->postJson("/api/auth/labs/{$lab->id}/pricing/rules", [
            'code' => 'vip_urgent_multiplier',
            'name' => 'Override urgent multiplier',
            'kind' => 'multiplier',
            'applies_to' => 'order',
            'value' => 1.50,
            'effective_from' => '2026-04-15',
            'condition' => [
                '==' => [
                    ['var' => 'order.priority'],
                    'urgent',
                ],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $createRuleResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'vip_urgent_multiplier');

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

        // units=2, base=21.00, implant add=5.00, intraoral fee=8.00 => 34.00 then urgent multiplier overridden by rule 1.50 => 51.00
        $pricingResponse = $this->postJson("/api/auth/orders/{$order->id}/pricing/calculate", [
            'compensation_code' => 'full_zircon_standard',
            'units' => 2,
            'is_implant' => true,
            'include_intraoral_print_examples' => true,
        ]);

        $pricingResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.breakdown.total', '51.00');
    }

    public function test_it_forbids_creating_rules_for_other_labs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $labA = Lab::query()->create([
            'name' => 'Lab A',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $labB = Lab::query()->create([
            'name' => 'Lab B',
            'phone' => '2222222',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $labManager = User::factory()->create([
            'lab_id' => $labA->id,
        ]);

        $labManagerRoleId = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($labManagerRoleId !== null) {
            $labManager->roles()->syncWithoutDetaching([$labManagerRoleId]);
        }

        Sanctum::actingAs($labManager);

        $response = $this->postJson("/api/auth/labs/{$labB->id}/pricing/rules", [
            'code' => 'vip_urgent_multiplier',
            'name' => 'Override urgent multiplier',
            'kind' => 'multiplier',
            'applies_to' => 'order',
            'value' => 1.50,
            'effective_from' => '2026-04-15',
            'condition' => [
                '==' => [
                    ['var' => 'order.priority'],
                    'urgent',
                ],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $response->assertForbidden();
    }
}
