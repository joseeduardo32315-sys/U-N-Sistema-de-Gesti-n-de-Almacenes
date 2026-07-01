<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\ProductionOrder;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarmentCutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            AreaSeeder::class,
            SizeSeeder::class,
        ]);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.cuts',
            'email' => 'admin.cuts@uyn.test',
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

    private function getSize(string $name): Size
    {
        return Size::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function createProductionOrder(
        User $creator,
        array $attributes = []
    ): ProductionOrder {
        return ProductionOrder::factory()->create(array_merge([
            'created_by' => $creator->id,
            'status' => 'registered',
            'priority' => 'normal',
        ], $attributes));
    }

    private function createGarmentModel(
        array $attributes = []
    ): GarmentModel {
        return GarmentModel::factory()->create(array_merge([
            'status' => 'active',
        ], $attributes));
    }

    private function createRegisteredCut(
        ProductionOrder $order,
        GarmentModel $model,
        array $attributes = [],
        ?array $sizeLines = null
    ): GarmentCut {
        $cutArea = $this->getArea('Corte');

        $sizeLines ??= [
            [
                'size_id' => $this->getSize('2')->id,
                'total_pieces' => 50,
            ],
            [
                'size_id' => $this->getSize('4')->id,
                'total_pieces' => 50,
            ],
        ];

        $quantities = collect($sizeLines)
            ->pluck('total_pieces')
            ->map(fn ($quantity) => (int) $quantity);

        $basePiecesPerSize = $quantities->unique()->count() === 1
            ? (int) $quantities->first()
            : null;

        $cut = GarmentCut::factory()->create(array_merge([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $cutArea->id,
            'status' => 'registered',
            'total_sizes' => count($sizeLines),
            'base_pieces_per_size' => $basePiecesPerSize,
            'total_pieces' => (int) $quantities->sum(),
        ], $attributes));

        $cut->cutSizes()->createMany($sizeLines);

        return $cut->fresh([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',
        ]);
    }

    public function test_administrator_can_create_cut_with_uniform_distribution(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin, [
            'order_code' => 'OP-2026-001',
        ]);

        $model = $this->createGarmentModel([
            'code' => 'PM-23',
            'name' => 'Conjunto infantil bordado',
        ]);

        $size2 = $this->getSize('2');
        $size4 = $this->getSize('4');
        $size6 = $this->getSize('6');

        $response = $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'code' => 'cut-pm23-001',
            'description' => 'Corte inicial del modelo PM-23.',
            'sizes' => [
                [
                    'size_id' => $size2->id,
                    'total_pieces' => 50,
                ],
                [
                    'size_id' => $size4->id,
                    'total_pieces' => 50,
                ],
                [
                    'size_id' => $size6->id,
                    'total_pieces' => 50,
                ],
            ],
            'notes' => 'Corte para temporada escolar.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Corte registrado correctamente.')
            ->assertJsonPath('data.code', 'CUT-PM23-001')
            ->assertJsonPath('data.total_sizes', 3)
            ->assertJsonPath('data.base_pieces_per_size', 50)
            ->assertJsonPath('data.total_pieces', 150)
            ->assertJsonPath('data.is_uniform_distribution', true)
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.current_area.name', 'Corte')
            ->assertJsonPath('data.production_order.order_code', 'OP-2026-001')
            ->assertJsonPath('data.garment_model.code', 'PM-23')
            ->assertJsonCount(3, 'data.sizes')
            ->assertJsonPath('data.sizes.0.size.name', '2')
            ->assertJsonPath('data.sizes.1.size.name', '4')
            ->assertJsonPath('data.sizes.2.size.name', '6');

        $cut = GarmentCut::query()
            ->where('code', 'CUT-PM23-001')
            ->firstOrFail();

        $this->assertDatabaseHas('garment_cuts', [
            'id' => $cut->id,
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'total_sizes' => 3,
            'base_pieces_per_size' => 50,
            'total_pieces' => 150,
            'status' => 'registered',
            'current_area_id' => $this->getArea('Corte')->id,
        ]);

        $this->assertDatabaseCount('garment_cut_sizes', 3);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-cuts',
            'action' => 'created',
            'subject_type' => GarmentCut::class,
            'subject_id' => $cut->id,
        ]);
    }

    public function test_administrator_can_create_cut_with_non_uniform_distribution(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);
        $model = $this->createGarmentModel();

        $response = $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'code' => 'CUT-NO-UNIFORME-001',
            'sizes' => [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 40,
                ],
                [
                    'size_id' => $this->getSize('4')->id,
                    'total_pieces' => 50,
                ],
                [
                    'size_id' => $this->getSize('6')->id,
                    'total_pieces' => 60,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total_sizes', 3)
            ->assertJsonPath('data.base_pieces_per_size', null)
            ->assertJsonPath('data.total_pieces', 150)
            ->assertJsonPath('data.is_uniform_distribution', false);
    }

    public function test_administrator_can_view_cut_detail_with_sizes(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin, [
            'order_code' => 'OP-DETALLE-001',
        ]);

        $model = $this->createGarmentModel([
            'code' => 'PM-DETALLE',
        ]);

        $cut = $this->createRegisteredCut(
            $order,
            $model,
            [
                'code' => 'CUT-DETALLE-001',
            ],
            [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 25,
                ],
                [
                    'size_id' => $this->getSize('4')->id,
                    'total_pieces' => 30,
                ],
            ]
        );

        $this->getJson("/api/v1/garment-cuts/{$cut->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cut->id)
            ->assertJsonPath('data.code', 'CUT-DETALLE-001')
            ->assertJsonPath('data.production_order.order_code', 'OP-DETALLE-001')
            ->assertJsonPath('data.garment_model.code', 'PM-DETALLE')
            ->assertJsonPath('data.current_area.name', 'Corte')
            ->assertJsonPath('data.total_pieces', 55)
            ->assertJsonPath('data.is_uniform_distribution', false)
            ->assertJsonCount(2, 'data.sizes')
            ->assertJsonPath('data.sizes.0.size.name', '2')
            ->assertJsonPath('data.sizes.0.total_pieces', 25)
            ->assertJsonPath('data.sizes.1.size.name', '4')
            ->assertJsonPath('data.sizes.1.total_pieces', 30);
    }

    public function test_administrator_can_filter_and_search_cuts(): void
    {
        $admin = $this->authenticateAdministrator();

        $cutArea = $this->getArea('Corte');
        $maquilaArea = $this->getArea('Maquila');

        $orderA = $this->createProductionOrder($admin, [
            'order_code' => 'OP-FILTRO-001',
        ]);

        $orderB = $this->createProductionOrder($admin, [
            'order_code' => 'OP-OTRA-001',
        ]);

        $modelA = $this->createGarmentModel([
            'code' => 'PM-FILTRO',
            'name' => 'Modelo para filtro',
        ]);

        $modelB = $this->createGarmentModel([
            'code' => 'PM-OTRO',
            'name' => 'Modelo alterno',
        ]);

        $this->createRegisteredCut($orderA, $modelA, [
            'code' => 'CUT-FILTRO-001',
            'current_area_id' => $cutArea->id,
            'status' => 'registered',
        ]);

        $this->createRegisteredCut($orderB, $modelB, [
            'code' => 'CUT-OTRO-001',
            'current_area_id' => $maquilaArea->id,
            'status' => 'in_progress',
        ]);

        $this->getJson(
            '/api/v1/garment-cuts'
            . '?search=FILTRO'
            . "&production_order_id={$orderA->id}"
            . "&garment_model_id={$modelA->id}"
            . "&current_area_id={$cutArea->id}"
            . '&status=registered'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CUT-FILTRO-001')
            ->assertJsonPath('data.0.status', 'registered')
            ->assertJsonPath('data.0.current_area.name', 'Corte');
    }

    public function test_administrator_can_update_registered_cut_and_recalculate_totals(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);
        $model = $this->createGarmentModel();

        $cut = $this->createRegisteredCut(
            $order,
            $model,
            [
                'code' => 'CUT-ACTUALIZAR-001',
            ]
        );

        $response = $this->patchJson("/api/v1/garment-cuts/{$cut->id}", [
            'description' => 'Distribución ajustada.',
            'notes' => 'Se modificaron las cantidades por talla.',
            'sizes' => [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 40,
                ],
                [
                    'size_id' => $this->getSize('4')->id,
                    'total_pieces' => 50,
                ],
                [
                    'size_id' => $this->getSize('6')->id,
                    'total_pieces' => 60,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Corte actualizado correctamente.')
            ->assertJsonPath('data.description', 'Distribución ajustada.')
            ->assertJsonPath('data.total_sizes', 3)
            ->assertJsonPath('data.base_pieces_per_size', null)
            ->assertJsonPath('data.total_pieces', 150)
            ->assertJsonPath('data.is_uniform_distribution', false)
            ->assertJsonCount(3, 'data.sizes');

        $this->assertDatabaseHas('garment_cuts', [
            'id' => $cut->id,
            'total_sizes' => 3,
            'total_pieces' => 150,
            'base_pieces_per_size' => null,
            'description' => 'Distribución ajustada.',
        ]);

        $this->assertDatabaseCount('garment_cut_sizes', 3);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-cuts',
            'action' => 'updated',
            'subject_type' => GarmentCut::class,
            'subject_id' => $cut->id,
        ]);
    }

    public function test_non_registered_cut_cannot_be_updated(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);
        $model = $this->createGarmentModel();

        $cut = $this->createRegisteredCut($order, $model, [
            'status' => 'in_progress',
        ]);

        $this->patchJson("/api/v1/garment-cuts/{$cut->id}", [
            'notes' => 'Intento de modificación no permitido.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_cut');
    }

    public function test_cannot_create_cut_in_completed_or_cancelled_order(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin, [
            'status' => 'completed',
        ]);

        $model = $this->createGarmentModel();

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'code' => 'CUT-ORDEN-CERRADA-001',
            'sizes' => [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 20,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_order_id');

        $this->assertDatabaseCount('garment_cuts', 0);
    }

    public function test_cut_creation_rejects_inactive_model_or_inactive_size(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);

        $inactiveModel = $this->createGarmentModel([
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $inactiveModel->id,
            'code' => 'CUT-MODELO-INACTIVO-001',
            'sizes' => [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 20,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('garment_model_id');

        $inactiveSize = $this->getSize('14');

        $inactiveSize->update([
            'status' => 'inactive',
        ]);

        $activeModel = $this->createGarmentModel();

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $activeModel->id,
            'code' => 'CUT-TALLA-INACTIVA-001',
            'sizes' => [
                [
                    'size_id' => $inactiveSize->id,
                    'total_pieces' => 20,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sizes.0.size_id');

        $this->assertDatabaseCount('garment_cuts', 0);
    }

    public function test_cut_creation_rejects_server_controlled_fields(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);
        $model = $this->createGarmentModel();

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'code' => 'CUT-CAMPOS-PROHIBIDOS-001',
            'status' => 'completed',
            'current_area_id' => $this->getArea('Maquila')->id,
            'total_pieces' => 999,
            'sizes' => [
                [
                    'size_id' => $this->getSize('2')->id,
                    'total_pieces' => 20,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'current_area_id',
                'total_pieces',
            ]);

        $this->assertDatabaseCount('garment_cuts', 0);
    }

    public function test_consultation_user_can_view_cuts_but_cannot_create_them(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor.cuts',
            'email' => 'supervisor.cuts@uyn.test',
            'status' => 'active',
        ]);

        $user->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/garment-cuts')
            ->assertOk();

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => 1,
            'garment_model_id' => 1,
            'code' => 'CUT-NO-AUTORIZADO-001',
            'sizes' => [
                [
                    'size_id' => 1,
                    'total_pieces' => 10,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_user_without_cut_permissions_cannot_access_cuts(): void
    {
        $user = User::factory()->create([
            'username' => 'without.cuts.permission',
            'email' => 'without.cuts.permission@uyn.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/garment-cuts')
            ->assertForbidden();

        $this->postJson('/api/v1/garment-cuts', [
            'production_order_id' => 1,
            'garment_model_id' => 1,
            'code' => 'CUT-SIN-PERMISO-001',
            'sizes' => [
                [
                    'size_id' => 1,
                    'total_pieces' => 10,
                ],
            ],
        ])->assertForbidden();
    }
}