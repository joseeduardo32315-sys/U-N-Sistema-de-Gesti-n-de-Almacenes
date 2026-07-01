<?php

namespace App\Services;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentCutComplement;
use App\Models\PieceType;
use App\Models\Process;
use App\Models\SpecialProcessPiece;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GarmentCutClassificationService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function configure(
        GarmentCut $garmentCut,
        array $data,
        User $actor,
        Request $request
    ): GarmentCut {
        return DB::transaction(function () use (
            $garmentCut,
            $data,
            $actor,
            $request
        ) {
            $target = GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($garmentCut->id);

            $designArea = $this->getAreaByName('Diseño');

            $this->ensureCutCanBeClassified(
                $target,
                $designArea
            );

            $before = $this->snapshot(
                $this->loadDetailRelations($target)
            );

            $specialPieceLines = $this->normalizeSpecialPieceLines(
                $data['special_process_pieces']
            );

            $this->validateSpecialPieceLines(
                $specialPieceLines
            );

            $complement = GarmentCutComplement::query()
                ->where('garment_cut_id', $target->id)
                ->lockForUpdate()
                ->first();

            $existingSpecialPieces = SpecialProcessPiece::query()
                ->where('garment_cut_id', $target->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('piece_type_id');

            $wasConfigured = $complement !== null
                || $existingSpecialPieces->isNotEmpty();

            $this->ensureClassificationCanBeUpdated(
                $complement,
                $existingSpecialPieces
            );

            if (! $complement) {
                $complement = GarmentCutComplement::create([
                    'garment_cut_id' => $target->id,
                    'current_area_id' => $designArea->id,
                    'status' => 'pending',
                    'notes' => $data['complement_notes'] ?? null,
                ]);
            } elseif (array_key_exists('complement_notes', $data)) {
                $complement->update([
                    'notes' => $data['complement_notes'],
                ]);
            }

            $selectedPieceTypeIds = collect($specialPieceLines)
                ->pluck('piece_type_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($existingSpecialPieces as $existingPiece) {
                $isStillSelected = in_array(
                    (int) $existingPiece->piece_type_id,
                    $selectedPieceTypeIds,
                    true
                );

                if (
                    ! $isStillSelected
                    && $existingPiece->status !== 'cancelled'
                ) {
                    $existingPiece->update([
                        'status' => 'cancelled',
                    ]);
                }
            }

            foreach ($specialPieceLines as $line) {
                $existingPiece = $existingSpecialPieces->get(
                    $line['piece_type_id']
                );

                $attributes = [
                    'process_id' => $line['process_id'],
                    'current_area_id' => $designArea->id,
                    'status' => 'pending',
                    'notes' => $line['notes'],
                ];

                if ($existingPiece) {
                    $existingPiece->update($attributes);

                    continue;
                }

                SpecialProcessPiece::create([
                    'garment_cut_id' => $target->id,
                    'piece_type_id' => $line['piece_type_id'],
                    ...$attributes,
                ]);
            }

            $target = $this->loadDetailRelations(
                $target->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'garment-cut-classification',
                action: $wasConfigured
                    ? 'updated'
                    : 'configured',
                subject: $target,
                description: $wasConfigured
                    ? "Se actualizó la clasificación del corte {$target->code}."
                    : "Se configuró la clasificación del corte {$target->code}.",
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function ensureCutCanBeClassified(
        GarmentCut $garmentCut,
        Area $designArea
    ): void {
        if ($garmentCut->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'garment_cut' =>
                    'El corte debe estar en proceso antes de clasificar sus piezas.',
            ]);
        }

        if ($garmentCut->current_area_id !== $designArea->id) {
            throw ValidationException::withMessages([
                'garment_cut' =>
                    'La clasificación solo puede realizarse cuando el corte se encuentre en Diseño.',
            ]);
        }
    }

    private function ensureClassificationCanBeUpdated(
        ?GarmentCutComplement $complement,
        $existingSpecialPieces
    ): void {
        if (
            $complement
            && $complement->status !== 'pending'
        ) {
            throw ValidationException::withMessages([
                'garment_cut' =>
                    'No puedes modificar la clasificación cuando el complemento ya inició su flujo.',
            ]);
        }

        $startedPieces = $existingSpecialPieces
            ->filter(function (SpecialProcessPiece $piece) {
                return ! in_array(
                    $piece->status,
                    ['pending', 'cancelled'],
                    true
                );
            });

        if ($startedPieces->isNotEmpty()) {
            throw ValidationException::withMessages([
                'garment_cut' =>
                    'No puedes modificar la clasificación cuando una pieza especial ya inició su flujo.',
            ]);
        }
    }

    private function getAreaByName(string $name): Area
    {
        return Area::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function normalizeSpecialPieceLines(
        array $specialPieces
    ): array {
        return collect($specialPieces)
            ->map(function (array $piece) {
                return [
                    'piece_type_id' => (int) $piece['piece_type_id'],
                    'process_id' => (int) $piece['process_id'],
                    'notes' => $piece['notes'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function validateSpecialPieceLines(
        array $specialPieceLines
    ): void {
        $pieceTypeIds = collect($specialPieceLines)
            ->pluck('piece_type_id')
            ->all();

        if (
            count($pieceTypeIds)
            !== count(array_unique($pieceTypeIds))
        ) {
            throw ValidationException::withMessages([
                'special_process_pieces' =>
                    'No puedes repetir un tipo de pieza dentro de la clasificación.',
            ]);
        }

        $activePieceTypes = PieceType::query()
            ->where('status', 'active')
            ->whereIn('id', $pieceTypeIds)
            ->lockForUpdate()
            ->get();

        if (
            $activePieceTypes->count()
            !== count($pieceTypeIds)
        ) {
            throw ValidationException::withMessages([
                'special_process_pieces' =>
                    'Uno o más tipos de pieza no existen o están inactivos.',
            ]);
        }

        if ($specialPieceLines === []) {
            return;
        }

        $designProcess = Process::query()
            ->where('name', 'Diseño')
            ->lockForUpdate()
            ->firstOrFail();

        $processIds = collect($specialPieceLines)
            ->pluck('process_id')
            ->unique()
            ->values()
            ->all();

        $processes = Process::query()
            ->whereIn('id', $processIds)
            ->lockForUpdate()
            ->get();

        if ($processes->count() !== count($processIds)) {
            throw ValidationException::withMessages([
                'special_process_pieces' =>
                    'Uno o más procesos seleccionados no existen.',
            ]);
        }

        $invalidProcesses = $processes
            ->filter(function (Process $process) use ($designProcess) {
                return $process->flow_order
                    <= $designProcess->flow_order;
            });

        if ($invalidProcesses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'special_process_pieces' =>
                    'Una pieza especial solo puede enviarse a un proceso posterior a Diseño.',
            ]);
        }

        $targetAreas = Area::query()
            ->whereIn(
                'name',
                $processes->pluck('name')->all()
            )
            ->get();

        if ($targetAreas->count() !== $processes->count()) {
            throw ValidationException::withMessages([
                'special_process_pieces' =>
                    'Uno de los procesos seleccionados no tiene un área de producción equivalente.',
            ]);
        }
    }

    private function loadDetailRelations(
        GarmentCut $garmentCut
    ): GarmentCut {
        return $garmentCut->load([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',

            'complement.currentArea',

            'specialProcessPieces.pieceType',
            'specialProcessPieces.process',
            'specialProcessPieces.currentArea',
        ]);
    }

    private function snapshot(
        GarmentCut $garmentCut
    ): array {
        $garmentCut->loadMissing([
            'currentArea',
            'complement.currentArea',
            'specialProcessPieces.pieceType',
            'specialProcessPieces.process',
            'specialProcessPieces.currentArea',
        ]);

        return [
            'id' => $garmentCut->id,
            'code' => $garmentCut->code,
            'status' => $garmentCut->status,

            'current_area' => [
                'id' => $garmentCut->currentArea?->id,
                'name' => $garmentCut->currentArea?->name,
            ],

            'complement' => $garmentCut->complement
                ? [
                    'id' => $garmentCut->complement->id,
                    'status' => $garmentCut->complement->status,
                    'current_area' =>
                        $garmentCut->complement->currentArea?->name,
                    'notes' => $garmentCut->complement->notes,
                ]
                : null,

            'special_process_pieces' => $garmentCut
                ->specialProcessPieces
                ->sortBy('id')
                ->values()
                ->map(function (SpecialProcessPiece $piece) {
                    return [
                        'id' => $piece->id,
                        'piece_type' => $piece->pieceType?->name,
                        'process' => $piece->process?->name,
                        'current_area' => $piece->currentArea?->name,
                        'status' => $piece->status,
                        'notes' => $piece->notes,
                    ];
                })
                ->all(),
        ];
    }
}