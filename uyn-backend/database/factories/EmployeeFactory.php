<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'area_id' => Area::factory(),
            'worker_type' => fake()->randomElement(['internal', 'external']),
            'phone' => fake()->numerify('##########'),
            'status' => fake()->randomElement(['active', 'inactive']),
            'notes' => fake()->optional()->sentence(),

        ];
    }
}
