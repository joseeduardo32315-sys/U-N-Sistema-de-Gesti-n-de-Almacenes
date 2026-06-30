<?php

namespace Database\Factories;

use App\Models\GarmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentModel>
 */
class GarmentModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MOD-####'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'size_range' => fake()->randomElement([
                '2 a 8',
                '4 a 10',
                'CH, M, G',
                '1, 2, 3, 4'
            ]),
            'image_path' => null,
            'status' => 'active',
        ];
    }
}
