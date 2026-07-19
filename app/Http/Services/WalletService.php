<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function creditFromPayment(Payment $payment, Order $order): Transaction
    {
        $lab = $order->lab;

        if (! $lab) {
            Log::warning('Wallet credit skipped: order has no lab', [
                'order_id' => $order->id,
            ]);

            throw new \RuntimeException('Order has no associated lab.');
        }

        $wallet = Wallet::query()->firstOrCreate(
            ['lab_id' => $lab->id],
            ['currency' => $payment->currency ?? 'USD']
        );

        $pivotPayment = $payment->orders()
            ->wherePivot('order_id', $order->id)
            ->first();

        $creditAmount = $pivotPayment
            ? (float) $pivotPayment->pivot->amount
            : (float) $payment->amount;

        if ($creditAmount <= 0) {
            Log::warning('Wallet credit skipped: amount is zero or negative', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $creditAmount,
            ]);

            throw new \RuntimeException('Credit amount must be positive.');
        }

        $wallet->increment('balance', $creditAmount);
        $wallet->refresh();

        return Transaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'order_payment_credit',
            'amount' => $creditAmount,
            'balance_after' => $wallet->balance,
            'currency' => $payment->currency ?? 'USD',
            'description' => "Payment received for Order #{$order->serial_number}",
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'metadata' => [
                'order_id' => $order->id,
                'order_serial' => $order->serial_number,
                'payment_id' => $payment->id,
                'charge_id' => $payment->charge_id,
                'payment_intent_id' => $payment->payment_intent_id,
            ],
        ]);
    }
}
