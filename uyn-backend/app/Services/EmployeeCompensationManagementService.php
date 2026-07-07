<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeCompensationManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): EmployeeCompensation {
        return DB::transaction(function () use ($data, $actor, $request) {
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);

            if ($employee->status !== 'active') {
                throw ValidationException::withMessages([
                    'employee_id' =>
                        'No puedes registrar una compensación para un trabajador inactivo.',
                ]);
            }

            $attributes = $this->normalizeAttributes($data);

            $this->ensureNoActivePeriodOverlap(
                employeeId: $employee->id,
                effectiveFrom: $attributes['effective_from'],
                effectiveTo: $attributes['effective_to']
            );

            $compensation = EmployeeCompensation::create([
                ...$attributes,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $compensation = $this->loadDetailRelations($compensation);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'employee-compensations',
                action: 'created',
                subject: $compensation,
                description: "Se registró la compensación {$compensation->id} para {$employee->name}.",
                newValues: $this->snapshot($compensation),
            );

            return $compensation;
        });
    }

    public function update(
        EmployeeCompensation $employeeCompensation,
        array $data,
        User $actor,
        Request $request
    ): EmployeeCompensation {
        return DB::transaction(function () use (
            $employeeCompensation,
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

            $compensation = EmployeeCompensation::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($employeeCompensation->id);

            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($compensation->employee_id);

            $before = $this->snapshot(
                $this->loadDetailRelations($compensation)
            );

            $effectiveTo = array_key_exists('effective_to', $data)
                ? $this->normalizeDate($data['effective_to'])
                : $compensation->effective_to?->toDateString();

            $status = $data['status'] ?? $compensation->status;

            if ($status === 'active') {
                if ($employee->status !== 'active') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'No puedes activar una compensación si el trabajador está inactivo.',
                    ]);
                }

                $this->ensureNoActivePeriodOverlap(
                    employeeId: $employee->id,
                    effectiveFrom: $compensation->effective_from
                        ?->toDateString(),
                    effectiveTo: $effectiveTo,
                    exceptId: $compensation->id
                );
            }

            $wasStatusChanged = array_key_exists('status', $data)
                && $status !== $compensation->status;

            $compensation->fill(Arr::only($data, [
                'effective_to',
                'status',
                'notes',
            ]));

            if (array_key_exists('effective_to', $data)) {
                $compensation->effective_to = $effectiveTo;
            }

            $compensation->save();

            $compensation = $this->loadDetailRelations(
                $compensation->fresh()
            );

            $action = $wasStatusChanged
                ? ($compensation->status === 'active'
                    ? 'activated'
                    : 'deactivated')
                : 'updated';

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'employee-compensations',
                action: $action,
                subject: $compensation,
                description: "Se actualizó la compensación {$compensation->id}.",
                oldValues: $before,
                newValues: $this->snapshot($compensation),
            );

            return $compensation;
        });
    }

    private function normalizeAttributes(array $data): array
    {
        $paymentType = $data['payment_type'];

        return [
            'employee_id' => (int) $data['employee_id'],
            'payment_type' => $paymentType,

            'payment_frequency' => $paymentType === 'fixed'
                ? $data['payment_frequency']
                : null,

            'fixed_amount' => $paymentType === 'fixed'
                ? $data['fixed_amount']
                : null,

            'effective_from' => $this->normalizeDate(
                $data['effective_from']
            ),

            'effective_to' => $this->normalizeDate(
                $data['effective_to'] ?? null
            ),

            'notes' => $data['notes'] ?? null,
        ];
    }

    private function ensureNoActivePeriodOverlap(
        int $employeeId,
        ?string $effectiveFrom,
        ?string $effectiveTo,
        ?int $exceptId = null
    ): void {
        $effectiveFrom ??= now()->toDateString();

        $effectiveToForQuery = $effectiveTo ?? '9999-12-31';

        $overlaps = EmployeeCompensation::query()
            ->where('employee_id', $employeeId)
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
                    'Ya existe una compensación activa que se cruza con el periodo indicado para este trabajador.',
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
        EmployeeCompensation $compensation
    ): EmployeeCompensation {
        return $compensation->load([
            'employee.area',
            'createdBy',
        ]);
    }

    private function snapshot(
        EmployeeCompensation $compensation
    ): array {
        $compensation->loadMissing([
            'employee.area',
            'createdBy',
        ]);

        return [
            'id' => $compensation->id,
            'payment_type' => $compensation->payment_type,
            'payment_frequency' => $compensation->payment_frequency,
            'fixed_amount' => $compensation->fixed_amount,
            'effective_from' => $compensation->effective_from?->toDateString(),
            'effective_to' => $compensation->effective_to?->toDateString(),
            'status' => $compensation->status,
            'notes' => $compensation->notes,

            'employee' => [
                'id' => $compensation->employee?->id,
                'name' => $compensation->employee?->name,
                'area' => $compensation->employee?->area?->name,
            ],

            'created_by' => $compensation->createdBy?->username,
        ];
    }
}