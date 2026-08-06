<?php

namespace Tests\Unit\Services;

use App\Http\Services\StripeConnectService;
use App\Models\Lab;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StripeConnectServiceTest extends TestCase
{
    protected StripeConnectService $stripeConnectService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeConnectService = app(StripeConnectService::class);
    }

    /** @test */
    public function it_creates_connected_account_for_lab_successfully()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_success_123']);

        $lab = $this->createLab();

        $result = $this->stripeConnectService->createConnectedAccountForLab($lab);

        $this->assertTrue($result['success']);
        $this->assertEquals('acct_success_123', $result['stripe_account_id']);
        $this->assertEquals('Connected account created successfully', $result['message']);
        $this->assertEquals('acct_success_123', $lab->refresh()->stripe_account_id);
    }

    /** @test */
    public function it_returns_error_when_lab_already_has_connected_account()
    {
        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_existing',
        ]);

        $result = $this->stripeConnectService->createConnectedAccountForLab($lab);

        $this->assertFalse($result['success']);
        $this->assertEquals('Lab already has a connected account', $result['message']);
    }

    /** @test */
    public function it_fails_when_lab_information_incomplete()
    {
        $lab = Lab::factory()->create([
            'name' => '',
            'phone' => '',
            'address' => '',
        ]);

        $this->expectException(ValidationException::class);
        $this->stripeConnectService->createConnectedAccountForLab($lab);
    }

    /** @test */
    public function it_creates_account_link_successfully()
    {
        $this->mockStripeClient()
            ->shouldReceive('accountLinks->create')
            ->andReturn((object) ['url' => 'https://connect.stripe.com/test/abc123']);

        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);

        $result = $this->stripeConnectService->createAccountLink(
            $lab,
            'https://example.com/return',
            'https://example.com/refresh'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('https://connect.stripe.com/test/abc123', $result['url']);
    }

    /** @test */
    public function it_fails_when_creating_account_link_for_nonexistent_account()
    {
        $lab = Lab::factory()->create([
            'stripe_account_id' => null,
        ]);

        try {
            $this->stripeConnectService->createAccountLink(
                $lab,
                'https://example.com/return',
                'https://example.com/refresh'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Lab does not have a connected Stripe account',
                $e->errors()['stripe_account_id'][0]
            );
        }
    }

    /** @test */
    public function it_detects_account_is_not_onboarded()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->retrieve')
            ->andReturn((object) [
                'details_submitted' => false,
                'charges_enabled' => false,
                'transfers_enabled' => false,
            ]);

        $isOnboarded = $this->stripeConnectService->isAccountOnboarded('acct_test123');

        $this->assertFalse($isOnboarded);
    }

    /** @test */
    public function it_detects_account_is_fully_onboarded()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->retrieve')
            ->andReturn((object) [
                'details_submitted' => true,
                'charges_enabled' => true,
                'transfers_enabled' => true,
            ]);

        $isOnboarded = $this->stripeConnectService->isAccountOnboarded('acct_test123');

        $this->assertTrue($isOnboarded);
    }

    /** @test */
    public function it_says_account_cannot_process_payments_without_onboarding()
    {
        $this->mockStripeClient()
            ->shouldReceive('accounts->retrieve')
            ->andReturn((object) [
                'details_submitted' => false,
                'charges_enabled' => false,
                'transfers_enabled' => false,
            ]);

        $this->assertFalse($this->stripeConnectService->canProcessPayments('acct_test123'));
    }

    /** @test */
    public function it_says_account_cannot_process_payments_without_id()
    {
        $this->assertFalse($this->stripeConnectService->canProcessPayments(''));
    }

    /** @test */
    public function it_uses_test_mode_config()
    {
        Config::set('stripe-connect.connect.test_mode', true);

        $this->mockStripeClient()
            ->shouldReceive('accounts->create')
            ->andReturn((object) ['id' => 'acct_test123']);

        $lab = Lab::factory()->create([
            'name' => 'Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Test St',
        ]);

        $this->stripeConnectService->createConnectedAccountForLab($lab);

        $lab->refresh();
        $this->assertEquals('acct_test123', $lab->stripe_account_id);
    }

    /** @test */
    public function it_throws_exception_when_not_in_test_mode()
    {
        Config::set('stripe-connect.connect.test_mode', false);

        $lab = Lab::factory()->create([
            'name' => 'Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Test St',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe Connect is only configured for test mode.');

        $this->stripeConnectService->createConnectedAccountForLab($lab);
    }

    private function createLab(): Lab
    {
        return Lab::factory()->create([
            'name' => 'Test Lab',
            'phone' => '+1234567890',
            'address' => '123 Test St',
        ]);
    }

    private function mockStripeClient()
    {
        return $this->mock('\\Stripe\\StripeClient');
    }
}
