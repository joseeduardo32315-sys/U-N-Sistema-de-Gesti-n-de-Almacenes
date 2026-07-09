<?php

namespace App\Services\Reports;

use App\Models\PayrollEmployeeSummary;
use App\Models\PayrollPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PayrollReportService
{
    public function getPeriodReport(
        PayrollPeriod $period,
        array $filters
    ): PayrollPeriod {
        $includeDetails = (bool) ($filters['include_details'] ?? false);

        $paymentType = $filters['payment_type'] ?? 'all';
        $employeeId = $filters['employee_id'] ?? null;

        $period->load([
            'employeeSummaries' => function ($query) use (
                $paymentType,
                $employeeId,
                $includeDetails
            ) {
                $query
                    ->with([
                        'employee.area',
                        'payrollPeriod',
                    ])
                    ->when(
                        $paymentType !== 'all',
                        fn ($summaryQuery) => $summaryQuery->where(
                            'payment_type',
                            $paymentType
                        )
                    )
                    ->when(
                        $employeeId !== null,
                        fn ($summaryQuery) => $summaryQuery->where(
                            'employee_id',
                            $employeeId
                        )
                    )
                    ->when(
                        $includeDetails,
                        fn ($summaryQuery) => $summaryQuery->with([
                            'details.productionOperationLog.operationProcess.process',
                            'details.employeeCompensation',
                        ])
                    )
                    ->orderByDesc('total_amount')
                    ->orderBy('employee_id');
            },
        ]);

        return $period;
    }

    public function getEmployeePayrollReport(
        array $filters
    ): Collection|LengthAwarePaginator {
        $paymentType = $filters['payment_type'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $query = PayrollEmployeeSummary::query()
            ->with([
                'employee.area',
                'payrollPeriod',
            ])
            ->whereHas('payrollPeriod', function ($periodQuery) use (
                $filters
            ) {
                $periodQuery
                    ->whereDate('start_date', '<=', $filters['to'])
                    ->whereDate('end_date', '>=', $filters['from']);
            })
            ->when(
                $filters['employee_id'] ?? null,
                fn ($summaryQuery, $employeeId) => $summaryQuery->where(
                    'employee_id',
                    $employeeId
                )
            )
            ->when(
                $paymentType !== 'all',
                fn ($summaryQuery) => $summaryQuery->where(
                    'payment_type',
                    $paymentType
                )
            )
            ->when(
                $status !== 'all',
                fn ($summaryQuery) => $summaryQuery->where(
                    'status',
                    $status
                )
            )
            ->orderByDesc('total_amount')
            ->orderBy('employee_id');

        return $query->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}