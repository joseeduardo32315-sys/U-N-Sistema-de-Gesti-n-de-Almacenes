<?php

namespace Database\Factories;

use App\Models\PayrollEmployeeSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PayrollDetail>
 */
class PayrollDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payroll_employee_summary_id' =>
                PayrollEmployeeSummary::factory(),

            'source_type' => 'operation_log',

            'production_operation_log_id' => null,
            'employee_compensation_id' => null,

            'description' => 'Pago por operación completada.',

            'quantity' => 10,

            'unit_amount' => '4.5000',

            'amount' => '45.00',

            'occurred_at' => now(),

            'calculation_snapshot' => null,
        ];
    }
}