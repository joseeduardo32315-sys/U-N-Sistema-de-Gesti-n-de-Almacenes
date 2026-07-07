<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OperationProcess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PieceworkRate>
 */
class PieceworkRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),

            'operation_process_id' => OperationProcess::factory(),

            'amount_per_piece' => fake()
                ->randomFloat(4, 1, 50),

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
}