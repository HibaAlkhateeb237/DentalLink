<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Http\Services\StripePaymentService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private StripePaymentService $paymentService,
        private ApiResponse $apiResponse,
    ) {}

    public function show(Order $order): JsonResponse
    {
        $this->validateOrderAccess($order, request()->user());

        $payment = $order->payments()->orderByDesc('created_at')->first();

        if (! $payment) {
            return $this->apiResponse->error('No payment found for this order', 404);
        }

        return $this->apiResponse->success($payment, 'Payment retrieved successfully');
    }

    public function status(Order $order): JsonResponse
    {
        $this->validateOrderAccess($order, request()->user());

        $payment = $order->payments()->orderByDesc('created_at')->first();

        if (! $payment) {
            return $this->apiResponse->success(['status' => 'pending'], 'No payment found yet');
        }

        $status = [
            'payment_status' => $payment->payment_status,
            'paid_at' => $payment->paid_at,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'checkout_session_id' => $payment->checkout_session_id,
            'charge_id' => $payment->charge_id,
        ];

        return $this->apiResponse->success($status, 'Payment status retrieved successfully');
    }

    private function validateOrderAccess(Order $order, mixed $user): void
    {
        if ($order->user_id !== $user->id) {
            throw new ValidationException(
                ValidationException::withMessages([
                    'order' => ['You are not authorized to access this order.'],
                ])
            );
        }
    }
}
