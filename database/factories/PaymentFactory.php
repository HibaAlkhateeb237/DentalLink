<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'payment_method' => fake()->randomElement(['card', 'cash', 'bank_transfer']),
            'payment_status' => 'pending',
            'currency' => 'USD',
            'paid_at' => null,
        ];
    }
}
