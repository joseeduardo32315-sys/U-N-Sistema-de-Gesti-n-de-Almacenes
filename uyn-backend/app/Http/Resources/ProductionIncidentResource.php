<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'incident_type' => $this->incident_type,

            'incident_type_label' => match ($this->incident_type) {
                'damage' => 'Daño o merma',
                'loss' => 'Pérdida o faltante',
                'quality' => 'Defecto de calidad',
                'delay' => 'Retraso',
                'other' => 'Otra incidencia',
                default => 'No definido',
            },

            'quantity_affected' => $this->quantity_affected,

            'description' => $this->description,

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'open' => 'Abierta',
                'resolved' => 'Resuelta',
                'cancelled' => 'Cancelada',
                default => 'No definido',
            },

            'notes' => $this->notes,

            'resolved_at' => $this->resolved_at?->toISOString(),

            'rework_movement' => $this->whenLoaded(
                'reworkMovement',
                function () {
                    return $this->reworkMovement
                        ? [
                            'id' => $this->reworkMovement->id,
                            'quantity' => $this->reworkMovement->quantity,
                            'status' => $this->reworkMovement->status,

                            'process' => $this->reworkMovement
                                ->process
                                ?->name,

                            'from_area' => $this->reworkMovement
                                ->fromArea
                                ?->name,

                            'to_area' => $this->reworkMovement
                                ->toArea
                                ?->name,
                        ]
                        : null;
                }
            ),

            'garment_cut' => $this->whenLoaded(
                'garmentCut',
                function () {
                    return [
                        'id' => $this->garmentCut?->id,
                        'code' => $this->garmentCut?->code,
                        'status' => $this->garmentCut?->status,
                    ];
                }
            ),

            'production_movement' => $this->whenLoaded(
                'productionMovement',
                function () {
                    $movement = $this->productionMovement;

                    return [
                        'id' => $movement?->id,
                        'target_type' => $movement?->target_type,
                        'quantity' => $movement?->quantity,
                        'status' => $movement?->status,

                        'process' => [
                            'id' => $movement?->process?->id,
                            'name' => $movement?->process?->name,
                        ],

                        'from_area' => [
                            'id' => $movement?->fromArea?->id,
                            'name' => $movement?->fromArea?->name,
                        ],

                        'to_area' => [
                            'id' => $movement?->toArea?->id,
                            'name' => $movement?->toArea?->name,
                        ],

                        'target' => match ($movement?->target_type) {
                            'cut' => [
                                'id' => $movement?->garmentCut?->id,
                                'code' => $movement?->garmentCut?->code,
                                'status' => $movement?->garmentCut?->status,
                                'current_area' => $movement
                                    ?->garmentCut
                                    ?->currentArea
                                    ?->name,
                            ],

                            'complement' => [
                                'id' => $movement?->complement?->id,
                                'status' => $movement?->complement?->status,
                                'current_area' => $movement
                                    ?->complement
                                    ?->currentArea
                                    ?->name,
                            ],

                            'special_piece' => [
                                'id' => $movement
                                    ?->specialProcessPiece
                                    ?->id,

                                'piece_type' => $movement
                                    ?->specialProcessPiece
                                    ?->pieceType
                                    ?->name,

                                'status' => $movement
                                    ?->specialProcessPiece
                                    ?->status,

                                'current_area' => $movement
                                    ?->specialProcessPiece
                                    ?->currentArea
                                    ?->name,
                            ],

                            default => null,
                        },
                    ];
                }
            ),

            'responsible_employee' => $this->whenLoaded(
                'responsibleEmployee',
                function () {
                    return $this->responsibleEmployee
                        ? [
                            'id' => $this->responsibleEmployee->id,
                            'name' => $this->responsibleEmployee->name,
                            'area' => $this->responsibleEmployee
                                ->area
                                ?->name,
                        ]
                        : null;
                }
            ),

            'resolved_by' => $this->whenLoaded(
                'resolvedBy',
                function () {
                    return $this->resolvedBy
                        ? [
                            'id' => $this->resolvedBy->id,
                            'name' => $this->resolvedBy->name,
                            'username' => $this->resolvedBy->username,
                        ]
                        : null;
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}