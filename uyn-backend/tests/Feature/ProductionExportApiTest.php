<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\OperationProcess;
use App\Models\Process;
use App\Models\ProductionIncident;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use App\Models\ProductionOrder;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\PieceTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\WorkflowProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductionExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

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

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->administrator = User::factory()->create([
            'status' => 'active',
        ]);

        $this->administrator->assignRole('Administrador');

        Sanctum::actingAs($this->administrator, ['*']);
    }

    public function test_can_export_production_cuts_as_csv(): void
    {
        [$cut] = $this->createProductionExportScenario();

        $response = $this->get(
            '/api/v1/reports/production-cuts/export?search=PM-CSV'
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID corte', $content);
        $this->assertStringContainsString('Piezas planeadas', $content);
        $this->assertStringContainsString('Piezas efectivas', $content);
        $this->assertStringContainsString('PM-CSV', $content);
        $this->assertStringContainsString((string) $cut->id, $content);
        $this->assertStringContainsString('95', $content);
    }

    public function test_can_export_production_processes_as_csv(): void
    {
        [, $movement] = $this->createProductionExportScenario();

        $response = $this->get(
            "/api/v1/reports/production-processes/export?process_id={$movement->process_id}"
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID proceso', $content);
        $this->assertStringContainsString('Proceso', $content);
        $this->assertStringContainsString('Cantidad procesada', $content);
        $this->assertStringContainsString('Bordado', $content);
        $this->assertStringContainsString('100', $content);
        $this->assertStringContainsString('5', $content);
    }

    public function test_can_export_production_movements_as_csv(): void
    {
        [, $movement] = $this->createProductionExportScenario();

        $response = $this->get(
            "/api/v1/reports/production-movements/export?garment_cut_id={$movement->garment_cut_id}"
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID movimiento', $content);
        $this->assertStringContainsString('Tipo objetivo', $content);
        $this->assertStringContainsString('Cantidad efectiva', $content);
        $this->assertStringContainsString('Pieza especial', $content);
        $this->assertStringContainsString('Bordado', $content);
        $this->assertStringContainsString('95', $content);
    }

    public function test_user_without_reports_export_permission_cannot_export_production_reports(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->get('/api/v1/reports/production-cuts/export')
            ->assertForbidden();

        $this->get('/api/v1/reports/production-processes/export')
            ->assertForbidden();

        $this->get('/api/v1/reports/production-movements/export')
            ->assertForbidden();
    }

    private function createProductionExportScenario(): array
    {
        $order = ProductionOrder::factory()->create([
            'created_by' => $this->administrator->id,
            'status' => 'registered',
        ]);

        $model = GarmentModel::factory()->create([
            'code' => 'PM-CSV',
            'name' => 'Modelo Exportación CSV',
            'status' => 'active',
        ]);

        $cut = GarmentCut::factory()->create([
            'production_order_id' => $order->id,
            'garment_model_id' => $model->id,
            'current_area_id' => $this->getArea('Bordado')->id,
            'status' => 'in_progress',
            'total_sizes' => 2,
            'base_pieces_per_size' => 50,
            'total_pieces' => 100,
        ]);

        $process = $this->getProcess('Bordado');
        $operation = $this->getOperation('Bordado');

        $movement = ProductionMovement::factory()->create([
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'process_id' => $process->id,
            'operation_process_id' => $operation->id,
            'from_area_id' => $this->getArea('Diseño')->id,
            'to_area_id' => $this->getArea('Bordado')->id,
            'quantity' => 100,
            'status' => 'completed',
            'created_by' => $this->administrator->id,
            'received_by' => $this->administrator->id,
        ]);

        $employee = Employee::factory()->create([
            'area_id' => $this->getArea('Bordado')->id,
            'status' => 'active',
        ]);

        ProductionOperationLog::factory()->create([
            'production_movement_id' => $movement->id,
            'operation_process_id' => $operation->id,
            'employee_id' => $employee->id,
            'quantity_processed' => 100,
            'status' => 'completed',
        ]);

        ProductionIncident::factory()->create([
            'garment_cut_id' => $cut->id,
            'production_movement_id' => $movement->id,
            'incident_type' => 'loss',
            'quantity_affected' => 5,
            'description' => 'Pérdida resuelta para exportación.',
            'responsible_employee_id' => $employee->id,
            'status' => 'resolved',
            'resolved_by' => $this->administrator->id,
            'resolved_at' => now(),
        ]);

        return [
            $cut->fresh(),
            $movement->fresh(),
            $employee->fresh(),
        ];
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

    private function assertCsvResponse($response): void
    {
        $this->assertStringContainsString(
            'text/csv',
            $response->headers->get('content-type')
        );

        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition')
        );

        $this->assertStringContainsString(
            '.csv',
            $response->headers->get('content-disposition')
        );
    }
}