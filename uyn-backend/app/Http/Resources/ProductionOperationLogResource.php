<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOperationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewPayroll = $request->user()?->can('payroll.view') ?? false;

        return [
            'id' => $this->id,

            'quantity_processed' => $this->quantity_processed,

            'stitches_count' => $this->stitches_count,
            'applications_count' => $this->applications_count,

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'pending' => 'Pendiente',
                'in_progress' => 'En proceso',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
                'with_incident' => 'Con incidencia',
                default => 'No definido',
            },

            'start_time' => $this->start_time?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),

            'notes' => $this->notes,

            'payout_amount' => $this->when(
                $canViewPayroll,
                $this->payout_amount
            ),

            'payout_status' => $this->when(
                $canViewPayroll,
                data_get($this->payout_snapshot, 'payment_status')
            ),

            'payout_snapshot' => $this->when(
                $canViewPayroll,
                $this->payout_snapshot
            ),

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee?->id,
                    'name' => $this->employee?->name,
                    'worker_type' => $this->employee?->worker_type,
                    'status' => $this->employee?->status,
                ];
            }),

            'operation_process' => $this->whenLoaded(
                'operationProcess',
                function () {
                    return [
                        'id' => $this->operationProcess?->id,
                        'name' => $this->operationProcess?->name,
                        'flow_order' => $this->operationProcess?->flow_order,
                    ];
                }
            ),

            'production_movement' => $this->whenLoaded(
                'productionMovement',
                function () {
                    return [
                        'id' => $this->productionMovement?->id,
                        'quantity' => $this->productionMovement?->quantity,
                        'status' => $this->productionMovement?->status,
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}