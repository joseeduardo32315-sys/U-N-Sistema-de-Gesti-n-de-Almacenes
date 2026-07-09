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

class PayrollReportApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    public function test_payroll_period_report_returns_totals_and_details(): void
    {
        $pieceworkEmployee = $this->makeActiveEmployee();
        $fixedEmployee = $this->makeActiveEmployee();

        $period = PayrollPeriod::factory()->create([
            'code' => 'NOM-2026-W28',
            'frequency' => 'weekly',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
            'payment_date' => '2026-07-13',
            'status' => 'closed',
            'created_by' => $this->administrator->id,
            'generated_by' => $this->administrator->id,
            'closed_by' => $this->administrator->id,
        ]);

        $pieceworkSummary = PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $pieceworkEmployee->id,
            'payment_type' => 'piecework',
            'piecework_amount' => '84.00',
            'fixed_amount' => '0.00',
            'total_amount' => '84.00',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        PayrollDetail::factory()->create([
            'payroll_employee_summary_id' => $pieceworkSummary->id,
            'source_type' => 'operation_log',
            'quantity' => 100,
            'unit_amount' => '0.8400',
            'amount' => '84.00',
            'calculation_snapshot' => [
                'calculation_type' => 'embroidery_formula',
                'final_payment_per_piece' => '0.8400',
                'payout_amount' => '84.00',
            ],
        ]);

        $fixedSummary = PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $fixedEmployee->id,
            'payment_type' => 'fixed',
            'piecework_amount' => '0.00',
            'fixed_amount' => '1428.57',
            'total_amount' => '1428.57',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        PayrollDetail::factory()->create([
            'payroll_employee_summary_id' => $fixedSummary->id,
            'source_type' => 'fixed_compensation',
            'quantity' => 4,
            'unit_amount' => '357.1429',
            'amount' => '1428.57',
            'calculation_snapshot' => [
                'calculation_type' => 'fixed_prorated',
                'period_days' => 7,
                'payable_days' => 4,
                'amount' => '1428.57',
            ],
        ]);

        $response = $this->getJson(
            "/api/v1/reports/payroll-periods/{$period->id}?include_details=1"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.period.code', 'NOM-2026-W28')
            ->assertJsonPath('data.period.status', 'closed')
            ->assertJsonPath('data.totals.workers_count', 2)
            ->assertJsonPath('data.totals.piecework_workers_count', 1)
            ->assertJsonPath('data.totals.fixed_workers_count', 1)
            ->assertJsonPath('data.totals.piecework_amount', '84.00')
            ->assertJsonPath('data.totals.fixed_amount', '1428.57')
            ->assertJsonPath('data.totals.grand_total', '1512.57')
            ->assertJsonPath('data.totals.details_count', 2)
            ->assertJsonCount(2, 'data.employees')
            ->assertJsonCount(1, 'data.employees.0.details');
    }

    public function test_payroll_period_report_can_filter_by_payment_type(): void
    {
        $pieceworkEmployee = $this->makeActiveEmployee();
        $fixedEmployee = $this->makeActiveEmployee();

        $period = PayrollPeriod::factory()->create([
            'code' => 'NOM-2026-W28',
            'frequency' => 'weekly',
            'status' => 'closed',
            'created_by' => $this->administrator->id,
        ]);

        PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $pieceworkEmployee->id,
            'payment_type' => 'piecework',
            'piecework_amount' => '84.00',
            'fixed_amount' => '0.00',
            'total_amount' => '84.00',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $fixedEmployee->id,
            'payment_type' => 'fixed',
            'piecework_amount' => '0.00',
            'fixed_amount' => '1428.57',
            'total_amount' => '1428.57',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        $response = $this->getJson(
            "/api/v1/reports/payroll-periods/{$period->id}?payment_type=fixed"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.payment_type', 'fixed')
            ->assertJsonPath('data.totals.fixed_amount', '1428.57')
            ->assertJsonPath('data.totals.grand_total', '1428.57');
    }

    public function test_employee_payroll_report_returns_totals_for_date_range(): void
    {
        $employee = $this->makeActiveEmployee();

        $period = PayrollPeriod::factory()->create([
            'code' => 'NOM-2026-W28',
            'frequency' => 'weekly',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
            'status' => 'closed',
            'created_by' => $this->administrator->id,
        ]);

        PayrollEmployeeSummary::factory()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'payment_type' => 'piecework',
            'piecework_amount' => '84.00',
            'fixed_amount' => '0.00',
            'total_amount' => '84.00',
            'details_count' => 1,
            'status' => 'paid',
        ]);

        $response = $this->getJson(
            '/api/v1/reports/payroll-employees?from=2026-07-01&to=2026-07-31&status=paid'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.from', '2026-07-01')
            ->assertJsonPath('data.filters.to', '2026-07-31')
            ->assertJsonPath('data.filters.status', 'paid')
            ->assertJsonPath('data.totals.records_count', 1)
            ->assertJsonPath('data.totals.piecework_amount', '84.00')
            ->assertJsonPath('data.totals.grand_total', '84.00')
            ->assertJsonPath('data.payments.0.employee.id', $employee->id)
            ->assertJsonPath('data.payments.0.period.code', 'NOM-2026-W28')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_without_reports_permission_cannot_view_payroll_reports(): void
    {
        $period = PayrollPeriod::factory()->create([
            'created_by' => $this->administrator->id,
        ]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson(
            "/api/v1/reports/payroll-periods/{$period->id}"
        )->assertForbidden();

        $this->getJson(
            '/api/v1/reports/payroll-employees?from=2026-07-01&to=2026-07-31'
        )->assertForbidden();
    }
}