<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmployeeCompensation>
 */
class EmployeeCompensationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),

            'payment_type' => 'piecework',

            'payment_frequency' => null,
            'fixed_amount' => null,

            'effective_from' => now()
                ->startOfMonth()
                ->toDateString(),

            'effective_to' => null,

            'status' => 'active',

            'notes' => fake()
                ->optional()
                ->sentence(),

            'created_by' => User::factory(),
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn () => [
            'payment_type' => 'fixed',
            'payment_frequency' => 'weekly',
            'fixed_amount' => 2500.00,
        ]);
    }
}