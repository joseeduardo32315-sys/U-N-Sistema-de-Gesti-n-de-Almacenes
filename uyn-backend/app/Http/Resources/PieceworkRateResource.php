<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PieceworkRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = today();

        return [
            'id' => $this->id,

            'amount_per_piece' => $this->amount_per_piece,

            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),

            'status' => $this->status,

            'status_label' => $this->status === 'active'
                ? 'Activa'
                : 'Inactiva',

            'is_current' => $this->status === 'active'
                && $this->effective_from?->lte($today)
                && (
                    $this->effective_to === null
                    || $this->effective_to->gte($today)
                ),

            'notes' => $this->notes,

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee?->id,
                    'name' => $this->employee?->name,
                    'worker_type' => $this->employee?->worker_type,
                    'area' => $this->employee?->area?->name,
                ];
            }),

            'operation_process' => $this->whenLoaded(
                'operationProcess',
                function () {
                    return [
                        'id' => $this->operationProcess?->id,
                        'name' => $this->operationProcess?->name,
                        'flow_order' => $this->operationProcess?->flow_order,

                        'process' => [
                            'id' => $this->operationProcess?->process?->id,
                            'name' => $this->operationProcess?->process?->name,
                        ],
                    ];
                }
            ),

            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy?->id,
                    'name' => $this->createdBy?->name,
                    'username' => $this->createdBy?->username,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}