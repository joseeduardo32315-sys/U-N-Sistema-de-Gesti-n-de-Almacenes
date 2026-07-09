<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionReworkReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'incident' => [
                'id' => $this->id,
                'incident_type' => $this->incident_type,

                'incident_type_label' => match ($this->incident_type) {
                    'damage' => 'Daño',
                    'quality' => 'Calidad',
                    default => 'Otro',
                },

                'status' => $this->status,
                'quantity_affected' => (int) $this->quantity_affected,
                'description' => $this->description,
                'resolution_notes' => $this->resolution_notes,
                'created_at' => $this->created_at?->toISOString(),
                'resolved_at' => $this->resolved_at?->toISOString(),
            ],

            'garment_cut' => $this->whenLoaded(
                'garmentCut',
                function () {
                    return [
                        'id' => $this->garmentCut?->id,
                        'status' => $this->garmentCut?->status,
                        'garment_model' => [
                            'id' => $this->garmentCut?->garmentModel?->id,
                            'code' => $this->garmentCut?->garmentModel?->code,
                            'name' => $this->garmentCut?->garmentModel?->name,
                        ],
                    ];
                }
            ),

            'origin_movement' => $this->whenLoaded(
                'productionMovement',
                function () {
                    return [
                        'id' => $this->productionMovement?->id,
                        'quantity' => $this->productionMovement?->quantity,
                        'status' => $this->productionMovement?->status,

                        'process' => [
                            'id' => $this->productionMovement?->process?->id,
                            'name' =>
                                $this->productionMovement?->process?->name,
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

                            'operation_process' => [
                                'id' => $this->reworkMovement
                                    ?->operationProcess?->id,
                                'name' => $this->reworkMovement
                                    ?->operationProcess?->name,
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

            'responsible_employee' => $this->whenLoaded(
                'responsibleEmployee',
                function () {
                    return [
                        'id' => $this->responsibleEmployee?->id,
                        'name' => $this->responsibleEmployee?->name,
                        'area' =>
                            $this->responsibleEmployee?->area?->name,
                    ];
                }
            ),
        ];
    }
}