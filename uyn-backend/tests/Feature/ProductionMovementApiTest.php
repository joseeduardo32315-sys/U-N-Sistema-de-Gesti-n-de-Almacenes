<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\GarmentModel;
use App\Models\OperationProcess;
use App\Models\PieceType;
use App\Models\Process;
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

class ProductionMovementApiTest extends TestCase
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
            'username' => 'admin.movements',
            'email' => 'admin.movements@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
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

    private function getSize(string $name): Size
    {
        return Size::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function getPieceType(string $name): PieceType
    {
        return PieceType::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function createCut(
        User $creator,
        string $areaName = 'Corte',
        string $status = 'registered',
        array $attributes = []
    ): GarmentCut {
        $order = ProductionOrder::factory()->create([
            'created_by' => $creator->id,
            'status' => 'registered',
            'priority' => 'normal',
        ]);

        $model = GarmentModel::factory()->create([
            'status' => 'active',
        ]);

        $cut = GarmentCut::factory()->create(array_merge([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $this->getArea($areaName)->id,
            'status' => $status,
            'total_sizes' => 2,
            'base_pieces_per_size' => 50,
            'total_pieces' => 100,
        ], $attributes));

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

        return $cut->fresh([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',
        ]);
    }

    private function configureClassification(
        GarmentCut $cut
    ): array {
        $designArea = $this->getArea('Diseño');

        $complement = GarmentCutComplement::create([
            'garment_cut_id' => $cut->id,
            'current_area_id' => $designArea->id,
            'status' => 'pending',
            'notes' => 'Complemento para pruebas.',
        ]);

        $specialPiece = SpecialProcessPiece::create([
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $this->getPieceType('Delantero')->id,
            'process_id' => $this->getProcess('Bordado')->id,
            'current_area_id' => $designArea->id,
            'status' => 'pending',
            'notes' => 'Delantero para bordado.',
        ]);

        return [$complement, $specialPiece];
    }

    public function test_administrator_can_dispatch_cut_from_corte_to_diseno(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin, 'Corte', 'registered', [
            'code' => 'CUT-MOV-001',
        ]);

        $designProcess = $this->getProcess('Diseño');
        $designOperation = $this->getOperation('Diseño');

        $response = $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $designProcess->id,
            'operation_process_id' => $designOperation->id,
            'quantity' => 100,
            'notes' => 'Se envía el corte al área de Diseño.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Envío de producción registrado correctamente.'
            )
            ->assertJsonPath('data.target_type', 'cut')
            ->assertJsonPath('data.quantity', 100)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.from_area.name', 'Corte')
            ->assertJsonPath('data.to_area.name', 'Diseño')
            ->assertJsonPath('data.process.name', 'Diseño')
            ->assertJsonPath('data.operation_process.name', $designOperation->name)
            ->assertJsonPath('data.target.current_area.name', 'Corte');

        $movement = ProductionMovement::query()
            ->where('garment_cut_id', $cut->id)
            ->firstOrFail();

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'from_area_id' => $this->getArea('Corte')->id,
            'to_area_id' => $this->getArea('Diseño')->id,
            'quantity' => 100,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-movements',
            'action' => 'created',
            'subject_type' => ProductionMovement::class,
            'subject_id' => $movement->id,
        ]);
    }

    public function test_receiving_movement_updates_cut_area_and_status(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $movement = ProductionMovement::factory()->create([
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Diseño')->id,
            'from_area_id' => $this->getArea('Corte')->id,
            'to_area_id' => $this->getArea('Diseño')->id,
            'quantity' => 100,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/receive"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Recepción de producción confirmada correctamente.'
            )
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.garment_cut.status', 'in_progress')
            ->assertJsonPath('data.target.current_area.name', 'Diseño')
            ->assertJsonPath('data.received_by.id', $admin->id);

        $this->assertDatabaseHas('garment_cuts', [
            'id' => $cut->id,
            'current_area_id' => $this->getArea('Diseño')->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('production_movements', [
            'id' => $movement->id,
            'status' => 'received',
            'received_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-movements',
            'action' => 'received',
            'subject_type' => ProductionMovement::class,
            'subject_id' => $movement->id,
        ]);
    }

    public function test_pending_movement_cannot_be_received_twice(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $movement = ProductionMovement::factory()->create([
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Diseño')->id,
            'from_area_id' => $this->getArea('Corte')->id,
            'to_area_id' => $this->getArea('Diseño')->id,
            'quantity' => 100,
            'status' => 'received',
            'created_by' => $admin->id,
            'received_by' => $admin->id,
        ]);

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/receive"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_movement');
    }

    public function test_administrator_can_dispatch_special_piece_to_configured_process(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut(
            $admin,
            'Diseño',
            'in_progress',
            ['code' => 'CUT-ESPECIAL-001']
        );

        [, $specialPiece] = $this->configureClassification($cut);

        $bordado = $this->getProcess('Bordado');

        $response = $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'process_id' => $bordado->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'quantity' => 100,
            'notes' => 'Se envía delantero a Bordado.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'special_piece')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.from_area.name', 'Diseño')
            ->assertJsonPath('data.to_area.name', 'Bordado')
            ->assertJsonPath(
                'data.target.piece_type.name',
                'Delantero'
            )
            ->assertJsonPath(
                'data.target.special_process.name',
                'Bordado'
            );

        $movement = ProductionMovement::query()
            ->latest('id')
            ->firstOrFail();

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/receive"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.target.current_area.name', 'Bordado')
            ->assertJsonPath('data.target.status', 'in_progress');

        $this->assertDatabaseHas('special_process_pieces', [
            'id' => $specialPiece->id,
            'current_area_id' => $this->getArea('Bordado')->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_administrator_can_dispatch_complement_from_diseno_to_maquila(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut(
            $admin,
            'Diseño',
            'in_progress',
            ['code' => 'CUT-COMPLEMENTO-001']
        );

        [$complement] = $this->configureClassification($cut);

        $maquila = $this->getProcess('Maquila');

        $response = $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'complement',
            'complement_id' => $complement->id,
            'process_id' => $maquila->id,
            'operation_process_id' => $this->getOperation('Maquila')->id,
            'quantity' => 100,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'complement')
            ->assertJsonPath('data.from_area.name', 'Diseño')
            ->assertJsonPath('data.to_area.name', 'Maquila')
            ->assertJsonPath('data.target.status', 'pending');

        $movement = ProductionMovement::query()
            ->latest('id')
            ->firstOrFail();

        $this->postJson(
            "/api/v1/production-movements/{$movement->id}/receive"
        )
            ->assertOk()
            ->assertJsonPath('data.target.current_area.name', 'Maquila')
            ->assertJsonPath('data.target.status', 'in_progress');

        $this->assertDatabaseHas('garment_cut_complements', [
            'id' => $complement->id,
            'current_area_id' => $this->getArea('Maquila')->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_movement_rejects_wrong_quantity(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Diseño')->id,
            'quantity' => 99,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('production_movements', 0);
    }

    public function test_movement_rejects_invalid_transition(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Bordado')->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'quantity' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('process_id');

        $this->assertDatabaseCount('production_movements', 0);
    }

    public function test_special_piece_must_go_to_configured_process(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut(
            $admin,
            'Diseño',
            'in_progress'
        );

        [, $specialPiece] = $this->configureClassification($cut);

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'process_id' => $this->getProcess('Maquila')->id,
            'operation_process_id' => $this->getOperation('Maquila')->id,
            'quantity' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('process_id');

        $this->assertDatabaseCount('production_movements', 0);
    }

    public function test_operation_must_belong_to_selected_process(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'quantity' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation_process_id');
    }

    public function test_target_cannot_have_two_active_movements(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $payload = [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Diseño')->id,
            'quantity' => 100,
        ];

        $this->postJson('/api/v1/production-movements', $payload)
            ->assertCreated();

        $this->postJson('/api/v1/production-movements', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_type');

        $this->assertDatabaseCount('production_movements', 1);
    }

    public function test_administrator_can_filter_production_movements(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut(
            $admin,
            'Diseño',
            'in_progress',
            ['code' => 'CUT-FILTRO-MOV-001']
        );

        [, $specialPiece] = $this->configureClassification($cut);

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'special_process_piece_id' => $specialPiece->id,
            'process_id' => $this->getProcess('Bordado')->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'quantity' => 100,
            'notes' => 'Movimiento para filtro.',
        ])->assertCreated();

        $this->getJson(
            "/api/v1/production-movements"
            . "?garment_cut_id={$cut->id}"
            . '&target_type=special_piece'
            . '&status=pending'
            . '&search=FILTRO-MOV'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_type', 'special_piece')
            ->assertJsonPath('data.0.garment_cut.code', 'CUT-FILTRO-MOV-001');
    }

    public function test_consultation_user_can_view_movements_but_cannot_dispatch(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createCut($admin);

        $supervisor = User::factory()->create([
            'username' => 'supervisor.movements',
            'email' => 'supervisor.movements@uyn.test',
            'status' => 'active',
        ]);

        $supervisor->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($supervisor, ['*']);

        $this->getJson('/api/v1/production-movements')
            ->assertOk();

        $this->postJson('/api/v1/production-movements', [
            'garment_cut_id' => $cut->id,
            'target_type' => 'cut',
            'process_id' => $this->getProcess('Diseño')->id,
            'operation_process_id' => $this->getOperation('Diseño')->id,
            'quantity' => 100,
        ])->assertForbidden();
    }
}