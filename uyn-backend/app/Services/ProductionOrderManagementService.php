<?php

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOrderManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): ProductionOrder {
        return DB::transaction(function () use ($data, $actor, $request) {
            $this->validateDates(
                startDate: $data['start_date'],
                endDate: $data['end_date'] ?? null
            );

            $order = ProductionOrder::create([
                'order_code' => $data['order_code'],
                'location' => $data['location'] ?? null,
                'status' => 'registered',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->load('createdBy');

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-orders',
                action: 'created',
                subject: $order,
                description: "Se registró la orden de producción {$order->order_code}.",
                newValues: $this->snapshot($order),
            );

            return $order;
        });
    }

    public function update(
        ProductionOrder $productionOrder,
        array $data,
        User $actor,
        Request $request
    ): ProductionOrder {
        return DB::transaction(function () use (
            $productionOrder,
            $data,
            $actor,
            $request
        ) {
            $target = ProductionOrder::query()
                ->with('createdBy')
                ->lockForUpdate()
                ->findOrFail($productionOrder->id);

            if (in_array($target->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'production_order' => 'No se puede modificar una orden completada o cancelada.',
                ]);
            }

            $before = $this->snapshot($target);

            $effectiveStartDate = $data['start_date']
                ?? $target->start_date?->toDateString();

            $effectiveEndDate = array_key_exists('end_date', $data)
                ? $data['end_date']
                : $target->end_date?->toDateString();

            $this->validateDates(
                startDate: $effectiveStartDate,
                endDate: $effectiveEndDate
            );

            $target->fill(Arr::only($data, [
                'location',
                'start_date',
                'end_date',
                'priority',
                'notes',
            ]));

            $target->save();

            $target = $target->fresh(['createdBy']);
            $target->loadCount('garmentCuts');

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'production-orders',
                action: 'updated',
                subject: $target,
                description: "Se actualizó la orden de producción {$target->order_code}.",
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function validateDates(
        string $startDate,
        ?string $endDate
    ): void {
        if (
            $endDate !== null
            && Carbon::parse($endDate)->isBefore(
                Carbon::parse($startDate)
            )
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha estimada no puede ser anterior a la fecha de inicio.',
            ]);
        }
    }

    private function snapshot(ProductionOrder $order): array
    {
        $order->loadMissing('createdBy');

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'location' => $order->location,
            'status' => $order->status,
            'priority' => $order->priority,
            'start_date' => $order->start_date?->toDateString(),
            'end_date' => $order->end_date?->toDateString(),
            'created_by' => $order->created_by,
            'created_by_username' => $order->createdBy?->username,
            'notes' => $order->notes,
        ];
    }
}