<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OperationProcess;
use App\Models\PayrollDetail;
use App\Models\PayrollEmployeeSummary;
use App\Models\PayrollPeriod;
use App\Models\ProductionOperationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class PayrollPeriodApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-08 10:00:00');

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_administrator_can_create_payroll_period(): void
    {
        $response = $this->postJson(
            '/api/v1/payroll-periods',
            [
                'code' => 'NOM-2026-W28',
                'frequency' => 'weekly',
                'start_date' => '2026-07-06',
                'end_date' => '2026-07-12',
                'payment_date' => '2026-07-13',
                'notes' => 'Nómina semanal de prueba.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'NOM-2026-W28')
            ->assertJsonPath('data.frequency', 'weekly')
            ->assertJsonPath('data.start_date', '2026-07-06')
            ->assertJsonPath('data.end_date', '2026-07-12')
            ->assertJsonPath('data.payment_date', '2026-07-13')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'message',
                'Periodo de nómina creado correctamente.'
            );

        $this->assertDatabaseHas('payroll_periods', [
            'code' => 'NOM-2026-W28',
            'frequency' => 'weekly',
            'status' => 'draft',
            'created_by' => $this->administrator->id,
        ]);
    }

    public function test_payroll_periods_cannot_overlap_with_same_frequency(): void
    {
        $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $response = $this->postJson(
            '/api/v1/payroll-periods',
            [
                'code' => 'NOM-2026-W28-DUP',
                'frequency' => 'weekly',
                'start_date' => '2026-07-10',
                'end_date' => '2026-07-16',
                'payment_date' => '2026-07-17',
                'notes' => 'Periodo traslapado de prueba.',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');

        $this->assertDatabaseCount('payroll_periods', 1);
    }

    public function test_generate_period_includes_completed_piecework_operation_log(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $operationLog = $this->createCompletedPayoutOperationLog(
            employee: $employee,
            operation: $operation,
            quantityProcessed: 100,
            payoutAmount: '84.00',
            finalPaymentPerPiece: '0.8400',
            endTime: '2026-07-08 12:00:00'
        );

        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $response = $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            [
                'notes' => 'Generación con destajo.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath(
                'message',
                'Periodo de nómina generado correctamente.'
            );

        $summary = PayrollEmployeeSummary::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertSame('piecework', $summary->payment_type);
        $this->assertSame('84.00', $summary->piecework_amount);
        $this->assertSame('0.00', $summary->fixed_amount);
        $this->assertSame('84.00', $summary->total_amount);
        $this->assertSame(1, $summary->details_count);

        $detail = PayrollDetail::query()
            ->where('payroll_employee_summary_id', $summary->id)
            ->firstOrFail();

        $this->assertSame('operation_log', $detail->source_type);
        $this->assertSame($operationLog->id, $detail->production_operation_log_id);
        $this->assertSame(100, $detail->quantity);
        $this->assertSame('0.8400', $detail->unit_amount);
        $this->assertSame('84.00', $detail->amount);

        $this->assertSame(
            'calculated',
            data_get($detail->calculation_snapshot, 'payment_status')
        );

        $this->assertSame(
            '0.8400',
            data_get($detail->calculation_snapshot, 'final_payment_per_piece')
        );

        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'status' => 'generated',
            'generated_by' => $this->administrator->id,
        ]);
    }

    public function test_generate_period_prorates_fixed_compensation_by_days(): void
    {
        $employee = $this->makeActiveEmployee();

        $this->createFixedCompensation(
            employee: $employee,
            actor: $this->administrator,
            effectiveFrom: '2026-07-09'
        );

        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            [
                'notes' => 'Generación con pago fijo prorrateado.',
            ]
        )->assertOk();

        $summary = PayrollEmployeeSummary::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertSame('fixed', $summary->payment_type);
        $this->assertSame('0.00', $summary->piecework_amount);
        $this->assertSame('1428.57', $summary->fixed_amount);
        $this->assertSame('1428.57', $summary->total_amount);
        $this->assertSame(1, $summary->details_count);

        $detail = PayrollDetail::query()
            ->where('payroll_employee_summary_id', $summary->id)
            ->firstOrFail();

        $this->assertSame('fixed_compensation', $detail->source_type);
        $this->assertSame(4, $detail->quantity);
        $this->assertSame('357.1429', $detail->unit_amount);
        $this->assertSame('1428.57', $detail->amount);

        $this->assertSame(
            'fixed_prorated',
            data_get($detail->calculation_snapshot, 'calculation_type')
        );

        $this->assertSame(
            7,
            data_get($detail->calculation_snapshot, 'period_days')
        );

        $this->assertSame(
            4,
            data_get($detail->calculation_snapshot, 'payable_days')
        );

        $this->assertSame(
            '2026-07-09',
            data_get($detail->calculation_snapshot, 'payable_start_date')
        );

        $this->assertSame(
            '2026-07-12',
            data_get($detail->calculation_snapshot, 'payable_end_date')
        );
    }

    public function test_generate_period_creates_mixed_summary_when_employee_has_piecework_and_fixed_payment(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $this->createFixedCompensation(
            employee: $employee,
            actor: $this->administrator,
            effectiveFrom: '2026-07-09'
        );

        $this->createCompletedPayoutOperationLog(
            employee: $employee,
            operation: $operation,
            quantityProcessed: 100,
            payoutAmount: '84.00',
            finalPaymentPerPiece: '0.8400',
            endTime: '2026-07-08 12:00:00'
        );

        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            [
                'notes' => 'Generación mixta.',
            ]
        )->assertOk();

        $summary = PayrollEmployeeSummary::query()
            ->with('details')
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertSame('mixed', $summary->payment_type);
        $this->assertSame('84.00', $summary->piecework_amount);
        $this->assertSame('1428.57', $summary->fixed_amount);
        $this->assertSame('1512.57', $summary->total_amount);
        $this->assertSame(2, $summary->details_count);

        $this->assertCount(2, $summary->details);

        $this->assertSame(
            1,
            $summary->details
                ->where('source_type', 'operation_log')
                ->count()
        );

        $this->assertSame(
            1,
            $summary->details
                ->where('source_type', 'fixed_compensation')
                ->count()
        );
    }

    public function test_generation_excludes_pending_configuration_operation_logs(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $this->createPendingPayoutOperationLog(
            employee: $employee,
            operation: $operation,
            quantityProcessed: 100,
            endTime: '2026-07-08 12:00:00'
        );

        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $response = $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            [
                'notes' => 'Generación sin pagos calculados.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'generated');

        $this->assertDatabaseCount('payroll_employee_summaries', 0);
        $this->assertDatabaseCount('payroll_details', 0);

        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'status' => 'generated',
        ]);
    }

    public function test_operation_log_is_not_paid_twice_in_another_period_frequency(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $operationLog = $this->createCompletedPayoutOperationLog(
            employee: $employee,
            operation: $operation,
            quantityProcessed: 100,
            payoutAmount: '84.00',
            finalPaymentPerPiece: '0.8400',
            endTime: '2026-07-08 12:00:00'
        );

        $weeklyPeriod = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$weeklyPeriod->id}/generate",
            []
        )->assertOk();

        $biweeklyPeriod = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-B14',
            frequency: 'biweekly',
            startDate: '2026-07-01',
            endDate: '2026-07-15'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$biweeklyPeriod->id}/generate",
            []
        )->assertOk();

        $this->assertDatabaseHas('payroll_details', [
            'production_operation_log_id' => $operationLog->id,
            'amount' => '84.00',
        ]);

        $this->assertSame(
            1,
            PayrollDetail::query()
                ->where('production_operation_log_id', $operationLog->id)
                ->count()
        );

        $this->assertSame(
            0,
            PayrollEmployeeSummary::query()
                ->where('payroll_period_id', $biweeklyPeriod->id)
                ->count()
        );
    }

    public function test_generated_period_can_be_closed_and_summaries_are_marked_as_paid(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $this->createCompletedPayoutOperationLog(
            employee: $employee,
            operation: $operation,
            quantityProcessed: 100,
            payoutAmount: '84.00',
            finalPaymentPerPiece: '0.8400',
            endTime: '2026-07-08 12:00:00'
        );

        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            []
        )->assertOk();

        $response = $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/close",
            [
                'notes' => 'Periodo cerrado en pruebas.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath(
                'message',
                'Periodo de nómina cerrado correctamente.'
            );

        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'status' => 'closed',
            'closed_by' => $this->administrator->id,
        ]);

        $this->assertDatabaseHas('payroll_employee_summaries', [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'paid',
        ]);
    }

    public function test_closed_period_cannot_be_updated(): void
    {
        $period = $this->createPayrollPeriodThroughApi(
            code: 'NOM-2026-W28',
            frequency: 'weekly',
            startDate: '2026-07-06',
            endDate: '2026-07-12'
        );

        $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/generate",
            []
        )->assertOk();

        $this->postJson(
            "/api/v1/payroll-periods/{$period->id}/close",
            []
        )->assertOk();

        $response = $this->patchJson(
            "/api/v1/payroll-periods/{$period->id}",
            [
                'notes' => 'Intento de modificación posterior al cierre.',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function createPayrollPeriodThroughApi(
        string $code,
        string $frequency,
        string $startDate,
        string $endDate,
        ?string $paymentDate = null
    ): PayrollPeriod {
        $response = $this->postJson(
            '/api/v1/payroll-periods',
            [
                'code' => $code,
                'frequency' => $frequency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_date' => $paymentDate ?? Carbon::parse($endDate)
                    ->addDay()
                    ->toDateString(),
                'notes' => "Periodo {$code} creado para prueba.",
            ]
        );

        $response->assertCreated();

        return PayrollPeriod::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    private function createCompletedPayoutOperationLog(
        Employee $employee,
        OperationProcess $operation,
        int $quantityProcessed,
        string $payoutAmount,
        string $finalPaymentPerPiece,
        string $endTime
    ): ProductionOperationLog {
        return ProductionOperationLog::factory()->create([
            'employee_id' => $employee->id,
            'operation_process_id' => $operation->id,
            'status' => 'completed',
            'quantity_processed' => $quantityProcessed,
            'stitches_count' => null,
            'applications_count' => null,
            'start_time' => Carbon::parse($endTime)->subHour(),
            'end_time' => Carbon::parse($endTime),
            'payout_amount' => $payoutAmount,
            'payout_snapshot' => [
                'payment_status' => 'calculated',
                'payment_type' => 'piecework',
                'calculation_type' => 'per_piece',
                'employee_id' => $employee->id,
                'operation_process_id' => $operation->id,
                'operation_process_name' => $operation->name,
                'quantity_processed' => $quantityProcessed,
                'final_payment_per_piece' => $finalPaymentPerPiece,
                'payout_amount' => $payoutAmount,
            ],
        ]);
    }

    private function createPendingPayoutOperationLog(
        Employee $employee,
        OperationProcess $operation,
        int $quantityProcessed,
        string $endTime
    ): ProductionOperationLog {
        return ProductionOperationLog::factory()->create([
            'employee_id' => $employee->id,
            'operation_process_id' => $operation->id,
            'status' => 'completed',
            'quantity_processed' => $quantityProcessed,
            'start_time' => Carbon::parse($endTime)->subHour(),
            'end_time' => Carbon::parse($endTime),
            'payout_amount' => null,
            'payout_snapshot' => [
                'payment_status' => 'pending_configuration',
                'reason' => 'No existe una tarifa activa por pieza para el trabajador y la operación.',
                'employee_id' => $employee->id,
                'operation_process_id' => $operation->id,
                'quantity_processed' => $quantityProcessed,
                'payout_amount' => null,
            ],
        ]);
    }
}