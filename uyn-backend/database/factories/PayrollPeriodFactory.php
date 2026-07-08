<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'NOM-' . fake()->unique()->numerify('########'),

            'frequency' => 'weekly',

            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',

            'payment_date' => '2026-07-13',

            'status' => 'draft',

            'notes' => fake()->optional()->sentence(),

            'generated_at' => null,
            'closed_at' => null,

            'created_by' => User::factory(),
            'generated_by' => null,
            'closed_by' => null,
        ];
    }
}