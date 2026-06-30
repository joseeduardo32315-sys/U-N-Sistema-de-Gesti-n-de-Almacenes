<?php

namespace App\Services;

use App\Models\GarmentModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GarmentModelManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): GarmentModel {
        $newImagePath = null;

        try {
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')
                    ->store('garment-models', 'public');
            }

            return DB::transaction(function () use (
                $data,
                $actor,
                $request,
                $newImagePath
            ) {
                $garmentModel = GarmentModel::create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'size_range' => $data['size_range'] ?? null,
                    'image_path' => $newImagePath,
                    'status' => $data['status'] ?? 'active',
                ]);

                $this->operationLogService->record(
                    actor: $actor,
                    request: $request,
                    module: 'garment-models',
                    action: 'created',
                    subject: $garmentModel,
                    description: "Se registró el modelo {$garmentModel->code}.",
                    newValues: $this->snapshot($garmentModel),
                );

                return $garmentModel;
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }
    }

    public function update(
        GarmentModel $garmentModel,
        array $data,
        User $actor,
        Request $request
    ): GarmentModel {
        $newImagePath = null;

        try {
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')
                    ->store('garment-models', 'public');
            }

            $result = DB::transaction(function () use (
                $garmentModel,
                $data,
                $actor,
                $request,
                $newImagePath
            ) {
                $target = GarmentModel::query()
                    ->lockForUpdate()
                    ->findOrFail($garmentModel->id);

                $before = $this->snapshot($target);
                $oldImagePath = $target->image_path;

                $attributes = Arr::only($data, [
                    'code',
                    'name',
                    'description',
                    'size_range',
                ]);

                if ($newImagePath) {
                    $attributes['image_path'] = $newImagePath;
                }

                $target->fill($attributes);
                $target->save();

                $target = $target->fresh();

                $this->operationLogService->record(
                    actor: $actor,
                    request: $request,
                    module: 'garment-models',
                    action: 'updated',
                    subject: $target,
                    description: "Se actualizó el modelo {$target->code}.",
                    oldValues: $before,
                    newValues: $this->snapshot($target),
                );

                return [
                    'garment_model' => $target,
                    'old_image_path' => $oldImagePath,
                ];
            });

            if (
                $newImagePath
                && $result['old_image_path']
                && $result['old_image_path'] !== $newImagePath
            ) {
                Storage::disk('public')->delete(
                    $result['old_image_path']
                );
            }

            return $result['garment_model'];
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }
    }

    public function changeStatus(
        GarmentModel $garmentModel,
        string $status,
        User $actor,
        Request $request
    ): GarmentModel {
        return DB::transaction(function () use (
            $garmentModel,
            $status,
            $actor,
            $request
        ) {
            $target = GarmentModel::query()
                ->lockForUpdate()
                ->findOrFail($garmentModel->id);

            $before = $this->snapshot($target);

            $target->update([
                'status' => $status,
            ]);

            $target = $target->fresh();

            $action = $status === 'active'
                ? 'activated'
                : 'deactivated';

            $description = $status === 'active'
                ? "Se activó el modelo {$target->code}."
                : "Se desactivó el modelo {$target->code}.";

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'garment-models',
                action: $action,
                subject: $target,
                description: $description,
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function snapshot(GarmentModel $garmentModel): array
    {
        return [
            'id' => $garmentModel->id,
            'code' => $garmentModel->code,
            'name' => $garmentModel->name,
            'description' => $garmentModel->description,
            'size_range' => $garmentModel->size_range,
            'image_path' => $garmentModel->image_path,
            'status' => $garmentModel->status,
        ];
    }
}