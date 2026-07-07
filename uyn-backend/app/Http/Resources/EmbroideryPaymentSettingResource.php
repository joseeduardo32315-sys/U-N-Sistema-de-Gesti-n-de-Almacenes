<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmbroideryPaymentSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = today();

        return [
            'id' => $this->id,

            'stitch_price' => $this->stitch_price,
            'application_price' => $this->application_price,
            'payment_percentage' => $this->payment_percentage,

            'minimum_payment_per_piece' =>
                $this->minimum_payment_per_piece,

            'default_payment_per_piece' =>
                $this->default_payment_per_piece,

            'effective_from' =>
                $this->effective_from?->toDateString(),

            'effective_to' =>
                $this->effective_to?->toDateString(),

            'status' => $this->status,

            'status_label' => $this->status === 'active'
                ? 'Activa'
                : 'Inactiva',

            'is_current' => $this->status === 'active'
                && $this->effective_from?->lte($today)
                && (
                    $this->effective_to === null
                    || $this->effective_to->gte($today)
                ),

            'notes' => $this->notes,

            'operation_process' => $this->whenLoaded(
                'operationProcess',
                function () {
                    return [
                        'id' => $this->operationProcess?->id,
                        'name' => $this->operationProcess?->name,
                        'payroll_calculation_type' =>
                            $this->operationProcess
                                ?->payroll_calculation_type,

                        'process' => [
                            'id' => $this->operationProcess?->process?->id,
                            'name' => $this->operationProcess?->process?->name,
                        ],
                    ];
                }
            ),

            'created_by' => $this->whenLoaded(
                'createdBy',
                function () {
                    return [
                        'id' => $this->createdBy?->id,
                        'name' => $this->createdBy?->name,
                        'username' => $this->createdBy?->username,
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}