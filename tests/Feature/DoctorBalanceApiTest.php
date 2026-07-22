<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_manager_can_view_doctor_balances(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctorA = $this->createDoctor('Doctor A');
        $doctorB = $this->createDoctor('Doctor B');
        $this->createDoctor('Doctor No Orders');

        $this->createOrder($lab, $doctorA, 500, 200);
        $this->createOrder($lab, $doctorA, 300, 100);
        $this->createOrder($lab, $doctorB, 150, 0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/balances');

        $response->assertOk();

        $doctors = $response->json('data.doctors');
        $this->assertCount(2, $doctors);

        $byName = collect($doctors)->keyBy('name');

        $this->assertEquals(2, $byName['Doctor A']['orders_count']);
        $this->assertEquals(800, $byName['Doctor A']['total_billed']);
        $this->assertEquals(300, $byName['Doctor A']['total_paid']);
        $this->assertEquals(500, $byName['Doctor A']['total_owed']);

        $this->assertEquals(1, $byName['Doctor B']['orders_count']);
        $this->assertEquals(150, $byName['Doctor B']['total_billed']);
        $this->assertEquals(0, $byName['Doctor B']['total_paid']);
        $this->assertEquals(150, $byName['Doctor B']['total_owed']);

        $totals = $response->json('data.totals');
        $this->assertEquals(950, $totals['total_billed']);
        $this->assertEquals(300, $totals['total_paid']);
        $this->assertEquals(650, $totals['total_owed']);
        $this->assertEquals(31.58, $totals['repayment_percentage']);
    }

    public function test_search_filters_doctors(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctorA = $this->createDoctor('Doctor A');
        $doctorB = $this->createDoctor('Doctor B');

        $this->createOrder($lab, $doctorA, 500, 200);
        $this->createOrder($lab, $doctorB, 150, 0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/balances?search='.urlencode('Doctor A'));

        $response->assertOk();
        $doctors = $response->json('data.doctors');
        $this->assertCount(1, $doctors);
        $this->assertEquals('Doctor A', $doctors[0]['name']);
    }

    public function test_balances_paginates(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        for ($i = 1; $i <= 3; $i++) {
            $doctor = $this->createDoctor('Doctor '.$i);
            $this->createOrder($lab, $doctor, 100, 0);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/balances?per_page=2&page=1');

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $data['pagination'];

        $this->assertCount(2, $data['doctors']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['per_page']);
        $this->assertEquals(3, $pagination['total']);
        $this->assertEquals(2, $pagination['last_page']);

        $page2 = $this->getJson('/api/auth/lab/doctors/balances?per_page=2&page=2');
        $page2->assertOk();
        $this->assertCount(1, $page2->json('data.doctors'));
        $this->assertEquals(2, $page2->json('data.pagination.current_page'));
    }

    public function test_receptionist_can_view_doctor_balances(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctor = $this->createDoctor('Doctor Receptionist');
        $this->createOrder($lab, $doctor, 500, 200);

        $receptionist = $this->createReceptionist($lab);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/lab/doctors/balances');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.doctors'));
    }

    public function test_ignores_doctors_from_other_labs(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $otherLab = Lab::query()->create([
            'name' => 'Other Lab',
            'phone' => '2222',
            'address' => 'Aleppo',
            'latitude' => 36.2,
            'longitude' => 37.1,
        ]);

        $doctor = $this->createDoctor('Cross Doctor');
        $this->createOrder($otherLab, $doctor, 999, 0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/balances');

        $response->assertOk();
        $this->assertCount(0, $response->json('data.doctors'));
    }

    private function authenticateLabManager(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Manager Lab',
            'phone' => '0111111111',
            'address' => 'Damascus',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.20,
        ]);

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management Dept',
            'is_management' => false,
            'sort_order' => 1,
        ]);

        $manager = User::factory()->create(['email' => 'lab.manager@example.com']);
        $role = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        return [$manager, $lab];
    }

    private function createDoctor(string $name): User
    {
        $doctor = User::factory()->create(['name' => $name]);
        $roleId = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->value('id');
        $doctor->roles()->syncWithoutDetaching([$roleId]);

        return $doctor;
    }

    private function createReceptionist(Lab $lab): User
    {
        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Reception Dept',
            'is_management' => false,
            'sort_order' => 2,
        ]);

        $receptionist = User::factory()->create(['name' => 'Receptionist']);
        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $receptionist->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);

        return $receptionist;
    }

    private function createOrder(Lab $lab, User $doctor, float $price, float $paid): Order
    {
        $remaining = $price - $paid;
        $order = Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'serial_number' => 'ORD-'.$doctor->id.'-'.$price,
            'qr_code' => 'ORD-'.$doctor->id.'-'.$price,
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'price' => $price,
            'remaining_amount' => $remaining,
        ]);

        if ($paid > 0) {
            $payment = Payment::query()->create([
                'user_id' => $doctor->id,
                'amount' => $paid,
                'payment_method' => 'cash',
                'paid_at' => now(),
            ]);

            $order->payments()->attach($payment->id, ['amount' => $paid]);
        }

        return $order;
    }
}
