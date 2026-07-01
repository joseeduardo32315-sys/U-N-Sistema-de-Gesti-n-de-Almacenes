<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'target_type' => $this->target_type,

            'target_type_label' => match ($this->target_type) {
                'cut' => 'Corte completo',
                'complement' => 'Complemento del corte',
                'special_piece' => 'Pieza con proceso especial',
                default => 'No definido',
            },

            'quantity' => $this->quantity,

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'pending' => 'Pendiente de recepción',
                'received' => 'Recibido',
                'in_progress' => 'En proceso',
                'partially_completed' => 'Parcialmente completado',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
                'with_incident' => 'Con incidencia',
                'delayed' => 'Retrasado',
                default => 'No definido',
            },

            'start_time' => $this->start_time?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),
            'notes' => $this->notes,

            'garment_cut' => [
                'id' => $this->garmentCut?->id,
                'code' => $this->garmentCut?->code,
                'total_pieces' => $this->garmentCut?->total_pieces,
                'status' => $this->garmentCut?->status,
            ],

            'target' => match ($this->target_type) {
                'cut' => [
                    'id' => $this->garmentCut?->id,
                    'code' => $this->garmentCut?->code,
                    'current_area' => [
                        'id' => $this->garmentCut?->currentArea?->id,
                        'name' => $this->garmentCut?->currentArea?->name,
                    ],
                ],

                'complement' => [
                    'id' => $this->complement?->id,
                    'status' => $this->complement?->status,
                    'current_area' => [
                        'id' => $this->complement?->currentArea?->id,
                        'name' => $this->complement?->currentArea?->name,
                    ],
                ],

                'special_piece' => [
                    'id' => $this->specialProcessPiece?->id,
                    'status' => $this->specialProcessPiece?->status,

                    'piece_type' => [
                        'id' => $this->specialProcessPiece?->pieceType?->id,
                        'name' => $this->specialProcessPiece?->pieceType?->name,
                    ],

                    'special_process' => [
                        'id' => $this->specialProcessPiece?->process?->id,
                        'name' => $this->specialProcessPiece?->process?->name,
                    ],

                    'current_area' => [
                        'id' => $this->specialProcessPiece?->currentArea?->id,
                        'name' => $this->specialProcessPiece?->currentArea?->name,
                    ],
                ],

                default => null,
            },

            'process' => [
                'id' => $this->process?->id,
                'name' => $this->process?->name,
                'flow_order' => $this->process?->flow_order,
            ],

            'operation_process' => [
                'id' => $this->operationProcess?->id,
                'name' => $this->operationProcess?->name,
                'flow_order' => $this->operationProcess?->flow_order,
            ],

            'from_area' => [
                'id' => $this->fromArea?->id,
                'name' => $this->fromArea?->name,
            ],

            'to_area' => [
                'id' => $this->toArea?->id,
                'name' => $this->toArea?->name,
            ],

            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
                'username' => $this->createdBy?->username,
            ],

            'received_by' => $this->receivedBy
                ? [
                    'id' => $this->receivedBy->id,
                    'name' => $this->receivedBy->name,
                    'username' => $this->receivedBy->username,
                ]
                : null,

            'operation_logs_count' => $this->when(
                isset($this->operation_logs_count),
                (int) $this->operation_logs_count
            ),

            'operation_logs' => $this->whenLoaded(
                'operationLogs',
                function () {
                    return ProductionOperationLogResource::collection(
                        $this->operationLogs
                            ->sortBy('id')
                            ->values()
                    );
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}