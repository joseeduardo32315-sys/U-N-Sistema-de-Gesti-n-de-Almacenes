<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ProductionIncidentReportRequest;
use App\Http\Requests\Report\ProductionLossReportRequest;
use App\Http\Requests\Report\ProductionReworkReportRequest;
use App\Models\ProductionIncident;
use App\Services\Exports\CsvExportService;
use App\Services\Reports\ProductionIncidentReportService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionIncidentExportController extends Controller
{
    public function __construct(
        private readonly ProductionIncidentReportService $incidentReportService,
        private readonly CsvExportService $csvExportService
    ) {
    }

    public function incidents(
        ProductionIncidentReportRequest $request
    ): StreamedResponse {
        $incidents = $this->incidentReportService
            ->getIncidentReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-incidencias'),
            headers: $this->incidentHeaders(),
            rows: $incidents->map(
                fn (ProductionIncident $incident) =>
                    $this->incidentRow($incident)
            )
        );
    }

    public function losses(
        ProductionLossReportRequest $request
    ): StreamedResponse {
        $losses = $this->incidentReportService
            ->getLossReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-perdidas'),
            headers: $this->lossHeaders(),
            rows: $losses->map(
                fn ($row) => $this->lossRow($row)
            )
        );
    }

    public function reworks(
        ProductionReworkReportRequest $request
    ): StreamedResponse {
        $reworks = $this->incidentReportService
            ->getReworkReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-reprocesos'),
            headers: $this->reworkHeaders(),
            rows: $reworks->map(
                fn (ProductionIncident $incident) =>
                    $this->reworkRow($incident)
            )
        );
    }

    private function incidentHeaders(): array
    {
        return [
            'ID incidencia',
            'Tipo incidencia',
            'Estado',
            'Cantidad afectada',
            'Descripción',
            'Notas resolución',
            'ID corte',
            'Modelo',
            'Nombre modelo',
            'ID movimiento',
            'Tipo objetivo',
            'Proceso',
            'Operación',
            'Área origen',
            'Área destino',
            'Trabajador responsable',
            'Área trabajador',
            'Resuelto por',
            'Tiene reproceso',
            'Fecha resolución',
            'Fecha creación',
        ];
    }

    private function lossHeaders(): array
    {
        return [
            'Tipo agrupación',
            'ID grupo',
            'Nombre grupo',
            'Código grupo',
            'Incidencias totales',
            'Incidencias abiertas',
            'Incidencias resueltas',
            'Incidencias canceladas',
            'Cantidad afectada',
            'Pérdida resuelta',
        ];
    }

    private function reworkHeaders(): array
    {
        return [
            'ID incidencia',
            'Tipo incidencia',
            'Estado incidencia',
            'Cantidad afectada',
            'Descripción',
            'ID corte',
            'Modelo',
            'Nombre modelo',
            'ID movimiento origen',
            'Estado movimiento origen',
            'Proceso origen',
            'Área origen inicial',
            'Área origen destino',
            'ID movimiento reproceso',
            'Estado reproceso',
            'Proceso reproceso',
            'Operación reproceso',
            'Área reproceso origen',
            'Área reproceso destino',
            'Trabajador responsable',
            'Fecha creación',
            'Fecha resolución',
        ];
    }

    private function incidentRow(
        ProductionIncident $incident
    ): array {
        $movement = $incident->productionMovement;
        $cut = $incident->garmentCut;

        return [
            $incident->id,
            $this->incidentTypeLabel($incident->incident_type),
            $this->incidentStatusLabel($incident->status),
            (int) $incident->quantity_affected,
            $incident->description,
            $incident->resolution_notes,
            $cut?->id,
            $cut?->garmentModel?->code,
            $cut?->garmentModel?->name,
            $movement?->id,
            $this->targetTypeLabel($movement?->target_type),
            $movement?->process?->name,
            $movement?->operationProcess?->name,
            $movement?->fromArea?->name,
            $movement?->toArea?->name,
            $incident->responsibleEmployee?->name,
            $incident->responsibleEmployee?->area?->name,
            $incident->resolvedBy?->name,
            $incident->reworkMovement ? 'Sí' : 'No',
            $incident->resolved_at?->format('Y-m-d H:i:s'),
            $incident->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function lossRow($row): array
    {
        return [
            data_get($row, 'group.type'),
            data_get($row, 'group.id'),
            data_get($row, 'group.name'),
            data_get($row, 'group.code'),
            (int) data_get($row, 'stats.incidents_count', 0),
            (int) data_get($row, 'stats.open_incidents_count', 0),
            (int) data_get($row, 'stats.resolved_incidents_count', 0),
            (int) data_get($row, 'stats.cancelled_incidents_count', 0),
            (int) data_get($row, 'stats.affected_quantity', 0),
            (int) data_get($row, 'stats.resolved_loss_quantity', 0),
        ];
    }

    private function reworkRow(
        ProductionIncident $incident
    ): array {
        $cut = $incident->garmentCut;
        $origin = $incident->productionMovement;
        $rework = $incident->reworkMovement;

        return [
            $incident->id,
            $this->incidentTypeLabel($incident->incident_type),
            $this->incidentStatusLabel($incident->status),
            (int) $incident->quantity_affected,
            $incident->description,
            $cut?->id,
            $cut?->garmentModel?->code,
            $cut?->garmentModel?->name,
            $origin?->id,
            $this->movementStatusLabel($origin?->status),
            $origin?->process?->name,
            $origin?->fromArea?->name,
            $origin?->toArea?->name,
            $rework?->id,
            $this->movementStatusLabel($rework?->status),
            $rework?->process?->name,
            $rework?->operationProcess?->name,
            $rework?->fromArea?->name,
            $rework?->toArea?->name,
            $incident->responsibleEmployee?->name,
            $incident->created_at?->format('Y-m-d H:i:s'),
            $incident->resolved_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function buildFilename(string $prefix): string
    {
        return Str::slug($prefix, '-')
            . '-'
            . now()->format('Ymd-His')
            . '.csv';
    }

    private function incidentTypeLabel(?string $type): string
    {
        return match ($type) {
            'damage' => 'Daño',
            'loss' => 'Pérdida',
            'quality' => 'Calidad',
            'delay' => 'Retraso',
            'other' => 'Otro',
            default => 'No definido',
        };
    }

    private function incidentStatusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Abierta',
            'resolved' => 'Resuelta',
            'cancelled' => 'Cancelada',
            default => 'No definido',
        };
    }

    private function movementStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'received' => 'Recibido',
            'in_progress' => 'En proceso',
            'completed' => 'Completado',
            'with_incident' => 'Con incidencia',
            'delayed' => 'Retrasado',
            'cancelled' => 'Cancelado',
            default => 'No definido',
        };
    }

    private function targetTypeLabel(?string $targetType): string
    {
        return match ($targetType) {
            'cut' => 'Corte completo',
            'complement' => 'Complemento',
            'special_piece' => 'Pieza especial',
            default => 'No definido',
        };
    }
}