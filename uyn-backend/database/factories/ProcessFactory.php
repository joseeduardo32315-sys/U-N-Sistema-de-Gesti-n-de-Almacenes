<?php

namespace Database\Factories;

use App\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Process>
 */
class ProcessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),

            'flow_order' => (
                (int) Process::query()->max('flow_order')
            ) + 1,
        ];
    }
}