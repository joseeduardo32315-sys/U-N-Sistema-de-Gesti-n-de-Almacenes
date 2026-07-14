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

class ProductionIncidentExportApiTest extends TestCase
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

    public function test_can_export_production_incidents_as_csv(): void
    {
        [$incident] = $this->createLossIncidentScenario();

        $response = $this->get(
            '/api/v1/reports/production-incidents/export'
            . '?incident_type=loss&status=resolved'
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID incidencia', $content);
        $this->assertStringContainsString('Tipo incidencia', $content);
        $this->assertStringContainsString('Cantidad afectada', $content);
        $this->assertStringContainsString('Pérdida', $content);
        $this->assertStringContainsString('Resuelta', $content);
        $this->assertStringContainsString((string) $incident->id, $content);
    }

    public function test_can_export_production_losses_as_csv(): void
    {
        $this->createLossIncidentScenario();

        $response = $this->get(
            '/api/v1/reports/production-losses/export'
            . '?group_by=process&status=resolved'
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('Tipo agrupación', $content);
        $this->assertStringContainsString('Cantidad afectada', $content);
        $this->assertStringContainsString('Pérdida resuelta', $content);
        $this->assertStringContainsString('process', $content);
        $this->assertStringContainsString('Bordado', $content);
        $this->assertStringContainsString('5', $content);
    }

    public function test_can_export_production_reworks_as_csv(): void
    {
        [$incident, , $reworkMovement] =
            $this->createReworkIncidentScenario();

        $response = $this->get(
            '/api/v1/reports/production-reworks/export'
        );

        $response->assertOk();

        $this->assertCsvResponse($response);

        $content = $response->streamedContent();

        $this->assertStringContainsString('ID incidencia', $content);
        $this->assertStringContainsString('ID movimiento reproceso', $content);
        $this->assertStringContainsString('Proceso reproceso', $content);
        $this->assertStringContainsString('Calidad', $content);
        $this->assertStringContainsString((string) $incident->id, $content);
        $this->assertStringContainsString(
            (string) $reworkMovement->id,
            $content
        );
    }

    public function test_user_without_reports_export_permission_cannot_export_incident_reports(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->get('/api/v1/reports/production-incidents/export')
            ->assertForbidden();

        $this->get('/api/v1/reports/production-losses/export')
            ->assertForbidden();

        $this->get('/api/v1/reports/production-reworks/export')
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
            'description' => 'Pérdida resuelta para exportación.',
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
            'code' => 'PM-INC-CSV',
            'name' => 'Modelo Incidencia Exportación CSV',
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