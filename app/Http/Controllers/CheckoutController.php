<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\StripePaymentService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private StripePaymentService $paymentService,
        private ApiResponse $apiResponse,
    ) {}

    public function createSession(Order $order): JsonResponse
    {
        $this->validateOrderAccess($order, request()->user());

        $result = $this->paymentService->createCheckoutSession($order, request()->user());

        return $this->apiResponse->success($result, 'Checkout session created successfully');
    }

    public function processWebhook(): JsonResponse
    {
        $payload = request()->all();
        $signature = request()->header('Stripe-Signature');

        try {
            $this->paymentService->handlePaymentEvent($payload);

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            return $this->apiResponse->error('Webhook processing failed: '.$e->getMessage(), 400);
        }
    }

    private function validateOrderAccess(Order $order, User $user): void
    {
        if ($order->user_id !== $user->id) {
            throw new ValidationException(
                ValidationException::withMessages([
                    'order' => ['You are not authorized to access this order.'],
                ])
            );
        }

        $this->paymentService->processPaymentForOrder($order);
    }
}
