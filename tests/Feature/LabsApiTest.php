<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_labs_from_the_listing_endpoint(): void
    {
        Lab::query()->create([
            'name' => 'Alpha Lab',
            'phone' => '1111111',
            'address' => 'Damascus, Syria',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.50,
        ]);

        Lab::query()->create([
            'name' => 'Beta Lab',
            'phone' => '2222222',
            'address' => 'Aleppo, Syria',
            'latitude' => 36.2021040,
            'longitude' => 37.1342600,
            'rating' => 4.70,
        ]);

        $response = $this->getJson('/api/labs?per_page=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Labs retrieved successfully')
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_it_searches_labs_by_name_contains_with_pagination(): void
    {
        Lab::query()->create([
            'name' => 'Alpha Lab',
            'phone' => '1111111',
            'address' => 'Damascus, Syria',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.50,
        ]);

        Lab::query()->create([
            'name' => 'Beta Lab',
            'phone' => '2222222',
            'address' => 'Homs, Syria',
            'latitude' => 36.2021040,
            'longitude' => 37.1342600,
            'rating' => 4.70,
        ]);

        $response = $this->postJson('/api/labs/search', [
            'search' => 'pha',
            'per_page' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Labs search results retrieved successfully')
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Alpha Lab');
    }

    public function test_it_searches_labs_by_address_contains_with_pagination(): void
    {
        Lab::query()->create([
            'name' => 'Alpha Lab',
            'phone' => '1111111',
            'address' => 'Damascus Center',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 4.50,
        ]);

        Lab::query()->create([
            'name' => 'Beta Lab',
            'phone' => '2222222',
            'address' => 'Aleppo Center',
            'latitude' => 36.2021040,
            'longitude' => 37.1342600,
            'rating' => 4.70,
        ]);

        $response = $this->postJson('/api/labs/search', [
            'search' => 'Center',
            'per_page' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_it_does_not_limit_search_results_to_ten_records(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            Lab::query()->create([
                'name' => 'Gamma Lab '.$index,
                'phone' => '100000'.$index,
                'address' => 'Damascus Block '.$index,
                'latitude' => 33.5138070,
                'longitude' => 36.2765279,
                'rating' => 4.20,
            ]);
        }

        $response = $this->postJson('/api/labs/search', [
            'search' => 'Damascus',
            'per_page' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 12)
            ->assertJsonPath('data.per_page', 20)
            ->assertJsonCount(12, 'data.data');
    }

    public function test_it_rejects_empty_search_terms(): void
    {
        $response = $this->postJson('/api/labs/search', [
            'search' => '',
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_it_returns_top_rated_labs_in_descending_order(): void
    {
        Lab::query()->create([
            'name' => 'Low Rated Lab',
            'phone' => '1111111',
            'address' => 'Damascus, Syria',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 3.90,
        ]);

        Lab::query()->create([
            'name' => 'High Rated Lab',
            'phone' => '2222222',
            'address' => 'Aleppo, Syria',
            'latitude' => 36.2021040,
            'longitude' => 37.1342600,
            'rating' => 4.90,
        ]);

        $response = $this->getJson('/api/labs/top-rated');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Top rated labs retrieved successfully')
            ->assertJsonPath('data.data.0.name', 'High Rated Lab')
            ->assertJsonPath('data.data.1.name', 'Low Rated Lab');
    }

    public function test_it_returns_nearby_labs_based_on_doctor_location(): void
    {
        $doctor = User::factory()->create([
            'location_lat' => 33.5000000,
            'location_lng' => 36.2500000,
        ]);

        Lab::query()->create([
            'name' => 'Far Lab',
            'phone' => '1111111',
            'address' => 'Far Address',
            'latitude' => 34.5000000,
            'longitude' => 37.2500000,
            'rating' => 4.20,
        ]);

        Lab::query()->create([
            'name' => 'Near Lab',
            'phone' => '2222222',
            'address' => 'Near Address',
            'latitude' => 33.5100000,
            'longitude' => 36.2600000,
            'rating' => 4.10,
        ]);

        $response = $this->getJson('/api/labs/nearby?doctor_id='.$doctor->id.'&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Nearby labs retrieved successfully')
            ->assertJsonPath('data.data.0.name', 'Near Lab')
            ->assertJsonPath('data.data.1.name', 'Far Lab');
    }

    public function test_it_rejects_nearby_requests_when_doctor_has_no_location(): void
    {
        $doctor = User::factory()->create();

        $response = $this->getJson('/api/labs/nearby?doctor_id='.$doctor->id);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['doctor_id']);
    }

    public function test_it_returns_suggested_labs_randomly(): void
    {
        Lab::query()->create([
            'name' => 'Suggested Lab 1',
            'phone' => '1111111',
            'address' => 'Address 1',
            'latitude' => 33.5000000,
            'longitude' => 36.2500000,
            'rating' => 4.00,
        ]);

        Lab::query()->create([
            'name' => 'Suggested Lab 2',
            'phone' => '2222222',
            'address' => 'Address 2',
            'latitude' => 33.6000000,
            'longitude' => 36.3500000,
            'rating' => 4.10,
        ]);

        Lab::query()->create([
            'name' => 'Suggested Lab 3',
            'phone' => '3333333',
            'address' => 'Address 3',
            'latitude' => 33.7000000,
            'longitude' => 36.4500000,
            'rating' => 4.20,
        ]);

        $response = $this->getJson('/api/labs/suggested?per_page=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Suggested labs retrieved successfully')
            ->assertJsonCount(2, 'data.data');

        $returnedNames = collect($response->json('data.data'))->pluck('name');

        $this->assertTrue($returnedNames->every(fn (string $name): bool => in_array($name, [
            'Suggested Lab 1',
            'Suggested Lab 2',
            'Suggested Lab 3',
        ], true)));
    }

    public function test_it_returns_most_ordered_labs_in_descending_order(): void
    {
        $labOne = Lab::query()->create([
            'name' => 'Least Ordered Lab',
            'phone' => '1111111',
            'address' => 'Address 1',
            'latitude' => 33.5000000,
            'longitude' => 36.2500000,
            'rating' => 4.00,
        ]);

        $labTwo = Lab::query()->create([
            'name' => 'Most Ordered Lab',
            'phone' => '2222222',
            'address' => 'Address 2',
            'latitude' => 33.6000000,
            'longitude' => 36.3500000,
            'rating' => 4.50,
        ]);

        $labThree = Lab::query()->create([
            'name' => 'Mid Ordered Lab',
            'phone' => '3333333',
            'address' => 'Address 3',
            'latitude' => 33.7000000,
            'longitude' => 36.4500000,
            'rating' => 4.20,
        ]);

        $user = User::factory()->create();

        $this->createOrdersForLab($user, $labOne, 1);
        $this->createOrdersForLab($user, $labTwo, 3);
        $this->createOrdersForLab($user, $labThree, 2);

        $response = $this->getJson('/api/labs/most-ordered');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Most ordered labs retrieved successfully')
            ->assertJsonPath('data.data.0.name', 'Most Ordered Lab')
            ->assertJsonPath('data.data.1.name', 'Mid Ordered Lab')
            ->assertJsonPath('data.data.2.name', 'Least Ordered Lab');
    }

    public function test_it_returns_lab_details_with_basic_fields_only(): void
    {
        $lab = Lab::query()->create([
            'name' => 'Detail Lab',
            'phone' => '4444444',
            'address' => 'Detail Address',
            'latitude' => 33.8000000,
            'longitude' => 36.5500000,
            'rating' => 4.75,
        ]);

        $response = $this->getJson('/api/labs/'.$lab->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lab details retrieved successfully')
            ->assertJsonPath('data.id', $lab->id)
            ->assertJsonPath('data.name', 'Detail Lab')
            ->assertJsonPath('data.phone', '4444444')
            ->assertJsonPath('data.address', 'Detail Address')
            ->assertJsonPath('data.latitude', '33.8000000')
            ->assertJsonPath('data.longitude', '36.5500000')
            ->assertJsonPath('data.rating', '4.75');

        $response->assertJsonMissingPath('data.created_at');
        $response->assertJsonMissingPath('data.updated_at');
    }

    public function test_it_returns_404_for_missing_lab_details(): void
    {
        $response = $this->getJson('/api/labs/999999');

        $response->assertNotFound();
    }

    public function test_system_admin_can_create_lab_with_manager_account(): void
    {
        $this->authenticateAsRole('system_admin');

        $response = $this->postJson('/api/admin/labs', [
            'lab_name' => 'Admin Created Lab',
            'manager_name' => 'Lab Manager One',
            'phone' => '0500000000',
            'location' => 'Damascus Al-Mazzeh',
            'email' => 'manager1@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('data.lab.lab_name', 'Admin Created Lab')
            ->assertJsonPath('data.lab.location', 'Damascus Al-Mazzeh')
            ->assertJsonPath('data.manager.email', 'manager1@example.com');

        $this->assertDatabaseHas('labs', [
            'name' => 'Admin Created Lab',
            'phone' => '0500000000',
            'address' => 'Damascus Al-Mazzeh',
        ]);

        $manager = User::query()->where('email', 'manager1@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret12345', (string) $manager->password));
        $this->assertSame('Admin Created Lab', $manager->lab_name);
    }

    public function test_non_system_admin_cannot_create_admin_lab(): void
    {
        $this->authenticateAsRole('doctor');

        $response = $this->postJson('/api/admin/labs', [
            'lab_name' => 'Unauthorized Lab',
            'manager_name' => 'No Access',
            'phone' => '0500000000',
            'location' => 'Damascus',
            'email' => 'unauthorized@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertForbidden();
    }

    public function test_system_admin_can_update_lab_and_manager_information(): void
    {
        $this->authenticateAsRole('system_admin');

        $lab = Lab::query()->create([
            'name' => 'Old Lab Name',
            'phone' => '0111111111',
            'address' => 'Old Address',
            'latitude' => 33.5000000,
            'longitude' => 36.2000000,
            'rating' => 4.10,
        ]);

        $manager = User::query()->create([
            'name' => 'Old Manager',
            'email' => 'old.manager@example.com',
            'phone' => '0111111111',
            'lab_name' => 'Old Lab Name',
            'password' => 'secret12345',
        ]);

        $labManagerRole = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $manager->roles()->sync([$labManagerRole->id]);

        $response = $this->putJson('/api/admin/labs/'.$lab->id, [
            'lab_name' => 'Updated Lab Name',
            'manager_name' => 'Updated Manager',
            'phone' => '0999999999',
            'location' => 'Updated Address',
            'email' => 'updated.manager@example.com',
            'password' => 'newsecret12345',
            'password_confirmation' => 'newsecret12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.lab.lab_name', 'Updated Lab Name')
            ->assertJsonPath('data.lab.location', 'Updated Address')
            ->assertJsonPath('data.manager.name', 'Updated Manager')
            ->assertJsonPath('data.manager.email', 'updated.manager@example.com');

        $this->assertDatabaseHas('labs', [
            'id' => $lab->id,
            'name' => 'Updated Lab Name',
            'phone' => '0999999999',
            'address' => 'Updated Address',
        ]);

        $updatedManager = User::query()->where('email', 'updated.manager@example.com')->firstOrFail();
        $this->assertSame('Updated Lab Name', $updatedManager->lab_name);
        $this->assertTrue(Hash::check('newsecret12345', (string) $updatedManager->password));
    }

    public function test_system_admin_can_delete_lab(): void
    {
        $this->authenticateAsRole('system_admin');

        $lab = Lab::query()->create([
            'name' => 'Delete Me Lab',
            'phone' => '0222222222',
            'address' => 'Delete Address',
            'latitude' => 33.5000000,
            'longitude' => 36.2500000,
            'rating' => 3.90,
        ]);

        $response = $this->deleteJson('/api/admin/labs/'.$lab->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200);

        $this->assertDatabaseMissing('labs', [
            'id' => $lab->id,
        ]);
    }

    private function authenticateAsRole(string $roleName): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);
    }

    private function createOrdersForLab(User $user, Lab $lab, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            Order::query()->create([
                'user_id' => $user->id,
                'lab_id' => $lab->id,
                'qr_code' => (string) Str::uuid(),
                'priority' => 'normal',
                'status' => 'pending',
                'order_type' => 'digital',
                'notes' => null,
                'price' => 0,
                'remaining_amount' => 0,
            ]);
        }
    }
}
