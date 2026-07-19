<?php

namespace Database\Factories;

use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Lab::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->text(),
            'license_number' => 'LAB-'.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}
