<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentCut>
 */
class GarmentCutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            
            'garment_model_id' => GarmentModel::factory(),

            'code' => fake()
                ->unique()
                ->bothify('CUT-#####'),

            'total_sizes' => 2,

            'base_pieces_per_size' => 50,

            'total_pieces' => 100,

            'status' => 'registered',

            'current_area_id' => Area::factory(),

            'notes' => fake()
                ->optional()
                ->sentence(),

        ];
    }
}
