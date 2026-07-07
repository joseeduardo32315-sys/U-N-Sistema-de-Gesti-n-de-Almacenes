<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\OperationProcess;
use App\Models\PieceworkRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PieceworkRateManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): PieceworkRate {
        return DB::transaction(function () use ($data, $actor, $request) {
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);

            if ($employee->status !== 'active') {
                throw ValidationException::withMessages([
                    'employee_id' =>
                        'No puedes registrar una tarifa para un trabajador inactivo.',
                ]);
            }

            $operation = OperationProcess::query()
                ->with('process')
                ->lockForUpdate()
                ->findOrFail($data['operation_process_id']);

            $this->ensureOperationAllowsPerPieceRate($operation);

            $attributes = $this->normalizeAttributes($data);

            $this->ensureEmployeeHasPieceworkCompensation(
                employeeId: $employee->id,
                effectiveFrom: $attributes['effective_from']
            );

            $this->ensureNoActivePeriodOverlap(
                employeeId: $employee->id,
                operationProcessId: $operation->id,
                effectiveFrom: $attributes['effective_from'],
                effectiveTo: $attributes['effective_to']
            );

            $rate = PieceworkRate::create([
                ...$attributes,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $rate = $this->loadDetailRelations($rate);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'piecework-rates',
                action: 'created',
                subject: $rate,
                description: "Se registró la tarifa {$rate->id} para {$employee->name} en {$operation->name}.",
                newValues: $this->snapshot($rate),
            );

            return $rate;
        });
    }

    public function update(
        PieceworkRate $pieceworkRate,
        array $data,
        User $actor,
        Request $request
    ): PieceworkRate {
        return DB::transaction(function () use (
            $pieceworkRate,
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

            $rate = PieceworkRate::query()
                ->with(['employee', 'operationProcess'])
                ->lockForUpdate()
                ->findOrFail($pieceworkRate->id);

            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($rate->employee_id);

            $before = $this->snapshot(
                $this->loadDetailRelations($rate)
            );

            $effectiveTo = array_key_exists('effective_to', $data)
                ? $this->normalizeDate($data['effective_to'])
                : $rate->effective_to?->toDateString();

            $status = $data['status'] ?? $rate->status;

            $operation = OperationProcess::query()
                ->lockForUpdate()
                ->findOrFail($rate->operation_process_id);

            if ($status === 'active') {

                $this->ensureOperationAllowsPerPieceRate($operation);    

                if ($employee->status !== 'active') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'No puedes activar una tarifa si el trabajador está inactivo.',
                    ]);
                }

                $this->ensureEmployeeHasPieceworkCompensation(
                    employeeId: $employee->id,
                    effectiveFrom: $rate->effective_from?->toDateString()
                );

                $this->ensureNoActivePeriodOverlap(
                    employeeId: $rate->employee_id,
                    operationProcessId: $rate->operation_process_id,
                    effectiveFrom: $rate->effective_from?->toDateString(),
                    effectiveTo: $effectiveTo,
                    exceptId: $rate->id
                );
            }

            $wasStatusChanged = array_key_exists('status', $data)
                && $status !== $rate->status;

            $rate->fill(Arr::only($data, [
                'effective_to',
                'status',
                'notes',
            ]));

            if (array_key_exists('effective_to', $data)) {
                $rate->effective_to = $effectiveTo;
            }

            $rate->save();

            $rate = $this->loadDetailRelations($rate->fresh());

            $action = $wasStatusChanged
                ? ($rate->status === 'active'
                    ? 'activated'
                    : 'deactivated')
                : 'updated';

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'piecework-rates',
                action: $action,
                subject: $rate,
                description: "Se actualizó la tarifa {$rate->id}.",
                oldValues: $before,
                newValues: $this->snapshot($rate),
            );

            return $rate;
        });
    }

    private function normalizeAttributes(array $data): array
    {
        return [
            'employee_id' => (int) $data['employee_id'],
            'operation_process_id' => (int) $data['operation_process_id'],

            'amount_per_piece' => number_format(
                (float) $data['amount_per_piece'],
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

    private function ensureEmployeeHasPieceworkCompensation(
        int $employeeId,
        ?string $effectiveFrom
    ): void {
        $effectiveFrom ??= now()->toDateString();

        $hasPieceworkProfile = EmployeeCompensation::query()
            ->where('employee_id', $employeeId)
            ->where('payment_type', 'piecework')
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $effectiveFrom)
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

        if (! $hasPieceworkProfile) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El trabajador debe contar con una compensación activa de tipo destajo para la fecha de vigencia de la tarifa.',
            ]);
        }
    }

    private function ensureNoActivePeriodOverlap(
        int $employeeId,
        int $operationProcessId,
        ?string $effectiveFrom,
        ?string $effectiveTo,
        ?int $exceptId = null
    ): void {
        $effectiveFrom ??= now()->toDateString();

        $effectiveToForQuery = $effectiveTo ?? '9999-12-31';

        $overlaps = PieceworkRate::query()
            ->where('employee_id', $employeeId)
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
                    'Ya existe una tarifa activa que se cruza con el periodo indicado para este trabajador y operación.',
            ]);
        }
    }

    private function normalizeDate(?string $date): ?string
    {
        return $date !== null
            ? Carbon::parse($date)->toDateString()
            : null;
    }

    private function loadDetailRelations(
        PieceworkRate $rate
    ): PieceworkRate {
        return $rate->load([
            'employee.area',
            'operationProcess.process',
            'createdBy',
        ]);
    }

    private function snapshot(
        PieceworkRate $rate
    ): array {
        $rate->loadMissing([
            'employee.area',
            'operationProcess.process',
            'createdBy',
        ]);

        return [
            'id' => $rate->id,
            'amount_per_piece' => $rate->amount_per_piece,
            'effective_from' => $rate->effective_from?->toDateString(),
            'effective_to' => $rate->effective_to?->toDateString(),
            'status' => $rate->status,
            'notes' => $rate->notes,

            'employee' => [
                'id' => $rate->employee?->id,
                'name' => $rate->employee?->name,
                'area' => $rate->employee?->area?->name,
            ],

            'operation_process' => [
                'id' => $rate->operationProcess?->id,
                'name' => $rate->operationProcess?->name,
                'process' => $rate->operationProcess?->process?->name,
            ],

            'created_by' => $rate->createdBy?->username,
        ];
    }

    private function ensureOperationAllowsPerPieceRate(
        OperationProcess $operation
    ): void {
        if ($operation->payroll_calculation_type !== 'per_piece') {
            throw ValidationException::withMessages([
                'operation_process_id' =>
                    'La operación seleccionada utiliza una fórmula especial de pago y no admite una tarifa directa por pieza.',
            ]);
        }
    }
}