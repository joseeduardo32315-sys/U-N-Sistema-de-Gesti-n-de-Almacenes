<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EmployeeManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): Employee {
        return DB::transaction(function () use ($data, $actor, $request) {
            $employee = Employee::create([
                'name' => $data['name'],
                'area_id' => $data['area_id'],
                'worker_type' => $data['worker_type'],
                'phone' => $data['phone'],
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            $employee->load('area');

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'employees',
                action: 'created',
                subject: $employee,
                description: "Se registró el {$this->workerLabel($employee)} {$employee->name}.",
                newValues: $this->snapshot($employee),
            );

            return $employee;
        });
    }

    public function update(
        Employee $employee,
        array $data,
        User $actor,
        Request $request
    ): Employee {
        return DB::transaction(function () use (
            $employee,
            $data,
            $actor,
            $request
        ) {
            $target = Employee::query()
                ->with('area')
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $before = $this->snapshot($target);

            $attributes = Arr::only($data, [
                'name',
                'area_id',
                'worker_type',
                'phone',
                'notes',
            ]);

            $target->fill($attributes);
            $target->save();

            $target = $target->fresh(['area']);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'employees',
                action: 'updated',
                subject: $target,
                description: "Se actualizó el {$this->workerLabel($target)} {$target->name}.",
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    public function changeStatus(
        Employee $employee,
        string $status,
        User $actor,
        Request $request
    ): Employee {
        return DB::transaction(function () use (
            $employee,
            $status,
            $actor,
            $request
        ) {
            $target = Employee::query()
                ->with('area')
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $before = $this->snapshot($target);

            $target->update([
                'status' => $status,
            ]);

            $target = $target->fresh(['area']);

            $action = $status === 'active'
                ? 'activated'
                : 'deactivated';

            $description = $status === 'active'
                ? "Se activó el {$this->workerLabel($target)} {$target->name}."
                : "Se desactivó el {$this->workerLabel($target)} {$target->name}.";

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'employees',
                action: $action,
                subject: $target,
                description: $description,
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function workerLabel(Employee $employee): string
    {
        return $employee->worker_type === 'external'
            ? 'maquilero externo'
            : 'empleado interno';
    }

    private function snapshot(Employee $employee): array
    {
        $employee->loadMissing('area');

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'area_id' => $employee->area_id,
            'area_name' => $employee->area?->name,
            'worker_type' => $employee->worker_type,
            'phone' => $employee->phone,
            'status' => $employee->status,
            'notes' => $employee->notes,
        ];
    }
}