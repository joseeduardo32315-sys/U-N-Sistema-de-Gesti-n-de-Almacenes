<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionCutReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->getAttribute('report_stats') ?? [];

        $totalPieces = (int) $this->total_pieces;

        $resolvedLossQuantity = (int) ($stats['resolved_loss_quantity'] ?? 0);

        $effectivePieces = max(
            0,
            $totalPieces - $resolvedLossQuantity
        );

        return [
            'id' => $this->id,

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'registered' => 'Registrado',
                'in_progress' => 'En proceso',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
                default => 'No definido',
            },

            'total_sizes' => $this->total_sizes,
            'base_pieces_per_size' => $this->base_pieces_per_size,
            'total_pieces' => $totalPieces,
            'effective_pieces' => $effectivePieces,

            'current_area' => $this->whenLoaded(
                'currentArea',
                function () {
                    return [
                        'id' => $this->currentArea?->id,
                        'name' => $this->currentArea?->name,
                    ];
                }
            ),

            'garment_model' => $this->whenLoaded(
                'garmentModel',
                function () {
                    return [
                        'id' => $this->garmentModel?->id,
                        'code' => $this->garmentModel?->code,
                        'name' => $this->garmentModel?->name,
                        'status' => $this->garmentModel?->status,
                    ];
                }
            ),

            'production_order' => $this->whenLoaded(
                'productionOrder',
                function () {
                    return [
                        'id' => $this->productionOrder?->id,
                        'code' => $this->productionOrder?->code,
                        'status' => $this->productionOrder?->status,
                        'priority' => $this->productionOrder?->priority,
                    ];
                }
            ),

            'movement_summary' => [
                'movements_count' =>
                    (int) ($stats['movements_count'] ?? 0),

                'dispatched_quantity' =>
                    (int) ($stats['dispatched_quantity'] ?? 0),

                'received_quantity' =>
                    (int) ($stats['received_quantity'] ?? 0),

                'completed_quantity' =>
                    (int) ($stats['completed_quantity'] ?? 0),

                'processed_quantity' =>
                    (int) ($stats['processed_quantity'] ?? 0),

                'resolved_loss_quantity' =>
                    $resolvedLossQuantity,

                'open_incidents_count' =>
                    (int) ($stats['open_incidents_count'] ?? 0),
            ],

            'progress' => [
                'completed_percentage' => $effectivePieces > 0
                    ? round(
                        ((int) ($stats['completed_quantity'] ?? 0)
                        / $effectivePieces) * 100,
                        2
                    )
                    : 0,

                'processed_percentage' => $effectivePieces > 0
                    ? round(
                        ((int) ($stats['processed_quantity'] ?? 0)
                        / $effectivePieces) * 100,
                        2
                    )
                    : 0,
            ],

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}