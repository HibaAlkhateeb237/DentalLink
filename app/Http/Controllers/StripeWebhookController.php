<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripePaymentService $paymentService,
        private ApiResponse $apiResponse,
    ) {}

    public function handleEvent(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('Stripe-Signature');
        $rawBody = $request->getContent();

        try {
            $this->verifyWebhookSignature($rawBody, $signature);
            $this->paymentService->handlePaymentEvent($payload);

            return $this->apiResponse->success(['status' => 'received'], 'Webhook processed successfully');
        } catch (ValidationException $e) {
            return $this->apiResponse->error('Invalid webhook signature or data', 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_type' => $payload['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->apiResponse->error('Webhook processing failed: '.$e->getMessage(), 500);
        }
    }

    private function verifyWebhookSignature(string $rawBody, ?string $signature): void
    {
        $webhookSecret = config('stripe-connect.connect.test_webhook_secret');

        if (empty($webhookSecret)) {
            return;
        }

        if (empty($signature)) {
            throw ValidationException::withMessages([
                'signature' => 'Missing Stripe-Signature header.',
            ]);
        }

        Webhook::constructEvent($rawBody, $signature, $webhookSecret);
    }
}
