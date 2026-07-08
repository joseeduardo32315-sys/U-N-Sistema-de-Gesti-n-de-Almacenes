<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'source_type' => $this->source_type,
            'production_operation_log_id' =>
                $this->production_operation_log_id,
            'employee_compensation_id' =>
                $this->employee_compensation_id,

            'description' => $this->description,

            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'amount' => $this->amount,

            'occurred_at' => $this->occurred_at?->toISOString(),

            'calculation_snapshot' => $this->calculation_snapshot,

            'production_operation_log' => $this->whenLoaded(
                'productionOperationLog',
                function () {
                    return [
                        'id' => $this->productionOperationLog?->id,
                        'quantity_processed' =>
                            $this->productionOperationLog
                                ?->quantity_processed,
                        'status' =>
                            $this->productionOperationLog?->status,
                        'end_time' =>
                            $this->productionOperationLog
                                ?->end_time?->toISOString(),

                        'operation_process' => [
                            'id' => $this->productionOperationLog
                                ?->operationProcess?->id,
                            'name' => $this->productionOperationLog
                                ?->operationProcess?->name,
                        ],

                        'movement' => [
                            'id' => $this->productionOperationLog
                                ?->productionMovement?->id,
                            'target_type' => $this->productionOperationLog
                                ?->productionMovement?->target_type,
                        ],
                    ];
                }
            ),

            'employee_compensation' => $this->whenLoaded(
                'employeeCompensation',
                function () {
                    return [
                        'id' => $this->employeeCompensation?->id,
                        'payment_type' =>
                            $this->employeeCompensation?->payment_type,
                        'payment_frequency' =>
                            $this->employeeCompensation
                                ?->payment_frequency,
                        'fixed_amount' =>
                            $this->employeeCompensation?->fixed_amount,
                        'effective_from' =>
                            $this->employeeCompensation
                                ?->effective_from?->toDateString(),
                        'effective_to' =>
                            $this->employeeCompensation
                                ?->effective_to?->toDateString(),
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}