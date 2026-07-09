<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\EmployeePayrollReportRequest;
use App\Http\Requests\Report\PayrollPeriodReportRequest;
use App\Models\PayrollDetail;
use App\Models\PayrollEmployeeSummary;
use App\Models\PayrollPeriod;
use App\Services\Exports\CsvExportService;
use App\Services\Reports\PayrollReportService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportController extends Controller
{
    public function __construct(
        private readonly PayrollReportService $payrollReportService,
        private readonly CsvExportService $csvExportService
    ) {
    }

    public function period(
        PayrollPeriodReportRequest $request,
        PayrollPeriod $payrollPeriod
    ): StreamedResponse {
        $filters = $request->validated();
        $filters['include_details'] = true;

        $period = $this->payrollReportService->getPeriodReport(
            period: $payrollPeriod,
            filters: $filters
        );

        $filename = $this->buildFilename(
            prefix: 'nomina-periodo',
            code: $period->code
        );

        return $this->csvExportService->download(
            filename: $filename,
            headers: $this->periodHeaders(),
            rows: $this->periodRows($period)
        );
    }

    public function employees(
        EmployeePayrollReportRequest $request
    ): StreamedResponse {
        $filters = $request->validated();

        $payments = $this->payrollReportService
            ->getEmployeePayrollReportForExport($filters);

        $filename = $this->buildFilename(
            prefix: 'nomina-trabajadores',
            code: $filters['from'] . '_a_' . $filters['to']
        );

        return $this->csvExportService->download(
            filename: $filename,
            headers: $this->employeeHeaders(),
            rows: $payments->map(
                fn (PayrollEmployeeSummary $summary) =>
                    $this->employeeRow($summary)
            )
        );
    }

    private function periodHeaders(): array
    {
        return [
            'Periodo',
            'Frecuencia',
            'Fecha inicio',
            'Fecha fin',
            'Fecha pago',
            'Estado periodo',

            'ID trabajador',
            'Trabajador',
            'Área',
            'Tipo trabajador',

            'Tipo pago',
            'Estado resumen',
            'Total destajo',
            'Total fijo',
            'Total trabajador',

            'Origen detalle',
            'Descripción detalle',
            'Cantidad',
            'Importe unitario',
            'Importe detalle',
            'Fecha detalle',
        ];
    }

    private function employeeHeaders(): array
    {
        return [
            'ID trabajador',
            'Trabajador',
            'Área',
            'Tipo trabajador',

            'Periodo',
            'Frecuencia',
            'Fecha inicio',
            'Fecha fin',
            'Fecha pago',
            'Estado periodo',

            'Tipo pago',
            'Estado resumen',
            'Total destajo',
            'Total fijo',
            'Total trabajador',
            'Cantidad de detalles',
        ];
    }

    private function periodRows(PayrollPeriod $period): array
    {
        $rows = [];

        foreach ($period->employeeSummaries as $summary) {
            if ($summary->details->isEmpty()) {
                $rows[] = $this->periodRow(
                    period: $period,
                    summary: $summary,
                    detail: null
                );

                continue;
            }

            foreach ($summary->details as $detail) {
                $rows[] = $this->periodRow(
                    period: $period,
                    summary: $summary,
                    detail: $detail
                );
            }
        }

        return $rows;
    }

    private function periodRow(
        PayrollPeriod $period,
        PayrollEmployeeSummary $summary,
        ?PayrollDetail $detail
    ): array {
        return [
            $period->code,
            $this->frequencyLabel($period->frequency),
            $period->start_date?->toDateString(),
            $period->end_date?->toDateString(),
            $period->payment_date?->toDateString(),
            $this->periodStatusLabel($period->status),

            $summary->employee?->id,
            $summary->employee?->name,
            $summary->employee?->area?->name,
            $summary->employee?->worker_type,

            $this->paymentTypeLabel($summary->payment_type),
            $this->summaryStatusLabel($summary->status),
            $summary->piecework_amount,
            $summary->fixed_amount,
            $summary->total_amount,

            $detail ? $this->detailSourceLabel($detail->source_type) : '',
            $detail?->description,
            $detail?->quantity,
            $detail?->unit_amount,
            $detail?->amount,
            $detail?->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function employeeRow(
        PayrollEmployeeSummary $summary
    ): array {
        $period = $summary->payrollPeriod;

        return [
            $summary->employee?->id,
            $summary->employee?->name,
            $summary->employee?->area?->name,
            $summary->employee?->worker_type,

            $period?->code,
            $period ? $this->frequencyLabel($period->frequency) : '',
            $period?->start_date?->toDateString(),
            $period?->end_date?->toDateString(),
            $period?->payment_date?->toDateString(),
            $period ? $this->periodStatusLabel($period->status) : '',

            $this->paymentTypeLabel($summary->payment_type),
            $this->summaryStatusLabel($summary->status),
            $summary->piecework_amount,
            $summary->fixed_amount,
            $summary->total_amount,
            $summary->details_count,
        ];
    }

    private function buildFilename(
        string $prefix,
        string $code
    ): string {
        $safeCode = Str::slug($code, '-');

        return $prefix . '-' . $safeCode . '-' . now()->format('Ymd-His')
            . '.csv';
    }

    private function frequencyLabel(?string $frequency): string
    {
        return match ($frequency) {
            'weekly' => 'Semanal',
            'biweekly' => 'Quincenal',
            'monthly' => 'Mensual',
            default => 'No definido',
        };
    }

    private function periodStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'generated' => 'Generada',
            'closed' => 'Cerrada',
            'cancelled' => 'Cancelada',
            default => 'No definido',
        };
    }

    private function summaryStatusLabel(?string $status): string
    {
        return match ($status) {
            'generated' => 'Generado',
            'reviewed' => 'Revisado',
            'paid' => 'Pagado',
            default => 'No definido',
        };
    }

    private function paymentTypeLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'piecework' => 'Destajo',
            'fixed' => 'Pago fijo',
            'mixed' => 'Mixto',
            default => 'No definido',
        };
    }

    private function detailSourceLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'operation_log' => 'Operación de producción',
            'fixed_compensation' => 'Compensación fija',
            default => 'No definido',
        };
    }
}