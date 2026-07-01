<?php

namespace App\Services;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\OperationProcess;
use App\Models\Process;
use App\Models\ProductionMovement;
use App\Models\SpecialProcessPiece;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionMovementManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function dispatch(
        array $data,
        User $actor,
        Request $request
    ): ProductionMovement {
        return DB::transaction(function () use ($data, $actor, $request) {
            $garmentCut = GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($data['garment_cut_id']);

            $target = $this->resolveTarget(
                garmentCut: $garmentCut,
                data: $data
            );

            $destination = $this->resolveDestination(
                processId: (int) $data['process_id'],
                operationProcessId: (int) $data['operation_process_id']
            );

            $this->ensureTargetCanMove(
                target: $target,
                targetType: $data['target_type']
            );

            $this->ensureNoOpenMovement(
                garmentCut: $garmentCut,
                target: $target,
                targetType: $data['target_type']
            );

            $this->ensureQuantityMatchesCut(
                garmentCut: $garmentCut,
                quantity: (int) $data['quantity']
            );

            $fromArea = Area::query()
                ->lockForUpdate()
                ->findOrFail($target->current_area_id);

            $this->ensureTransitionIsAllowed(
                target: $target,
                targetType: $data['target_type'],
                fromArea: $fromArea,
                destinationProcess: $destination['process'],
                destinationArea: $destination['area']
            );

            $movement = ProductionMovement::create([
                'garment_cut_id' => $garmentCut->id,
                'target_type' => $data['target_type'],

                'special_process_piece_id' =>
                    $data['target_type'] === 'special_piece'
                        ? $target->id
                        : null,

                'complement_id' =>
                    $data['target_type'] === 'complement'
                        ? $target->id
                        : null,

                'process_id' => $destination['process']->id,
                'operation_process_id' => $destination['operation']->id,

                'from_area_id' => $fromArea->id,
                'to_area_id' => $destination['area']->id,

                'quantity' => (int) $data['quantity'],
                'status' => 'pending',

                'notes' => $data['notes'] ?? null,

                'created_by' => $actor->id,
            ]);

            $movement = $this->loadDetailRelations($movement);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-movements',
                action: 'created',
                subject: $movement,
                description: "Se registró el envío {$movement->id} del corte {$garmentCut->code}.",
                newValues: $this->snapshot($movement),
            );

            return $movement;
        });
    }

    public function receive(
        ProductionMovement $productionMovement,
        User $actor,
        Request $request
    ): ProductionMovement {
        return DB::transaction(function () use (
            $productionMovement,
            $actor,
            $request
        ) {
            $movement = ProductionMovement::query()
                ->lockForUpdate()
                ->findOrFail($productionMovement->id);

            if ($movement->status !== 'pending') {
                throw ValidationException::withMessages([
                    'production_movement' =>
                        'Solo se pueden recibir movimientos con estado pendiente.',
                ]);
            }

            $garmentCut = GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($movement->garment_cut_id);

            $target = $this->resolveTargetFromMovement(
                garmentCut: $garmentCut,
                movement: $movement
            );

            if ((int) $target->current_area_id !== (int) $movement->from_area_id) {
                throw ValidationException::withMessages([
                    'production_movement' =>
                        'El objetivo del movimiento ya no se encuentra en el área de origen registrada.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadDetailRelations($movement)
            );

            $movement->update([
                'status' => 'received',
                'received_by' => $actor->id,
                'start_time' => now(),
            ]);

            $target->update([
                'current_area_id' => $movement->to_area_id,
                'status' => 'in_progress',
            ]);

            $movement = $this->loadDetailRelations(
                $movement->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-movements',
                action: 'received',
                subject: $movement,
                description: "Se confirmó la recepción del movimiento {$movement->id}.",
                oldValues: $before,
                newValues: $this->snapshot($movement),
            );

            return $movement;
        });
    }

    private function resolveTarget(
        GarmentCut $garmentCut,
        array $data
    ): Model {
        return match ($data['target_type']) {
            'cut' => $this->resolveCutTarget($garmentCut, $data),

            'complement' => $this->resolveComplementTarget(
                garmentCut: $garmentCut,
                complementId: $data['complement_id'] ?? null,
                specialProcessPieceId:
                    $data['special_process_piece_id'] ?? null
            ),

            'special_piece' => $this->resolveSpecialPieceTarget(
                garmentCut: $garmentCut,
                specialProcessPieceId:
                    $data['special_process_piece_id'] ?? null,
                complementId: $data['complement_id'] ?? null
            ),

            default => throw ValidationException::withMessages([
                'target_type' => 'El tipo de objetivo no es válido.',
            ]),
        };
    }

    private function resolveCutTarget(
        GarmentCut $garmentCut,
        array $data
    ): GarmentCut {
        if (
            ($data['special_process_piece_id'] ?? null) !== null
            || ($data['complement_id'] ?? null) !== null
        ) {
            throw ValidationException::withMessages([
                'target_type' =>
                    'Un movimiento de corte completo no debe incluir complemento ni pieza especial.',
            ]);
        }

        return $garmentCut;
    }

    private function resolveComplementTarget(
        GarmentCut $garmentCut,
        ?int $complementId,
        ?int $specialProcessPieceId
    ): GarmentCutComplement {
        if ($complementId === null) {
            throw ValidationException::withMessages([
                'complement_id' =>
                    'Debes indicar el complemento que será trasladado.',
            ]);
        }

        if ($specialProcessPieceId !== null) {
            throw ValidationException::withMessages([
                'special_process_piece_id' =>
                    'Un movimiento de complemento no puede incluir una pieza especial.',
            ]);
        }

        $complement = GarmentCutComplement::query()
            ->whereKey($complementId)
            ->where('garment_cut_id', $garmentCut->id)
            ->lockForUpdate()
            ->first();

        if (! $complement) {
            throw ValidationException::withMessages([
                'complement_id' =>
                    'El complemento seleccionado no pertenece al corte indicado.',
            ]);
        }

        return $complement;
    }

    private function resolveSpecialPieceTarget(
        GarmentCut $garmentCut,
        ?int $specialProcessPieceId,
        ?int $complementId
    ): SpecialProcessPiece {
        if ($specialProcessPieceId === null) {
            throw ValidationException::withMessages([
                'special_process_piece_id' =>
                    'Debes indicar la pieza especial que será trasladada.',
            ]);
        }

        if ($complementId !== null) {
            throw ValidationException::withMessages([
                'complement_id' =>
                    'Un movimiento de pieza especial no puede incluir complemento.',
            ]);
        }

        $specialPiece = SpecialProcessPiece::query()
            ->whereKey($specialProcessPieceId)
            ->where('garment_cut_id', $garmentCut->id)
            ->lockForUpdate()
            ->first();

        if (! $specialPiece) {
            throw ValidationException::withMessages([
                'special_process_piece_id' =>
                    'La pieza especial seleccionada no pertenece al corte indicado.',
            ]);
        }

        $specialPiece->load('process');

        return $specialPiece;
    }

    private function resolveTargetFromMovement(
        GarmentCut $garmentCut,
        ProductionMovement $movement
    ): Model {
        return match ($movement->target_type) {
            'cut' => $garmentCut,

            'complement' => GarmentCutComplement::query()
                ->whereKey($movement->complement_id)
                ->where('garment_cut_id', $garmentCut->id)
                ->lockForUpdate()
                ->firstOrFail(),

            'special_piece' => SpecialProcessPiece::query()
                ->whereKey($movement->special_process_piece_id)
                ->where('garment_cut_id', $garmentCut->id)
                ->lockForUpdate()
                ->firstOrFail(),

            default => throw ValidationException::withMessages([
                'production_movement' =>
                    'El movimiento tiene un tipo de objetivo inválido.',
            ]),
        };
    }

    private function resolveDestination(
        int $processId,
        int $operationProcessId
    ): array {
        $process = Process::query()
            ->lockForUpdate()
            ->findOrFail($processId);

        $operation = OperationProcess::query()
            ->whereKey($operationProcessId)
            ->where('process_id', $process->id)
            ->lockForUpdate()
            ->first();

        if (! $operation) {
            throw ValidationException::withMessages([
                'operation_process_id' =>
                    'La operación seleccionada no pertenece al proceso indicado.',
            ]);
        }

        $area = Area::query()
            ->where('name', $process->name)
            ->lockForUpdate()
            ->first();

        if (! $area) {
            throw ValidationException::withMessages([
                'process_id' =>
                    'El proceso seleccionado no tiene un área equivalente configurada.',
            ]);
        }

        return [
            'process' => $process,
            'operation' => $operation,
            'area' => $area,
        ];
    }

    private function ensureTargetCanMove(
        Model $target,
        string $targetType
    ): void {
        if (in_array($target->status, [
            'completed',
            'cancelled',
        ], true)) {
            throw ValidationException::withMessages([
                'target_type' =>
                    'No se puede trasladar un objetivo completado o cancelado.',
            ]);
        }

        if (
            $targetType !== 'cut'
            && ! in_array($target->status, [
                'pending',
                'in_progress',
            ], true)
        ) {
            throw ValidationException::withMessages([
                'target_type' =>
                    'El complemento o la pieza especial no se encuentra disponible para traslado.',
            ]);
        }

        if (
            $targetType === 'cut'
            && ! in_array($target->status, [
                'registered',
                'in_progress',
            ], true)
        ) {
            throw ValidationException::withMessages([
                'target_type' =>
                    'El corte no se encuentra disponible para traslado.',
            ]);
        }
    }

    private function ensureNoOpenMovement(
        GarmentCut $garmentCut,
        Model $target,
        string $targetType
    ): void {
        $query = ProductionMovement::query()
            ->where('garment_cut_id', $garmentCut->id)
            ->where('target_type', $targetType)
            ->whereIn('status', [
                'pending',
                'received',
                'in_progress',
                'partially_completed',
                'with_incident',
                'delayed',
            ]);

        if ($targetType === 'complement') {
            $query->where('complement_id', $target->id);
        }

        if ($targetType === 'special_piece') {
            $query->where(
                'special_process_piece_id',
                $target->id
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'target_type' =>
                    'Ya existe un movimiento activo para el objetivo seleccionado.',
            ]);
        }
    }

    private function ensureQuantityMatchesCut(
        GarmentCut $garmentCut,
        int $quantity
    ): void {
        if ($quantity !== (int) $garmentCut->total_pieces) {
            throw ValidationException::withMessages([
                'quantity' =>
                    "En esta fase el traslado debe incluir las {$garmentCut->total_pieces} piezas del corte. La distribución entre trabajadores se registrará mediante operaciones.",
            ]);
        }
    }

    private function ensureTransitionIsAllowed(
        Model $target,
        string $targetType,
        Area $fromArea,
        Process $destinationProcess,
        Area $destinationArea
    ): void {
        if ($fromArea->id === $destinationArea->id) {
            throw ValidationException::withMessages([
                'process_id' =>
                    'El proceso destino debe corresponder a un área distinta al área actual.',
            ]);
        }

        $allowedDestinations = match ($fromArea->name) {
            'Corte' => $targetType === 'cut'
                ? ['Diseño']
                : [],

            'Diseño' => match ($targetType) {
                'complement' => ['Maquila'],

                'special_piece' => [
                    $target instanceof SpecialProcessPiece
                        ? $target->process?->name
                        : null,
                ],

                default => [],
            },

            'Bordado', 'Maquila' => ['Preparación'],

            'Preparación' => ['Terminado'],

            default => [],
        };

        if (
            ! in_array(
                $destinationArea->name,
                array_filter($allowedDestinations),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'process_id' =>
                    "No se permite mover este objetivo de {$fromArea->name} a {$destinationProcess->name}.",
            ]);
        }

        if (
            $targetType === 'special_piece'
            && $fromArea->name === 'Diseño'
            && $target instanceof SpecialProcessPiece
            && (int) $destinationProcess->id
                !== (int) $target->process_id
        ) {
            throw ValidationException::withMessages([
                'process_id' =>
                    'La pieza especial debe enviarse primero al proceso configurado durante la clasificación.',
            ]);
        }
    }

    private function loadDetailRelations(
        ProductionMovement $movement
    ): ProductionMovement {
        return $movement
            ->load([
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
            ])
            ->loadCount('operationLogs');
    }

    private function snapshot(
        ProductionMovement $movement
    ): array {
        $movement->loadMissing([
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

        return [
            'id' => $movement->id,
            'target_type' => $movement->target_type,
            'quantity' => $movement->quantity,
            'status' => $movement->status,

            'garment_cut' => [
                'id' => $movement->garmentCut?->id,
                'code' => $movement->garmentCut?->code,
            ],

            'from_area' => $movement->fromArea?->name,
            'to_area' => $movement->toArea?->name,

            'process' => $movement->process?->name,
            'operation_process' => $movement->operationProcess?->name,

            'created_by' => $movement->createdBy?->username,
            'received_by' => $movement->receivedBy?->username,
        ];
    }
}