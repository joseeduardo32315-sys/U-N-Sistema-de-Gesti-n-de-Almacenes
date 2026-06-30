<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'worker_type' => $this->worker_type,
            'worker_type_label' => match ($this->worker_type) {
                'internal' => 'Empleado interno',
                'external' => 'Maquilero externo',
                default => 'No definido',
            },

            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,

            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'name' => $this->area->name,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
