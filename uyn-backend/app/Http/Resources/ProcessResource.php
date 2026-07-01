<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'flow_order' => $this->flow_order,

            'operations' => $this->whenLoaded(
                'operationProcesses',
                function () {
                    return $this->operationProcesses
                        ->sortBy('flow_order')
                        ->values()
                        ->map(function ($operation) {
                            return [
                                'id' => $operation->id,
                                'name' => $operation->name,
                                'flow_order' => $operation->flow_order,
                            ];
                        })
                        ->all();
                }
            ),
        ];
    }
}