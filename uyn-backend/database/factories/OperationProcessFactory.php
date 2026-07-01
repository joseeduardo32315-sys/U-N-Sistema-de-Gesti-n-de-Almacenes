<?php

namespace Database\Factories;

use App\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OperationProcess>
 */
class OperationProcessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'process_id' => Process::factory(),
            'name' => fake()->words(2, true),
            'flow_order' => 1,
        ];
    }
}