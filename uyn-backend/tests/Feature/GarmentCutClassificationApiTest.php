<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\GarmentModel;
use App\Models\PieceType;
use App\Models\Process;
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

class GarmentCutClassificationApiTest extends TestCase
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
            'username' => 'admin.classification',
            'email' => 'admin.classification@uyn.test',
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

    private function createDesignCut(
        User $creator,
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

        $designArea = $this->getArea('Diseño');

        $cut = GarmentCut::factory()->create(array_merge([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $designArea->id,

            'status' => 'in_progress',

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

    private function classificationUrl(GarmentCut $cut): string
    {
        return "/api/v1/garment-cuts/{$cut->id}/classification";
    }

    public function test_administrator_can_view_workflow_and_piece_type_catalogs(): void
    {
        $this->authenticateAdministrator();

        $this->getJson('/api/v1/processes')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.name', 'Corte')
            ->assertJsonPath('data.0.flow_order', 1)
            ->assertJsonPath('data.0.operations.0.name', 'Trazo')
            ->assertJsonPath('data.0.operations.1.name', 'Tendido')
            ->assertJsonPath('data.0.operations.2.name', 'Corte')
            ->assertJsonPath('data.1.name', 'Diseño')
            ->assertJsonPath('data.2.name', 'Bordado');

        $this->getJson('/api/v1/piece-types')
            ->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonFragment([
                'name' => 'Delantero',
                'status' => 'active',
            ])
            ->assertJsonFragment([
                'name' => 'Manga',
                'status' => 'active',
            ]);
    }

    public function test_administrator_can_configure_complement_and_special_piece(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-CLASIFICACION-001',
        ]);

        $delantero = $this->getPieceType('Delantero');
        $bordado = $this->getProcess('Bordado');

        $response = $this->patchJson(
            $this->classificationUrl($cut),
            [
                'complement_notes' => 'El resto de las piezas continuará por la ruta normal.',

                'special_process_pieces' => [
                    [
                        'piece_type_id' => $delantero->id,
                        'process_id' => $bordado->id,
                        'notes' => 'El delantero requiere bordado frontal.',
                    ],
                ],
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Clasificación del corte guardada correctamente.'
            )
            ->assertJsonPath('data.id', $cut->id)
            ->assertJsonPath('data.complement.status', 'pending')
            ->assertJsonPath(
                'data.complement.current_area.name',
                'Diseño'
            )
            ->assertJsonPath(
                'data.complement.notes',
                'El resto de las piezas continuará por la ruta normal.'
            )
            ->assertJsonCount(1, 'data.special_process_pieces')
            ->assertJsonPath(
                'data.special_process_pieces.0.piece_type.name',
                'Delantero'
            )
            ->assertJsonPath(
                'data.special_process_pieces.0.process.name',
                'Bordado'
            )
            ->assertJsonPath(
                'data.special_process_pieces.0.current_area.name',
                'Diseño'
            )
            ->assertJsonPath(
                'data.special_process_pieces.0.status',
                'pending'
            );

        $this->assertDatabaseHas('garment_cut_complements', [
            'garment_cut_id' => $cut->id,
            'current_area_id' => $this->getArea('Diseño')->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $delantero->id,
            'process_id' => $bordado->id,
            'current_area_id' => $this->getArea('Diseño')->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-cut-classification',
            'action' => 'configured',
            'subject_type' => GarmentCut::class,
            'subject_id' => $cut->id,
        ]);
    }

    public function test_administrator_can_configure_cut_without_special_pieces(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-SIN-ESPECIALES-001',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'complement_notes' => 'Todas las piezas seguirán el flujo normal.',
                'special_process_pieces' => [],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.complement.status', 'pending')
            ->assertJsonPath(
                'data.complement.notes',
                'Todas las piezas seguirán el flujo normal.'
            )
            ->assertJsonCount(0, 'data.special_process_pieces');

        $this->assertDatabaseHas('garment_cut_complements', [
            'garment_cut_id' => $cut->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('special_process_pieces', 0);
    }

    public function test_administrator_can_update_pending_classification_and_cancel_removed_piece(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-ACTUALIZAR-CLASIFICACION-001',
        ]);

        $delantero = $this->getPieceType('Delantero');
        $manga = $this->getPieceType('Manga');

        $bordado = $this->getProcess('Bordado');
        $maquila = $this->getProcess('Maquila');

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'complement_notes' => 'Complemento inicial.',

                'special_process_pieces' => [
                    [
                        'piece_type_id' => $delantero->id,
                        'process_id' => $bordado->id,
                        'notes' => 'Delantero para bordado.',
                    ],
                    [
                        'piece_type_id' => $manga->id,
                        'process_id' => $maquila->id,
                        'notes' => 'Manga para maquila.',
                    ],
                ],
            ]
        )->assertOk();

        $response = $this->patchJson(
            $this->classificationUrl($cut),
            [
                'complement_notes' => 'Complemento actualizado.',

                'special_process_pieces' => [
                    [
                        'piece_type_id' => $delantero->id,
                        'process_id' => $maquila->id,
                        'notes' => 'El delantero ahora seguirá maquila.',
                    ],
                ],
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.complement.notes',
                'Complemento actualizado.'
            );

        $this->assertDatabaseHas('special_process_pieces', [
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $delantero->id,
            'process_id' => $maquila->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('special_process_pieces', [
            'garment_cut_id' => $cut->id,
            'piece_type_id' => $manga->id,
            'process_id' => $maquila->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-cut-classification',
            'action' => 'updated',
            'subject_type' => GarmentCut::class,
            'subject_id' => $cut->id,
        ]);
    }

    public function test_cut_must_be_in_progress_before_classification(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-NO-INICIADO-001',
            'status' => 'registered',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_cut');

        $this->assertDatabaseCount('garment_cut_complements', 0);
    }

    public function test_cut_must_be_in_design_area_before_classification(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-FUERA-DISENO-001',
            'current_area_id' => $this->getArea('Corte')->id,
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_cut');

        $this->assertDatabaseCount('garment_cut_complements', 0);
    }

    public function test_special_piece_can_only_be_sent_to_process_after_design(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-PROCESO-INVALIDO-001',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [
                    [
                        'piece_type_id' => $this->getPieceType('Delantero')->id,
                        'process_id' => $this->getProcess('Corte')->id,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('special_process_pieces');

        $this->assertDatabaseCount('garment_cut_complements', 0);
        $this->assertDatabaseCount('special_process_pieces', 0);
    }

    public function test_inactive_piece_type_cannot_be_used_in_classification(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-PIEZA-INACTIVA-001',
        ]);

        $delantero = $this->getPieceType('Delantero');

        $delantero->update([
            'status' => 'inactive',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [
                    [
                        'piece_type_id' => $delantero->id,
                        'process_id' => $this->getProcess('Bordado')->id,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'special_process_pieces.0.piece_type_id'
            );

        $this->assertDatabaseCount('garment_cut_complements', 0);
        $this->assertDatabaseCount('special_process_pieces', 0);
    }

    public function test_classification_cannot_be_changed_when_complement_has_started(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-COMPLEMENTO-INICIADO-001',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )->assertOk();

        GarmentCutComplement::query()
            ->where('garment_cut_id', $cut->id)
            ->firstOrFail()
            ->update([
                'status' => 'in_progress',
            ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'complement_notes' => 'Intento no permitido.',
                'special_process_pieces' => [],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_cut');
    }

    public function test_classification_cannot_be_changed_when_special_piece_has_started(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-PIEZA-INICIADA-001',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [
                    [
                        'piece_type_id' => $this->getPieceType('Delantero')->id,
                        'process_id' => $this->getProcess('Bordado')->id,
                    ],
                ],
            ]
        )->assertOk();

        SpecialProcessPiece::query()
            ->where('garment_cut_id', $cut->id)
            ->firstOrFail()
            ->update([
                'status' => 'in_progress',
            ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_cut');
    }

    public function test_design_manager_can_configure_classification(): void
    {
        $designer = User::factory()->create([
            'username' => 'designer.classification',
            'email' => 'designer.classification@uyn.test',
            'status' => 'active',
        ]);

        $designer->assignRole('Encargado de diseño');

        Sanctum::actingAs($designer, ['*']);

        $cut = $this->createDesignCut($designer, [
            'code' => 'CUT-DISENO-AUTORIZADO-001',
        ]);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.complement.status', 'pending');
    }

    public function test_consultation_user_can_view_but_cannot_configure_classification(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-CONSULTA-001',
        ]);

        $supervisor = User::factory()->create([
            'username' => 'supervisor.classification',
            'email' => 'supervisor.classification@uyn.test',
            'status' => 'active',
        ]);

        $supervisor->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($supervisor, ['*']);

        $this->getJson($this->classificationUrl($cut))
            ->assertOk()
            ->assertJsonPath('data.id', $cut->id);

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )->assertForbidden();
    }

    public function test_user_without_process_permissions_cannot_access_classification(): void
    {
        $admin = $this->authenticateAdministrator();

        $cut = $this->createDesignCut($admin, [
            'code' => 'CUT-SIN-PERMISOS-001',
        ]);

        $user = User::factory()->create([
            'username' => 'without.classification.permission',
            'email' => 'without.classification.permission@uyn.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson($this->classificationUrl($cut))
            ->assertForbidden();

        $this->patchJson(
            $this->classificationUrl($cut),
            [
                'special_process_pieces' => [],
            ]
        )->assertForbidden();
    }
}