<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summaries = $this->employeeSummaries;

        $pieceworkTotal = (float) $summaries->sum('piecework_amount');
        $fixedTotal = (float) $summaries->sum('fixed_amount');
        $grandTotal = (float) $summaries->sum('total_amount');

        $pieceworkWorkers = $summaries
            ->where('payment_type', 'piecework')
            ->count();

        $fixedWorkers = $summaries
            ->where('payment_type', 'fixed')
            ->count();

        $mixedWorkers = $summaries
            ->where('payment_type', 'mixed')
            ->count();

        return [
            'period' => [
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

                'generated_at' => $this->generated_at?->toISOString(),
                'closed_at' => $this->closed_at?->toISOString(),
            ],

            'totals' => [
                'workers_count' => $summaries->count(),

                'piecework_workers_count' => $pieceworkWorkers,
                'fixed_workers_count' => $fixedWorkers,
                'mixed_workers_count' => $mixedWorkers,

                'piecework_amount' => number_format(
                    $pieceworkTotal,
                    2,
                    '.',
                    ''
                ),

                'fixed_amount' => number_format(
                    $fixedTotal,
                    2,
                    '.',
                    ''
                ),

                'grand_total' => number_format(
                    $grandTotal,
                    2,
                    '.',
                    ''
                ),

                'details_count' => $summaries->sum('details_count'),
            ],

            'employees' => PayrollEmployeePaymentResource::collection(
                $summaries
            ),
        ];
    }
}