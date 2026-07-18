<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorOrdersPaymentStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createDoctorWithLab(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->syncWithoutDetaching([$role->id]);

        return ['lab' => $lab, 'doctor' => $doctor];
    }

    private function createOrder(User $doctor, Lab $lab, string $status = 'completed', float $price = 150.00): Order
    {
        return Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => $status,
            'order_type' => 'digital',
            'case_type' => 'normal',
            'price' => $price,
            'remaining_amount' => $price,
            'serial_number' => null,
        ]);
    }

    private function createPayment(User $doctor, Order $order, float $amount): void
    {
        $payment = Payment::query()->create([
            'user_id' => $doctor->id,
            'amount' => $amount,
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        DB::table('payment_order')->insert([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'amount' => $amount,
        ]);
    }

    public function test_filter_paid_returns_only_paid_orders(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        $paidOrder = $this->createOrder($doctor, $lab, 'completed', 200.00);
        $unpaidOrder = $this->createOrder($doctor, $lab, 'completed', 300.00);

        $this->createPayment($doctor, $paidOrder, 200.00);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status?status=paid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paidOrder->id);
    }

    public function test_filter_unpaid_returns_only_unpaid_orders(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        $paidOrder = $this->createOrder($doctor, $lab, 'completed', 200.00);
        $unpaidOrder = $this->createOrder($doctor, $lab, 'completed', 300.00);

        $this->createPayment($doctor, $paidOrder, 200.00);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status?status=unpaid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unpaidOrder->id);
    }

    public function test_no_filter_returns_all_orders(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        $this->createOrder($doctor, $lab, 'completed', 200.00);
        $this->createOrder($doctor, $lab, 'completed', 300.00);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_response_contains_required_fields(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        $order = $this->createOrder($doctor, $lab, 'completed', 150.00);
        $this->createPayment($doctor, $order, 150.00);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status?status=paid');

        $response->assertOk();

        $item = $response->json('data')[0];

        $this->assertEquals($order->id, $item['id']);
        $this->assertEquals($order->serial_number, $item['serial_number']);
        $this->assertEquals('150.00', $item['price']);
        $this->assertEquals('Test Lab', $item['lab_name']);
        $this->assertArrayHasKey('order_date', $item);
    }

    public function test_doctor_with_no_orders_returns_empty_array(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/auth/doctor/orders/payment-status');

        $response->assertUnauthorized();
    }

    public function test_non_doctor_cannot_access(): void
    {
        $this->seedRoles();

        $receptionist = User::factory()->create();
        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();
        $receptionist->roles()->syncWithoutDetaching([$role->id]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status');

        $response->assertForbidden();
    }

    public function test_only_own_orders_are_returned(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'doctor' => $doctor] = $this->createDoctorWithLab();

        $otherDoctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $otherDoctor->roles()->syncWithoutDetaching([$role->id]);

        $this->createOrder($doctor, $lab, 'completed', 100.00);
        $this->createOrder($otherDoctor, $lab, 'completed', 200.00);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/doctor/orders/payment-status');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
