<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'lab_id' => Lab::factory(),
            'balance' => 0,
            'currency' => 'USD',
        ];
    }
}
