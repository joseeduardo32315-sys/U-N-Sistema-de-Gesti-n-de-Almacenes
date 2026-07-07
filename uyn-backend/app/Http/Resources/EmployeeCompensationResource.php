<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCompensationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = today();

        return [
            'id' => $this->id,

            'payment_type' => $this->payment_type,

            'payment_type_label' => match ($this->payment_type) {
                'piecework' => 'Pago por destajo',
                'fixed' => 'Pago fijo',
                default => 'No definido',
            },

            'payment_frequency' => $this->payment_frequency,

            'payment_frequency_label' => match ($this->payment_frequency) {
                'weekly' => 'Semanal',
                'biweekly' => 'Quincenal',
                'monthly' => 'Mensual',
                default => null,
            },

            'fixed_amount' => $this->fixed_amount,

            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),

            'status' => $this->status,

            'status_label' => $this->status === 'active'
                ? 'Activo'
                : 'Inactivo',

            'is_current' => $this->status === 'active'
                && $this->effective_from?->lte($today)
                && (
                    $this->effective_to === null
                    || $this->effective_to->gte($today)
                ),

            'notes' => $this->notes,

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee?->id,
                    'name' => $this->employee?->name,
                    'worker_type' => $this->employee?->worker_type,
                    'status' => $this->employee?->status,
                    'area' => $this->employee?->area?->name,
                ];
            }),

            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy?->id,
                    'name' => $this->createdBy?->name,
                    'username' => $this->createdBy?->username,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}