<?php

namespace Database\Factories;

use App\Models\GarmentCut;
use App\Models\ProductionMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductionIncident>
 */
class ProductionIncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'garment_cut_id' => GarmentCut::factory(),

            'production_movement_id' => ProductionMovement::factory(),

            'incident_type' => 'damage',

            'quantity_affected' => fake()
                ->numberBetween(1, 10),

            'description' => fake()
                ->sentence(),

            'responsible_employee_id' => null,

            'status' => 'open',

            'resolved_at' => null,
            'resolved_by' => null,

            'notes' => fake()
                ->optional()
                ->sentence(),
        ];
    }
}