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

class ProductionReportApiTest extends TestCase
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

    public function test_production_cut_report_returns_effective_pieces_and_progress(): void
    {
        [$cut] = $this->createCompletedProductionScenario();

        $response = $this->getJson(
            '/api/v1/reports/production-cuts?search=PM-REPORT'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cut->id)
            ->assertJsonPath('data.0.total_pieces', 100)
            ->assertJsonPath('data.0.effective_pieces', 95)
            ->assertJsonPath('data.0.movement_summary.movements_count', 1)
            ->assertJsonPath('data.0.movement_summary.completed_quantity', 100)
            ->assertJsonPath('data.0.movement_summary.processed_quantity', 100)
            ->assertJsonPath('data.0.movement_summary.resolved_loss_quantity', 5)
            ->assertJsonPath('data.0.progress.processed_percentage', 105.26);
    }

    public function test_production_process_report_groups_quantities_by_process_and_operation(): void
    {
        [, $movement] = $this->createCompletedProductionScenario();

        $response = $this->getJson(
            "/api/v1/reports/production-processes?process_id={$movement->process_id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.process.id', $movement->process_id)
            ->assertJsonPath(
                'data.0.operation_process.id',
                $movement->operation_process_id
            )
            ->assertJsonPath('data.0.stats.movements_count', 1)
            ->assertJsonPath('data.0.stats.dispatched_quantity', 100)
            ->assertJsonPath('data.0.stats.completed_quantity', 100)
            ->assertJsonPath('data.0.stats.processed_quantity', 100)
            ->assertJsonPath('data.0.stats.resolved_loss_quantity', 5);
    }

    public function test_production_movement_report_returns_movement_operation_summary(): void
    {
        [, $movement] = $this->createCompletedProductionScenario();

        $response = $this->getJson(
            "/api/v1/reports/production-movements?garment_cut_id={$movement->garment_cut_id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movement->id)
            ->assertJsonPath('data.0.quantity', 100)
            ->assertJsonPath('data.0.effective_quantity', 95)
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.operation_summary.workers_count', 1)
            ->assertJsonPath('data.0.operation_summary.processed_quantity', 100)
            ->assertJsonPath('data.0.operation_summary.resolved_loss_quantity', 5)
            ->assertJsonPath('data.0.operation_summary.progress_percentage', 105.26);
    }

    public function test_user_without_reports_permission_cannot_view_production_reports(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/reports/production-cuts')
            ->assertForbidden();

        $this->getJson('/api/v1/reports/production-processes')
            ->assertForbidden();

        $this->getJson('/api/v1/reports/production-movements')
            ->assertForbidden();
    }

    private function createCompletedProductionScenario(): array
    {
        $order = ProductionOrder::factory()->create([
            'created_by' => $this->administrator->id,
            'status' => 'registered',
        ]);

        $model = GarmentModel::factory()->create([
            'code' => 'PM-REPORT',
            'name' => 'Modelo Reporte',
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
            'description' => 'Pérdida resuelta para reporte.',
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
}