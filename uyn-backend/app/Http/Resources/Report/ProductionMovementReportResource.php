<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionMovementReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->getAttribute('report_stats') ?? [];

        $quantity = (int) $this->quantity;

        $resolvedLossQuantity =
            (int) ($stats['resolved_loss_quantity'] ?? 0);

        $effectiveQuantity = max(
            0,
            $quantity - $resolvedLossQuantity
        );

        return [
            'id' => $this->id,

            'target_type' => $this->target_type,

            'target_type_label' => match ($this->target_type) {
                'cut' => 'Corte completo',
                'complement' => 'Complemento',
                'special_piece' => 'Pieza especial',
                default => 'No definido',
            },

            'quantity' => $quantity,
            'effective_quantity' => $effectiveQuantity,

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'pending' => 'Pendiente',
                'received' => 'Recibido',
                'in_progress' => 'En proceso',
                'completed' => 'Completado',
                'with_incident' => 'Con incidencia',
                'delayed' => 'Retrasado',
                'cancelled' => 'Cancelado',
                default => 'No definido',
            },

            'garment_cut' => $this->whenLoaded(
                'garmentCut',
                function () {
                    return [
                        'id' => $this->garmentCut?->id,
                        'status' => $this->garmentCut?->status,
                        'total_pieces' =>
                            $this->garmentCut?->total_pieces,

                        'garment_model' => [
                            'id' => $this->garmentCut
                                ?->garmentModel?->id,
                            'code' => $this->garmentCut
                                ?->garmentModel?->code,
                            'name' => $this->garmentCut
                                ?->garmentModel?->name,
                        ],
                    ];
                }
            ),

            'process' => $this->whenLoaded(
                'process',
                function () {
                    return [
                        'id' => $this->process?->id,
                        'name' => $this->process?->name,
                        'flow_order' => $this->process?->flow_order,
                    ];
                }
            ),

            'operation_process' => $this->whenLoaded(
                'operationProcess',
                function () {
                    return [
                        'id' => $this->operationProcess?->id,
                        'name' => $this->operationProcess?->name,
                        'flow_order' =>
                            $this->operationProcess?->flow_order,
                    ];
                }
            ),

            'from_area' => $this->whenLoaded(
                'fromArea',
                function () {
                    return [
                        'id' => $this->fromArea?->id,
                        'name' => $this->fromArea?->name,
                    ];
                }
            ),

            'to_area' => $this->whenLoaded(
                'toArea',
                function () {
                    return [
                        'id' => $this->toArea?->id,
                        'name' => $this->toArea?->name,
                    ];
                }
            ),

            'operation_summary' => [
                'workers_count' =>
                    (int) ($stats['workers_count'] ?? 0),

                'processed_quantity' =>
                    (int) ($stats['processed_quantity'] ?? 0),

                'resolved_loss_quantity' =>
                    $resolvedLossQuantity,

                'open_incidents_count' =>
                    (int) ($stats['open_incidents_count'] ?? 0),

                'progress_percentage' => $effectiveQuantity > 0
                    ? round(
                        ((int) ($stats['processed_quantity'] ?? 0)
                        / $effectiveQuantity) * 100,
                        2
                    )
                    : 0,
            ],

            'start_time' => $this->start_time?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}