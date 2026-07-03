<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\OperationProcess;
use App\Models\PieceType;
use App\Models\Process;
use App\Models\ProductionIncident;
use App\Models\ProductionMovement;
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
use App\Models\ProductionOperationLog;


class ProductionIncidentApiTest extends TestCase
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
            'username' => 'admin.incidents',
            'email' => 'admin.incidents@uyn.test',
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
        array $cutAttributes = [],
        array $movementAttributes = []
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
        $bordadoProcess = $this->getProcess('Bordado');

        $cut = GarmentCut::factory()->create(array_merge([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $bordadoArea->id,
            'status' => 'in_progress',
            'total_sizes' => 2,
            'base_pieces_per_size' => 50,
            'total_pieces' => 100,
        ], $cutAttributes));

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

        $specialPiece = SpecialProcessPiece::create([
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $this->getPieceType('Delantero')->id,
            'process_id' => $bordadoProcess->id,
            'current_area_id' => $bordadoArea->id,
            'status' => 'in_progress',
            'notes' => 'Delantero enviado a Bordado.',
        ]);

        $movement = ProductionMovement::factory()->create(array_merge([
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'complement_id' => null,
            'process_id' => $bordadoProcess->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'from_area_id' => $this->getArea('Diseño')->id,
            'to_area_id' => $bordadoArea->id,
            'quantity' => 100,
            'status' => 'received',
            'start_time' => now(),
            'created_by' => $creator->id,
            'received_by' => $creator->id,
        ], $movementAttributes));

        return [
            $cut->fresh(),
            $specialPiece->fresh(),
            $movement->fresh(),
        ];
    }

    private function createReceivedPreparationMovement(
        User $creator,
        array $movementAttributes = []
    ): array {
        [$cut, $specialPiece, $bordadoMovement] =
            $this->createReceivedBordadoMovement($creator);

        $preparationArea = $this->getArea('Preparación');
        $preparationProcess = $this->getProcess('Preparación');

        /*
        * Simula que Bordado ya concluyó y la pieza llegó
        * correctamente a Preparación.
        */
        $bordadoMovement->update([
            'status' => 'completed',
            'end_time' => now(),
        ]);

        $specialPiece->update([
            'current_area_id' => $preparationArea->id,
            'status' => 'in_progress',
        ]);

        $movement = ProductionMovement::factory()->create(
            array_merge([
                'garment_cut_id' => $cut->id,

                'return_incident_id' => null,

                'target_type' => 'special_piece',
                'special_process_piece_id' => $specialPiece->id,
                'complement_id' => null,

                'process_id' => $preparationProcess->id,
                'operation_process_id' => $this->getOperation(
                    'Preparación'
                )->id,

                'from_area_id' => $this->getArea('Bordado')->id,
                'to_area_id' => $preparationArea->id,

                'quantity' => 100,
                'status' => 'received',
                'start_time' => now(),

                'created_by' => $creator->id,
                'received_by' => $creator->id,
            ], $movementAttributes)
        );

        return [
            $cut->fresh(),
            $specialPiece->fresh(),
            $movement->fresh(),
        ];
    }

    private function createIncident(
        ProductionMovement $movement,
        array $attributes = []
    ): ProductionIncident {
        $payload = array_merge([
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Incidencia de calidad para pruebas.',
            'notes' => 'Pendiente de revisión.',
        ], $attributes);

        $this->postJson(
            '/api/v1/production-incidents',
            $payload
        )->assertCreated();

        return ProductionIncident::query()
            ->latest('id')
            ->firstOrFail();
    }

    public function test_administrator_can_create_quality_incident_and_block_movement(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $employee = $this->createEmployee('Bordado', [
            'name' => 'María Hernández',
        ]);

        $response = $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Cinco piezas presentan desprendimiento parcial.',
            'responsible_employee_id' => $employee->id,
            'notes' => 'Se requiere revisión antes de continuar.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Incidencia de producción registrada correctamente.'
            )
            ->assertJsonPath('data.incident_type', 'quality')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.quantity_affected', 5)
            ->assertJsonPath(
                'data.production_movement.status',
                'with_incident'
            )
            ->assertJsonPath(
                'data.production_movement.target.status',
                'with_incident'
            )
            ->assertJsonPath(
                'data.responsible_employee.id',
                $employee->id
            );

        $incident = ProductionIncident::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('production_incidents', [
            'id' => $incident->id,
            'garment_cut_id' => $movement->garment_cut_id,
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'responsible_employee_id' => $employee->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'with_incident',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'status' => 'with_incident',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-incidents',
            'action' => 'created',
            'subject_type' => ProductionIncident::class,
            'subject_id' => $incident->id,
        ]);
    }

    public function test_delay_incident_sets_movement_and_target_as_delayed(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $response = $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'delay',
            'quantity_affected' => 0,
            'description' => 'El área de Bordado presenta retraso operativo.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.incident_type', 'delay')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath(
                'data.production_movement.status',
                'delayed'
            )
            ->assertJsonPath(
                'data.production_movement.target.status',
                'delayed'
            );

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'delayed',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'status' => 'delayed',
        ]);
    }

    public function test_administrator_can_update_open_incident(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $incident = $this->createIncident($movement);

        $response = $this->patchJson(
            "/api/v1/production-incidents/{$incident->id}",
            [
                'quantity_affected' => 8,
                'description' => 'Se detectaron ocho piezas con defecto.',
                'notes' => 'Se actualiza la evaluación de calidad.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Incidencia de producción actualizada correctamente.'
            )
            ->assertJsonPath('data.quantity_affected', 8)
            ->assertJsonPath(
                'data.description',
                'Se detectaron ocho piezas con defecto.'
            )
            ->assertJsonPath(
                'data.notes',
                'Se actualiza la evaluación de calidad.'
            )
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('production_incidents', [
            'id' => $incident->id,
            'quantity_affected' => 8,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-incidents',
            'action' => 'updated',
            'subject_type' => ProductionIncident::class,
            'subject_id' => $incident->id,
        ]);
    }

    public function test_administrator_can_resolve_incident_and_restore_workflow(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $incident = $this->createIncident($movement);

        $response = $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/resolve",
            [
                'notes' => 'Las piezas fueron revisadas y corregidas.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Incidencia de producción resuelta correctamente.'
            )
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by.id', $admin->id)
            ->assertJsonPath(
                'data.notes',
                'Las piezas fueron revisadas y corregidas.'
            );

        $this->assertDatabaseHas('production_incidents', [
            'id' => $incident->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-incidents',
            'action' => 'resolved',
            'subject_type' => ProductionIncident::class,
            'subject_id' => $incident->id,
        ]);
    }

    public function test_resolved_incident_cannot_be_updated_again(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $incident = $this->createIncident($movement);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/resolve",
            [
                'notes' => 'Incidencia atendida correctamente.',
            ]
        )->assertOk();

        $this->patchJson(
            "/api/v1/production-incidents/{$incident->id}",
            [
                'description' => 'Intento de modificación posterior.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_incident');
    }

    public function test_incident_quantity_cannot_exceed_movement_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'damage',
            'quantity_affected' => 101,
            'description' => 'Cantidad mayor a las piezas enviadas.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_affected');

        $this->assertDatabaseCount('production_incidents', 0);
    }

    public function test_open_incident_quantities_cannot_exceed_movement_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $this->createIncident($movement, [
            'incident_type' => 'quality',
            'quantity_affected' => 70,
        ]);

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'loss',
            'quantity_affected' => 31,
            'description' => 'Faltante adicional detectado.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_affected');

        $this->assertDatabaseCount('production_incidents', 1);
    }

    public function test_delay_incident_requires_zero_affected_quantity(): void
    {
        $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement(
                User::query()->firstOrFail()
            );

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'delay',
            'quantity_affected' => 1,
            'description' => 'Retraso registrado incorrectamente.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_affected');

        $this->assertDatabaseCount('production_incidents', 0);
    }

    public function test_responsible_employee_must_belong_to_origin_or_destination_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $maquilaEmployee = $this->createEmployee('Maquila');

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Incidencia con responsable incorrecto.',
            'responsible_employee_id' => $maquilaEmployee->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('responsible_employee_id');

        $this->assertDatabaseCount('production_incidents', 0);
    }

    public function test_bordado_manager_can_create_incident_in_bordado_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $bordadoManager = $this->authenticateAsRole(
            'Encargado de bordado',
            'manager.incidents.bordado'
        );

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Incidencia detectada en el área de Bordado.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $bordadoManager->id,
            'module' => 'production-incidents',
            'action' => 'created',
        ]);
    }

    public function test_manager_cannot_create_incident_for_different_area(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $this->authenticateAsRole(
            'Encargado de maquila',
            'manager.incidents.maquila'
        );

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Intento no autorizado.',
        ])->assertForbidden();

        $this->assertDatabaseCount('production_incidents', 0);
    }

    public function test_supervisor_can_view_incidents_but_cannot_create_them(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $this->createIncident($movement);

        $this->authenticateAsRole(
            'Usuario de consulta/supervisión',
            'supervisor.incidents'
        );

        $this->getJson('/api/v1/production-incidents')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Intento no autorizado del supervisor.',
        ])->assertForbidden();
    }

    public function test_administrator_can_filter_and_search_incidents(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement(
                $admin,
                [
                    'code' => 'CUT-INC-FILTER-001',
                ]
            );

        $this->createIncident($movement, [
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Incidencia para filtro.',
        ]);

        $this->getJson(
            '/api/v1/production-incidents'
            . "?garment_cut_id={$movement->garment_cut_id}"
            . "&production_movement_id={$movement->id}"
            . '&incident_type=quality'
            . '&status=open'
            . '&search=FILTER'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.incident_type', 'quality')
            ->assertJsonPath(
                'data.0.garment_cut.code',
                'CUT-INC-FILTER-001'
            )
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_resolved_loss_reduces_effective_movement_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $loss = $this->createIncident($movement, [
            'incident_type' => 'loss',
            'quantity_affected' => 10,
            'description' => 'Se extraviaron diez piezas durante el proceso.',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$loss->id}/resolve",
            [
                'notes' => 'La pérdida fue confirmada y registrada.',
            ]
        )->assertOk();

        $this->getJson(
            "/api/v1/production-movements/{$movement->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.quantity', 100)
            ->assertJsonPath('data.resolved_loss_quantity', 10)
            ->assertJsonPath('data.effective_quantity', 90);

        $employee = $this->createEmployee('Bordado');

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/operation-logs",
            [
                'employee_id' => $employee->id,
            ]
        )->assertCreated();

        $operationLog = ProductionOperationLog::query()
            ->latest('id')
            ->firstOrFail();

        $this->patchJson(
            "/api/v1/production-operation-logs/{$operationLog->id}",
            [
                'quantity_processed' => 91,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_processed');

        $this->patchJson(
            "/api/v1/production-operation-logs/{$operationLog->id}",
            [
                'complete' => true,
                'quantity_processed' => 90,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.production_movement.status',
                'completed'
            );
    }

    public function test_full_resolved_loss_closes_movement_and_target(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $loss = $this->createIncident($movement, [
            'incident_type' => 'loss',
            'quantity_affected' => 100,
            'description' => 'Se confirmó la pérdida total del lote enviado.',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$loss->id}/resolve",
            [
                'notes' => 'La pérdida total fue validada por producción.',
            ]
        )->assertOk();

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'status' => 'completed',
        ]);
    }

    public function test_resolved_and_open_losses_cannot_exceed_movement_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedBordadoMovement($admin);

        $firstLoss = $this->createIncident($movement, [
            'incident_type' => 'loss',
            'quantity_affected' => 70,
            'description' => 'Primera pérdida registrada.',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$firstLoss->id}/resolve",
            [
                'notes' => 'La primera pérdida fue confirmada.',
            ]
        )->assertOk();

        $this->postJson('/api/v1/production-incidents', [
            'production_movement_id' => $movement->id,
            'incident_type' => 'loss',
            'quantity_affected' => 31,
            'description' => 'Segunda pérdida que supera el límite.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_affected');

        $this->assertDatabaseCount('production_incidents', 1);
    }

    public function test_next_movement_uses_quantity_after_resolved_loss(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedBordadoMovement($admin);

        $loss = $this->createIncident($movement, [
            'incident_type' => 'loss',
            'quantity_affected' => 10,
            'description' => 'Diez piezas se perdieron en Bordado.',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$loss->id}/resolve",
            [
                'notes' => 'Se autoriza continuar con las piezas restantes.',
            ]
        )->assertOk();

        $employee = $this->createEmployee('Bordado');

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/operation-logs",
            [
                'employee_id' => $employee->id,
            ]
        )->assertCreated();

        $operationLog = ProductionOperationLog::query()
            ->latest('id')
            ->firstOrFail();

        $this->patchJson(
            "/api/v1/production-operation-logs/{$operationLog->id}",
            [
                'complete' => true,
                'quantity_processed' => 90,
            ]
        )->assertOk();

        $preparation = $this->getProcess('Preparación');

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $movement->garment_cut_id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'process_id' => $preparation->id,
            'operation_process_id' => $this->getOperation(
                'Preparación'
            )->id,
            'quantity' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $movement->garment_cut_id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'process_id' => $preparation->id,
            'operation_process_id' => $this->getOperation(
                'Preparación'
            )->id,
            'quantity' => 90,
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 90)
            ->assertJsonPath('data.to_area.name', 'Preparación');
    }

    public function test_administrator_can_return_open_quality_incident_for_rework(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
            'quantity_affected' => 5,
            'description' => 'Se detectó defecto en el bordado.',
        ]);

        $response = $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Se devuelve el lote completo a Bordado para corrección.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Devolución para reproceso registrada correctamente.'
            )
            ->assertJsonPath('data.target_type', 'special_piece')
            ->assertJsonPath('data.return_incident_id', $incident->id)
            ->assertJsonPath('data.is_return_for_rework', true)
            ->assertJsonPath('data.quantity', 100)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.from_area.name', 'Preparación')
            ->assertJsonPath('data.to_area.name', 'Bordado')
            ->assertJsonPath('data.process.name', 'Bordado')
            ->assertJsonPath(
                'data.target.piece_type.name',
                'Delantero'
            );

        $reworkMovement = ProductionMovement::query()
            ->where('return_incident_id', $incident->id)
            ->firstOrFail();

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $reworkMovement->id,
            'return_incident_id' => $incident->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'from_area_id' => $this->getArea('Preparación')->id,
            'to_area_id' => $this->getArea('Bordado')->id,
            'quantity' => 100,
            'status' => 'pending',
        ]);

        $this->getJson(
            "/api/v1/production-incidents/{$incident->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath(
                'data.rework_movement.id',
                $reworkMovement->id
            )
            ->assertJsonPath(
                'data.rework_movement.process',
                'Bordado'
            );

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-incidents',
            'action' => 'returned_for_rework',
            'subject_type' => ProductionIncident::class,
            'subject_id' => $incident->id,
        ]);
    }

    public function test_rework_requires_operation_from_required_process(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Preparación'
                )->id,
                'notes' => 'Intento de devolución con operación incorrecta.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation_process_id');

        $this->assertDatabaseCount('production_movements', 2);
    }

    public function test_only_damage_or_quality_incidents_can_return_for_rework(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'loss',
            'quantity_affected' => 5,
            'description' => 'Se reporta pérdida de piezas.',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Intento de reproceso para pérdida.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_incident');

        $this->assertDatabaseCount('production_movements', 2);
    }

    public function test_rework_rejects_movement_that_already_has_processed_work(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $employee = $this->createEmployee('Preparación');

        ProductionOperationLog::factory()->create([
            'production_movement_id' => $movement->id,
            'operation_process_id' => $movement->operation_process_id,
            'employee_id' => $employee->id,
            'quantity_processed' => 1,
            'status' => 'in_progress',
        ]);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Intento de devolución después de registrar avance.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_incident');

        $this->assertDatabaseCount('production_movements', 2);
    }

    public function test_incident_can_only_generate_one_rework_movement(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
        ]);

        $payload = [
            'operation_process_id' => $this->getOperation(
                'Bordado'
            )->id,
            'notes' => 'Se devuelve el lote para corrección de calidad.',
        ];

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            $payload
        )->assertCreated();

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_incident');

        $this->assertDatabaseCount('production_movements', 3);
    }

    public function test_open_incident_cannot_be_resolved_until_rework_is_completed(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Se devuelve el lote a Bordado para corrección.',
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/resolve",
            [
                'notes' => 'Intento de cierre antes de concluir reproceso.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_incident');

        $this->assertDatabaseHas('production_incidents', [
            'id' => $incident->id,
            'status' => 'open',
        ]);
    }

    public function test_completed_rework_allows_original_incident_resolution(): void
    {
        $admin = $this->authenticateAdministrator();

        [, $specialPiece, $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
            'quantity_affected' => 5,
        ]);

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Se devuelve el lote a Bordado para reproceso.',
            ]
        )->assertCreated();

        $reworkMovement = ProductionMovement::query()
            ->where('return_incident_id', $incident->id)
            ->firstOrFail();

        $this->postJson(
            "/api/v1/production-movements/{$reworkMovement->id}/receive"
        )->assertOk();

        $employee = $this->createEmployee('Bordado');

        $this->postJson(
            "/api/v1/production-movements/{$reworkMovement->id}/operation-logs",
            [
                'employee_id' => $employee->id,
            ]
        )->assertCreated();

        $operationLog = ProductionOperationLog::query()
            ->latest('id')
            ->firstOrFail();

        $this->patchJson(
            "/api/v1/production-operation-logs/{$operationLog->id}",
            [
                'complete' => true,
                'quantity_processed' => 100,
                'notes' => 'Corrección de bordado completada.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.production_movement.status',
                'completed'
            );

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/resolve",
            [
                'notes' => 'El reproceso fue concluido y validado.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath(
                'data.rework_movement.status',
                'completed'
            );

        $this->assertDatabaseHas('production_incidents', [
            'id' => $incident->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $reworkMovement->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'current_area_id' => $this->getArea('Bordado')->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_preparation_manager_can_return_incident_for_rework(): void
    {
        $admin = $this->authenticateAdministrator();

        [, , $movement] =
            $this->createReceivedPreparationMovement($admin);

        $incident = $this->createIncident($movement, [
            'incident_type' => 'quality',
        ]);

        $preparationManager = $this->authenticateAsRole(
            'Encargado de preparación/terminado',
            'manager.preparation.rework'
        );

        $this->postJson(
            "/api/v1/production-incidents/{$incident->id}/return-for-rework",
            [
                'operation_process_id' => $this->getOperation(
                    'Bordado'
                )->id,
                'notes' => 'Se devuelve el lote a Bordado para reproceso.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.to_area.name', 'Bordado');

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $preparationManager->id,
            'module' => 'production-incidents',
            'action' => 'returned_for_rework',
            'subject_type' => ProductionIncident::class,
            'subject_id' => $incident->id,
        ]);
    }
}