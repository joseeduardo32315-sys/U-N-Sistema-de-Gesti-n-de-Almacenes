<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\GarmentCut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GarmentCutComplement>
 */
class GarmentCutComplementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'garment_cut_id' => GarmentCut::factory(),
            'current_area_id' => Area::factory(),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}