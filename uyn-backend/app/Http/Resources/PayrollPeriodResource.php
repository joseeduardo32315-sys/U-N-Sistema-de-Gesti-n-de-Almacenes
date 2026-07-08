<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'code' => $this->code,
            'frequency' => $this->frequency,

            'frequency_label' => match ($this->frequency) {
                'weekly' => 'Semanal',
                'biweekly' => 'Quincenal',
                'monthly' => 'Mensual',
                default => 'No definido',
            },

            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'payment_date' => $this->payment_date?->toDateString(),

            'status' => $this->status,

            'status_label' => match ($this->status) {
                'draft' => 'Borrador',
                'generated' => 'Generada',
                'closed' => 'Cerrada',
                'cancelled' => 'Cancelada',
                default => 'No definido',
            },

            'notes' => $this->notes,

            'generated_at' => $this->generated_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),

            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy?->id,
                    'name' => $this->createdBy?->name,
                    'username' => $this->createdBy?->username,
                ];
            }),

            'generated_by' => $this->whenLoaded('generatedBy', function () {
                return [
                    'id' => $this->generatedBy?->id,
                    'name' => $this->generatedBy?->name,
                    'username' => $this->generatedBy?->username,
                ];
            }),

            'closed_by' => $this->whenLoaded('closedBy', function () {
                return [
                    'id' => $this->closedBy?->id,
                    'name' => $this->closedBy?->name,
                    'username' => $this->closedBy?->username,
                ];
            }),

            'employee_summaries' =>
                PayrollEmployeeSummaryResource::collection(
                    $this->whenLoaded('employeeSummaries')
                ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}