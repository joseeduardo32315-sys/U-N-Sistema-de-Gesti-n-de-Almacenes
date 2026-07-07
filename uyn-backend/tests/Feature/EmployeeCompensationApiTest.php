<?php

namespace Tests\Feature;

use App\Models\EmployeeCompensation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesPayrollTestData;
use Tests\TestCase;

class EmployeeCompensationApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPayrollTestData;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->signInAsPayrollAdministrator();
    }

    public function test_administrator_can_create_piecework_compensation(): void
    {
        $employee = $this->makeActiveEmployee();

        $response = $this->postJson(
            '/api/v1/employee-compensations',
            [
                'employee_id' => $employee->id,
                'payment_type' => 'piecework',
                'effective_from' => '2026-07-01',
                'notes' => 'Pago por destajo para pruebas.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.payment_type', 'piecework')
            ->assertJsonPath('data.payment_frequency', null)
            ->assertJsonPath('data.fixed_amount', null)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('employee_compensations', [
            'employee_id' => $employee->id,
            'payment_type' => 'piecework',
            'status' => 'active',
            'created_by' => $this->administrator->id,
        ]);
    }

    public function test_fixed_compensation_requires_frequency_and_fixed_amount(): void
    {
        $employee = $this->makeActiveEmployee();

        $response = $this->postJson(
            '/api/v1/employee-compensations',
            [
                'employee_id' => $employee->id,
                'payment_type' => 'fixed',
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_frequency',
                'fixed_amount',
            ]);
    }

    public function test_active_compensations_cannot_overlap_for_same_employee(): void
    {
        $employee = $this->makeActiveEmployee();

        $this->postJson(
            '/api/v1/employee-compensations',
            [
                'employee_id' => $employee->id,
                'payment_type' => 'piecework',
                'effective_from' => '2026-07-01',
            ]
        )->assertCreated();

        $response = $this->postJson(
            '/api/v1/employee-compensations',
            [
                'employee_id' => $employee->id,
                'payment_type' => 'fixed',
                'payment_frequency' => 'weekly',
                'fixed_amount' => 2500.00,
                'effective_from' => '2026-07-01',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');
    }

    public function test_administrator_can_deactivate_compensation(): void
    {
        $employee = $this->makeActiveEmployee();

        $compensation = $this->createPieceworkCompensation(
            employee: $employee,
            actor: $this->administrator
        );

        $response = $this->patchJson(
            "/api/v1/employee-compensations/{$compensation->id}",
            [
                'status' => 'inactive',
                'notes' => 'Desactivada para pruebas.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('employee_compensations', [
            'id' => $compensation->id,
            'status' => 'inactive',
        ]);
    }

    public function test_user_without_payroll_permission_cannot_create_compensation(): void
    {
        $employee = $this->makeActiveEmployee();

        $userWithoutPermission = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($userWithoutPermission, ['*']);

        $response = $this->postJson(
            '/api/v1/employee-compensations',
            [
                'employee_id' => $employee->id,
                'payment_type' => 'piecework',
                'effective_from' => '2026-07-01',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'employee_compensations',
            0
        );
    }
}