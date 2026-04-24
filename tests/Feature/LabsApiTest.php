<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Review;
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

    public function test_it_returns_top_rated_labs_using_weighted_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $lowConfidenceLab = Lab::query()->create([
            'name' => 'Low Rated Lab',
            'phone' => '1111111',
            'address' => 'Damascus, Syria',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'rating' => 5.00,
        ]);

        $highConfidenceLab = Lab::query()->create([
            'name' => 'High Rated Lab',
            'phone' => '2222222',
            'address' => 'Aleppo, Syria',
            'latitude' => 36.2021040,
            'longitude' => 37.1342600,
            'rating' => 4.00,
        ]);

        $this->createReviewsForLab($user, $lowConfidenceLab, 1, 5);
        $this->createReviewsForLab($user, $highConfidenceLab, 200, 4);

        $response = $this->getJson('/api/labs/top-rated');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Top rated labs retrieved successfully')
            ->assertJsonPath('data.0.name', 'High Rated Lab')
            ->assertJsonPath('data.1.name', 'Low Rated Lab')
            ->assertJsonPath('data.0.reviews_count', 200)
            ->assertJsonPath('data.1.reviews_count', 1);
    }

    public function test_top_rated_falls_back_to_rating_when_no_reviews_exist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Lab::query()->create([
            'name' => 'No Reviews High Rating',
            'phone' => '9991111',
            'address' => 'Address A',
            'latitude' => 33.6138070,
            'longitude' => 36.3765279,
            'rating' => 4.80,
        ]);

        Lab::query()->create([
            'name' => 'No Reviews Low Rating',
            'phone' => '9992222',
            'address' => 'Address B',
            'latitude' => 33.7138070,
            'longitude' => 36.4765279,
            'rating' => 4.20,
        ]);

        $response = $this->getJson('/api/labs/top-rated');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'No Reviews High Rating')
            ->assertJsonPath('data.1.name', 'No Reviews Low Rating')
            ->assertJsonPath('data.0.reviews_count', 0)
            ->assertJsonPath('data.1.reviews_count', 0);
    }

    public function test_it_returns_nearby_labs_based_on_doctor_location(): void
    {
        $doctor = User::factory()->create([
            'location_lat' => 33.5000000,
            'location_lng' => 36.2500000,
        ]);

        Sanctum::actingAs($doctor);

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

        $response = $this->getJson('/api/auth/labs/nearby');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Nearby labs retrieved successfully')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Near Lab');
    }

    public function test_it_rejects_nearby_requests_when_doctor_has_no_location(): void
    {
        $doctor = User::factory()->create();

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/labs/nearby');

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['doctor_id']);
    }

    public function test_it_returns_suggested_labs_randomly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

        $response = $this->getJson('/api/auth/labs/suggested');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Suggested labs retrieved successfully')
            ->assertJsonCount(3, 'data');

        $returnedNames = collect($response->json('data'))->pluck('name');

        $this->assertTrue($returnedNames->every(fn (string $name): bool => in_array($name, [
            'Suggested Lab 1',
            'Suggested Lab 2',
            'Suggested Lab 3',
        ], true)));
    }

    public function test_it_returns_most_ordered_labs_in_descending_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

        $this->createOrdersForLab($user, $labOne, 1);
        $this->createOrdersForLab($user, $labTwo, 3);
        $this->createOrdersForLab($user, $labThree, 2);

        $response = $this->getJson('/api/auth/labs/most-ordered');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Most ordered labs retrieved successfully')
            ->assertJsonPath('data.0.name', 'Most Ordered Lab')
            ->assertJsonPath('data.1.name', 'Mid Ordered Lab')
            ->assertJsonPath('data.2.name', 'Least Ordered Lab');
    }

    public function test_top_rated_returns_only_four_labs_when_context_home(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($index = 1; $index <= 6; $index++) {
            Lab::query()->create([
                'name' => 'Top Lab '.$index,
                'phone' => '100000'.$index,
                'address' => 'Address '.$index,
                'latitude' => 33.5000000 + ($index * 0.00001),
                'longitude' => 36.2500000 + ($index * 0.00001),
                'rating' => 4.00 + ($index * 0.10),
            ]);
        }

        $response = $this->getJson('/api/auth/labs/top-rated?context=home');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_nearby_returns_only_four_labs_when_context_home(): void
    {
        $doctor = User::factory()->create([
            'location_lat' => 33.5000000,
            'location_lng' => 36.2500000,
        ]);

        Sanctum::actingAs($doctor);

        for ($index = 1; $index <= 6; $index++) {
            Lab::query()->create([
                'name' => 'Near Home Lab '.$index,
                'phone' => '200000'.$index,
                'address' => 'Address '.$index,
                'latitude' => 33.5000000 + ($index * 0.001),
                'longitude' => 36.2500000 + ($index * 0.001),
                'rating' => 4.00,
            ]);
        }

        $response = $this->getJson('/api/auth/labs/nearby?context=home');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_suggested_returns_only_four_labs_when_context_home(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($index = 1; $index <= 6; $index++) {
            Lab::query()->create([
                'name' => 'Suggested Home Lab '.$index,
                'phone' => '300000'.$index,
                'address' => 'Address '.$index,
                'latitude' => 33.6000000 + ($index * 0.00001),
                'longitude' => 36.3500000 + ($index * 0.00001),
                'rating' => 4.00,
            ]);
        }

        $response = $this->getJson('/api/auth/labs/suggested?context=home');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_most_ordered_returns_only_four_labs_when_context_home(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $orderingUser = User::factory()->create();

        for ($index = 1; $index <= 6; $index++) {
            $lab = Lab::query()->create([
                'name' => 'Most Ordered Home Lab '.$index,
                'phone' => '400000'.$index,
                'address' => 'Address '.$index,
                'latitude' => 33.7000000 + ($index * 0.00001),
                'longitude' => 36.4500000 + ($index * 0.00001),
                'rating' => 4.00,
            ]);

            $this->createOrdersForLab($orderingUser, $lab, $index);
        }

        $response = $this->getJson('/api/auth/labs/most-ordered?context=home');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
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
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
            'email' => 'manager1@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('data.lab.lab_name', 'Admin Created Lab')
            ->assertJsonPath('data.lab.location', 'Damascus Al-Mazzeh')
            ->assertJsonPath('data.lab.latitude', '33.5138070')
            ->assertJsonPath('data.lab.longitude', '36.2765279')
            ->assertJsonPath('data.manager.email', 'manager1@example.com');

        $createdLab = Lab::query()->where('name', 'Admin Created Lab')->firstOrFail();
        $this->assertNotNull($createdLab->license_number);
        $this->assertMatchesRegularExpression('/^LAB-\\d{8}-\\d{6}$/', (string) $createdLab->license_number);

        $response->assertJsonPath('data.lab.license_number', $createdLab->license_number);

        $this->assertDatabaseHas('labs', [
            'name' => 'Admin Created Lab',
            'phone' => '0500000000',
            'address' => 'Damascus Al-Mazzeh',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $manager = User::query()->where('email', 'manager1@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret12345', (string) $manager->password));
        $this->assertSame($createdLab->id, $manager->lab_id);

        $managementDepartment = Department::query()
            ->where('lab_id', $createdLab->id)
            ->where('is_management', true)
            ->firstOrFail();

        $this->assertDatabaseHas('department_user_roles', [
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail()->id,
            'department_id' => $managementDepartment->id,
        ]);
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
            'lab_id' => $lab->id,
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
            'latitude' => 35.1200000,
            'longitude' => 37.3300000,
            'email' => 'updated.manager@example.com',
            'password' => 'newsecret12345',
            'password_confirmation' => 'newsecret12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.lab.lab_name', 'Updated Lab Name')
            ->assertJsonPath('data.lab.location', 'Updated Address')
            ->assertJsonPath('data.lab.latitude', '35.1200000')
            ->assertJsonPath('data.lab.longitude', '37.3300000')
            ->assertJsonPath('data.manager.name', 'Updated Manager')
            ->assertJsonPath('data.manager.email', 'updated.manager@example.com');

        $this->assertDatabaseHas('labs', [
            'id' => $lab->id,
            'name' => 'Updated Lab Name',
            'phone' => '0999999999',
            'address' => 'Updated Address',
            'latitude' => 35.1200000,
            'longitude' => 37.3300000,
        ]);

        $updatedManager = User::query()->where('email', 'updated.manager@example.com')->firstOrFail();
        $this->assertSame($lab->id, $updatedManager->lab_id);
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

    private function createReviewsForLab(User $user, Lab $lab, int $count, int $rating): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $order = Order::query()->create([
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

            Review::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'rating' => $rating,
                'comment' => null,
            ]);
        }
    }
}
