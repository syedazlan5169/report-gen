<?php

namespace Database\Factories;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTemplate>
 */
class ReportTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->sentence(3),
            'body' => "*Report*\n\n{{date}}",
            'is_enabled' => true,
            'sort_order' => fake()->unique()->numberBetween(1, 1000),
        ];
    }
}
