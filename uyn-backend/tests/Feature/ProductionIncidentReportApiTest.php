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

class ProductionIncidentReportApiTest extends TestCase
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

    public function test_production_incident_report_filters_by_type_and_status(): void
    {
        [$incident] = $this->createLossIncidentScenario();

        $response = $this->getJson(
            '/api/v1/reports/production-incidents?incident_type=loss&status=resolved'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $incident->id)
            ->assertJsonPath('data.0.incident_type', 'loss')
            ->assertJsonPath('data.0.status', 'resolved')
            ->assertJsonPath('data.0.quantity_affected', 5)
            ->assertJsonPath(
                'data.0.production_movement.process.name',
                'Bordado'
            );
    }

    public function test_production_losses_report_groups_by_process(): void
    {
        [, $movement] = $this->createLossIncidentScenario();

        $response = $this->getJson(
            '/api/v1/reports/production-losses?group_by=process&status=resolved'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group.type', 'process')
            ->assertJsonPath('data.0.group.id', $movement->process_id)
            ->assertJsonPath('data.0.group.name', 'Bordado')
            ->assertJsonPath('data.0.stats.incidents_count', 1)
            ->assertJsonPath('data.0.stats.resolved_incidents_count', 1)
            ->assertJsonPath('data.0.stats.affected_quantity', 5)
            ->assertJsonPath('data.0.stats.resolved_loss_quantity', 5);
    }

    public function test_production_losses_report_groups_by_responsible_employee(): void
    {
        [, , $employee] = $this->createLossIncidentScenario();

        $response = $this->getJson(
            '/api/v1/reports/production-losses?group_by=responsible_employee'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group.type', 'responsible_employee')
            ->assertJsonPath('data.0.group.id', $employee->id)
            ->assertJsonPath('data.0.stats.affected_quantity', 5);
    }

    public function test_production_reworks_report_returns_origin_and_rework_movement(): void
    {
        [$incident, $originMovement, $reworkMovement] =
            $this->createReworkIncidentScenario();

        $response = $this->getJson(
            '/api/v1/reports/production-reworks'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.incident.id', $incident->id)
            ->assertJsonPath('data.0.incident.incident_type', 'quality')
            ->assertJsonPath('data.0.origin_movement.id', $originMovement->id)
            ->assertJsonPath('data.0.rework_movement.id', $reworkMovement->id)
            ->assertJsonPath(
                'data.0.rework_movement.process.name',
                'Bordado'
            );
    }

    public function test_user_without_reports_permission_cannot_view_incident_reports(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/reports/production-incidents')
            ->assertForbidden();

        $this->getJson('/api/v1/reports/production-losses')
            ->assertForbidden();

        $this->getJson('/api/v1/reports/production-reworks')
            ->assertForbidden();
    }

    private function createLossIncidentScenario(): array
    {
        [$cut, $movement, $employee] = $this->createBaseMovementScenario();

        $incident = ProductionIncident::factory()->create([
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
            $incident->fresh(),
            $movement->fresh(),
            $employee->fresh(),
        ];
    }

    private function createReworkIncidentScenario(): array
    {
        [$cut, $originMovement, $employee] =
            $this->createBaseMovementScenario();

        $incident = ProductionIncident::factory()->create([
            'garment_cut_id' => $cut->id,
            'production_movement_id' => $originMovement->id,
            'incident_type' => 'quality',
            'quantity_affected' => 100,
            'description' => 'Incidencia de calidad con reproceso.',
            'responsible_employee_id' => $employee->id,
            'status' => 'open',
        ]);

        $reworkMovement = ProductionMovement::factory()->create([
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'process_id' => $this->getProcess('Bordado')->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
            'from_area_id' => $this->getArea('Preparación')->id,
            'to_area_id' => $this->getArea('Bordado')->id,
            'quantity' => 100,
            'status' => 'pending',
            'return_incident_id' => $incident->id,
            'created_by' => $this->administrator->id,
        ]);

        return [
            $incident->fresh(),
            $originMovement->fresh(),
            $reworkMovement->fresh(),
        ];
    }

    private function createBaseMovementScenario(): array
    {
        $order = ProductionOrder::factory()->create([
            'created_by' => $this->administrator->id,
            'status' => 'registered',
        ]);

        $model = GarmentModel::factory()->create([
            'code' => 'PM-INC',
            'name' => 'Modelo Incidencia',
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

        $movement = ProductionMovement::factory()->create([
            'garment_cut_id' => $cut->id,
            'target_type' => 'special_piece',
            'process_id' => $this->getProcess('Bordado')->id,
            'operation_process_id' => $this->getOperation('Bordado')->id,
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