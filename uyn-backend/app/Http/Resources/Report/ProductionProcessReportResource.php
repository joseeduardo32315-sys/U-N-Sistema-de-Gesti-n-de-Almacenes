<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionProcessReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'process' => [
                'id' => data_get($this->resource, 'process.id'),
                'name' => data_get($this->resource, 'process.name'),
                'flow_order' => data_get(
                    $this->resource,
                    'process.flow_order'
                ),
            ],

            'operation_process' => [
                'id' => data_get(
                    $this->resource,
                    'operation_process.id'
                ),
                'name' => data_get(
                    $this->resource,
                    'operation_process.name'
                ),
                'flow_order' => data_get(
                    $this->resource,
                    'operation_process.flow_order'
                ),
                'payroll_calculation_type' => data_get(
                    $this->resource,
                    'operation_process.payroll_calculation_type'
                ),
            ],

            'stats' => [
                'movements_count' => (int) data_get(
                    $this->resource,
                    'stats.movements_count',
                    0
                ),

                'dispatched_quantity' => (int) data_get(
                    $this->resource,
                    'stats.dispatched_quantity',
                    0
                ),

                'received_quantity' => (int) data_get(
                    $this->resource,
                    'stats.received_quantity',
                    0
                ),

                'in_progress_quantity' => (int) data_get(
                    $this->resource,
                    'stats.in_progress_quantity',
                    0
                ),

                'completed_quantity' => (int) data_get(
                    $this->resource,
                    'stats.completed_quantity',
                    0
                ),

                'processed_quantity' => (int) data_get(
                    $this->resource,
                    'stats.processed_quantity',
                    0
                ),

                'resolved_loss_quantity' => (int) data_get(
                    $this->resource,
                    'stats.resolved_loss_quantity',
                    0
                ),

                'open_incidents_count' => (int) data_get(
                    $this->resource,
                    'stats.open_incidents_count',
                    0
                ),
            ],
        ];
    }
}