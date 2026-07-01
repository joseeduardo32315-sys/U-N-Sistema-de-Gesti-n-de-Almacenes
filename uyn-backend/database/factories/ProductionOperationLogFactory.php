<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OperationProcess;
use App\Models\ProductionMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionOperationLog>
 */
class ProductionOperationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'production_movement_id' => ProductionMovement::factory(),

            'operation_process_id' => OperationProcess::factory(),

            'employee_id' => Employee::factory(),

            'start_time' => null,
            'end_time' => null,

            'stitches_count' => null,
            'applications_count' => null,

            'quantity_processed' => 0,

            'status' => 'pending',

            'notes' => fake()
                ->optional()
                ->sentence(),

            'payout_amount' => null,
        ];
    }
}