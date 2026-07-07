<?php

namespace App\Services;

use App\Models\EmbroideryPaymentSetting;
use App\Models\OperationProcess;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmbroideryPaymentSettingManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): EmbroideryPaymentSetting {
        return DB::transaction(function () use ($data, $actor, $request) {
            $operation = OperationProcess::query()
                ->with('process')
                ->lockForUpdate()
                ->findOrFail($data['operation_process_id']);

            $this->ensureOperationUsesEmbroideryFormula($operation);

            $attributes = $this->normalizeAttributes($data);

            $this->ensureNoActivePeriodOverlap(
                operationProcessId: $operation->id,
                effectiveFrom: $attributes['effective_from'],
                effectiveTo: $attributes['effective_to']
            );

            $setting = EmbroideryPaymentSetting::create([
                ...$attributes,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $setting = $this->loadDetailRelations($setting);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'embroidery-payment-settings',
                action: 'created',
                subject: $setting,
                description: "Se registró la configuración de pago para {$operation->name}.",
                newValues: $this->snapshot($setting),
            );

            return $setting;
        });
    }

    public function update(
        EmbroideryPaymentSetting $embroideryPaymentSetting,
        array $data,
        User $actor,
        Request $request
    ): EmbroideryPaymentSetting {
        return DB::transaction(function () use (
            $embroideryPaymentSetting,
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

            $setting = EmbroideryPaymentSetting::query()
                ->with('operationProcess')
                ->lockForUpdate()
                ->findOrFail($embroideryPaymentSetting->id);

            $operation = OperationProcess::query()
                ->lockForUpdate()
                ->findOrFail($setting->operation_process_id);

            $before = $this->snapshot(
                $this->loadDetailRelations($setting)
            );

            $effectiveTo = array_key_exists('effective_to', $data)
                ? $this->normalizeDate($data['effective_to'])
                : $setting->effective_to?->toDateString();

            $status = $data['status'] ?? $setting->status;

            if ($status === 'active') {
                $this->ensureOperationUsesEmbroideryFormula($operation);

                $this->ensureNoActivePeriodOverlap(
                    operationProcessId: $operation->id,
                    effectiveFrom: $setting->effective_from
                        ?->toDateString(),
                    effectiveTo: $effectiveTo,
                    exceptId: $setting->id
                );
            }

            $wasStatusChanged = array_key_exists('status', $data)
                && $status !== $setting->status;

            $setting->fill(Arr::only($data, [
                'effective_to',
                'status',
                'notes',
            ]));

            if (array_key_exists('effective_to', $data)) {
                $setting->effective_to = $effectiveTo;
            }

            $setting->save();

            $setting = $this->loadDetailRelations($setting->fresh());

            $action = $wasStatusChanged
                ? ($setting->status === 'active'
                    ? 'activated'
                    : 'deactivated')
                : 'updated';

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'embroidery-payment-settings',
                action: $action,
                subject: $setting,
                description: "Se actualizó la configuración {$setting->id} de pago de Bordado.",
                oldValues: $before,
                newValues: $this->snapshot($setting),
            );

            return $setting;
        });
    }

    private function ensureOperationUsesEmbroideryFormula(
        OperationProcess $operation
    ): void {
        if (
            $operation->payroll_calculation_type
            !== 'embroidery_formula'
        ) {
            throw ValidationException::withMessages([
                'operation_process_id' =>
                    'La operación seleccionada no utiliza la fórmula especial de pago de Bordado.',
            ]);
        }
    }

    private function ensureNoActivePeriodOverlap(
        int $operationProcessId,
        ?string $effectiveFrom,
        ?string $effectiveTo,
        ?int $exceptId = null
    ): void {
        $effectiveFrom ??= now()->toDateString();

        $effectiveToForQuery = $effectiveTo ?? '9999-12-31';

        $overlaps = EmbroideryPaymentSetting::query()
            ->where('operation_process_id', $operationProcessId)
            ->where('status', 'active')
            ->when(
                $exceptId !== null,
                fn ($query) => $query->whereKeyNot($exceptId)
            )
            ->whereDate(
                'effective_from',
                '<=',
                $effectiveToForQuery
            )
            ->where(function ($query) use ($effectiveFrom) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $effectiveFrom
                    );
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'effective_from' =>
                    'Ya existe una configuración activa de Bordado que se cruza con el periodo indicado.',
            ]);
        }
    }

    private function normalizeAttributes(array $data): array
    {
        return [
            'operation_process_id' =>
                (int) $data['operation_process_id'],

            'stitch_price' => number_format(
                (float) $data['stitch_price'],
                8,
                '.',
                ''
            ),

            'application_price' => number_format(
                (float) $data['application_price'],
                4,
                '.',
                ''
            ),

            'payment_percentage' => number_format(
                (float) $data['payment_percentage'],
                6,
                '.',
                ''
            ),

            'minimum_payment_per_piece' => number_format(
                (float) $data['minimum_payment_per_piece'],
                4,
                '.',
                ''
            ),

            'default_payment_per_piece' => number_format(
                (float) $data['default_payment_per_piece'],
                4,
                '.',
                ''
            ),

            'effective_from' => $this->normalizeDate(
                $data['effective_from']
            ),

            'effective_to' => $this->normalizeDate(
                $data['effective_to'] ?? null
            ),

            'notes' => $data['notes'] ?? null,
        ];
    }

    private function normalizeDate(?string $date): ?string
    {
        return $date !== null
            ? Carbon::parse($date)->toDateString()
            : null;
    }

    private function loadDetailRelations(
        EmbroideryPaymentSetting $setting
    ): EmbroideryPaymentSetting {
        return $setting->load([
            'operationProcess.process',
            'createdBy',
        ]);
    }

    private function snapshot(
        EmbroideryPaymentSetting $setting
    ): array {
        $setting->loadMissing([
            'operationProcess.process',
            'createdBy',
        ]);

        return [
            'id' => $setting->id,
            'stitch_price' => $setting->stitch_price,
            'application_price' => $setting->application_price,
            'payment_percentage' => $setting->payment_percentage,
            'minimum_payment_per_piece' =>
                $setting->minimum_payment_per_piece,
            'default_payment_per_piece' =>
                $setting->default_payment_per_piece,
            'effective_from' =>
                $setting->effective_from?->toDateString(),
            'effective_to' =>
                $setting->effective_to?->toDateString(),
            'status' => $setting->status,
            'notes' => $setting->notes,

            'operation_process' => [
                'id' => $setting->operationProcess?->id,
                'name' => $setting->operationProcess?->name,
                'process' => $setting->operationProcess?->process?->name,
            ],

            'created_by' => $setting->createdBy?->username,
        ];
    }
}