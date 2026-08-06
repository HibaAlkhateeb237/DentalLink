<?php

namespace Tests\Feature;

use App\Http\Services\StripeConnectService;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabStripeAccountLinkApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function labManager(): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createLab(array $attributes = []): Lab
    {
        return Lab::create(array_merge([
            'name' => 'Test Lab',
            'description' => 'Test Description',
            'license_number' => 'LAB-'.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
            'stripe_account_id' => 'acct_test123',
        ], $attributes));
    }

    private function createDepartment(int $labId): Department
    {
        return Department::create([
            'lab_id' => $labId,
            'name' => 'Test Department',
            'sort_order' => 1,
            'is_management' => false,
        ]);
    }

    private function createDepartmentUserRole(int $userId, int $departmentId): void
    {
        $roleId = Role::where('name', 'lab_manager')->where('guard_name', 'sanctum')->value('id');
        DepartmentUserRole::create([
            'user_id' => $userId,
            'department_id' => $departmentId,
            'role_id' => $roleId,
        ]);
    }

    public function test_lab_manager_can_get_account_link_for_own_lab(): void
    {
        $this->mock(StripeConnectService::class)
            ->shouldReceive('createAccountLink')
            ->once()
            ->withArgs(function (Lab $lab, string $returnUrl, string $refreshUrl) {
                return $lab->stripe_account_id === 'acct_test123'
                    && filter_var($returnUrl, FILTER_VALIDATE_URL)
                    && filter_var($refreshUrl, FILTER_VALIDATE_URL);
            })
            ->andReturn(['success' => true, 'url' => 'https://connect.stripe.com/test/abc123']);

        $manager = $this->labManager();
        $lab = $this->createLab();

        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/lab/stripe/account-link')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/test/abc123');
    }

    public function test_account_link_passes_query_return_and_refresh_urls(): void
    {
        $this->mock(StripeConnectService::class)
            ->shouldReceive('createAccountLink')
            ->once()
            ->withArgs(fn (Lab $lab, string $returnUrl, string $refreshUrl) => $returnUrl === 'https://app.example.com/lab'
                && $refreshUrl === 'https://app.example.com/lab?refresh=1')
            ->andReturn(['success' => true, 'url' => 'https://connect.stripe.com/test/abc123']);

        $manager = $this->labManager();
        $lab = $this->createLab();

        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/lab/stripe/account-link?return_url=https%3A%2F%2Fapp.example.com%2Flab&refresh_url=https%3A%2F%2Fapp.example.com%2Flab%3Frefresh%3D1')
            ->assertOk();
    }

    public function test_lab_manager_without_lab_gets_404(): void
    {
        $this->mock(StripeConnectService::class)
            ->shouldReceive('createAccountLink')
            ->never();

        $manager = $this->labManager();

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/lab/stripe/account-link')
            ->assertNotFound();
    }

    public function test_lab_without_connected_stripe_account_returns_400(): void
    {
        $manager = $this->labManager();
        $lab = $this->createLab(['stripe_account_id' => null]);

        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/lab/stripe/account-link')
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.stripe_account_id.0', 'Lab does not have a connected Stripe account');
    }

    public function test_account_link_service_error_returns_400(): void
    {
        $this->mock(StripeConnectService::class)
            ->shouldReceive('createAccountLink')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Lab is already fully onboarded']);

        $manager = $this->labManager();
        $lab = $this->createLab();

        $dept = $this->createDepartment($lab->id);
        $this->createDepartmentUserRole($manager->id, $dept->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/auth/lab/stripe/account-link')
            ->assertStatus(400)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lab is already fully onboarded');
    }

    public function test_non_lab_manager_cannot_access_account_link(): void
    {
        $this->mock(StripeConnectService::class)
            ->shouldReceive('createAccountLink')
            ->never();

        $user = User::factory()->create();
        $role = Role::where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/lab/stripe/account-link')
            ->assertForbidden();
    }
}
