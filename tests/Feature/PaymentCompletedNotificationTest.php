<?php

namespace Tests\Feature;

use App\Http\Services\StripePaymentService;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Payment\PaymentCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createDoctorWithOrderAndPayment(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $doctor = User::factory()->create();

        $order = Order::query()->create([
            'lab_id' => $lab->id,
            'user_id' => $doctor->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'completed',
            'order_type' => 'digital',
            'case_type' => 'normal',
            'price' => 200.00,
            'remaining_amount' => 200.00,
            'serial_number' => null,
        ]);

        $payment = Payment::query()->create([
            'user_id' => $doctor->id,
            'amount' => 200.00,
            'payment_method' => 'card',
            'payment_intent_id' => 'pi_test_notif_123',
            'payment_status' => 'requires_payment_method',
        ]);

        $payment->orders()->attach($order->id, ['amount' => 200.00]);

        return ['lab' => $lab, 'doctor' => $doctor, 'order' => $order, 'payment' => $payment];
    }

    public function test_doctor_receives_notification_on_payment_intent_succeeded(): void
    {
        Notification::fake();

        ['doctor' => $doctor, 'order' => $order] = $this->createDoctorWithOrderAndPayment();

        $service = app(StripePaymentService::class);

        $service->handlePaymentEvent([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_notif_123',
                    'status' => 'succeeded',
                ],
            ],
        ]);

        Notification::assertSentTo($doctor, PaymentCompleted::class);
    }

    public function test_notification_contains_correct_order_data(): void
    {
        Notification::fake();

        ['doctor' => $doctor, 'order' => $order] = $this->createDoctorWithOrderAndPayment();

        $service = app(StripePaymentService::class);

        $service->handlePaymentEvent([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_notif_123',
                    'status' => 'succeeded',
                ],
            ],
        ]);

        Notification::assertSentTo($doctor, PaymentCompleted::class, function (PaymentCompleted $notification) use ($order): bool {
            return $notification->order->id === $order->id;
        });
    }

    public function test_no_notification_sent_on_payment_failure(): void
    {
        Notification::fake();

        ['doctor' => $doctor] = $this->createDoctorWithOrderAndPayment();

        $service = app(StripePaymentService::class);

        $service->handlePaymentEvent([
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_notif_123',
                    'status' => 'failed',
                ],
            ],
        ]);

        Notification::assertNotSentTo($doctor, PaymentCompleted::class);
    }

    public function test_no_duplicate_notification_on_already_paid_order(): void
    {
        Notification::fake();

        ['doctor' => $doctor, 'payment' => $payment] = $this->createDoctorWithOrderAndPayment();

        $payment->update(['paid_at' => now()]);

        $service = app(StripePaymentService::class);

        $service->handlePaymentEvent([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_notif_123',
                    'status' => 'succeeded',
                ],
            ],
        ]);

        Notification::assertNotSentTo($doctor, PaymentCompleted::class);
    }

    public function test_notification_sent_for_checkout_session_paid(): void
    {
        Notification::fake();

        ['doctor' => $doctor, 'order' => $order, 'payment' => $payment] = $this->createDoctorWithOrderAndPayment();

        $payment->update(['checkout_session_id' => 'cs_test_notif_456']);

        $service = app(StripePaymentService::class);

        $service->handlePaymentEvent([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_notif_456',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_notif_123',
                ],
            ],
        ]);

        Notification::assertSentTo($doctor, PaymentCompleted::class);
    }
}
