<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Employee;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use App\Models\SpecialProcessPiece;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOperationLogManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function assign(
        ProductionMovement $productionMovement,
        array $data,
        User $actor,
        Request $request
    ): ProductionOperationLog {
        return DB::transaction(function () use (
            $productionMovement,
            $data,
            $actor,
            $request
        ) {
            $movement = ProductionMovement::query()
                ->with(['toArea', 'operationProcess'])
                ->lockForUpdate()
                ->findOrFail($productionMovement->id);

            $this->ensureMovementAcceptsOperationLogs($movement);

            $this->ensureActorCanManageArea(
                $actor,
                $movement->toArea
            );

            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);

            $this->ensureEmployeeCanWorkInMovement(
                $employee,
                $movement
            );

            $alreadyAssigned = ProductionOperationLog::query()
                ->where(
                    'production_movement_id',
                    $movement->id
                )
                ->where('employee_id', $employee->id)
                ->whereIn('status', [
                    'pending',
                    'in_progress',
                ])
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'employee_id' =>
                        'El trabajador ya tiene una asignación activa para este movimiento.',
                ]);
            }

            $operationLog = ProductionOperationLog::create([
                'production_movement_id' => $movement->id,
                'operation_process_id' =>
                    $movement->operation_process_id,

                'employee_id' => $employee->id,

                'quantity_processed' => 0,
                'status' => 'pending',

                'notes' => $data['notes'] ?? null,
            ]);

            $operationLog = $this->loadDetailRelations(
                $operationLog
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-operation-logs',
                action: 'assigned',
                subject: $operationLog,
                description: "Se asignó a {$employee->name} al movimiento {$movement->id}.",
                newValues: $this->snapshot($operationLog),
            );

            return $operationLog;
        });
    }

    public function updateProgress(
        ProductionOperationLog $productionOperationLog,
        array $data,
        User $actor,
        Request $request
    ): ProductionOperationLog {
        return DB::transaction(function () use (
            $productionOperationLog,
            $data,
            $actor,
            $request
        ) {
            $operationLog = ProductionOperationLog::query()
                ->with([
                    'productionMovement.toArea',
                    'productionMovement.process',
                ])
                ->lockForUpdate()
                ->findOrFail($productionOperationLog->id);

            $movement = ProductionMovement::query()
                ->with(['toArea', 'process'])
                ->lockForUpdate()
                ->findOrFail(
                    $operationLog->production_movement_id
                );

            $this->ensureMovementAcceptsOperationLogs($movement);

            $this->ensureActorCanManageArea(
                $actor,
                $movement->toArea
            );

            if (in_array($operationLog->status, [
                'completed',
                'cancelled',
            ], true)) {
                throw ValidationException::withMessages([
                    'production_operation_log' =>
                        'No se puede modificar un registro operativo completado o cancelado.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadDetailRelations($operationLog)
            );

            $currentQuantity = (int) $operationLog->quantity_processed;

            $newQuantity = array_key_exists(
                'quantity_processed',
                $data
            )
                ? (int) $data['quantity_processed']
                : $currentQuantity;

            if ($newQuantity < $currentQuantity) {
                throw ValidationException::withMessages([
                    'quantity_processed' =>
                        'La cantidad procesada no puede disminuir.',
                ]);
            }

            $processedByOtherEmployees = (int) ProductionOperationLog::query()
                ->where(
                    'production_movement_id',
                    $movement->id
                )
                ->whereKeyNot($operationLog->id)
                ->sum('quantity_processed');

            if (
                $processedByOtherEmployees + $newQuantity
                > (int) $movement->quantity
            ) {
                throw ValidationException::withMessages([
                    'quantity_processed' =>
                        'La cantidad procesada supera la cantidad enviada en el movimiento.',
                ]);
            }

            $shouldStart = ($data['start'] ?? false) === true
                || $newQuantity > 0;

            $shouldComplete = ($data['complete'] ?? false) === true;

            if ($shouldComplete && $newQuantity === 0) {
                throw ValidationException::withMessages([
                    'quantity_processed' =>
                        'Debes registrar una cantidad procesada antes de completar la operación.',
                ]);
            }

            $attributes = [];

            if (array_key_exists('quantity_processed', $data)) {
                $attributes['quantity_processed'] = $newQuantity;
            }

            if (array_key_exists('stitches_count', $data)) {
                $attributes['stitches_count'] = $data['stitches_count'];
            }

            if (array_key_exists('applications_count', $data)) {
                $attributes['applications_count'] =
                    $data['applications_count'];
            }

            if (array_key_exists('notes', $data)) {
                $attributes['notes'] = $data['notes'];
            }

            if (
                $shouldStart
                && $operationLog->status === 'pending'
            ) {
                $attributes['status'] = 'in_progress';
                $attributes['start_time'] = now();
            }

            if ($shouldComplete) {
                $attributes['status'] = 'completed';
                $attributes['start_time'] =
                    $operationLog->start_time ?? now();

                $attributes['end_time'] = now();
            }

            $operationLog->update($attributes);

            $this->synchronizeMovementStatus($movement);

            $operationLog = $this->loadDetailRelations(
                $operationLog->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-operation-logs',
                action: $operationLog->status === 'completed'
                    ? 'completed'
                    : 'updated',
                subject: $operationLog,
                description: $operationLog->status === 'completed'
                    ? "Se completó la operación {$operationLog->id}."
                    : "Se actualizó el avance de la operación {$operationLog->id}.",
                oldValues: $before,
                newValues: $this->snapshot($operationLog),
            );

            return $operationLog;
        });
    }

    private function ensureMovementAcceptsOperationLogs(
        ProductionMovement $movement
    ): void {
        if (! in_array($movement->status, [
            'received',
            'in_progress',
        ], true)) {
            throw ValidationException::withMessages([
                'production_movement' =>
                    'Solo se pueden registrar operaciones en movimientos recibidos o en proceso.',
            ]);
        }
    }

    private function ensureEmployeeCanWorkInMovement(
        Employee $employee,
        ProductionMovement $movement
    ): void {
        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El trabajador seleccionado está inactivo.',
            ]);
        }

        if (
            (int) $employee->area_id
            !== (int) $movement->to_area_id
        ) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El trabajador debe pertenecer al área destino del movimiento.',
            ]);
        }
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
                'No tienes autorización para operar movimientos de esta área.'
            );
        }
    }

    private function synchronizeMovementStatus(
        ProductionMovement $movement
    ): void {
        $movement = ProductionMovement::query()
            ->with(['process'])
            ->lockForUpdate()
            ->findOrFail($movement->id);

        $logs = ProductionOperationLog::query()
            ->where('production_movement_id', $movement->id)
            ->lockForUpdate()
            ->get();

        $totalProcessed = (int) $logs->sum('quantity_processed');

        $allLogsCompleted = $logs->isNotEmpty()
            && $logs->every(
                fn (ProductionOperationLog $log) =>
                    $log->status === 'completed'
            );

        if (
            $allLogsCompleted
            && $totalProcessed === (int) $movement->quantity
        ) {
            $movement->update([
                'status' => 'completed',
                'start_time' => $movement->start_time ?? now(),
                'end_time' => now(),
            ]);

            $this->completeTargetWhenTerminalProcess($movement);

            return;
        }

        $hasStartedLog = $logs->contains(
            fn (ProductionOperationLog $log) =>
                $log->status === 'in_progress'
                || $log->quantity_processed > 0
        );

        if ($hasStartedLog) {
            $movement->update([
                'status' => 'in_progress',
                'start_time' => $movement->start_time ?? now(),
            ]);
        }
    }

    private function completeTargetWhenTerminalProcess(
        ProductionMovement $movement
    ): void {
        if ($movement->process?->name !== 'Terminado') {
            return;
        }

        $target = match ($movement->target_type) {
            'cut' => GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($movement->garment_cut_id),

            'complement' => GarmentCutComplement::query()
                ->lockForUpdate()
                ->findOrFail($movement->complement_id),

            'special_piece' => SpecialProcessPiece::query()
                ->lockForUpdate()
                ->findOrFail(
                    $movement->special_process_piece_id
                ),
        };

        $target->update([
            'status' => 'completed',
        ]);
    }

    private function loadDetailRelations(
        ProductionOperationLog $operationLog
    ): ProductionOperationLog {
        return $operationLog->load([
            'employee',
            'operationProcess',

            'productionMovement.toArea',
            'productionMovement.process',
        ]);
    }

    private function snapshot(
        ProductionOperationLog $operationLog
    ): array {
        $operationLog->loadMissing([
            'employee',
            'operationProcess',

            'productionMovement.toArea',
            'productionMovement.process',
        ]);

        return [
            'id' => $operationLog->id,

            'production_movement_id' =>
                $operationLog->production_movement_id,

            'employee' => [
                'id' => $operationLog->employee?->id,
                'name' => $operationLog->employee?->name,
            ],

            'operation_process' =>
                $operationLog->operationProcess?->name,

            'quantity_processed' =>
                $operationLog->quantity_processed,

            'stitches_count' => $operationLog->stitches_count,
            'applications_count' =>
                $operationLog->applications_count,

            'status' => $operationLog->status,

            'movement_status' =>
                $operationLog->productionMovement?->status,

            'destination_area' =>
                $operationLog->productionMovement
                    ?->toArea
                    ?->name,
        ];
    }
}