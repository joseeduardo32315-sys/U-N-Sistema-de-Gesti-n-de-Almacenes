<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialProcessPieceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'garment_cut_id' => $this->garment_cut_id,

            'status' => $this->status,
            'status_label' => match ($this->status) {
                'pending' => 'Pendiente',
                'in_progress' => 'En proceso',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
                'with_incident' => 'Con incidencia',
                'delayed' => 'Retrasado',
                default => 'No definido',
            },

            'notes' => $this->notes,

            'piece_type' => $this->whenLoaded(
                'pieceType',
                function () {
                    return [
                        'id' => $this->pieceType?->id,
                        'name' => $this->pieceType?->name,
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

            'current_area' => $this->whenLoaded(
                'currentArea',
                function () {
                    return [
                        'id' => $this->currentArea?->id,
                        'name' => $this->currentArea?->name,
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}