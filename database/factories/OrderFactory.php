<?php

namespace Database\Factories;

use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\ToothShade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lab_id' => Lab::factory(),
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => 'pending',
            'order_type' => 'digital',
            'notes' => $this->faker->sentence(),
            'price' => $this->faker->numberBetween(100, 1000),
            'remaining_amount' => $this->faker->numberBetween(100, 1000),
            'tooth_shade_id' => ToothShade::query()->inRandomOrder()->first()?->id ?? ToothShade::factory(),
            'dental_compensation_type_price_id' => DentalCompensationTypePrice::query()->inRandomOrder()->first()?->id ?? DentalCompensationTypePrice::factory(),
        ];
    }
}
