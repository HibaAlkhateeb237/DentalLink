<?php

namespace Tests\Unit\Services;

use App\Http\Services\StripePaymentService;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StripePaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(StripePaymentService::class);
    }

    /** @test */
    public function it_creates_checkout_session_with_valid_order()
    {
        $this->mockStripeClient()
            ->shouldReceive('checkout->sessions->create')
            ->andReturn((object) [
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/test',
                'payment_intent' => 'pi_test_456',
            ]);

        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);

        $doctor = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'price' => 150.00,
            'status' => 'pending',
        ]);

        $result = $this->paymentService->createCheckoutSession($order, $doctor);

        $this->assertEquals('cs_test_123', $result['id']);
        $this->assertEquals('https://checkout.stripe.com/test', $result['url']);
        $this->assertEquals('pi_test_456', $result['payment_intent_id']);
    }

    /** @test */
    public function it_throws_exception_when_lab_not_connected()
    {
        $this->expectException(\Throwable::class);

        $lab = Lab::factory()->create([
            'stripe_account_id' => null,
        ]);

        $doctor = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'price' => 150.00,
            'status' => 'pending',
        ]);

        $this->paymentService->createCheckoutSession($order, $doctor);
    }

    /** @test */
    public function it_throws_exception_when_lab_not_onboarded()
    {
        $this->expectException(\Throwable::class);

        $this->mockStripeClient()
            ->shouldReceive('accounts->retrieve')
            ->andReturn((object) [
                'details_submitted' => false,
                'charges_enabled' => false,
                'transfers_enabled' => false,
            ]);

        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);

        $doctor = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'price' => 150.00,
            'status' => 'pending',
        ]);

        $this->paymentService->createCheckoutSession($order, $doctor);
    }

    /** @test */
    public function it_throws_exception_when_order_already_paid()
    {
        $this->expectException(\Throwable::class);

        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);

        $this->mockStripeClient()
            ->shouldReceive('accounts->retrieve')
            ->andReturn((object) [
                'details_submitted' => true,
                'charges_enabled' => true,
                'transfers_enabled' => true,
            ]);

        $doctor = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'price' => 150.00,
            'status' => 'paid',
        ]);

        $this->paymentService->createCheckoutSession($order, $doctor);
    }

    /** @test */
    public function it_handles_payment_intent_event()
    {
        $paymentIntentId = 'pi_test123';
        $payment = Payment::factory()->create([
            'id' => $paymentIntentId,
            'payment_status' => 'requires_payment_method',
        ]);

        $order = Order::factory()->create([
            'status' => 'new',
        ]);

        $payment->orders()->attach($order->id, ['amount' => 100.00]);

        $payload = [
            'id' => $paymentIntentId,
            'status' => 'succeeded',
        ];

        $this->paymentService->handlePaymentIntentEvent($payload);

        $payment->refresh();
        $this->assertEquals('succeeded', $payment->payment_status);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function it_handles_charge_event()
    {
        $paymentIntentId = 'pi_test456';
        $chargeId = 'ch_test789';
        $balanceTransactionId = 'txn_test123';

        $payment = Payment::factory()->create([
            'id' => $paymentIntentId,
            'payment_status' => 'pending',
        ]);

        $order = Order::factory()->create([
            'status' => 'new',
        ]);

        $payment->orders()->attach($order->id, ['amount' => 100.00]);

        $payload = [
            'id' => $chargeId,
            'payment_intent' => $paymentIntentId,
            'amount' => 15000,
            'currency' => 'usd',
            'status' => 'succeeded',
            'balance_transaction' => $balanceTransactionId,
        ];

        $this->paymentService->handleChargeEvent($payload);

        $payment->refresh();
        $this->assertEquals('succeeded', $payment->payment_status);
        $this->assertEquals(150.00, $payment->amount);
        $this->assertEquals('USD', $payment->currency);
        $this->assertEquals($chargeId, $payment->charge_id);
        $this->assertEquals('stripe', $payment->provider);
        $this->assertEquals($balanceTransactionId, $payment->provider_reference);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function it_skips_payment_if_payment_not_found_for_order()
    {
        $order = Order::factory()->create([
            'status' => 'new',
        ]);

        $canProcess = $this->paymentService->processPaymentForOrder($order);

        $this->assertFalse($canProcess);
    }

    /** @test */
    public function it_processes_payment_for_order_successfully()
    {
        $lab = Lab::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);

        $doctor = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $doctor->id,
            'paid_at' => now()->subHour(),
        ]);

        $payment->orders()->attach($order->id, ['amount' => $order->price]);

        $canProcess = $this->paymentService->processPaymentForOrder($order);

        $this->assertTrue($canProcess);
    }

    /** @test */
    public function it_handles_payment_event_correctly()
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => ['id' => 'pi_test123', 'status' => 'succeeded'],
            ],
        ];

        $this->paymentService->handlePaymentEvent($payload);

        $payment = Payment::where('id', 'pi_test123')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('succeeded', $payment->payment_status);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function it_logs_unhandled_events()
    {
        $payload = [
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => ['id' => 'inv_test123', 'customer' => 'cus_test'],
            ],
        ];

        $this->paymentService->handlePaymentEvent($payload);

        // Should log without crashing
    }

    private function mockStripeClient()
    {
        $this->mock(
            '\Stripe\StripeClient',
            function (Mockery\MockInterface $mock) {
                return $mock;
            }
        );

        return $this->mock('\Stripe\StripeClient');
    }
}
