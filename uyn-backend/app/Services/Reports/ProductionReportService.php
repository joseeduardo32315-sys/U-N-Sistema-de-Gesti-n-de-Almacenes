<?php

namespace App\Services\Reports;

use App\Models\GarmentCut;
use App\Models\OperationProcess;
use App\Models\Process;
use App\Models\ProductionIncident;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductionReportService
{
    public function getCutReport(
        array $filters
    ): LengthAwarePaginator {
        $status = $filters['status'] ?? 'all';

        $cuts = GarmentCut::query()
            ->with([
                'currentArea',
                'garmentModel',
                'productionOrder',
            ])
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['current_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'current_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['garment_model_id'] ?? null,
                fn ($query, $modelId) => $query->where(
                    'garment_model_id',
                    $modelId
                )
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->whereHas(
                                'garmentModel',
                                fn ($modelQuery) => $modelQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    )
                            )
                            ->orWhereHas(
                                'productionOrder',
                                fn ($orderQuery) => $orderQuery
                                    ->where(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    )
                            );
                    });
                }
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $this->attachCutStats($cuts->getCollection());

        return $cuts;
    }

    public function getProcessReport(
        array $filters
    ): Collection {
        $status = $filters['status'] ?? 'all';

        $movementStats = ProductionMovement::query()
            ->select([
                'process_id',
                'operation_process_id',
            ])
            ->selectRaw('COUNT(*) as movements_count')
            ->selectRaw('COALESCE(SUM(quantity), 0) as dispatched_quantity')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN ('received', 'in_progress', 'completed', 'with_incident', 'delayed') THEN quantity ELSE 0 END), 0) as received_quantity"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'in_progress' THEN quantity ELSE 0 END), 0) as in_progress_quantity"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END), 0) as completed_quantity"
            )
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->where(
                    'process_id',
                    $processId
                )
            )
            ->when(
                $filters['operation_process_id'] ?? null,
                fn ($query, $operationId) => $query->where(
                    'operation_process_id',
                    $operationId
                )
            )
            ->when(
                $filters['target_type'] ?? null,
                fn ($query, $targetType) => $query->where(
                    'target_type',
                    $targetType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->groupBy([
                'process_id',
                'operation_process_id',
            ])
            ->get();

        $movementIdsByOperation = ProductionMovement::query()
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->where(
                    'process_id',
                    $processId
                )
            )
            ->when(
                $filters['operation_process_id'] ?? null,
                fn ($query, $operationId) => $query->where(
                    'operation_process_id',
                    $operationId
                )
            )
            ->when(
                $filters['target_type'] ?? null,
                fn ($query, $targetType) => $query->where(
                    'target_type',
                    $targetType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->get([
                'id',
                'process_id',
                'operation_process_id',
            ])
            ->groupBy('operation_process_id');

        $operationIds = $movementStats
            ->pluck('operation_process_id')
            ->filter()
            ->unique()
            ->values();

        $processIds = $movementStats
            ->pluck('process_id')
            ->filter()
            ->unique()
            ->values();

        $operations = OperationProcess::query()
            ->whereIn('id', $operationIds)
            ->get()
            ->keyBy('id');

        $processes = Process::query()
            ->whereIn('id', $processIds)
            ->get()
            ->keyBy('id');

        $processedByOperation = $this->processedQuantityByOperation(
            $movementIdsByOperation
        );

        $incidentStatsByOperation = $this->incidentStatsByOperation(
            $movementIdsByOperation
        );

        return $movementStats->map(function ($stats) use (
            $operations,
            $processes,
            $processedByOperation,
            $incidentStatsByOperation
        ) {
            $operationId = $stats->operation_process_id;

            return collect([
                'process' => $processes->get($stats->process_id),
                'operation_process' => $operations->get($operationId),
                'stats' => [
                    'movements_count' =>
                        (int) $stats->movements_count,
                    'dispatched_quantity' =>
                        (int) $stats->dispatched_quantity,
                    'received_quantity' =>
                        (int) $stats->received_quantity,
                    'in_progress_quantity' =>
                        (int) $stats->in_progress_quantity,
                    'completed_quantity' =>
                        (int) $stats->completed_quantity,
                    'processed_quantity' =>
                        (int) ($processedByOperation[$operationId] ?? 0),
                    'resolved_loss_quantity' =>
                        (int) data_get(
                            $incidentStatsByOperation,
                            "{$operationId}.resolved_loss_quantity",
                            0
                        ),
                    'open_incidents_count' =>
                        (int) data_get(
                            $incidentStatsByOperation,
                            "{$operationId}.open_incidents_count",
                            0
                        ),
                ],
            ]);
        })->values();
    }

    public function getMovementReport(
        array $filters
    ): LengthAwarePaginator {
        $status = $filters['status'] ?? 'all';

        $movements = ProductionMovement::query()
            ->with([
                'garmentCut.garmentModel',
                'process',
                'operationProcess',
                'fromArea',
                'toArea',
            ])
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->where(
                    'process_id',
                    $processId
                )
            )
            ->when(
                $filters['operation_process_id'] ?? null,
                fn ($query, $operationId) => $query->where(
                    'operation_process_id',
                    $operationId
                )
            )
            ->when(
                $filters['from_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'from_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['to_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'to_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['target_type'] ?? null,
                fn ($query, $targetType) => $query->where(
                    'target_type',
                    $targetType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $this->attachMovementStats($movements->getCollection());

        return $movements;
    }

    private function attachCutStats(Collection $cuts): void
    {
        $cutIds = $cuts->pluck('id');

        if ($cutIds->isEmpty()) {
            return;
        }

        $movementStats = ProductionMovement::query()
            ->select('garment_cut_id')
            ->selectRaw('COUNT(*) as movements_count')
            ->selectRaw('COALESCE(SUM(quantity), 0) as dispatched_quantity')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN ('received', 'in_progress', 'completed', 'with_incident', 'delayed') THEN quantity ELSE 0 END), 0) as received_quantity"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END), 0) as completed_quantity"
            )
            ->whereIn('garment_cut_id', $cutIds)
            ->groupBy('garment_cut_id')
            ->get()
            ->keyBy('garment_cut_id');

        $processedStats = ProductionOperationLog::query()
            ->join(
                'production_movements',
                'production_movements.id',
                '=',
                'production_operation_logs.production_movement_id'
            )
            ->select('production_movements.garment_cut_id')
            ->selectRaw(
                'COALESCE(SUM(production_operation_logs.quantity_processed), 0) as processed_quantity'
            )
            ->whereIn('production_movements.garment_cut_id', $cutIds)
            ->groupBy('production_movements.garment_cut_id')
            ->get()
            ->keyBy('garment_cut_id');

        $incidentStats = ProductionIncident::query()
            ->select('garment_cut_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN incident_type = 'loss' AND status = 'resolved' THEN quantity_affected ELSE 0 END), 0) as resolved_loss_quantity"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open_incidents_count"
            )
            ->whereIn('garment_cut_id', $cutIds)
            ->groupBy('garment_cut_id')
            ->get()
            ->keyBy('garment_cut_id');

        $cuts->each(function (GarmentCut $cut) use (
            $movementStats,
            $processedStats,
            $incidentStats
        ) {
            $movement = $movementStats->get($cut->id);
            $processed = $processedStats->get($cut->id);
            $incident = $incidentStats->get($cut->id);

            $cut->setAttribute('report_stats', [
                'movements_count' =>
                    (int) ($movement?->movements_count ?? 0),
                'dispatched_quantity' =>
                    (int) ($movement?->dispatched_quantity ?? 0),
                'received_quantity' =>
                    (int) ($movement?->received_quantity ?? 0),
                'completed_quantity' =>
                    (int) ($movement?->completed_quantity ?? 0),
                'processed_quantity' =>
                    (int) ($processed?->processed_quantity ?? 0),
                'resolved_loss_quantity' =>
                    (int) ($incident?->resolved_loss_quantity ?? 0),
                'open_incidents_count' =>
                    (int) ($incident?->open_incidents_count ?? 0),
            ]);
        });
    }

    private function attachMovementStats(Collection $movements): void
    {
        $movementIds = $movements->pluck('id');

        if ($movementIds->isEmpty()) {
            return;
        }

        $operationStats = ProductionOperationLog::query()
            ->select('production_movement_id')
            ->selectRaw(
                'COUNT(DISTINCT employee_id) as workers_count'
            )
            ->selectRaw(
                'COALESCE(SUM(quantity_processed), 0) as processed_quantity'
            )
            ->whereIn('production_movement_id', $movementIds)
            ->groupBy('production_movement_id')
            ->get()
            ->keyBy('production_movement_id');

        $incidentStats = ProductionIncident::query()
            ->select('production_movement_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN incident_type = 'loss' AND status = 'resolved' THEN quantity_affected ELSE 0 END), 0) as resolved_loss_quantity"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open_incidents_count"
            )
            ->whereIn('production_movement_id', $movementIds)
            ->groupBy('production_movement_id')
            ->get()
            ->keyBy('production_movement_id');

        $movements->each(function (ProductionMovement $movement) use (
            $operationStats,
            $incidentStats
        ) {
            $operation = $operationStats->get($movement->id);
            $incident = $incidentStats->get($movement->id);

            $movement->setAttribute('report_stats', [
                'workers_count' =>
                    (int) ($operation?->workers_count ?? 0),
                'processed_quantity' =>
                    (int) ($operation?->processed_quantity ?? 0),
                'resolved_loss_quantity' =>
                    (int) ($incident?->resolved_loss_quantity ?? 0),
                'open_incidents_count' =>
                    (int) ($incident?->open_incidents_count ?? 0),
            ]);
        });
    }

    private function processedQuantityByOperation(
        Collection $movementIdsByOperation
    ): array {
        $result = [];

        foreach ($movementIdsByOperation as $operationId => $movements) {
            $movementIds = $movements->pluck('id');

            $result[$operationId] = (int) ProductionOperationLog::query()
                ->whereIn('production_movement_id', $movementIds)
                ->sum('quantity_processed');
        }

        return $result;
    }

    private function incidentStatsByOperation(
        Collection $movementIdsByOperation
    ): array {
        $result = [];

        foreach ($movementIdsByOperation as $operationId => $movements) {
            $movementIds = $movements->pluck('id');

            $stats = ProductionIncident::query()
                ->whereIn('production_movement_id', $movementIds)
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN incident_type = 'loss' AND status = 'resolved' THEN quantity_affected ELSE 0 END), 0) as resolved_loss_quantity"
                )
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open_incidents_count"
                )
                ->first();

            $result[$operationId] = [
                'resolved_loss_quantity' =>
                    (int) ($stats?->resolved_loss_quantity ?? 0),
                'open_incidents_count' =>
                    (int) ($stats?->open_incidents_count ?? 0),
            ];
        }

        return $result;
    }

    public function getCutReportForExport(array $filters): Collection
    {
        $status = $filters['status'] ?? 'all';

        $cuts = GarmentCut::query()
            ->with([
                'currentArea',
                'garmentModel',
                'productionOrder',
            ])
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['current_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'current_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['garment_model_id'] ?? null,
                fn ($query, $modelId) => $query->where(
                    'garment_model_id',
                    $modelId
                )
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->whereHas(
                                'garmentModel',
                                fn ($modelQuery) => $modelQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%")
                            )
                            ->orWhereHas(
                                'productionOrder',
                                fn ($orderQuery) => $orderQuery
                                    ->where('code', 'like', "%{$search}%")
                                    ->orWhere(
                                        'order_code',
                                        'like',
                                        "%{$search}%"
                                    )
                            );
                    });
                }
            )
            ->latest('id')
            ->get();

        $this->attachCutStats($cuts);

        return $cuts;
    }

    public function getProcessReportForExport(array $filters): Collection
    {
        return $this->getProcessReport($filters);
    }

    public function getMovementReportForExport(array $filters): Collection
    {
        $status = $filters['status'] ?? 'all';

        $movements = ProductionMovement::query()
            ->with([
                'garmentCut.garmentModel',
                'process',
                'operationProcess',
                'fromArea',
                'toArea',
            ])
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'created_at',
                    '<=',
                    $to
                )
            )
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->where(
                    'process_id',
                    $processId
                )
            )
            ->when(
                $filters['operation_process_id'] ?? null,
                fn ($query, $operationId) => $query->where(
                    'operation_process_id',
                    $operationId
                )
            )
            ->when(
                $filters['from_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'from_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['to_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'to_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['target_type'] ?? null,
                fn ($query, $targetType) => $query->where(
                    'target_type',
                    $targetType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->latest('id')
            ->get();

        $this->attachMovementStats($movements);

        return $movements;
    }
}