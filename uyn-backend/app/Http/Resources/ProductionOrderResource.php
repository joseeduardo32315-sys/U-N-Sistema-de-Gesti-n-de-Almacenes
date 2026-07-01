<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'location' => $this->location,

            'status' => $this->status,
            'status_label' => match ($this->status) {
                'registered' => 'Registrada',
                'in_progress' => 'En proceso',
                'completed' => 'Completada',
                'cancelled' => 'Cancelada',
                default => 'No definido',
            },

            'priority' => $this->priority,
            'priority_label' => match ($this->priority) {
                'low' => 'Baja',
                'normal' => 'Normal',
                'high' => 'Alta',
                'urgent' => 'Urgente',
                default => 'No definida',
            },

            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),

            'notes' => $this->notes,

            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy?->id,
                    'name' => $this->createdBy?->name,
                    'username' => $this->createdBy?->username,
                ];
            }),

            'garment_cuts_count' => $this->when(
                isset($this->garment_cuts_count),
                (int) $this->garment_cuts_count
            ),

            'garment_cuts' => $this->whenLoaded('garmentCuts', function () {
                return $this->garmentCuts
                    ->map(function ($cut) {
                        return [
                            'id' => $cut->id,
                            'code' => $cut->code,
                            'description' => $cut->description,
                            'total_sizes' => $cut->total_sizes,
                            'total_pieces' => $cut->total_pieces,
                            'status' => $cut->status,

                            'garment_model' => [
                                'id' => $cut->garmentModel?->id,
                                'code' => $cut->garmentModel?->code,
                                'name' => $cut->garmentModel?->name,
                            ],

                            'current_area' => [
                                'id' => $cut->currentArea?->id,
                                'name' => $cut->currentArea?->name,
                            ],
                        ];
                    })
                    ->values()
                    ->all();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}