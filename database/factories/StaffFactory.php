<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
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
            'short_code' => strtoupper(fake()->unique()->bothify('?##')),
            'rank_prefix' => fake()->randomElement(['PiK', 'PiKK', 'PPN']),
            'staff_number' => fake()->unique()->numberBetween(10000, 99999),
            'name' => strtoupper(fake()->name()),
            'is_base_member' => false,
            'is_active' => true,
        ];
    }
}
