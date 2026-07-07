<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class PieceworkRateApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    public function test_administrator_can_create_per_piece_rate(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->administrator
        );

        $response = $this->postJson(
            '/api/v1/piecework-rates',
            [
                'employee_id' => $employee->id,
                'operation_process_id' => $operation->id,
                'amount_per_piece' => 4.5000,
                'effective_from' => '2026-07-01',
                'notes' => 'Tarifa por pieza de prueba.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath(
                'data.operation_process.id',
                $operation->id
            )
            ->assertJsonPath('data.amount_per_piece', '4.5000')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('piecework_rates', [
            'employee_id' => $employee->id,
            'operation_process_id' => $operation->id,
            'status' => 'active',
        ]);
    }

    public function test_rate_requires_active_piecework_compensation(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $response = $this->postJson(
            '/api/v1/piecework-rates',
            [
                'employee_id' => $employee->id,
                'operation_process_id' => $operation->id,
                'amount_per_piece' => 4.5000,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_direct_rate_cannot_be_created_for_embroidery_formula_operation(): void
    {
        $employee = $this->makeActiveEmployee();

        $embroideryOperation = $this->makeOperation(
            'embroidery_formula'
        );

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->administrator
        );

        $response = $this->postJson(
            '/api/v1/piecework-rates',
            [
                'employee_id' => $employee->id,
                'operation_process_id' => $embroideryOperation->id,
                'amount_per_piece' => 4.5000,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation_process_id');
    }

    public function test_active_piecework_rates_cannot_overlap_for_same_worker_and_operation(): void
    {
        $employee = $this->makeActiveEmployee();

        $operation = $this->makeOperation('per_piece');

        $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->administrator
        );

        $this->createPieceworkRate(
            employee: $employee,
            operation: $operation,
            actor: $this->administrator
        );

        $response = $this->postJson(
            '/api/v1/piecework-rates',
            [
                'employee_id' => $employee->id,
                'operation_process_id' => $operation->id,
                'amount_per_piece' => 5.0000,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');
    }
}