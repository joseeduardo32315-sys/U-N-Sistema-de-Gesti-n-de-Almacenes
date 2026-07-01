<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\PieceType;
use App\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SpecialProcessPiece>
 */
class SpecialProcessPieceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'garment_cut_id' => GarmentCut::factory(),
            'piece_type_id' => PieceType::factory(),
            'process_id' => Process::factory(),
            'current_area_id' => Area::factory(),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}