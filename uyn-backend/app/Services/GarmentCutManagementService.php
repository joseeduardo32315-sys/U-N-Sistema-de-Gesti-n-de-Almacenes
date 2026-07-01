<?php

namespace App\Services;

use App\Models\Area;
use App\Models\GarmentCut;
use App\Models\GarmentModel;
use App\Models\ProductionOrder;
use App\Models\Size;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GarmentCutManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): GarmentCut {
        return DB::transaction(function () use ($data, $actor, $request) {
            $order = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($data['production_order_id']);

            $this->ensureOrderAllowsChanges($order);

            $garmentModel = GarmentModel::query()
                ->lockForUpdate()
                ->findOrFail($data['garment_model_id']);

            if ($garmentModel->status !== 'active') {
                throw ValidationException::withMessages([
                    'garment_model_id' => 'El modelo seleccionado está inactivo.',
                ]);
            }

            $cutArea = $this->getCutArea();

            $sizeLines = $this->normalizeSizeLines($data['sizes']);

            $this->ensureActiveSizes($sizeLines);

            $totals = $this->calculateTotals($sizeLines);

            $garmentCut = GarmentCut::create([
                'production_order_id' => $order->id,
                'garment_model_id' => $garmentModel->id,
                'code' => $data['code'],
                'description' => $data['description'] ?? null,

                'total_sizes' => $totals['total_sizes'],
                'base_pieces_per_size' => $totals['base_pieces_per_size'],
                'total_pieces' => $totals['total_pieces'],

                'status' => 'registered',
                'current_area_id' => $cutArea->id,

                'notes' => $data['notes'] ?? null,
            ]);

            $garmentCut->cutSizes()->createMany($sizeLines);

            $garmentCut = $this->loadDetailRelations($garmentCut);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'garment-cuts',
                action: 'created',
                subject: $garmentCut,
                description: "Se registró el corte {$garmentCut->code}.",
                newValues: $this->snapshot($garmentCut),
            );

            return $garmentCut;
        });
    }

    public function update(
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
            if ($data === []) {
                throw ValidationException::withMessages([
                    'request' => 'Debes enviar al menos un dato para actualizar.',
                ]);
            }

            $target = GarmentCut::query()
                ->lockForUpdate()
                ->findOrFail($garmentCut->id);

            $this->ensureCutIsEditable($target);

            $order = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($target->production_order_id);

            $this->ensureOrderAllowsChanges($order);

            $before = $this->snapshot($target);

            $attributes = Arr::only($data, [
                'description',
                'notes',
            ]);

            $sizeLines = null;

            if (array_key_exists('sizes', $data)) {
                $sizeLines = $this->normalizeSizeLines($data['sizes']);

                $this->ensureActiveSizes($sizeLines);

                $attributes = array_merge(
                    $attributes,
                    $this->calculateTotals($sizeLines)
                );
            }

            $target->fill($attributes);
            $target->save();

            if ($sizeLines !== null) {
                $target->cutSizes()->delete();
                $target->cutSizes()->createMany($sizeLines);
            }

            $target = $this->loadDetailRelations(
                $target->fresh()
            );

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'garment-cuts',
                action: 'updated',
                subject: $target,
                description: "Se actualizó el corte {$target->code}.",
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function ensureOrderAllowsChanges(
        ProductionOrder $order
    ): void {
        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'production_order_id' => 'No se pueden registrar o modificar cortes en una orden completada o cancelada.',
            ]);
        }
    }

    private function ensureCutIsEditable(
        GarmentCut $garmentCut
    ): void {
        if ($garmentCut->status !== 'registered') {
            throw ValidationException::withMessages([
                'garment_cut' => 'Solo se puede modificar un corte mientras se encuentre registrado.',
            ]);
        }
    }

    private function getCutArea(): Area
    {
        $area = Area::query()
            ->where('name', 'Corte')
            ->first();

        if (! $area) {
            throw ValidationException::withMessages([
                'current_area' => 'No existe el área inicial Corte.',
            ]);
        }

        return $area;
    }

    private function normalizeSizeLines(array $sizes): array
    {
        return collect($sizes)
            ->map(function (array $size) {
                return [
                    'size_id' => (int) $size['size_id'],
                    'total_pieces' => (int) $size['total_pieces'],
                ];
            })
            ->values()
            ->all();
    }

    private function ensureActiveSizes(array $sizeLines): void
    {
        $sizeIds = collect($sizeLines)
            ->pluck('size_id')
            ->map(fn ($sizeId) => (int) $sizeId)
            ->all();

        if (count($sizeIds) !== count(array_unique($sizeIds))) {
            throw ValidationException::withMessages([
                'sizes' => 'No puedes repetir una talla dentro del mismo corte.',
            ]);
        }

        $activeSizeIds = Size::query()
            ->where('status', 'active')
            ->whereIn('id', $sizeIds)
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($sizeId) => (int) $sizeId)
            ->all();

        if (count($activeSizeIds) !== count($sizeIds)) {
            throw ValidationException::withMessages([
                'sizes' => 'Una o más tallas seleccionadas no existen o están inactivas.',
            ]);
        }
    }

    private function calculateTotals(array $sizeLines): array
    {
        $quantities = collect($sizeLines)
            ->pluck('total_pieces')
            ->map(fn ($quantity) => (int) $quantity);

        $isUniformDistribution = $quantities
            ->unique()
            ->count() === 1;

        return [
            'total_sizes' => $quantities->count(),

            'base_pieces_per_size' => $isUniformDistribution
                ? (int) $quantities->first()
                : null,

            'total_pieces' => (int) $quantities->sum(),
        ];
    }

    private function loadDetailRelations(
        GarmentCut $garmentCut
    ): GarmentCut {
        return $garmentCut->load([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',
        ]);
    }

    private function snapshot(GarmentCut $garmentCut): array
    {
        $garmentCut->loadMissing([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',
        ]);

        return [
            'id' => $garmentCut->id,
            'code' => $garmentCut->code,
            'description' => $garmentCut->description,

            'production_order' => [
                'id' => $garmentCut->productionOrder?->id,
                'order_code' => $garmentCut->productionOrder?->order_code,
            ],

            'garment_model' => [
                'id' => $garmentCut->garmentModel?->id,
                'code' => $garmentCut->garmentModel?->code,
                'name' => $garmentCut->garmentModel?->name,
            ],

            'current_area' => [
                'id' => $garmentCut->currentArea?->id,
                'name' => $garmentCut->currentArea?->name,
            ],

            'total_sizes' => $garmentCut->total_sizes,
            'base_pieces_per_size' => $garmentCut->base_pieces_per_size,
            'total_pieces' => $garmentCut->total_pieces,
            'status' => $garmentCut->status,
            'notes' => $garmentCut->notes,

            'sizes' => $garmentCut->cutSizes
                ->sortBy(fn ($cutSize) => (int) ($cutSize->size?->name ?? 0))
                ->values()
                ->map(function ($cutSize) {
                    return [
                        'size_id' => $cutSize->size_id,
                        'size_name' => $cutSize->size?->name,
                        'total_pieces' => $cutSize->total_pieces,
                    ];
                })
                ->all(),
        ];
    }
}