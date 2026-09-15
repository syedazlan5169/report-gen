<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
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
            'code' => strtolower(fake()->unique()->bothify('??##')),
            'display_name' => fake()->words(2, true),
            'start_time' => fake()->randomElement(['07:00', '14:00', '22:00']),
            'end_time' => fake()->randomElement(['15:00', '23:00', '07:00']),
            'is_active' => true,
        ];
    }
}
