<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PayrollEmployeeSummary>
 */
class PayrollEmployeeSummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),

            'employee_id' => Employee::factory(),

            'payment_type' => 'piecework',

            'piecework_amount' => '0.00',
            'fixed_amount' => '0.00',
            'total_amount' => '0.00',

            'details_count' => 0,

            'status' => 'generated',

            'calculation_snapshot' => null,

            'notes' => fake()->optional()->sentence(),
        ];
    }
}