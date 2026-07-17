<?php

namespace App\Http\Services;

use App\Http\Controllers\Exception\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripePaymentService
{
    public function __construct(
        private StripeConnectService $connectService,
    ) {}

    public function createCheckoutSession(Order $order, User $doctor): array
    {
        $this->validateOrderForPayment($order, $doctor);

        $stripeAccountId = $order->lab->stripe_account_id;
        if (empty($stripeAccountId)) {
            throw PaymentException::labNotConnected();
        }

        $isOnboarded = $this->connectService->isAccountOnboarded($stripeAccountId);
        if (! $isOnboarded) {
            throw PaymentException::labNotOnboarded();
        }

        $session = $this->createStripeCheckoutSession($order, $doctor, $stripeAccountId);

        DB::transaction(function () use ($order, $doctor, $session): void {
            $this->createOrUpdatePayment($order, $doctor, $session);
        });

        return [
            'id' => $session->id,
            'url' => $session->url ?? null,
            'payment_intent_id' => $session->payment_intent,
        ];
    }

    public function handlePaymentEvent(array $payload): void
    {
        $eventType = data_get($payload, 'type');
        $data = data_get($payload, 'data.object');

        match (true) {
            str_starts_with($eventType, 'payment_intent.') => $this->handlePaymentIntentEvent($data),
            str_starts_with($eventType, 'charge.') => $this->handleChargeEvent($data),
            str_starts_with($eventType, 'checkout.session.') => $this->handleCheckoutSessionEvent($data),
            default => $this->logUnhandledEvent($eventType, $data),
        };
    }

    public function processPaymentForOrder(Order $order): bool
    {
        $payment = $this->getMostRecentPaymentForOrder($order);

        if (! $payment || ! $payment->paid_at) {
            return false;
        }

        return $payment->paid_at->isNotFuture();
    }

    private function validateOrderForPayment(Order $order, User $doctor): void
    {
        if ($order->user_id !== $doctor->id) {
            throw PaymentException::unauthorizedPayment();
        }

        if ($order->status === 'paid') {
            throw PaymentException::alreadyPaid($order->id);
        }

        if ($order->status === 'refunded') {
            throw PaymentException::alreadyRefunded($order->id);
        }

        if (! $order->lab) {
            throw PaymentException::labNotFound($order->lab_id);
        }
    }

    private function createStripeCheckoutSession(Order $order, User $doctor, string $stripeAccountId): object
    {
        try {
            $stripe = $this->getStripeClient();

            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => "Order #{$order->serial_number}",
                            'description' => 'Dental laboratory services',
                        ],
                        'unit_amount' => (int) ($order->price * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/orders/'.$order->id).'?payment=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/orders/'.$order->id).'?payment=cancelled',
                'payment_intent_data' => [
                    'on_behalf_of' => $stripeAccountId,
                    'transfer_data' => [
                        'destination' => $stripeAccountId,
                    ],
                ],
                'metadata' => [
                    'order_id' => $order->id,
                    'doctor_id' => $doctor->id,
                    'lab_id' => $order->lab_id,
                ],
            ]);

            return $session;
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw PaymentException::checkoutSessionFailed($e->getMessage());
        }
    }

    private function createOrUpdatePayment(Order $order, User $doctor, object $session): void
    {
        $payment = Payment::query()->updateOrCreate(
            [
                'checkout_session_id' => $session->id,
            ],
            [
                'user_id' => $doctor->id,
                'amount' => $order->price,
                'payment_method' => 'card',
                'payment_intent_id' => $session->payment_intent,
                'checkout_session_id' => $session->id,
                'currency' => 'usd',
            ]
        );

        PaymentOrder::query()->updateOrCreate(
            [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
            ],
            [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $order->price,
            ]
        );
    }

    private function handlePaymentIntentEvent(array $data): void
    {
        $paymentIntentId = $data['id'];
        $status = $data['status'];

        $payment = Payment::query()->where('payment_intent_id', $paymentIntentId)->first();

        if (! $payment) {
            Log::warning('Payment intent event: no matching payment found', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        $payment->update(['payment_status' => $status]);

        if ($status === 'succeeded') {
            $payment->update(['paid_at' => now()]);
            $payment->orders()->update(['remaining_amount' => 0]);
        }
    }

    private function handleChargeEvent(array $data): void
    {
        $chargeId = $data['id'];
        $paymentIntentId = $data['payment_intent'];
        $amount = $data['amount'] / 100;
        $currency = strtoupper($data['currency']);
        $status = $data['status'];

        $payment = Payment::query()->where('payment_intent_id', $paymentIntentId)->first();

        if (! $payment) {
            Log::warning('Charge event: no matching payment found', [
                'charge_id' => $chargeId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        $payment->update([
            'payment_status' => $status,
            'charge_id' => $chargeId,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => 'stripe',
            'provider_reference' => $data['balance_transaction'] ?? null,
        ]);

        if ($status === 'succeeded') {
            $payment->update(['paid_at' => now()]);
            $payment->orders()->update(['remaining_amount' => 0]);
        }
    }

    private function handleCheckoutSessionEvent(array $data): void
    {
        $sessionId = $data['id'];
        $paymentIntentId = $data['payment_intent'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;

        $payment = Payment::query()
            ->where('checkout_session_id', $sessionId)
            ->orWhere('payment_intent_id', $paymentIntentId)
            ->first();

        if (! $payment) {
            Log::warning('Checkout session event: no matching payment found', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        $update = [
            'checkout_session_id' => $sessionId,
        ];

        if ($paymentIntentId) {
            $update['payment_intent_id'] = $paymentIntentId;
        }

        if ($paymentStatus) {
            $update['payment_status'] = $paymentStatus;
        }

        if (isset($data['amount_total'])) {
            $update['amount'] = $data['amount_total'] / 100;
        }

        if (isset($data['currency'])) {
            $update['currency'] = strtoupper($data['currency']);
        }

        $payment->update($update);

        if ($paymentStatus === 'paid') {
            $payment->update(['paid_at' => now()]);
            $payment->orders()->update(['remaining_amount' => 0]);
        }
    }

    private function getMostRecentPaymentForOrder(Order $order): ?Payment
    {
        return $order->payments()->orderByDesc('created_at')->first();
    }

    private function logUnhandledEvent(string $eventType, array $data): void
    {
        Log::info('Unhandled Stripe event', [
            'event_type' => $eventType,
            'data' => $data,
        ]);
    }

    private function getStripeClient(): StripeClient
    {
        $config = config('stripe-connect.connect');

        if ($config['test_mode']) {
            return new StripeClient($config['test_secret_key']);
        }

        throw new \RuntimeException('Stripe Connect is only configured for test mode.');
    }
}
