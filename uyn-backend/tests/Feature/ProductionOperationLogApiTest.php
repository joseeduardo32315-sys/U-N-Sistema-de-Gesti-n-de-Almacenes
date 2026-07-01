<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\OperationProcess;
use App\Models\PieceType;
use App\Models\Process;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use App\Models\ProductionOrder;
use App\Models\Size;
use App\Models\SpecialProcessPiece;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\PieceTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\WorkflowProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionOperationLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            AreaSeeder::class,
            SizeSeeder::class,
            WorkflowProcessSeeder::class,
            PieceTypeSeeder::class,
        ]);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.operations',
            'email' => 'admin.operations@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function authenticateAsRole(
        string $roleName,
        string $username
    ): User {
        $user = User::factory()->create([
            'username' => $username,
            'email' => "{$username}@uyn.test",
            'status' => 'active',
        ]);

        $user->assignRole($roleName);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function getArea(string $name): Area
    {
        return Area::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function getProcess(string $name): Process
    {
        return Process::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function getOperation(string $processName): OperationProcess
    {
        $process = $this->getProcess($processName);

        return OperationProcess::query()
            ->where('process_id', $process->id)
            ->orderBy('flow_order')
            ->firstOrFail();
    }

    private function getPieceType(string $name): PieceType
    {
        return PieceType::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function getSize(string $name): Size
    {
        return Size::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function createEmployee(
        string $areaName,
        array $attributes = []
    ): Employee {
        return Employee::factory()->create(array_merge([
            'area_id' => $this->getArea($areaName)->id,
            'worker_type' => 'internal',
            'status' => 'active',
        ], $attributes));
    }

    private function createReceivedBordadoMovement(
        User $creator,
        array $attributes = []
    ): array {
        $order = ProductionOrder::factory()->create([
            'created_by' => $creator->id,
            'status' => 'registered',
            'priority' => 'normal',
        ]);

        $model = GarmentModel::factory()->create([
            'status' => 'active',
        ]);

        $bordadoArea = $this->getArea('Bordado');

        $cut = GarmentCut::factory()->create([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $bordadoArea->id,
            'status' => 'in_progress',
            'total_sizes' => 2,
            'base_pieces_per_size' => 50,
            'total_pieces' => 100,
        ]);

        $cut->cutSizes()->createMany([
            [
                'size_id' => $this->getSize('2')->id,
                'total_pieces' => 50,
            ],
            [
                'size_id' => $this->getSize('4')->id,
                'total_pieces' => 50,
            ],
        ]);

        $bordado = $this->getProcess('Bordado');

        $specialPiece = SpecialProcessPiece::create([
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $this->getPieceType('Delantero')->id,
            'process_id' => $bordado->id,
            'current_area_id' => $bordadoArea->id,
            'status' => 'in_progress',
            'notes' => 'Delantero asignado a bordado.',
        ]);

        $movement = ProductionMovement::factory()->create(array_merge([
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'complement_id' => null,
            'process_id' => $bordado->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'from_area_id' => $this->getArea('Diseño')->id,
            'to_area_id' => $bordadoArea->id,
            'quantity' => 100,
            'status' => 'received',
            'start_time' => now(),
            'created_by' => $creator->id,
            'received_by' => $creator->id,
        ], $attributes));

        return [
            $cut->fresh(),
            $specialPiece->fresh(),
            $movement->fresh(),
        ];
    }

    private function operationLogsUrl(
        ProductionMovement $movement
    ): string {
        return "/api/v1/production-movements/{$movement->id}/operation-logs";
    }

    private function operationLogUrl(
        ProductionOperationLog $operationLog
    ): string {
        return "/api/v1/production-operation-logs/{$operationLog->id}";
    }

    private function assignEmployee(
        ProductionMovement $movement,
        Employee $employee,
        array $attributes = []
    ): ProductionOperationLog {
        $response = $this->postJson(
            $this->operationLogsUrl($movement),
            array_merge([
                'employee_id' => $employee->id,
                'notes' => 'Asignación para prueba.',
            ], $attributes)
        );

        $response->assertCreated();

        return ProductionOperationLog::query()
            ->latest('id')
            ->firstOrFail();
    }

    public function test_administrator_can_assign_employee_to_received_movement(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado', [
            'name' => 'María Hernández',
        ]);

        $response = $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
                'notes' => 'Responsable del bordado frontal.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Trabajador asignado a la operación correctamente.'
            )
            ->assertJsonPath('data.quantity_processed', 0)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.employee.name', 'María Hernández')
            ->assertJsonPath('data.operation_process.name', 'Bordado')
            ->assertJsonPath(
                'data.production_movement.id',
                $movement->id
            )
            ->assertJsonPath(
                'data.production_movement.status',
                'received'
            );

        $operationLog = ProductionOperationLog::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('production_operation_logs', [
            'id' => $operationLog->id,
            'production_movement_id' => $movement->id,
            'operation_process_id' => $movement->operation_process_id,
            'employee_id' => $employee->id,
            'quantity_processed' => 0,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-operation-logs',
            'action' => 'assigned',
            'subject_type' => ProductionOperationLog::class,
            'subject_id' => $operationLog->id,
        ]);
    }

    public function test_administrator_can_register_partial_progress(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $operationLog = $this->assignEmployee(
            $movement,
            $employee
        );

        $response = $this->patchJson(
            $this->operationLogUrl($operationLog),
            [
                'start' => true,
                'quantity_processed' => 40,
                'stitches_count' => 6400,
                'notes' => 'Primer avance de bordado.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Avance de operación actualizado correctamente.'
            )
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.quantity_processed', 40)
            ->assertJsonPath('data.stitches_count', 6400)
            ->assertJsonPath(
                'data.notes',
                'Primer avance de bordado.'
            )
            ->assertJsonPath(
                'data.production_movement.status',
                'in_progress'
            );

        $this->assertDatabaseHas('production_operation_logs', [
            'id' => $operationLog->id,
            'quantity_processed' => 40,
            'stitches_count' => 6400,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-operation-logs',
            'action' => 'updated',
            'subject_type' => ProductionOperationLog::class,
            'subject_id' => $operationLog->id,
        ]);
    }

    public function test_administrator_can_complete_single_worker_operation_and_movement(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $operationLog = $this->assignEmployee(
            $movement,
            $employee
        );

        $response = $this->patchJson(
            $this->operationLogUrl($operationLog),
            [
                'complete' => true,
                'quantity_processed' => 100,
                'stitches_count' => 16000,
                'applications_count' => 100,
                'notes' => 'Bordado finalizado.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Operación completada correctamente.'
            )
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.quantity_processed', 100)
            ->assertJsonPath('data.stitches_count', 16000)
            ->assertJsonPath('data.applications_count', 100)
            ->assertJsonPath(
                'data.production_movement.status',
                'completed'
            );

        $this->assertDatabaseHas('production_operation_logs', [
            'id' => $operationLog->id,
            'quantity_processed' => 100,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'status' => 'in_progress',
            'current_area_id' => $this->getArea('Bordado')->id,
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-operation-logs',
            'action' => 'completed',
            'subject_type' => ProductionOperationLog::class,
            'subject_id' => $operationLog->id,
        ]);
    }

    public function test_two_workers_can_complete_operation_with_split_quantities(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employeeA = $this->createEmployee('Bordado');
        $employeeB = $this->createEmployee('Bordado');

        $operationLogA = $this->assignEmployee(
            $movement,
            $employeeA
        );

        $operationLogB = $this->assignEmployee(
            $movement,
            $employeeB
        );

        $this->patchJson(
            $this->operationLogUrl($operationLogA),
            [
                'complete' => true,
                'quantity_processed' => 40,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.production_movement.status',
                'in_progress'
            );

        $this->patchJson(
            $this->operationLogUrl($operationLogB),
            [
                'complete' => true,
                'quantity_processed' => 60,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.production_movement.status',
                'completed'
            );

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'completed',
        ]);
    }

    public function test_processed_quantities_cannot_exceed_movement_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employeeA = $this->createEmployee('Bordado');
        $employeeB = $this->createEmployee('Bordado');

        $operationLogA = $this->assignEmployee(
            $movement,
            $employeeA
        );

        $operationLogB = $this->assignEmployee(
            $movement,
            $employeeB
        );

        $this->patchJson(
            $this->operationLogUrl($operationLogA),
            [
                'quantity_processed' => 70,
            ]
        )->assertOk();

        $this->patchJson(
            $this->operationLogUrl($operationLogB),
            [
                'quantity_processed' => 31,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_processed');

        $this->assertDatabaseHas('production_operation_logs', [
            'id' => $operationLogB->id,
            'quantity_processed' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_processed_quantity_cannot_decrease(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $operationLog = $this->assignEmployee(
            $movement,
            $employee
        );

        $this->patchJson(
            $this->operationLogUrl($operationLog),
            [
                'quantity_processed' => 40,
            ]
        )->assertOk();

        $this->patchJson(
            $this->operationLogUrl($operationLog),
            [
                'quantity_processed' => 30,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_processed');
    }

    public function test_operation_cannot_be_completed_without_processed_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $operationLog = $this->assignEmployee(
            $movement,
            $employee
        );

        $this->patchJson(
            $this->operationLogUrl($operationLog),
            [
                'complete' => true,
                'quantity_processed' => 0,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_processed');

        $this->assertDatabaseHas('production_operation_logs', [
            'id' => $operationLog->id,
            'quantity_processed' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_same_employee_cannot_have_two_active_assignments_in_same_movement(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
            ]
        )->assertCreated();

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseCount(
            'production_operation_logs',
            1
        );
    }

    public function test_employee_must_belong_to_destination_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $maquilaEmployee = $this->createEmployee('Maquila');

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $maquilaEmployee->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseCount(
            'production_operation_logs',
            0
        );
    }

    public function test_inactive_employee_cannot_be_assigned(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $inactiveEmployee = $this->createEmployee('Bordado', [
            'status' => 'inactive',
        ]);

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $inactiveEmployee->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseCount(
            'production_operation_logs',
            0
        );
    }

    public function test_bordado_manager_can_assign_employee_in_bordado_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $bordadoManager = $this->authenticateAsRole(
            'Encargado de bordado',
            'manager.bordado'
        );

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $employee->id);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $bordadoManager->id,
            'module' => 'production-operation-logs',
            'action' => 'assigned',
        ]);
    }

    public function test_manager_cannot_operate_movement_from_different_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $this->authenticateAsRole(
            'Encargado de maquila',
            'manager.maquila'
        );

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
            ]
        )->assertForbidden();

        $this->assertDatabaseCount(
            'production_operation_logs',
            0
        );
    }

    public function test_supervisor_can_view_operation_logs_but_cannot_assign(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $this->assignEmployee($movement, $employee);

        $supervisor = $this->authenticateAsRole(
            'Usuario de consulta/supervisión',
            'supervisor.operations'
        );

        $this->getJson($this->operationLogsUrl($movement))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee.id', $employee->id)
            ->assertJsonPath('data.0.status', 'pending');

        $this->postJson(
            $this->operationLogsUrl($movement),
            [
                'employee_id' => $employee->id,
            ]
        )->assertForbidden();

        $this->assertNotNull($supervisor);
    }

    public function test_operation_logs_can_be_seen_in_movement_detail(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] = $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado');

        $operationLog = $this->assignEmployee(
            $movement,
            $employee
        );

        $this->getJson(
            "/api/v1/production-movements/{$movement->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.operation_logs_count', 1)
            ->assertJsonCount(1, 'data.operation_logs')
            ->assertJsonPath(
                'data.operation_logs.0.id',
                $operationLog->id
            )
            ->assertJsonPath(
                'data.operation_logs.0.employee.id',
                $employee->id
            )
            ->assertJsonPath(
                'data.operation_logs.0.operation_process.name',
                'Bordado'
            );
    }
}