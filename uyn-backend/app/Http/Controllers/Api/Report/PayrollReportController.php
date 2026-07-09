<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\EmployeePayrollReportRequest;
use App\Http\Requests\Report\PayrollPeriodReportRequest;
use App\Http\Resources\Report\EmployeePayrollReportResource;
use App\Http\Resources\Report\PayrollPeriodReportResource;
use App\Models\PayrollPeriod;
use App\Services\Reports\PayrollReportService;
use Illuminate\Http\JsonResponse;

class PayrollReportController extends Controller
{
    public function __construct(
        private readonly PayrollReportService $payrollReportService
    ) {
    }

    public function period(
        PayrollPeriodReportRequest $request,
        PayrollPeriod $payrollPeriod
    ): PayrollPeriodReportResource {
        $report = $this->payrollReportService->getPeriodReport(
            period: $payrollPeriod,
            filters: $request->validated()
        );

        return new PayrollPeriodReportResource($report);
    }

    public function employees(
        EmployeePayrollReportRequest $request
    ): JsonResponse {
        $payments = $this->payrollReportService
            ->getEmployeePayrollReport($request->validated());

        return (new EmployeePayrollReportResource(
            collect($payments->items())
        ))
            ->additional([
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ],
            ])
            ->response();
    }
}