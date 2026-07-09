<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PayrollDetailResource;

class PayrollEmployeePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary_id' => $this->id,

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee?->id,
                    'name' => $this->employee?->name,
                    'worker_type' => $this->employee?->worker_type,
                    'area' => $this->employee?->area?->name,
                    'status' => $this->employee?->status,
                ];
            }),

            'period' => $this->whenLoaded('payrollPeriod', function () {
                return [
                    'id' => $this->payrollPeriod?->id,
                    'code' => $this->payrollPeriod?->code,
                    'frequency' => $this->payrollPeriod?->frequency,
                    'start_date' => $this->payrollPeriod
                        ?->start_date?->toDateString(),
                    'end_date' => $this->payrollPeriod
                        ?->end_date?->toDateString(),
                    'payment_date' => $this->payrollPeriod
                        ?->payment_date?->toDateString(),
                    'status' => $this->payrollPeriod?->status,
                ];
            }),

            'payment_type' => $this->payment_type,

            'payment_type_label' => match ($this->payment_type) {
                'piecework' => 'Destajo',
                'fixed' => 'Pago fijo',
                'mixed' => 'Mixto',
                default => 'No definido',
            },

            'piecework_amount' => $this->piecework_amount,
            'fixed_amount' => $this->fixed_amount,
            'total_amount' => $this->total_amount,

            'details_count' => $this->details_count,

            'status' => $this->status,

            'details' => PayrollDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'status_label' => match ($this->status) {
                'generated' => 'Generado',
                'reviewed' => 'Revisado',
                'paid' => 'Pagado',
                default => 'No definido',
            },

            'calculation_snapshot' => $this->calculation_snapshot,
        ];
    }
}