<?php

namespace Tests\Feature;

use App\Models\ProductionOrder;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SizeSeeder::class,
        ]);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.orders',
            'email' => 'admin.orders@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function createProductionOrder(
        User $creator,
        array $attributes = []
    ): ProductionOrder {
        return ProductionOrder::factory()->create(array_merge([
            'order_code' => 'OP-2026-001',
            'location' => 'Área de producción principal',
            'status' => 'registered',
            'start_date' => '2026-06-30',
            'end_date' => '2026-07-10',
            'priority' => 'normal',
            'created_by' => $creator->id,
            'notes' => 'Orden de prueba.',
        ], $attributes));
    }

    public function test_administrator_can_list_active_sizes_and_all_sizes(): void
    {
        $this->authenticateAdministrator();

        $inactiveSize = Size::query()
            ->where('name', '14')
            ->firstOrFail();

        $inactiveSize->update([
            'status' => 'inactive',
        ]);

        $this->getJson('/api/v1/sizes')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonMissing([
                'name' => '14',
            ]);

        $this->getJson('/api/v1/sizes?status=all')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonFragment([
                'name' => '14',
                'status' => 'inactive',
            ]);
    }

    public function test_administrator_can_create_production_order(): void
    {
        $admin = $this->authenticateAdministrator();

        $response = $this->postJson('/api/v1/production-orders', [
            'order_code' => 'op-2026-001',
            'location' => 'Área de producción principal',
            'start_date' => '2026-06-30',
            'end_date' => '2026-07-10',
            'priority' => 'high',
            'notes' => 'Pedido prioritario para temporada escolar.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Orden de producción registrada correctamente.'
            )
            ->assertJsonPath('data.order_code', 'OP-2026-001')
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.status_label', 'Registrada')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.priority_label', 'Alta')
            ->assertJsonPath('data.created_by.id', $admin->id)
            ->assertJsonPath('data.created_by.username', 'admin.orders');

        $order = ProductionOrder::query()
            ->where('order_code', 'OP-2026-001')
            ->firstOrFail();

        $this->assertDatabaseHas('production_orders', [
            'id' => $order->id,
            'order_code' => 'OP-2026-001',
            'status' => 'registered',
            'priority' => 'high',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-orders',
            'action' => 'created',
            'subject_type' => ProductionOrder::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_production_order_code_must_be_unique(): void
    {
        $admin = $this->authenticateAdministrator();

        $this->createProductionOrder($admin, [
            'order_code' => 'OP-2026-001',
        ]);

        $this->postJson('/api/v1/production-orders', [
            'order_code' => 'op-2026-001',
            'start_date' => '2026-06-30',
            'end_date' => '2026-07-10',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_code');

        $this->assertDatabaseCount('production_orders', 1);
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $this->authenticateAdministrator();

        $this->postJson('/api/v1/production-orders', [
            'order_code' => 'OP-INVALIDA-001',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-09',
            'priority' => 'normal',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');

        $this->assertDatabaseCount('production_orders', 0);
    }

    public function test_administrator_can_filter_production_orders(): void
    {
        $admin = $this->authenticateAdministrator();

        $this->createProductionOrder($admin, [
            'order_code' => 'OP-ALTA-001',
            'location' => 'Taller principal',
            'status' => 'registered',
            'priority' => 'high',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
        ]);

        $this->createProductionOrder($admin, [
            'order_code' => 'OP-NORMAL-001',
            'location' => 'Taller secundario',
            'status' => 'in_progress',
            'priority' => 'normal',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-15',
        ]);

        $this->getJson(
            '/api/v1/production-orders'
            . '?search=OP-ALTA'
            . '&status=registered'
            . '&priority=high'
            . '&date_from=2026-06-01'
            . '&date_to=2026-06-30'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_code', 'OP-ALTA-001')
            ->assertJsonPath('data.0.priority', 'high')
            ->assertJsonPath('data.0.status', 'registered');
    }

    public function test_administrator_can_view_production_order_detail(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);

        $this->getJson("/api/v1/production-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_code', 'OP-2026-001')
            ->assertJsonPath('data.created_by.id', $admin->id)
            ->assertJsonPath('data.garment_cuts_count', 0);
    }

    public function test_administrator_can_update_production_order_and_register_audit_log(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin);

        $this->patchJson("/api/v1/production-orders/{$order->id}", [
            'priority' => 'urgent',
            'end_date' => '2026-07-08',
            'notes' => 'Fecha estimada ajustada por solicitud del cliente.',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Orden de producción actualizada correctamente.'
            )
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.priority_label', 'Urgente')
            ->assertJsonPath('data.end_date', '2026-07-08')
            ->assertJsonPath(
                'data.notes',
                'Fecha estimada ajustada por solicitud del cliente.'
            );

        $this->assertDatabaseHas('production_orders', [
            'id' => $order->id,
            'priority' => 'urgent',
        ]);

        $updatedOrder = ProductionOrder::findOrFail($order->id);

        $this->assertSame(
            '2026-07-08',
            $updatedOrder->end_date?->toDateString()
        );

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'production-orders',
            'action' => 'updated',
            'subject_type' => ProductionOrder::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_completed_or_cancelled_order_cannot_be_updated(): void
    {
        $admin = $this->authenticateAdministrator();

        $order = $this->createProductionOrder($admin, [
            'status' => 'completed',
        ]);

        $this->patchJson("/api/v1/production-orders/{$order->id}", [
            'priority' => 'urgent',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('production_order');

        $this->assertDatabaseHas('production_orders', [
            'id' => $order->id,
            'status' => 'completed',
            'priority' => 'normal',
        ]);
    }

    public function test_consultation_user_can_view_orders_but_cannot_create_them(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor.orders',
            'email' => 'supervisor.orders@uyn.test',
            'status' => 'active',
        ]);

        $user->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/production-orders')
            ->assertOk();

        $this->postJson('/api/v1/production-orders', [
            'order_code' => 'OP-NO-AUTORIZADA',
            'start_date' => '2026-06-30',
        ])
            ->assertForbidden();
    }

    public function test_user_without_cut_permissions_cannot_access_orders_or_sizes(): void
    {
        $user = User::factory()->create([
            'username' => 'without.orders.permission',
            'email' => 'without.orders.permission@uyn.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/sizes')
            ->assertForbidden();

        $this->getJson('/api/v1/production-orders')
            ->assertForbidden();
    }
}