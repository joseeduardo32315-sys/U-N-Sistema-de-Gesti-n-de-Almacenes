<?php

namespace Database\Factories;

use App\Models\OperationProcess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmbroideryPaymentSetting>
 */
class EmbroideryPaymentSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'operation_process_id' => OperationProcess::factory(),

            'stitch_price' => '0.00010000',
            'application_price' => '1.0000',
            'payment_percentage' => '0.300000',

            'minimum_payment_per_piece' => '0.7500',
            'default_payment_per_piece' => '0.7500',

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