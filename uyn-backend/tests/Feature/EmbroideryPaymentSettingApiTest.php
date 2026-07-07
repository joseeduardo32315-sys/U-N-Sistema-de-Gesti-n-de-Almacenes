<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class EmbroideryPaymentSettingApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    public function test_administrator_can_create_embroidery_payment_setting(): void
    {
        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $response = $this->postJson(
            '/api/v1/embroidery-payment-settings',
            [
                'operation_process_id' => $operation->id,
                'stitch_price' => 0.00010000,
                'application_price' => 1.0000,
                'payment_percentage' => 0.300000,
                'minimum_payment_per_piece' => 0.7500,
                'default_payment_per_piece' => 0.7500,
                'effective_from' => '2026-07-01',
                'notes' => 'Configuración de Bordado de prueba.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.operation_process.id',
                $operation->id
            )
            ->assertJsonPath(
                'data.operation_process.payroll_calculation_type',
                'embroidery_formula'
            )
            ->assertJsonPath('data.stitch_price', '0.00010000')
            ->assertJsonPath('data.application_price', '1.0000')
            ->assertJsonPath('data.payment_percentage', '0.300000')
            ->assertJsonPath(
                'data.minimum_payment_per_piece',
                '0.7500'
            )
            ->assertJsonPath(
                'data.default_payment_per_piece',
                '0.7500'
            );
    }

    public function test_embroidery_setting_rejects_default_payment_lower_than_minimum(): void
    {
        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $response = $this->postJson(
            '/api/v1/embroidery-payment-settings',
            [
                'operation_process_id' => $operation->id,
                'stitch_price' => 0.00010000,
                'application_price' => 1.0000,
                'payment_percentage' => 0.300000,
                'minimum_payment_per_piece' => 0.7500,
                'default_payment_per_piece' => 0.5000,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'default_payment_per_piece'
            );
    }

    public function test_embroidery_setting_cannot_be_created_for_normal_operation(): void
    {
        $normalOperation = $this->makeOperation('per_piece');

        $response = $this->postJson(
            '/api/v1/embroidery-payment-settings',
            [
                'operation_process_id' => $normalOperation->id,
                'stitch_price' => 0.00010000,
                'application_price' => 1.0000,
                'payment_percentage' => 0.300000,
                'minimum_payment_per_piece' => 0.7500,
                'default_payment_per_piece' => 0.7500,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation_process_id');
    }

    public function test_active_embroidery_settings_cannot_overlap(): void
    {
        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $this->createEmbroideryPaymentSetting(
            operation: $operation,
            actor: $this->administrator
        );

        $response = $this->postJson(
            '/api/v1/embroidery-payment-settings',
            [
                'operation_process_id' => $operation->id,
                'stitch_price' => 0.00020000,
                'application_price' => 1.0000,
                'payment_percentage' => 0.300000,
                'minimum_payment_per_piece' => 0.7500,
                'default_payment_per_piece' => 0.7500,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');
    }

    public function test_pricing_values_cannot_be_edited_on_existing_setting(): void
    {
        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $setting = $this->createEmbroideryPaymentSetting(
            operation: $operation,
            actor: $this->administrator
        );

        $response = $this->patchJson(
            "/api/v1/embroidery-payment-settings/{$setting->id}",
            [
                'stitch_price' => 0.00020000,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stitch_price');
    }
}