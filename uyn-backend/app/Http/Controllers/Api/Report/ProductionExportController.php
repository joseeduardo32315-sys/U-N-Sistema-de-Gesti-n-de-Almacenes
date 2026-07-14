<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ProductionCutReportRequest;
use App\Http\Requests\Report\ProductionMovementReportRequest;
use App\Http\Requests\Report\ProductionProcessReportRequest;
use App\Models\GarmentCut;
use App\Models\ProductionMovement;
use App\Services\Exports\CsvExportService;
use App\Services\Reports\ProductionReportService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionExportController extends Controller
{
    public function __construct(
        private readonly ProductionReportService $productionReportService,
        private readonly CsvExportService $csvExportService
    ) {
    }

    public function cuts(
        ProductionCutReportRequest $request
    ): StreamedResponse {
        $cuts = $this->productionReportService
            ->getCutReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-cortes'),
            headers: $this->cutHeaders(),
            rows: $cuts->map(
                fn (GarmentCut $cut) => $this->cutRow($cut)
            )
        );
    }

    public function processes(
        ProductionProcessReportRequest $request
    ): StreamedResponse {
        $processes = $this->productionReportService
            ->getProcessReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-procesos'),
            headers: $this->processHeaders(),
            rows: $processes->map(
                fn ($row) => $this->processRow($row)
            )
        );
    }

    public function movements(
        ProductionMovementReportRequest $request
    ): StreamedResponse {
        $movements = $this->productionReportService
            ->getMovementReportForExport($request->validated());

        return $this->csvExportService->download(
            filename: $this->buildFilename('produccion-movimientos'),
            headers: $this->movementHeaders(),
            rows: $movements->map(
                fn (ProductionMovement $movement) =>
                    $this->movementRow($movement)
            )
        );
    }

    private function cutHeaders(): array
    {
        return [
            'ID corte',
            'Modelo',
            'Nombre modelo',
            'Orden producción',
            'Estado corte',
            'Área actual',
            'Total tallas',
            'Piezas por talla',
            'Piezas planeadas',
            'Pérdidas resueltas',
            'Piezas efectivas',
            'Movimientos',
            'Cantidad enviada',
            'Cantidad recibida',
            'Cantidad completada',
            'Cantidad procesada',
            'Incidencias abiertas',
            'Avance completado %',
            'Avance procesado %',
            'Fecha creación',
        ];
    }

    private function processHeaders(): array
    {
        return [
            'ID proceso',
            'Proceso',
            'Orden flujo proceso',
            'ID operación',
            'Operación',
            'Orden flujo operación',
            'Tipo cálculo nómina',
            'Movimientos',
            'Cantidad enviada',
            'Cantidad recibida',
            'Cantidad en proceso',
            'Cantidad completada',
            'Cantidad procesada',
            'Pérdidas resueltas',
            'Incidencias abiertas',
        ];
    }

    private function movementHeaders(): array
    {
        return [
            'ID movimiento',
            'ID corte',
            'Modelo',
            'Nombre modelo',
            'Tipo objetivo',
            'Proceso',
            'Operación',
            'Área origen',
            'Área destino',
            'Estado movimiento',
            'Cantidad original',
            'Pérdidas resueltas',
            'Cantidad efectiva',
            'Trabajadores',
            'Cantidad procesada',
            'Incidencias abiertas',
            'Avance %',
            'Fecha inicio',
            'Fecha creación',
        ];
    }

    private function cutRow(GarmentCut $cut): array
    {
        $stats = $cut->getAttribute('report_stats') ?? [];

        $totalPieces = (int) $cut->total_pieces;
        $resolvedLossQuantity =
            (int) ($stats['resolved_loss_quantity'] ?? 0);

        $effectivePieces = max(
            0,
            $totalPieces - $resolvedLossQuantity
        );

        $completedQuantity = (int) ($stats['completed_quantity'] ?? 0);
        $processedQuantity = (int) ($stats['processed_quantity'] ?? 0);

        return [
            $cut->id,
            $cut->garmentModel?->code,
            $cut->garmentModel?->name,
            data_get($cut->productionOrder, 'code')
                ?? data_get($cut->productionOrder, 'order_code'),
            $this->cutStatusLabel($cut->status),
            $cut->currentArea?->name,
            $cut->total_sizes,
            $cut->base_pieces_per_size,
            $totalPieces,
            $resolvedLossQuantity,
            $effectivePieces,
            (int) ($stats['movements_count'] ?? 0),
            (int) ($stats['dispatched_quantity'] ?? 0),
            (int) ($stats['received_quantity'] ?? 0),
            $completedQuantity,
            $processedQuantity,
            (int) ($stats['open_incidents_count'] ?? 0),
            $this->percentage($completedQuantity, $effectivePieces),
            $this->percentage($processedQuantity, $effectivePieces),
            $cut->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function processRow($row): array
    {
        return [
            data_get($row, 'process.id'),
            data_get($row, 'process.name'),
            data_get($row, 'process.flow_order'),
            data_get($row, 'operation_process.id'),
            data_get($row, 'operation_process.name'),
            data_get($row, 'operation_process.flow_order'),
            data_get($row, 'operation_process.payroll_calculation_type'),
            (int) data_get($row, 'stats.movements_count', 0),
            (int) data_get($row, 'stats.dispatched_quantity', 0),
            (int) data_get($row, 'stats.received_quantity', 0),
            (int) data_get($row, 'stats.in_progress_quantity', 0),
            (int) data_get($row, 'stats.completed_quantity', 0),
            (int) data_get($row, 'stats.processed_quantity', 0),
            (int) data_get($row, 'stats.resolved_loss_quantity', 0),
            (int) data_get($row, 'stats.open_incidents_count', 0),
        ];
    }

    private function movementRow(
        ProductionMovement $movement
    ): array {
        $stats = $movement->getAttribute('report_stats') ?? [];

        $quantity = (int) $movement->quantity;
        $resolvedLossQuantity =
            (int) ($stats['resolved_loss_quantity'] ?? 0);

        $effectiveQuantity = max(
            0,
            $quantity - $resolvedLossQuantity
        );

        $processedQuantity = (int) ($stats['processed_quantity'] ?? 0);

        return [
            $movement->id,
            $movement->garmentCut?->id,
            $movement->garmentCut?->garmentModel?->code,
            $movement->garmentCut?->garmentModel?->name,
            $this->targetTypeLabel($movement->target_type),
            $movement->process?->name,
            $movement->operationProcess?->name,
            $movement->fromArea?->name,
            $movement->toArea?->name,
            $this->movementStatusLabel($movement->status),
            $quantity,
            $resolvedLossQuantity,
            $effectiveQuantity,
            (int) ($stats['workers_count'] ?? 0),
            $processedQuantity,
            (int) ($stats['open_incidents_count'] ?? 0),
            $this->percentage($processedQuantity, $effectiveQuantity),
            $movement->start_time?->format('Y-m-d H:i:s'),
            $movement->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function buildFilename(string $prefix): string
    {
        return Str::slug($prefix, '-')
            . '-'
            . now()->format('Ymd-His')
            . '.csv';
    }

    private function percentage(int $value, int $total): string
    {
        if ($total <= 0) {
            return '0.00';
        }

        return number_format(($value / $total) * 100, 2, '.', '');
    }

    private function cutStatusLabel(?string $status): string
    {
        return match ($status) {
            'registered' => 'Registrado',
            'in_progress' => 'En proceso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
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