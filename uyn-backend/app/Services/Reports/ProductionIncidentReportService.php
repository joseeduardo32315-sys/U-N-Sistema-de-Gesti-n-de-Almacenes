<?php

namespace App\Services\Reports;

use App\Models\ProductionIncident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductionIncidentReportService
{
    public function getIncidentReport(
        array $filters
    ): LengthAwarePaginator {
        $incidentType = $filters['incident_type'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        return ProductionIncident::query()
            ->with([
                'garmentCut.garmentModel',
                'productionMovement.process',
                'productionMovement.operationProcess',
                'productionMovement.fromArea',
                'productionMovement.toArea',
                'responsibleEmployee.area',
                'resolvedBy',
                'reworkMovement.process',
                'reworkMovement.operationProcess',
                'reworkMovement.fromArea',
                'reworkMovement.toArea',
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
                $incidentType !== 'all',
                fn ($query) => $query->where(
                    'incident_type',
                    $incidentType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['production_movement_id'] ?? null,
                fn ($query, $movementId) => $query->where(
                    'production_movement_id',
                    $movementId
                )
            )
            ->when(
                $filters['responsible_employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'responsible_employee_id',
                    $employeeId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->whereHas(
                    'productionMovement',
                    fn ($movementQuery) => $movementQuery->where(
                        'process_id',
                        $processId
                    )
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function getLossReport(
        array $filters
    ): Collection {
        $status = $filters['status'] ?? 'all';
        $groupBy = $filters['group_by'] ?? 'garment_cut';

        $losses = ProductionIncident::query()
            ->with([
                'garmentCut.garmentModel',
                'productionMovement.process',
                'responsibleEmployee.area',
            ])
            ->where('incident_type', 'loss')
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
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->whereHas(
                    'productionMovement',
                    fn ($movementQuery) => $movementQuery->where(
                        'process_id',
                        $processId
                    )
                )
            )
            ->when(
                $filters['responsible_employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'responsible_employee_id',
                    $employeeId
                )
            )
            ->latest('id')
            ->get();

        return $this->groupLosses(
            losses: $losses,
            groupBy: $groupBy
        );
    }

    public function getReworkReport(
        array $filters
    ): LengthAwarePaginator {
        $status = $filters['status'] ?? 'all';

        return ProductionIncident::query()
            ->with([
                'garmentCut.garmentModel',
                'productionMovement.process',
                'productionMovement.fromArea',
                'productionMovement.toArea',
                'reworkMovement.process',
                'reworkMovement.operationProcess',
                'reworkMovement.fromArea',
                'reworkMovement.toArea',
                'responsibleEmployee.area',
            ])
            ->whereIn('incident_type', [
                'damage',
                'quality',
            ])
            ->whereHas('reworkMovement')
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
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->whereHas(
                    'productionMovement',
                    fn ($movementQuery) => $movementQuery->where(
                        'process_id',
                        $processId
                    )
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    private function groupLosses(
        Collection $losses,
        string $groupBy
    ): Collection {
        return $losses
            ->groupBy(fn (ProductionIncident $incident) =>
                $this->groupKey($incident, $groupBy)
            )
            ->map(function (Collection $groupedLosses) use ($groupBy) {
                /** @var ProductionIncident $first */
                $first = $groupedLosses->first();

                return collect([
                    'group' => $this->groupData($first, $groupBy),
                    'stats' => [
                        'incidents_count' =>
                            $groupedLosses->count(),

                        'open_incidents_count' =>
                            $groupedLosses
                                ->where('status', 'open')
                                ->count(),

                        'resolved_incidents_count' =>
                            $groupedLosses
                                ->where('status', 'resolved')
                                ->count(),

                        'cancelled_incidents_count' =>
                            $groupedLosses
                                ->where('status', 'cancelled')
                                ->count(),

                        'affected_quantity' =>
                            (int) $groupedLosses
                                ->sum('quantity_affected'),

                        'resolved_loss_quantity' =>
                            (int) $groupedLosses
                                ->where('status', 'resolved')
                                ->sum('quantity_affected'),
                    ],
                ]);
            })
            ->sortByDesc(
                fn ($row) => data_get(
                    $row,
                    'stats.affected_quantity',
                    0
                )
            )
            ->values();
    }

    private function groupKey(
        ProductionIncident $incident,
        string $groupBy
    ): string {
        return match ($groupBy) {
            'process' => 'process-'
                . ($incident->productionMovement?->process_id ?? 'none'),

            'responsible_employee' => 'employee-'
                . ($incident->responsible_employee_id ?? 'none'),

            default => 'cut-'
                . ($incident->garment_cut_id ?? 'none'),
        };
    }

    private function groupData(
        ProductionIncident $incident,
        string $groupBy
    ): array {
        return match ($groupBy) {
            'process' => [
                'type' => 'process',
                'id' => $incident->productionMovement?->process?->id,
                'name' => $incident->productionMovement?->process?->name,
                'code' => null,
            ],

            'responsible_employee' => [
                'type' => 'responsible_employee',
                'id' => $incident->responsibleEmployee?->id,
                'name' => $incident->responsibleEmployee?->name,
                'code' => null,
            ],

            default => [
                'type' => 'garment_cut',
                'id' => $incident->garmentCut?->id,
                'name' => $incident->garmentCut?->garmentModel?->name,
                'code' => $incident->garmentCut?->garmentModel?->code,
            ],
        };
    }
    public function getIncidentReportForExport(array $filters): Collection
    {
        $incidentType = $filters['incident_type'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        return ProductionIncident::query()
            ->with([
                'garmentCut.garmentModel',
                'productionMovement.process',
                'productionMovement.operationProcess',
                'productionMovement.fromArea',
                'productionMovement.toArea',
                'responsibleEmployee.area',
                'resolvedBy',
                'reworkMovement.process',
                'reworkMovement.operationProcess',
                'reworkMovement.fromArea',
                'reworkMovement.toArea',
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
                $incidentType !== 'all',
                fn ($query) => $query->where(
                    'incident_type',
                    $incidentType
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['production_movement_id'] ?? null,
                fn ($query, $movementId) => $query->where(
                    'production_movement_id',
                    $movementId
                )
            )
            ->when(
                $filters['responsible_employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'responsible_employee_id',
                    $employeeId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->whereHas(
                    'productionMovement',
                    fn ($movementQuery) => $movementQuery->where(
                        'process_id',
                        $processId
                    )
                )
            )
            ->latest('id')
            ->get();
    }

    public function getLossReportForExport(array $filters): Collection
    {
        return $this->getLossReport($filters);
    }

    public function getReworkReportForExport(array $filters): Collection
    {
        $status = $filters['status'] ?? 'all';

        return ProductionIncident::query()
            ->with([
                'garmentCut.garmentModel',
                'productionMovement.process',
                'productionMovement.fromArea',
                'productionMovement.toArea',
                'reworkMovement.process',
                'reworkMovement.operationProcess',
                'reworkMovement.fromArea',
                'reworkMovement.toArea',
                'responsibleEmployee.area',
            ])
            ->whereIn('incident_type', [
                'damage',
                'quality',
            ])
            ->whereHas('reworkMovement')
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
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->whereHas(
                    'productionMovement',
                    fn ($movementQuery) => $movementQuery->where(
                        'process_id',
                        $processId
                    )
                )
            )
            ->latest('id')
            ->get();
    }
}