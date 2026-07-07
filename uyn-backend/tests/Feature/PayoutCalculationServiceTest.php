<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayoutCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class PayoutCalculationServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create([
            'status' => 'active',
        ]);
    }

    public function test_calculates_embroidery_payment_above_minimum(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $this->createEmbroideryPaymentSetting(
            operation: $operation,
            actor: $this->actor
        );

        $result = app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 150,
            stitchesCount: 8000,
            applicationsCount: 2,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );

        $this->assertSame('126.00', $result['payout_amount']);

        $this->assertSame(
            'calculated',
            data_get($result, 'payout_snapshot.payment_status')
        );

        $this->assertSame(
            'embroidery_formula',
            data_get($result, 'payout_snapshot.calculation_type')
        );

        $this->assertSame(
            '0.8400',
            data_get(
                $result,
                'payout_snapshot.formula_payment_per_piece'
            )
        );

        $this->assertFalse(
            data_get($result, 'payout_snapshot.minimum_applied')
        );

        $this->assertSame(
            '0.8400',
            data_get(
                $result,
                'payout_snapshot.final_payment_per_piece'
            )
        );
    }

    public function test_uses_default_payment_when_embroidery_formula_is_below_minimum(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $this->createEmbroideryPaymentSetting(
            operation: $operation,
            actor: $this->actor
        );

        $result = app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 100,
            stitchesCount: 1000,
            applicationsCount: 0,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );

        $this->assertSame('75.00', $result['payout_amount']);

        $this->assertSame(
            '0.0300',
            data_get(
                $result,
                'payout_snapshot.formula_payment_per_piece'
            )
        );

        $this->assertTrue(
            data_get($result, 'payout_snapshot.minimum_applied')
        );

        $this->assertSame(
            '0.7500',
            data_get(
                $result,
                'payout_snapshot.final_payment_per_piece'
            )
        );
    }

    public function test_calculates_standard_per_piece_payment(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $this->createPieceworkRate(
            employee: $employee,
            operation: $operation,
            actor: $this->actor,
            overrides: [
                'amount_per_piece' => '4.5000',
            ]
        );

        $result = app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 80,
            stitchesCount: null,
            applicationsCount: null,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );

        $this->assertSame('360.00', $result['payout_amount']);

        $this->assertSame(
            'per_piece',
            data_get($result, 'payout_snapshot.calculation_type')
        );

        $this->assertSame(
            '4.5000',
            data_get($result, 'payout_snapshot.amount_per_piece')
        );
    }

    public function test_fixed_compensation_does_not_generate_operation_payout(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createFixedCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $result = app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 50,
            stitchesCount: null,
            applicationsCount: null,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );

        $this->assertNull($result['payout_amount']);

        $this->assertSame(
            'not_applicable',
            data_get($result, 'payout_snapshot.payment_status')
        );

        $this->assertSame(
            'fixed_salary',
            data_get($result, 'payout_snapshot.calculation_type')
        );
    }

    public function test_piecework_operation_without_rate_remains_pending(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $result = app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 50,
            stitchesCount: null,
            applicationsCount: null,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );

        $this->assertNull($result['payout_amount']);

        $this->assertSame(
            'pending_configuration',
            data_get($result, 'payout_snapshot.payment_status')
        );
    }

    public function test_embroidery_piecework_requires_stitches_when_setting_exists(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation(
            'embroidery_formula'
        );

        $operationLog = $this->makeProductionOperationLog(
            employee: $employee,
            operation: $operation
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->actor
        );

        $this->createEmbroideryPaymentSetting(
            operation: $operation,
            actor: $this->actor
        );

        $this->expectException(ValidationException::class);

        app(PayoutCalculationService::class)->calculate(
            operationLog: $operationLog,
            quantityProcessed: 50,
            stitchesCount: null,
            applicationsCount: 0,
            completedAt: Carbon::parse('2026-07-07 10:00:00')
        );
    }
}