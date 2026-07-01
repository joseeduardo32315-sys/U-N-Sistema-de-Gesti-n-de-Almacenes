<?php

namespace Database\Factories;

use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_code' => fake()
                ->unique()
                ->bothify('OP-####'),

            'location' => fake()
                ->optional()
                ->city(),

            'status' => 'registered',

            'start_date' => now()
                ->addDays(fake()->numberBetween(1, 30))
                ->toDateString(),

            'end_date' => now()
                ->addDays(fake()->numberBetween(1, 30))
                ->toDateString(),

            'priority' => fake()->randomElement([
                'low',
                'normal',
                'high',
                'urgent',
            ]),

            'created_by' => User::factory(),

            'notes' => fake()
                ->optional()
                ->sentence(),
        ];
    }
}
