<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GarmentCutComplementResource extends JsonResource
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