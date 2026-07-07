<?php

namespace Tests\Concerns;

use App\Models\Area;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmbroideryPaymentSetting;
use App\Models\OperationProcess;
use App\Models\PieceworkRate;
use App\Models\ProductionOperationLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

trait CreatesPayrollTestData
{
    protected function signInAsPayrollAdministrator(): User
    {
        /** @var \Tests\TestCase $testCase */
        $testCase = $this;

        $testCase->seed(RolePermissionSeeder::class);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole('Administrador');

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function makeActiveEmployee(
        ?Area $area = null
    ): Employee {
        $area ??= Area::factory()->create();

        return Employee::factory()->create([
            'area_id' => $area->id,
            'status' => 'active',
        ]);
    }

    protected function makeOperation(
        string $calculationType = 'per_piece'
    ): OperationProcess {
        return OperationProcess::factory()->create([
            'payroll_calculation_type' => $calculationType,
        ]);
    }

    protected function createPieceworkCompensation(
        Employee $employee,
        User $actor,
        string $effectiveFrom = '2026-07-01'
    ): EmployeeCompensation {
        return EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
            'payment_type' => 'piecework',
            'payment_frequency' => null,
            'fixed_amount' => null,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
    }

    protected function createFixedCompensation(
        Employee $employee,
        User $actor,
        string $effectiveFrom = '2026-07-01'
    ): EmployeeCompensation {
        return EmployeeCompensation::factory()
            ->fixed()
            ->create([
                'employee_id' => $employee->id,
                'payment_type' => 'fixed',
                'payment_frequency' => 'weekly',
                'fixed_amount' => 2500.00,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
    }

    protected function createPieceworkRate(
        Employee $employee,
        OperationProcess $operation,
        User $actor,
        array $overrides = []
    ): PieceworkRate {
        return PieceworkRate::factory()->create([
            'employee_id' => $employee->id,
            'operation_process_id' => $operation->id,
            'amount_per_piece' => '4.5000',
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'status' => 'active',
            'created_by' => $actor->id,
            ...$overrides,
        ]);
    }

    protected function createEmbroideryPaymentSetting(
        OperationProcess $operation,
        User $actor,
        array $overrides = []
    ): EmbroideryPaymentSetting {
        return EmbroideryPaymentSetting::factory()->create([
            'operation_process_id' => $operation->id,
            'stitch_price' => '0.00010000',
            'application_price' => '1.0000',
            'payment_percentage' => '0.300000',
            'minimum_payment_per_piece' => '0.7500',
            'default_payment_per_piece' => '0.7500',
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'status' => 'active',
            'created_by' => $actor->id,
            ...$overrides,
        ]);
    }

    protected function makeProductionOperationLog(
        Employee $employee,
        OperationProcess $operation
    ): ProductionOperationLog {
        return ProductionOperationLog::factory()->create([
            'employee_id' => $employee->id,
            'operation_process_id' => $operation->id,
        ]);
    }
}