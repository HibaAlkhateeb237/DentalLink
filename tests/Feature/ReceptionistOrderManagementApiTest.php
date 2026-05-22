<?php

namespace Tests\Feature;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\OrderTooth;
use App\Models\Role;
use App\Models\ToothShade;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ToothShadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceptionistOrderManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_view_orders_list_with_filters(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ToothShadeSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctorOne = User::factory()->create(['name' => 'Doctor One']);
        $doctorTwo = User::factory()->create(['name' => 'Doctor Two']);

        $lab = Lab::query()->create([
            'name' => 'Main Lab',
            'phone' => '123456',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $type = DentalCompensationType::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Zircon Standard',
            'description' => null,
            'code' => 'zircon_standard',
            'category' => 'zircon',
        ]);

        $price = DentalCompensationTypePrice::query()->create([
            'dental_compensation_type_id' => $type->id,
            'base_price' => 125,
            'effective_from' => '2026-05-01',
            'is_active' => true,
        ]);

        $shade = ToothShade::query()->where('code', 'A1')->firstOrFail();

        $matchingOrder = Order::query()->create([
            'user_id' => $doctorOne->id,
            'lab_id' => $lab->id,
            'tooth_shade_id' => $shade->id,
            'dental_compensation_type_price_id' => $price->id,
            'qr_code' => 'QR-MATCH-001',
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 125,
            'remaining_amount' => 125,
        ]);

        OrderTooth::query()->create([
            'order_id' => $matchingOrder->id,
            'tooth_number' => 12,
            'notes' => null,
        ]);

        OrderFile::query()->create([
            'order_id' => $matchingOrder->id,
            'file_path' => 'orders/QR-MATCH-001/image-1.png',
            'file_type' => 'image/png',
            'uploaded_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $doctorTwo->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-NON-MATCH-002',
            'priority' => 'urgent',
            'status' => 'completed',
            'order_type' => 'physical',
            'notes' => null,
            'price' => 500,
            'remaining_amount' => 0,
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/orders?status=pending&search=QR-MATCH&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('orders.retrieved_successfully'))
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $matchingOrder->id)
            ->assertJsonPath('data.data.0.doctor.name', 'Doctor One')
            ->assertJsonPath('data.data.0.tooth_shade_name', $shade->name)
            ->assertJsonPath('data.data.0.material_type', $type->name)
            ->assertJsonPath('data.data.0.teeth.0', 12)
            ->assertJsonPath('data.data.0.files.0.file_type', 'image/png');
    }

    public function test_receptionist_can_view_order_details(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'Detail Lab',
            'phone' => '5555555',
            'address' => 'Aleppo',
            'latitude' => 33.5104140,
            'longitude' => 36.2783360,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'accepted',
            'order_type' => 'hybrid',
            'notes' => 'Needs careful margin finishing.',
            'price' => 230,
            'remaining_amount' => 230,
        ]);

        OrderTooth::query()->create([
            'order_id' => $order->id,
            'tooth_number' => 11,
            'notes' => 'Anterior esthetic zone',
        ]);

        OrderFile::query()->create([
            'order_id' => $order->id,
            'file_path' => 'orders/case-11.png',
            'file_type' => 'image/png',
            'uploaded_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson("/api/auth/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('orders.details_retrieved_successfully'))
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.doctor.id', $doctor->id)
            ->assertJsonCount(1, 'data.order.teeth')
            ->assertJsonCount(1, 'data.order.files');
    }

    public function test_receptionist_can_mark_order_for_resubmission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $receptionist = $this->actingAsRole('receptionist');
        $doctor = User::factory()->create();

        $lab = Lab::query()->create([
            'name' => 'Resubmission Lab',
            'phone' => '8888888',
            'address' => 'Homs',
            'latitude' => 34.7318100,
            'longitude' => 36.7099460,
        ]);

        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'accepted',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 200,
            'remaining_amount' => 200,
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson("/api/auth/orders/{$order->id}/resubmission", [
            'reason' => 'Impression quality is insufficient; please resubmit with clearer margins.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('orders.resubmission_marked_successfully'))
            ->assertJsonPath('data.order.requires_resubmission', true)
            ->assertJsonPath('data.order.resubmission_reason', 'Impression quality is insufficient; please resubmit with clearer margins.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'requires_resubmission' => 1,
            'resubmission_requested_by' => $receptionist->id,
        ]);
    }

    public function test_receptionist_can_view_order_qr_image(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('public');

        $lab = Lab::query()->create([
            'name' => 'QR Lab',
            'phone' => '101010',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Front Desk',
            'description' => null,
            'is_management' => true,
        ]);

        $receptionist = $this->actingAsRole('receptionist');

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id'),
            'department_id' => $department->id,
        ]);

        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-IMAGE-001',
            'qr_image_path' => 'orders/QR-IMAGE-001/qr.png',
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 100,
            'remaining_amount' => 100,
        ]);

        Storage::disk('public')->put($order->qr_image_path, 'qr');

        Sanctum::actingAs($receptionist);

        $response = $this->getJson("/api/auth/orders/{$order->id}/qr-image");

        $response->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_receptionist_cannot_view_qr_image_for_other_lab(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('public');

        $lab = Lab::query()->create([
            'name' => 'Lab One',
            'phone' => '101011',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Front Desk',
            'description' => null,
            'is_management' => true,
        ]);

        $otherLab = Lab::query()->create([
            'name' => 'Lab Two',
            'phone' => '202022',
            'address' => 'Aleppo',
            'latitude' => 33.5104140,
            'longitude' => 36.2783360,
        ]);

        $receptionist = $this->actingAsRole('receptionist');

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id'),
            'department_id' => $department->id,
        ]);

        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'lab_id' => $otherLab->id,
            'qr_code' => 'QR-IMAGE-002',
            'qr_image_path' => 'orders/QR-IMAGE-002/qr.png',
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 100,
            'remaining_amount' => 100,
        ]);

        Storage::disk('public')->put($order->qr_image_path, 'qr');

        Sanctum::actingAs($receptionist);

        $response = $this->getJson("/api/auth/orders/{$order->id}/qr-image");

        $response->assertStatus(403)
            ->assertJsonPath('message', __('auth.forbidden'));
    }

    public function test_receptionist_gets_not_found_when_qr_image_missing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('public');

        $lab = Lab::query()->create([
            'name' => 'Missing QR Lab',
            'phone' => '909090',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Front Desk',
            'description' => null,
            'is_management' => true,
        ]);

        $receptionist = $this->actingAsRole('receptionist');

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id'),
            'department_id' => $department->id,
        ]);

        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'lab_id' => $lab->id,
            'qr_code' => 'QR-IMAGE-003',
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => null,
            'price' => 100,
            'remaining_amount' => 100,
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson("/api/auth/orders/{$order->id}/qr-image");

        $response->assertOk()
            ->assertHeader('content-type', 'image/png');

        $order = $order->refresh();

        $this->assertNotNull($order->qr_image_path);
        Storage::disk('public')->assertExists($order->qr_image_path);
    }

    public function test_non_receptionist_cannot_access_receptionist_order_routes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $doctor = $this->actingAsRole('doctor');

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/orders');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('message', __('auth.forbidden'));
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
}
