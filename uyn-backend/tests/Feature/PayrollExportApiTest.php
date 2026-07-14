<?php

namespace Tests\Feature;

use App\Models\PayrollDetail;
use App\Models\PayrollEmployeeSummary;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class PayrollExportApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    public function test_can_export_payroll_period_report_as_csv(): void
    {
        [$period] = $this->createPayrollExportScenario();

        $response = $this->get(
            "/api/v1/reports/payroll-periods/{$period->id}/export"
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('Periodo', $content);
        $this->assertStringContainsString('Frecuencia', $content);
        $this->assertStringContainsString('Trabajador', $content);
        $this->assertStringContainsString('Total trabajador', $content);
        $this->assertStringContainsString('NOM-2026-W28-EXPORT', $content);
        $this->assertStringContainsString('Destajo', $content);
        $this->assertStringContainsString('84.00', $content);
    }

    public function test_can_export_employee_payroll_report_as_csv(): void
    {
        [$period, $employee] = $this->createPayrollExportScenario();

        $response = $this->get(
            '/api/v1/reports/payroll-employees/export'
            . '?from=2026-07-01&to=2026-07-31&status=paid'
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID trabajador', $content);
        $this->assertStringContainsString('Trabajador', $content);
        $this->assertStringContainsString('Periodo', $content);
        $this->assertStringContainsString('Total trabajador', $content);
        $this->assertStringContainsString($period->code, $content);
        $this->assertStringContainsString($employee->name, $content);
        $this->assertStringContainsString('84.00', $content);
    }

    public function test_user_without_reports_export_permission_cannot_export_payroll_reports(): void
    {
        [$period] = $this->createPayrollExportScenario();

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->get(
            "/api/v1/reports/payroll-periods/{$period->id}/export"
        )->assertForbidden();

        $this->get(
            '/api/v1/reports/payroll-employees/export'
            . '?from=2026-07-01&to=2026-07-31'
        )->assertForbidden();
    }

    private function createPayrollExportScenario(): array
    {
        $employee = $this->makeActiveEmployee();

        $period = PayrollPeriod::factory()->create([
            'code' => 'NOM-2026-W28-EXPORT',
            'frequency' => 'weekly',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
            'payment_date' => '2026-07-13',
            'status' => 'closed',
            'created_by' => $this->administrator->id,
            'generated_by' => $this->administrator->id,
            'closed_by' => $this->administrator->id,
        ]);

        $summary = PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'payment_type' => 'piecework',
            'piecework_amount' => '84.00',
            'fixed_amount' => '0.00',
            'total_amount' => '84.00',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        PayrollDetail::factory()->create([
            'payroll_employee_summary_id' => $summary->id,
            'source_type' => 'operation_log',
            'description' => 'Pago por Bordado',
            'quantity' => 100,
            'unit_amount' => '0.8400',
            'amount' => '84.00',
            'occurred_at' => '2026-07-08 10:00:00',
            'calculation_snapshot' => [
                'calculation_type' => 'embroidery_formula',
                'final_payment_per_piece' => '0.8400',
                'payout_amount' => '84.00',
            ],
        ]);

        return [
            $period->fresh(),
            $employee->fresh(),
            $summary->fresh(),
        ];
    }

    private function assertCsvResponse($response): void
    {
        $this->assertStringContainsString(
            'text/csv',
            $response->headers->get('content-type')
        );

        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition')
        );

        $this->assertStringContainsString(
            '.csv',
            $response->headers->get('content-disposition')
        );
    }
}