<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionIncidentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'incident_type' => $this->incident_type,

            'incident_type_label' => match ($this->incident_type) {
                'damage' => 'Daño',
                'loss' => 'Pérdida',
                'quality' => 'Calidad',
                'delay' => 'Retraso',
                'other' => 'Otro',
                default => 'No definido',
            },

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'open' => 'Abierta',
                'resolved' => 'Resuelta',
                'cancelled' => 'Cancelada',
                default => 'No definido',
            },

            'quantity_affected' => (int) $this->quantity_affected,

            'description' => $this->description,
            'resolution_notes' => $this->resolution_notes,

            'garment_cut' => $this->whenLoaded(
                'garmentCut',
                function () {
                    return [
                        'id' => $this->garmentCut?->id,
                        'status' => $this->garmentCut?->status,
                        'total_pieces' => $this->garmentCut?->total_pieces,

                        'garment_model' => [
                            'id' => $this->garmentCut?->garmentModel?->id,
                            'code' => $this->garmentCut?->garmentModel?->code,
                            'name' => $this->garmentCut?->garmentModel?->name,
                        ],
                    ];
                }
            ),

            'production_movement' => $this->whenLoaded(
                'productionMovement',
                function () {
                    return [
                        'id' => $this->productionMovement?->id,
                        'target_type' =>
                            $this->productionMovement?->target_type,
                        'quantity' =>
                            $this->productionMovement?->quantity,
                        'status' =>
                            $this->productionMovement?->status,

                        'process' => [
                            'id' => $this->productionMovement?->process?->id,
                            'name' =>
                                $this->productionMovement?->process?->name,
                        ],

                        'operation_process' => [
                            'id' => $this->productionMovement
                                ?->operationProcess?->id,
                            'name' => $this->productionMovement
                                ?->operationProcess?->name,
                        ],

                        'from_area' => [
                            'id' => $this->productionMovement?->fromArea?->id,
                            'name' =>
                                $this->productionMovement?->fromArea?->name,
                        ],

                        'to_area' => [
                            'id' => $this->productionMovement?->toArea?->id,
                            'name' =>
                                $this->productionMovement?->toArea?->name,
                        ],
                    ];
                }
            ),

            'responsible_employee' => $this->whenLoaded(
                'responsibleEmployee',
                function () {
                    return [
                        'id' => $this->responsibleEmployee?->id,
                        'name' => $this->responsibleEmployee?->name,
                        'worker_type' =>
                            $this->responsibleEmployee?->worker_type,
                        'area' =>
                            $this->responsibleEmployee?->area?->name,
                    ];
                }
            ),

            'resolved_by' => $this->whenLoaded(
                'resolvedBy',
                function () {
                    return [
                        'id' => $this->resolvedBy?->id,
                        'name' => $this->resolvedBy?->name,
                        'username' => $this->resolvedBy?->username,
                    ];
                }
            ),

            'has_rework_movement' =>
                $this->relationLoaded('reworkMovement')
                && $this->reworkMovement !== null,

            'rework_movement' => $this->whenLoaded(
                'reworkMovement',
                function () {
                    return $this->reworkMovement
                        ? [
                            'id' => $this->reworkMovement->id,
                            'quantity' => $this->reworkMovement->quantity,
                            'status' => $this->reworkMovement->status,

                            'process' => [
                                'id' => $this->reworkMovement?->process?->id,
                                'name' =>
                                    $this->reworkMovement?->process?->name,
                            ],

                            'from_area' => [
                                'id' =>
                                    $this->reworkMovement?->fromArea?->id,
                                'name' =>
                                    $this->reworkMovement?->fromArea?->name,
                            ],

                            'to_area' => [
                                'id' => $this->reworkMovement?->toArea?->id,
                                'name' =>
                                    $this->reworkMovement?->toArea?->name,
                            ],
                        ]
                        : null;
                }
            ),

            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}