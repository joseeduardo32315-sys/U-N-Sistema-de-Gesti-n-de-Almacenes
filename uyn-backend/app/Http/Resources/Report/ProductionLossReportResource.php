<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionLossReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => [
                'type' => data_get($this->resource, 'group.type'),
                'id' => data_get($this->resource, 'group.id'),
                'name' => data_get($this->resource, 'group.name'),
                'code' => data_get($this->resource, 'group.code'),
            ],

            'stats' => [
                'incidents_count' => (int) data_get(
                    $this->resource,
                    'stats.incidents_count',
                    0
                ),

                'open_incidents_count' => (int) data_get(
                    $this->resource,
                    'stats.open_incidents_count',
                    0
                ),

                'resolved_incidents_count' => (int) data_get(
                    $this->resource,
                    'stats.resolved_incidents_count',
                    0
                ),

                'cancelled_incidents_count' => (int) data_get(
                    $this->resource,
                    'stats.cancelled_incidents_count',
                    0
                ),

                'affected_quantity' => (int) data_get(
                    $this->resource,
                    'stats.affected_quantity',
                    0
                ),

                'resolved_loss_quantity' => (int) data_get(
                    $this->resource,
                    'stats.resolved_loss_quantity',
                    0
                ),
            ],
        ];
    }
}