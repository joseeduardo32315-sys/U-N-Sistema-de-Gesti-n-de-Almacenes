<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\Process;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionMovement>
 */
class ProductionMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'garment_cut_id' => GarmentCut::factory(),

            'target_type' => 'cut',

            'special_process_piece_id' => null,
            'complement_id' => null,

            'process_id' => Process::factory(),

            'operation_process_id' => null,

            'from_area_id' => Area::factory(),
            'to_area_id' => Area::factory(),

            'quantity' => fake()->numberBetween(1, 200),

            'status' => 'pending',

            'start_time' => null,
            'end_time' => null,

            'notes' => fake()
                ->optional()
                ->sentence(),

            'created_by' => User::factory(),

            'received_by' => null,
        ];
    }
}