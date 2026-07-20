<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'duration_days' => fake()->numberBetween(1, 90),
            'price' => fake()->randomFloat(2, 10, 1000),
            'is_active' => true,
        ];
    }
}
