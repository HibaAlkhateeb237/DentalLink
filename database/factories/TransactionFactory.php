<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => 'order_payment_credit',
            'amount' => fake()->randomFloat(2, 10, 500),
            'balance_after' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'USD',
            'description' => fake()->sentence(),
        ];
    }

    public function forOrderPayment(Order $order, Payment $payment): static
    {
        return $this->state([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'amount' => $payment->amount,
            'description' => "Payment received for Order #{$order->serial_number}",
            'metadata' => [
                'order_id' => $order->id,
                'order_serial' => $order->serial_number,
                'payment_id' => $payment->id,
            ],
        ]);
    }
}
