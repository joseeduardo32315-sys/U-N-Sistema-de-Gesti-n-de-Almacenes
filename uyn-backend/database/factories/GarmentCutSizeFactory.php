<?php

namespace Database\Factories;

use App\Models\GarmentCut;
use App\Models\GarmentCutSize;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentCutSize>
 */
class GarmentCutSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'garment_cut_id' => GarmentCut::factory(),

            'size_id' => Size::factory(),

            'total_pieces' => fake()
                ->numberBetween(1, 200)
        ];
    }
}
