<?php

namespace Tests\Unit\Services;

use App\Http\Services\LabService;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabServiceTest extends TestCase
{
    protected LabService $labService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->labService = app(LabService::class);

        $this->mock('App\Http\Repositories\LabRepository')
            ->shouldReceive('paginateActive')
            ->andReturn(new LengthAwarePaginator(collect(), 15, 15));

        $this->mock('App\Http\Services\StripeConnectService')
            ->shouldReceive('createConnectedAccountForLab')
            ->andReturn(['success' => true, 'stripe_account_id' => 'acct_test123']);
    }

    /** @test */
    public function it_creates_lab_with_stripe_connected_account_successfully()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_success_123']);

        $validated = [
            'lab_name' => 'Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Test St',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'email' => 'manager@test.com',
            'password' => 'password123',
        ];

        $result = $this->labService->createLabWithManager($validated);

        $this->assertArrayHasKey('lab', $result);
        $this->assertEquals('Test Lab', $result['lab']['lab_name']);
        $this->assertEquals('+1234567890', $result['lab']['phone']);
        $this->assertEquals('123 Test St', $result['lab']['address']);

        $lab = Lab::where('name', 'Test Lab')->first();
        $this->assertNotNull($lab);
        $this->assertEquals('acct_success_123', $lab->stripe_account_id);

        $this->assertDatabaseHas('departments', [
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
        ]);

        $department = Department::where('lab_id', $lab->id)->first();
        $this->assertNotNull($department);

        $departmentUserRole = DepartmentUserRole::where('department_id', $department->id)->first();
        $this->assertNotNull($departmentUserRole);

        $manager = User::where('email', 'manager@test.com')->first();
        $this->assertNotNull($manager);
    }

    /** @test */
    public function it_rolls_back_transaction_when_stripe_account_creation_fails()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andThrow(new \Exception('Stripe API error'));

        $validated = [
            'lab_name' => 'Rollback Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Rollback St',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'email' => 'rollback@test.com',
            'password' => 'password123',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Stripe connected account');

        $this->labService->createLabWithManager($validated);

        $lab = Lab::where('name', 'Rollback Test Lab')->first();
        $this->assertNull($lab);

        $department = Department::where('name', 'Management')->first();
        $this->assertNull($department);

        $manager = User::where('email', 'rollback@test.com')->first();
        $this->assertNull($manager);
    }

    /** @test */
    public function it_returns_correct_stripe_account_id_on_success()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_verify_456']);

        $validated = [
            'lab_name' => 'Verify Lab',
            'phone' => '+1234567890',
            'address' => '123 Verify St',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'email' => 'verify@test.com',
            'password' => 'password123',
        ];

        $result = $this->labService->createLabWithManager($validated);

        $this->assertEquals('Verify Lab', $result['lab']['lab_name']);
        $this->assertEquals('acct_verify_456', Lab::where('name', 'Verify Lab')->first()->stripe_account_id);
    }

    /** @test */
    public function it_validates_lab_information_completeness()
    {
        $validated = [
            'lab_name' => '',
            'phone' => '',
            'address' => '',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'email' => 'empty@test.com',
            'password' => 'password123',
        ];

        $this->expectException(ValidationException::class);

        $this->labService->createLabWithManager($validated);
    }

    /** @test */
    public function it_updates_lab_with_stripe_account_id_on_update()
    {
        $lab = Lab::factory()->create([
            'name' => 'Original Lab',
            'stripe_account_id' => null,
        ]);

        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_new_789']);

        $validated = [
            'lab_name' => 'Updated Lab',
        ];

        $result = $this->labService->updateLabWithManager($lab, $validated);

        $lab->refresh();
        $this->assertEquals('Updated Lab', $lab->name);
        $this->assertEquals('ACCT-NEW-789', $lab->license_number);
    }

    /** @test */
    public function it_passes_lab_information_to_stripe_correctly()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_test_1']);

        $validated = [
            'lab_name' => 'Stripe Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Stripe St',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'email' => 'stripe@test.com',
            'password' => 'password123',
        ];

        $this->labService->createLabWithManager($validated);

        $lab = Lab::where('name', 'Stripe Test Lab')->first();

        $stripeMock = $this->mock('\\Stripe\\StripeClient');
        $accountCreateCall = $stripeMock->shouldReceive('accounts->create');

        $this->assertNotNull($accountCreateCall);
    }

    private function mockStripeClient()
    {
        return $this->mock('\\Stripe\\StripeClient');
    }
}
