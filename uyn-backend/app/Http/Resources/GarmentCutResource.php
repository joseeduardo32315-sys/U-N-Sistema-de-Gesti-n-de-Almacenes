<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GarmentCutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'code' => $this->code,
            'description' => $this->description,

            'total_sizes' => $this->total_sizes,
            'base_pieces_per_size' => $this->base_pieces_per_size,
            'total_pieces' => $this->total_pieces,

            'is_uniform_distribution' => $this->base_pieces_per_size !== null,

            'status' => $this->status,
            'status_label' => match ($this->status) {
                'registered' => 'Registrado',
                'in_progress' => 'En proceso',
                'partially_completed' => 'Parcialmente completado',
                'completed' => 'Terminado',
                'cancelled' => 'Cancelado',
                'with_incident' => 'Con incidencia',
                'delayed' => 'Retrasado',
                default => 'No definido',
            },

            'notes' => $this->notes,

            'production_order' => $this->whenLoaded('productionOrder', function () {
                return [
                    'id' => $this->productionOrder?->id,
                    'order_code' => $this->productionOrder?->order_code,
                    'status' => $this->productionOrder?->status,
                    'priority' => $this->productionOrder?->priority,
                    'start_date' => $this->productionOrder?->start_date?->toDateString(),
                    'end_date' => $this->productionOrder?->end_date?->toDateString(),
                ];
            }),

            'garment_model' => $this->whenLoaded('garmentModel', function () {
                return [
                    'id' => $this->garmentModel?->id,
                    'code' => $this->garmentModel?->code,
                    'name' => $this->garmentModel?->name,
                    'status' => $this->garmentModel?->status,
                ];
            }),

            'current_area' => $this->whenLoaded('currentArea', function () {
                return [
                    'id' => $this->currentArea?->id,
                    'name' => $this->currentArea?->name,
                ];
            }),

            'sizes' => $this->whenLoaded('cutSizes', function () {
                return $this->cutSizes
                    ->sortBy(fn ($cutSize) => (int) ($cutSize->size?->name ?? 0))
                    ->values()
                    ->map(function ($cutSize) {
                        return [
                            'id' => $cutSize->id,
                            'size' => [
                                'id' => $cutSize->size?->id,
                                'name' => $cutSize->size?->name,
                            ],
                            'total_pieces' => $cutSize->total_pieces,
                        ];
                    })
                    ->all();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}