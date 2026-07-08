<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollEmployeeSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'payment_type' => $this->payment_type,

            'piecework_amount' => $this->piecework_amount,
            'fixed_amount' => $this->fixed_amount,
            'total_amount' => $this->total_amount,

            'details_count' => $this->details_count,

            'status' => $this->status,

            'calculation_snapshot' => $this->calculation_snapshot,

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

            'details' => PayrollDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}