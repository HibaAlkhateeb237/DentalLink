<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemLog>
 */
class SystemLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_id' => Lab::factory(),
            'user_id' => User::factory(),
            'level' => 'info',
            'event' => 'order.created',
            'message' => fake()->sentence(),
            'metadata' => ['order_id' => fake()->randomDigitNotNull()],
        ];
    }

    public function withLevel(string $level): static
    {
        return $this->state(['level' => $level]);
    }
}
