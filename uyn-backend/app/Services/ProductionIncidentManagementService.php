<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Employee;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\ProductionIncident;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use App\Models\SpecialProcessPiece;
use App\Models\User;
use App\Models\OperationProcess;
use App\Models\Process;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionIncidentManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): ProductionIncident {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request
        ) {
            $movement = ProductionMovement::query()
                ->with('toArea')
                ->lockForUpdate()
                ->findOrFail($data['production_movement_id']);

            $this->ensureMovementAllowsIncidents($movement);

            $target = $this->resolveTargetFromMovement($movement);

            $this->ensureTargetIsAtMovementDestination(
                $target,
                $movement
            );

            $this->ensureActorCanManageArea(
                $actor,
                $movement->toArea
            );

            $this->validateResponsibleEmployee(
                responsibleEmployeeId:
                    $data['responsible_employee_id'] ?? null,
                movement: $movement
            );

            $this->validateIncidentQuantity(
                movement: $movement,
                incidentType: $data['incident_type'],
                quantityAffected:
                    (int) $data['quantity_affected']
            );

            $incident = ProductionIncident::create([
                'garment_cut_id' => $movement->garment_cut_id,
                'production_movement_id' => $movement->id,

                'incident_type' => $data['incident_type'],
                'quantity_affected' =>
                    (int) $data['quantity_affected'],

                'description' => $data['description'],

                'responsible_employee_id' =>
                    $data['responsible_employee_id'] ?? null,

                'status' => 'open',

                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyOpenIncidentStatus(
                movement: $movement,
                target: $target
            );

            $incident = $this->loadDetailRelations(
                $incident->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-incidents',
                action: 'created',
                subject: $incident,
                description: "Se registró la incidencia {$incident->id} en el movimiento {$movement->id}.",
                newValues: $this->snapshot($incident),
            );

            return $incident;
        });
    }

    public function returnForRework(
        ProductionIncident $productionIncident,
        array $data,
        User $actor,
        Request $request
    ): ProductionMovement {
        return DB::transaction(function () use (
            $productionIncident,
            $data,
            $actor,
            $request
        ) {
            $incident = ProductionIncident::query()
                ->lockForUpdate()
                ->findOrFail($productionIncident->id);

            $this->ensureIncidentIsOpen($incident);
            $this->ensureIncidentAllowsRework($incident);

            $incidentMovement = ProductionMovement::query()
                ->with('toArea')
                ->lockForUpdate()
                ->findOrFail($incident->production_movement_id);

            $target = $this->resolveTargetFromMovement(
                $incidentMovement
            );

            $this->ensureTargetIsAtMovementDestination(
                $target,
                $incidentMovement
            );

            $this->ensureActorCanManageArea(
                $actor,
                $incidentMovement->toArea
            );

            $this->ensureMovementHasNoProcessedWork(
                $incidentMovement
            );

            $existingReworkMovement = ProductionMovement::query()
                ->where('return_incident_id', $incident->id)
                ->lockForUpdate()
                ->first();

            if ($existingReworkMovement) {
                throw ValidationException::withMessages([
                    'production_incident' =>
                        'Esta incidencia ya cuenta con una devolución para reproceso.',
                ]);
            }

            $destination = $this->resolveReworkDestination(
                incidentMovement: $incidentMovement,
                target: $target
            );

            $operation = OperationProcess::query()
                ->whereKey($data['operation_process_id'])
                ->where('process_id', $destination['process']->id)
                ->lockForUpdate()
                ->first();

            if (! $operation) {
                throw ValidationException::withMessages([
                    'operation_process_id' =>
                        'La operación seleccionada no corresponde al proceso de reproceso requerido.',
                ]);
            }

            $quantity = $this->effectiveMovementQuantity(
                $incidentMovement
            );

            if ($quantity === 0) {
                throw ValidationException::withMessages([
                    'production_incident' =>
                        'No existen piezas disponibles para devolver a reproceso.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadDetailRelations($incident)
            );

            $reworkMovement = ProductionMovement::create([
                'garment_cut_id' => $incidentMovement->garment_cut_id,

                'return_incident_id' => $incident->id,

                'target_type' => $incidentMovement->target_type,

                'special_process_piece_id' =>
                    $incidentMovement->special_process_piece_id,

                'complement_id' =>
                    $incidentMovement->complement_id,

                'process_id' => $destination['process']->id,

                'operation_process_id' => $operation->id,

                'from_area_id' => $target->current_area_id,

                'to_area_id' => $destination['area']->id,

                'quantity' => $quantity,

                'status' => 'pending',

                'notes' => $data['notes'],

                'created_by' => $actor->id,
            ]);

            $incidentMovement->update([
                'status' => 'cancelled',
                'notes' => $this->appendSystemNote(
                    $incidentMovement->notes,
                    "Devolución para reproceso mediante movimiento {$reworkMovement->id}, derivada de la incidencia {$incident->id}."
                ),
            ]);

            $incident = $this->loadDetailRelations(
                $incident->fresh()
            );

            $reworkMovement = $this->loadReworkMovementRelations(
                $reworkMovement
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-incidents',
                action: 'returned_for_rework',
                subject: $incident,
                description: "Se registró la devolución a reproceso del movimiento {$reworkMovement->id} por la incidencia {$incident->id}.",
                oldValues: $before,
                newValues: array_merge(
                    $this->snapshot($incident),
                    [
                        'rework_movement' => [
                            'id' => $reworkMovement->id,
                            'status' => $reworkMovement->status,
                            'quantity' => $reworkMovement->quantity,
                            'from_area' => $reworkMovement->fromArea?->name,
                            'to_area' => $reworkMovement->toArea?->name,
                            'process' => $reworkMovement->process?->name,
                        ],
                    ]
                ),
            );

            return $reworkMovement;
        });
    }

    public function update(
        ProductionIncident $productionIncident,
        array $data,
        User $actor,
        Request $request
    ): ProductionIncident {
        return DB::transaction(function () use (
            $productionIncident,
            $data,
            $actor,
            $request
        ) {
            if ($data === []) {
                throw ValidationException::withMessages([
                    'request' =>
                        'Debes enviar al menos un dato para actualizar.',
                ]);
            }

            $incident = ProductionIncident::query()
                ->lockForUpdate()
                ->findOrFail($productionIncident->id);

            $this->ensureIncidentIsOpen($incident);

            $movement = ProductionMovement::query()
                ->with('toArea')
                ->lockForUpdate()
                ->findOrFail($incident->production_movement_id);

            $target = $this->resolveTargetFromMovement($movement);

            $this->ensureActorCanManageArea(
                $actor,
                $movement->toArea
            );

            $before = $this->snapshot(
                $this->loadDetailRelations($incident)
            );

            $effectiveQuantity = array_key_exists(
                'quantity_affected',
                $data
            )
                ? (int) $data['quantity_affected']
                : (int) $incident->quantity_affected;

            $this->validateIncidentQuantity(
                movement: $movement,
                incidentType: $incident->incident_type,
                quantityAffected: $effectiveQuantity,
                exceptIncidentId: $incident->id
            );

            if (array_key_exists(
                'responsible_employee_id',
                $data
            )) {
                $this->validateResponsibleEmployee(
                    responsibleEmployeeId:
                        $data['responsible_employee_id'],
                    movement: $movement
                );
            }

            $incident->fill(Arr::only($data, [
                'description',
                'quantity_affected',
                'responsible_employee_id',
                'notes',
            ]));

            $incident->save();

            $this->applyOpenIncidentStatus(
                movement: $movement,
                target: $target
            );

            $incident = $this->loadDetailRelations(
                $incident->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-incidents',
                action: 'updated',
                subject: $incident,
                description: "Se actualizó la incidencia {$incident->id}.",
                oldValues: $before,
                newValues: $this->snapshot($incident),
            );

            return $incident;
        });
    }

    public function resolve(
        ProductionIncident $productionIncident,
        array $data,
        User $actor,
        Request $request
    ): ProductionIncident {
        return DB::transaction(function () use (
            $productionIncident,
            $data,
            $actor,
            $request
        ) {
            $incident = ProductionIncident::query()
                ->lockForUpdate()
                ->findOrFail($productionIncident->id);

            $this->ensureIncidentIsOpen($incident);

            $movement = ProductionMovement::query()
                ->with('toArea')
                ->lockForUpdate()
                ->findOrFail($incident->production_movement_id);

            $target = $this->resolveTargetFromMovement($movement);

            $reworkMovement = $this->getCompletedReworkMovement(
                $incident
            );

            $this->ensureResolvedLossCanBeApplied(
                incident: $incident,
                movement: $movement
            );

            $this->ensureActorCanManageArea(
                $actor,
                $movement->toArea
            );

            $before = $this->snapshot(
                $this->loadDetailRelations($incident)
            );

            $incident->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'notes' => $data['notes'],
            ]);

            $hasOtherOpenIncidents = ProductionIncident::query()
                ->where(
                    'production_movement_id',
                    $movement->id
                )
                ->where('status', 'open')
                ->whereKeyNot($incident->id)
                ->exists();

            if ($hasOtherOpenIncidents) {
                $this->applyOpenIncidentStatus(
                    movement: $movement,
                    target: $target
                );
            } elseif ($reworkMovement) {
                $this->restoreAfterCompletedRework(
                    target: $target,
                    reworkMovement: $reworkMovement
                );
            } else {
                $this->restoreOperationalStatus(
                    movement: $movement,
                    target: $target
                );
            }

            $incident = $this->loadDetailRelations(
                $incident->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-incidents',
                action: 'resolved',
                subject: $incident,
                description: "Se resolvió la incidencia {$incident->id}.",
                oldValues: $before,
                newValues: $this->snapshot($incident),
            );

            return $incident;
        });
    }

    private function ensureMovementAllowsIncidents(
        ProductionMovement $movement
    ): void {
        if (! in_array($movement->status, [
            'received',
            'in_progress',
            'partially_completed',
            'with_incident',
            'delayed',
        ], true)) {
            throw ValidationException::withMessages([
                'production_movement_id' =>
                    'Solo se pueden registrar incidencias en movimientos recibidos o en proceso.',
            ]);
        }
    }

    private function ensureIncidentIsOpen(
        ProductionIncident $incident
    ): void {
        if ($incident->status !== 'open') {
            throw ValidationException::withMessages([
                'production_incident' =>
                    'Solo se puede modificar o resolver una incidencia abierta.',
            ]);
        }
    }

    private function resolveTargetFromMovement(
        ProductionMovement $movement
    ): Model {
        return match ($movement->target_type) {
            'cut' => GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($movement->garment_cut_id),

            'complement' => GarmentCutComplement::query()
                ->whereKey($movement->complement_id)
                ->where(
                    'garment_cut_id',
                    $movement->garment_cut_id
                )
                ->lockForUpdate()
                ->firstOrFail(),

            'special_piece' => SpecialProcessPiece::query()
                ->whereKey($movement->special_process_piece_id)
                ->where(
                    'garment_cut_id',
                    $movement->garment_cut_id
                )
                ->lockForUpdate()
                ->firstOrFail(),

            default => throw ValidationException::withMessages([
                'production_movement_id' =>
                    'El movimiento tiene un objetivo no válido.',
            ]),
        };
    }

    private function ensureTargetIsAtMovementDestination(
        Model $target,
        ProductionMovement $movement
    ): void {
        if (
            (int) $target->current_area_id
            !== (int) $movement->to_area_id
        ) {
            throw ValidationException::withMessages([
                'production_movement_id' =>
                    'No puedes registrar una incidencia porque el objetivo ya no se encuentra en el área destino del movimiento.',
            ]);
        }
    }

    private function validateResponsibleEmployee(
        ?int $responsibleEmployeeId,
        ProductionMovement $movement
    ): void {
        if ($responsibleEmployeeId === null) {
            return;
        }

        $employee = Employee::query()
            ->lockForUpdate()
            ->findOrFail($responsibleEmployeeId);

        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'responsible_employee_id' =>
                    'El responsable seleccionado está inactivo.',
            ]);
        }

        if (! in_array(
            (int) $employee->area_id,
            [
                (int) $movement->from_area_id,
                (int) $movement->to_area_id,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'responsible_employee_id' =>
                    'El responsable debe pertenecer al área de origen o destino del movimiento.',
            ]);
        }
    }

    private function validateIncidentQuantity(
        ProductionMovement $movement,
        string $incidentType,
        int $quantityAffected,
        ?int $exceptIncidentId = null
    ): void {
        if (
            in_array(
                $incidentType,
                ['damage', 'loss', 'quality'],
                true
            )
            && $quantityAffected < 1
        ) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'La incidencia debe afectar al menos una pieza.',
            ]);
        }

        if (
            $incidentType === 'delay'
            && $quantityAffected !== 0
        ) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'Un retraso debe registrarse con cantidad afectada igual a cero.',
            ]);
        }

        if ($quantityAffected > (int) $movement->quantity) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'La cantidad afectada no puede superar la cantidad enviada en el movimiento.',
            ]);
        }

        if (! in_array(
            $incidentType,
            ['damage', 'loss', 'quality'],
            true
        )) {
            return;
        }

        $registeredAffectedQuantity = (int) ProductionIncident::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->when(
                $exceptIncidentId !== null,
                fn ($query) => $query->whereKeyNot(
                    $exceptIncidentId
                )
            )
            ->where(function ($query) {
                $query
                    ->where(function ($subQuery) {
                        $subQuery
                            ->where('status', 'open')
                            ->whereIn('incident_type', [
                                'damage',
                                'loss',
                                'quality',
                            ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery
                            ->where('status', 'resolved')
                            ->where('incident_type', 'loss');
                    });
            })
            ->sum('quantity_affected');

        if (
            $registeredAffectedQuantity + $quantityAffected
            > (int) $movement->quantity
        ) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'La suma de pérdidas e incidencias físicas supera la cantidad enviada en el movimiento.',
            ]);
        }
    }

    private function applyOpenIncidentStatus(
        ProductionMovement $movement,
        Model $target
    ): void {
        $openTypes = ProductionIncident::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->where('status', 'open')
            ->pluck('incident_type');

        $status = $openTypes->contains(
            fn (string $type) => $type !== 'delay'
        )
            ? 'with_incident'
            : 'delayed';

        $movement->update([
            'status' => $status,
        ]);

        $target->update([
            'status' => $status,
        ]);
    }

    private function ensureResolvedLossCanBeApplied(
        ProductionIncident $incident,
        ProductionMovement $movement
    ): void {
        if ($incident->incident_type !== 'loss') {
            return;
        }

        $previousResolvedLosses = (int) ProductionIncident::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->where('incident_type', 'loss')
            ->where('status', 'resolved')
            ->sum('quantity_affected');

        $projectedLosses = $previousResolvedLosses
            + (int) $incident->quantity_affected;

        if ($projectedLosses > (int) $movement->quantity) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'La pérdida acumulada supera la cantidad enviada en el movimiento.',
            ]);
        }

        $processedQuantity = (int) ProductionOperationLog::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->sum('quantity_processed');

        if (
            $processedQuantity + $projectedLosses
            > (int) $movement->quantity
        ) {
            throw ValidationException::withMessages([
                'quantity_affected' =>
                    'La pérdida no puede superar las piezas pendientes después de considerar el avance ya registrado.',
            ]);
        }
    }

    private function effectiveMovementQuantity(
        ProductionMovement $movement
    ): int {
        $resolvedLossQuantity = (int) ProductionIncident::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->where('incident_type', 'loss')
            ->where('status', 'resolved')
            ->sum('quantity_affected');

        return max(
            0,
            (int) $movement->quantity - $resolvedLossQuantity
        );
    }

    private function restoreOperationalStatus(
        ProductionMovement $movement,
        Model $target
    ): void {
        $movement->loadMissing('process');

        $effectiveQuantity = $this->effectiveMovementQuantity(
            $movement
        );

        if ($effectiveQuantity === 0) {
            $movement->update([
                'status' => 'completed',
                'start_time' => $movement->start_time ?? now(),
                'end_time' => now(),
            ]);

            $target->update([
                'status' => 'completed',
            ]);

            return;
        }

        $operationLogs = ProductionOperationLog::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->get();

        $totalProcessed = (int) $operationLogs
            ->sum('quantity_processed');

        $allLogsCompleted = $operationLogs->isNotEmpty()
            && $operationLogs->every(
                fn (ProductionOperationLog $log) =>
                    $log->status === 'completed'
            );

        if (
            $allLogsCompleted
            && $totalProcessed === $effectiveQuantity
        ) {
            $movement->update([
                'status' => 'completed',
                'start_time' => $movement->start_time ?? now(),
                'end_time' => now(),
            ]);

            $target->update([
                'status' => $movement->process?->name === 'Terminado'
                    ? 'completed'
                    : 'in_progress',
            ]);

            return;
        }

        $hasProgress = $operationLogs->contains(
            fn (ProductionOperationLog $log) =>
                $log->quantity_processed > 0
                || $log->status === 'in_progress'
        );

        $movement->update([
            'status' => $hasProgress
                ? 'in_progress'
                : 'received',
        ]);

        $target->update([
            'status' => 'in_progress',
        ]);
    }

    private function ensureActorCanManageArea(
        User $actor,
        ?Area $area
    ): void {
        if (! $area) {
            throw new AuthorizationException(
                'No fue posible identificar el área responsable.'
            );
        }

        if ($actor->hasAnyRole([
            'Administrador',
            'Encargado de producción',
        ])) {
            return;
        }

        $areaRoles = [
            'Corte' => 'Encargado de corte',
            'Diseño' => 'Encargado de diseño',
            'Bordado' => 'Encargado de bordado',
            'Maquila' => 'Encargado de maquila',
            'Preparación' => 'Encargado de preparación/terminado',
            'Terminado' => 'Encargado de preparación/terminado',
        ];

        $requiredRole = $areaRoles[$area->name] ?? null;

        if (
            $requiredRole === null
            || ! $actor->hasRole($requiredRole)
        ) {
            throw new AuthorizationException(
                'No tienes autorización para administrar incidencias de esta área.'
            );
        }
    }

    private function loadDetailRelations(
        ProductionIncident $incident
    ): ProductionIncident {
        return $incident->load([
            'garmentCut.currentArea',

            'productionMovement.garmentCut.currentArea',

            'productionMovement.complement.currentArea',

            'productionMovement.specialProcessPiece.pieceType',
            'productionMovement.specialProcessPiece.process',
            'productionMovement.specialProcessPiece.currentArea',

            'productionMovement.process',
            'productionMovement.fromArea',
            'productionMovement.toArea',

            'responsibleEmployee.area',
            'resolvedBy',

            'reworkMovement.process',
            'reworkMovement.fromArea',
            'reworkMovement.toArea',
        ]);
    }

    private function snapshot(
        ProductionIncident $incident
    ): array {
        $incident->loadMissing([
            'garmentCut',
            'productionMovement.process',
            'productionMovement.fromArea',
            'productionMovement.toArea',
            'responsibleEmployee',
            'resolvedBy',
            'reworkMovement.process',
            'reworkMovement.fromArea',
            'reworkMovement.toArea',
        ]);

        return [
            'id' => $incident->id,
            'incident_type' => $incident->incident_type,
            'quantity_affected' => $incident->quantity_affected,
            'description' => $incident->description,
            'status' => $incident->status,
            'notes' => $incident->notes,

            'garment_cut' => [
                'id' => $incident->garmentCut?->id,
                'code' => $incident->garmentCut?->code,
            ],

            'production_movement' => [
                'id' => $incident->productionMovement?->id,
                'status' => $incident->productionMovement?->status,
                'process' => $incident
                    ->productionMovement
                    ?->process
                    ?->name,
                'from_area' => $incident
                    ->productionMovement
                    ?->fromArea
                    ?->name,
                'to_area' => $incident
                    ->productionMovement
                    ?->toArea
                    ?->name,
            ],

            'responsible_employee' => $incident
                ->responsibleEmployee
                ?->name,

            'resolved_by' => $incident
                ->resolvedBy
                ?->username,

            'resolved_at' => $incident
                ->resolved_at
                ?->toISOString(),

            'rework_movement' => $incident->reworkMovement
                ? [
                    'id' => $incident->reworkMovement->id,
                    'status' => $incident->reworkMovement->status,
                    'quantity' => $incident->reworkMovement->quantity,
                ]
                : null,
        ];
    }

    private function ensureIncidentAllowsRework(
        ProductionIncident $incident
    ): void {
        if (! in_array(
            $incident->incident_type,
            ['damage', 'quality'],
            true
        )) {
            throw ValidationException::withMessages([
                'production_incident' =>
                    'Solo las incidencias de daño o calidad pueden enviarse a reproceso.',
            ]);
        }
    }

    private function ensureMovementHasNoProcessedWork(
        ProductionMovement $movement
    ): void {
        $hasProcessedWork = ProductionOperationLog::query()
            ->where(
                'production_movement_id',
                $movement->id
            )
            ->where(function ($query) {
                $query
                    ->where('quantity_processed', '>', 0)
                    ->orWhereIn('status', [
                        'in_progress',
                        'completed',
                    ]);
            })
            ->exists();

        if ($hasProcessedWork) {
            throw ValidationException::withMessages([
                'production_incident' =>
                    'No se puede devolver el lote completo porque el movimiento ya tiene avances registrados. Las devoluciones parciales se implementarán mediante segmentación de lotes.',
            ]);
        }
    }

    private function resolveReworkDestination(
        ProductionMovement $incidentMovement,
        Model $target
    ): array {
        $currentArea = Area::query()
            ->lockForUpdate()
            ->findOrFail($target->current_area_id);

        $processName = match ($incidentMovement->target_type) {
            'special_piece' => $target instanceof SpecialProcessPiece
                ? $target->process()->value('name')
                : null,

            'complement' => match ($currentArea->name) {
                'Preparación' => 'Maquila',
                'Terminado' => 'Preparación',
                default => null,
            },

            'cut' => $currentArea->name === 'Diseño'
                ? 'Corte'
                : null,

            default => null,
        };

        if (! $processName || $processName === $currentArea->name) {
            throw ValidationException::withMessages([
                'production_incident' =>
                    'No existe una ruta de reproceso válida para el objetivo y área actual.',
            ]);
        }

        $process = Process::query()
            ->where('name', $processName)
            ->lockForUpdate()
            ->firstOrFail();

        $area = Area::query()
            ->where('name', $process->name)
            ->lockForUpdate()
            ->firstOrFail();

        return [
            'process' => $process,
            'area' => $area,
        ];
    }

    private function loadReworkMovementRelations(
        ProductionMovement $movement
    ): ProductionMovement {
        return $movement->load([
            'returnIncident',

            'garmentCut.currentArea',

            'complement.currentArea',

            'specialProcessPiece.pieceType',
            'specialProcessPiece.process',
            'specialProcessPiece.currentArea',

            'process',
            'operationProcess',

            'fromArea',
            'toArea',

            'createdBy',
            'receivedBy',
        ]);
    }

    private function appendSystemNote(
        ?string $currentNotes,
        string $message
    ): string {
        return $currentNotes
            ? "{$currentNotes}\n{$message}"
            : $message;
    }

    private function getCompletedReworkMovement(
        ProductionIncident $incident
    ): ?ProductionMovement {
        $reworkMovement = ProductionMovement::query()
            ->with('process')
            ->where('return_incident_id', $incident->id)
            ->lockForUpdate()
            ->first();

        if (! $reworkMovement) {
            return null;
        }

        if ($reworkMovement->status !== 'completed') {
            throw ValidationException::withMessages([
                'production_incident' =>
                    'La incidencia no puede resolverse hasta que concluya el movimiento de reproceso.',
            ]);
        }

        return $reworkMovement;
    }

    private function restoreAfterCompletedRework(
        Model $target,
        ProductionMovement $reworkMovement
    ): void {
        $target->update([
            'status' => $reworkMovement->process?->name === 'Terminado'
                ? 'completed'
                : 'in_progress',
        ]);
    }
}