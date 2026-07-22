<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_manager_can_view_doctors_with_orders(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctorA = $this->createDoctor('Doctor A');
        $doctorB = $this->createDoctor('Doctor B');

        $this->createOrder($lab, $doctorA, 'ORD-A-1', 'normal', 150);
        $this->createOrder($lab, $doctorA, 'ORD-A-2', 'bridge', 300);
        $this->createOrder($lab, $doctorB, 'ORD-B-1', 'implant', 200);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders');

        $response->assertOk();
        $doctors = $response->json('data');

        $byName = collect($doctors)->keyBy('name');
        $this->assertCount(2, $doctors);

        $this->assertEquals(2, $byName['Doctor A']['orders_count']);
        $this->assertEquals(1, $byName['Doctor B']['orders_count']);

        $firstOrder = $byName['Doctor A']['orders'][0];
        $this->assertArrayHasKey('serial_number', $firstOrder);
        $this->assertArrayHasKey('case_type', $firstOrder);
        $this->assertArrayHasKey('date', $firstOrder);
        $this->assertArrayHasKey('cost', $firstOrder);

        $serialNumbers = collect($byName['Doctor A']['orders'])->pluck('serial_number')->all();
        $this->assertContains('ORD-A-1', $serialNumbers);
        $this->assertContains('ORD-A-2', $serialNumbers);
    }

    public function test_search_filters_doctors(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctorA = $this->createDoctor('Doctor A');
        $doctorB = $this->createDoctor('Doctor B');
        $this->createOrder($lab, $doctorA, 'ORD-A-1', 'normal', 150);
        $this->createOrder($lab, $doctorB, 'ORD-B-1', 'implant', 200);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders?search='.urlencode('Doctor A'));

        $response->assertOk();
        $doctors = $response->json('data');
        $this->assertCount(1, $doctors);
        $this->assertEquals('Doctor A', $doctors[0]['name']);
    }

    public function test_can_view_single_doctor_orders_by_id(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctorA = $this->createDoctor('Doctor A');
        $doctorB = $this->createDoctor('Doctor B');

        $this->createOrder($lab, $doctorA, 'ORD-A-1', 'normal', 150);
        $this->createOrder($lab, $doctorB, 'ORD-B-1', 'implant', 200);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders/'.$doctorA->id);

        $response->assertOk();
        $doctor = $response->json('data');

        $this->assertEquals($doctorA->id, $doctor['doctor_id']);
        $this->assertEquals('Doctor A', $doctor['name']);
        $this->assertEquals(1, $doctor['orders_count']);
        $this->assertCount(1, $doctor['orders']);
        $this->assertEquals('ORD-A-1', $doctor['orders'][0]['serial_number']);
        $this->assertArrayHasKey('profile_image', $doctor);
    }

    public function test_single_doctor_orders_filter_paid(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctor = $this->createDoctor('Doctor A');

        $this->createOrder($lab, $doctor, 'ORD-A-1', 'normal', 150, 150);
        $this->createOrder($lab, $doctor, 'ORD-A-2', 'bridge', 300, 100);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders/'.$doctor->id.'?status=paid');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data['orders_count']);
        $this->assertCount(1, $data['orders']);
        $this->assertEquals('ORD-A-1', $data['orders'][0]['serial_number']);
        $this->assertEquals('0.00', $data['total_amount_due']);
    }

    public function test_single_doctor_orders_filter_unpaid(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctor = $this->createDoctor('Doctor A');

        $this->createOrder($lab, $doctor, 'ORD-A-1', 'normal', 150, 150);
        $this->createOrder($lab, $doctor, 'ORD-A-2', 'bridge', 300, 100);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders/'.$doctor->id.'?status=unpaid');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data['orders_count']);
        $this->assertCount(1, $data['orders']);
        $this->assertEquals('ORD-A-2', $data['orders'][0]['serial_number']);
        $this->assertEquals('200.00', $data['total_amount_due']);
    }

    public function test_single_doctor_orders_paginates(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        $doctor = $this->createDoctor('Doctor A');

        for ($i = 1; $i <= 3; $i++) {
            $this->createOrder($lab, $doctor, 'ORD-A-'.$i, 'normal', 100);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders/'.$doctor->id.'?per_page=2&page=1');

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $data['pagination'];

        $this->assertCount(2, $data['orders']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['per_page']);
        $this->assertEquals(3, $pagination['total']);
        $this->assertEquals(2, $pagination['last_page']);
        $this->assertEquals(3, $data['orders_count']);

        $page2 = $this->getJson('/api/auth/lab/doctors/orders/'.$doctor->id.'?per_page=2&page=2');
        $page2->assertOk();
        $this->assertCount(1, $page2->json('data.orders'));
        $this->assertEquals(2, $page2->json('data.pagination.current_page'));
    }

    public function test_single_doctor_orders_returns_404_for_unknown_doctor(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/doctors/orders/999999');

        $response->assertNotFound();
    }

    public function test_receptionist_can_view_doctors_with_orders(): void
    {
        [$manager, $lab] = $this->authenticateLabManager();
        $doctor = $this->createDoctor('Doctor Reception');
        $this->createOrder($lab, $doctor, 'ORD-R-1', 'normal', 150);

        $receptionist = $this->createReceptionist($lab);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson('/api/auth/lab/doctors/orders');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
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

    private function createOrder(Lab $lab, User $doctor, string $serial, string $caseType, float $price, float $paid = 0): Order
    {
        return Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'serial_number' => $serial,
            'qr_code' => $serial,
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'case_type' => $caseType,
            'price' => $price,
            'remaining_amount' => $price - $paid,
        ]);
    }
}
