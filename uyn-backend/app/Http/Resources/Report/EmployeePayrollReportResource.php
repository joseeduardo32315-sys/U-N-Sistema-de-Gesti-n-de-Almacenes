<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePayrollReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payments = $this->resource;

        $pieceworkTotal = (float) $payments->sum('piecework_amount');
        $fixedTotal = (float) $payments->sum('fixed_amount');
        $grandTotal = (float) $payments->sum('total_amount');

        return [
            'filters' => [
                'employee_id' => $request->query('employee_id'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'payment_type' => $request->query(
                    'payment_type',
                    'all'
                ),
                'status' => $request->query('status', 'all'),
            ],

            'totals' => [
                'records_count' => $payments->count(),

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

                'details_count' => $payments->sum('details_count'),
            ],

            'payments' => PayrollEmployeePaymentResource::collection(
                $payments
            ),
        ];
    }
}