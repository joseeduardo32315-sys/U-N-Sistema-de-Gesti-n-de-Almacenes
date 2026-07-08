<?php

namespace App\Services;

use App\Models\EmployeeCompensation;
use App\Models\PayrollDetail;
use App\Models\PayrollEmployeeSummary;
use App\Models\PayrollPeriod;
use App\Models\ProductionOperationLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPeriodManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): PayrollPeriod {
        return DB::transaction(function () use ($data, $actor, $request) {
            $startDate = Carbon::parse($data['start_date'])
                ->toDateString();

            $endDate = Carbon::parse($data['end_date'])
                ->toDateString();

            $this->ensureNoPeriodOverlap(
                frequency: $data['frequency'],
                startDate: $startDate,
                endDate: $endDate
            );

            $period = PayrollPeriod::create([
                'code' => $data['code'],
                'frequency' => $data['frequency'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_date' => isset($data['payment_date'])
                    ? Carbon::parse($data['payment_date'])->toDateString()
                    : null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $period = $this->loadDetailRelations($period);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'payroll-periods',
                action: 'created',
                subject: $period,
                description: "Se creó el periodo de nómina {$period->code}.",
                newValues: $this->snapshot($period),
            );

            return $period;
        });
    }

    public function update(
        PayrollPeriod $payrollPeriod,
        array $data,
        User $actor,
        Request $request
    ): PayrollPeriod {
        return DB::transaction(function () use (
            $payrollPeriod,
            $data,
            $actor,
            $request
        ) {
            if ($data === []) {
                throw ValidationException::withMessages([
                    'request' =>
                        'Debes enviar al menos un dato para actualizar.',
                ]);
            }

            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($payrollPeriod->id);

            if ($period->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Solo puedes modificar periodos en estado borrador.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadDetailRelations($period)
            );

            if (array_key_exists('payment_date', $data)) {
                $period->payment_date = $data['payment_date'] !== null
                    ? Carbon::parse($data['payment_date'])->toDateString()
                    : null;
            }

            if (array_key_exists('notes', $data)) {
                $period->notes = $data['notes'];
            }

            $period->save();

            $period = $this->loadDetailRelations($period->fresh());

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'payroll-periods',
                action: 'updated',
                subject: $period,
                description: "Se actualizó el periodo de nómina {$period->code}.",
                oldValues: $before,
                newValues: $this->snapshot($period),
            );

            return $period;
        });
    }

    public function generate(
        PayrollPeriod $payrollPeriod,
        array $data,
        User $actor,
        Request $request
    ): PayrollPeriod {
        return DB::transaction(function () use (
            $payrollPeriod,
            $data,
            $actor,
            $request
        ) {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($payrollPeriod->id);

            if ($period->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Solo puedes generar periodos en estado borrador.',
                ]);
            }

            if ($period->employeeSummaries()->exists()) {
                throw ValidationException::withMessages([
                    'payroll_period' =>
                        'Este periodo ya contiene concentrados de nómina.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadDetailRelations($period)
            );

            if (array_key_exists('notes', $data) && $data['notes'] !== null) {
                $period->notes = $data['notes'];
            }

            $summaryCache = [];

            $pieceworkDetailsCount = $this->generatePieceworkDetails(
                period: $period,
                summaryCache: $summaryCache
            );

            $fixedDetailsCount = $this->generateFixedCompensationDetails(
                period: $period,
                summaryCache: $summaryCache
            );

            $this->recalculateEmployeeSummaries($period);

            $period->status = 'generated';
            $period->generated_at = now();
            $period->generated_by = $actor->id;
            $period->save();

            $period = $this->loadFullRelations($period->fresh());

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'payroll-periods',
                action: 'generated',
                subject: $period,
                description: "Se generó el periodo de nómina {$period->code}.",
                oldValues: $before,
                newValues: [
                    ...$this->snapshot($period),
                    'generated_piecework_details' =>
                        $pieceworkDetailsCount,
                    'generated_fixed_details' =>
                        $fixedDetailsCount,
                ],
            );

            return $period;
        });
    }

    public function close(
        PayrollPeriod $payrollPeriod,
        array $data,
        User $actor,
        Request $request
    ): PayrollPeriod {
        return DB::transaction(function () use (
            $payrollPeriod,
            $data,
            $actor,
            $request
        ) {
            $period = PayrollPeriod::query()
                ->with('employeeSummaries')
                ->lockForUpdate()
                ->findOrFail($payrollPeriod->id);

            if ($period->status !== 'generated') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Solo puedes cerrar periodos previamente generados.',
                ]);
            }

            $before = $this->snapshot(
                $this->loadFullRelations($period)
            );

            if (array_key_exists('notes', $data) && $data['notes'] !== null) {
                $period->notes = $data['notes'];
            }

            $period->status = 'closed';
            $period->closed_at = now();
            $period->closed_by = $actor->id;
            $period->save();

            $period->employeeSummaries()
                ->update([
                    'status' => 'paid',
                ]);

            $period = $this->loadFullRelations($period->fresh());

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'payroll-periods',
                action: 'closed',
                subject: $period,
                description: "Se cerró el periodo de nómina {$period->code}.",
                oldValues: $before,
                newValues: $this->snapshot($period),
            );

            return $period;
        });
    }

    private function generatePieceworkDetails(
        PayrollPeriod $period,
        array &$summaryCache
    ): int {
        $startDateTime = $period->start_date
            ->copy()
            ->startOfDay();

        $endDateTime = $period->end_date
            ->copy()
            ->endOfDay();

        $operationLogs = ProductionOperationLog::query()
            ->with([
                'employee',
                'operationProcess.process',
                'productionMovement',
            ])
            ->where('status', 'completed')
            ->whereNotNull('payout_amount')
            ->whereBetween('end_time', [
                $startDateTime,
                $endDateTime,
            ])
            ->whereDoesntHave('payrollDetail')
            ->lockForUpdate()
            ->get();

        $created = 0;

        foreach ($operationLogs as $operationLog) {
            $summary = $this->resolveSummary(
                period: $period,
                employeeId: $operationLog->employee_id,
                summaryCache: $summaryCache
            );

            $quantity = (int) $operationLog->quantity_processed;

            $unitAmount = data_get(
                $operationLog->payout_snapshot,
                'final_payment_per_piece'
            );

            if ($unitAmount === null && $quantity > 0) {
                $unitAmount = (float) $operationLog->payout_amount
                    / $quantity;
            }

            PayrollDetail::create([
                'payroll_employee_summary_id' => $summary->id,
                'source_type' => 'operation_log',
                'production_operation_log_id' => $operationLog->id,
                'employee_compensation_id' => null,
                'description' => $this->operationLogDescription(
                    $operationLog
                ),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount !== null
                    ? $this->decimal((float) $unitAmount, 4)
                    : null,
                'amount' => $this->decimal(
                    (float) $operationLog->payout_amount,
                    2
                ),
                'occurred_at' => $operationLog->end_time,
                'calculation_snapshot' =>
                    $operationLog->payout_snapshot,
            ]);

            $created++;
        }

        return $created;
    }

    private function generateFixedCompensationDetails(
        PayrollPeriod $period,
        array &$summaryCache
    ): int {
        $periodStart = $period->start_date->copy();
        $periodEnd = $period->end_date->copy();

        $periodDays = $this->inclusiveDays(
            $periodStart,
            $periodEnd
        );

        $compensations = EmployeeCompensation::query()
            ->with('employee')
            ->where('payment_type', 'fixed')
            ->where('payment_frequency', $period->frequency)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $periodStart->toDateString()
                    );
            })
            ->lockForUpdate()
            ->get();

        $created = 0;

        foreach ($compensations as $compensation) {
            $overlapStart = $compensation->effective_from
                ->copy()
                ->greaterThan($periodStart)
                    ? $compensation->effective_from->copy()
                    : $periodStart->copy();

            $effectiveTo = $compensation->effective_to
                ? $compensation->effective_to->copy()
                : $periodEnd->copy();

            $overlapEnd = $effectiveTo->lessThan($periodEnd)
                ? $effectiveTo
                : $periodEnd->copy();

            if ($overlapStart->greaterThan($overlapEnd)) {
                continue;
            }

            $payableDays = $this->inclusiveDays(
                $overlapStart,
                $overlapEnd
            );

            $dailyAmount = (float) $compensation->fixed_amount
                / $periodDays;

            $amount = round(
                $dailyAmount * $payableDays,
                2
            );

            if ($amount <= 0) {
                continue;
            }

            $summary = $this->resolveSummary(
                period: $period,
                employeeId: $compensation->employee_id,
                summaryCache: $summaryCache
            );

            PayrollDetail::create([
                'payroll_employee_summary_id' => $summary->id,
                'source_type' => 'fixed_compensation',
                'production_operation_log_id' => null,
                'employee_compensation_id' => $compensation->id,
                'description' =>
                    "Pago fijo prorrateado {$payableDays}/{$periodDays} días.",
                'quantity' => $payableDays,
                'unit_amount' => $this->decimal(
                    $dailyAmount,
                    4
                ),
                'amount' => $this->decimal(
                    $amount,
                    2
                ),
                'occurred_at' => $period->end_date
                    ->copy()
                    ->endOfDay(),
                'calculation_snapshot' => [
                    'payment_status' => 'calculated',
                    'calculation_type' => 'fixed_prorated',
                    'employee_compensation_id' =>
                        $compensation->id,
                    'employee_id' =>
                        $compensation->employee_id,
                    'payment_frequency' =>
                        $compensation->payment_frequency,
                    'fixed_amount' =>
                        $this->decimal(
                            (float) $compensation->fixed_amount,
                            2
                        ),
                    'period_frequency' =>
                        $period->frequency,
                    'period_start_date' =>
                        $period->start_date->toDateString(),
                    'period_end_date' =>
                        $period->end_date->toDateString(),
                    'period_days' =>
                        $periodDays,
                    'payable_start_date' =>
                        $overlapStart->toDateString(),
                    'payable_end_date' =>
                        $overlapEnd->toDateString(),
                    'payable_days' =>
                        $payableDays,
                    'daily_amount' =>
                        $this->decimal($dailyAmount, 4),
                    'amount' =>
                        $this->decimal($amount, 2),
                ],
            ]);

            $created++;
        }

        return $created;
    }

    private function recalculateEmployeeSummaries(
        PayrollPeriod $period
    ): void {
        $summaries = $period->employeeSummaries()
            ->with('details')
            ->get();

        foreach ($summaries as $summary) {
            $pieceworkAmount = (float) $summary->details
                ->where('source_type', 'operation_log')
                ->sum('amount');

            $fixedAmount = (float) $summary->details
                ->where('source_type', 'fixed_compensation')
                ->sum('amount');

            $totalAmount = $pieceworkAmount + $fixedAmount;

            $pieceworkDetailsCount = $summary->details
                ->where('source_type', 'operation_log')
                ->count();

            $fixedDetailsCount = $summary->details
                ->where('source_type', 'fixed_compensation')
                ->count();

            $summary->update([
                'payment_type' => $this->resolveSummaryPaymentType(
                    pieceworkAmount: $pieceworkAmount,
                    fixedAmount: $fixedAmount
                ),
                'piecework_amount' => $this->decimal(
                    $pieceworkAmount,
                    2
                ),
                'fixed_amount' => $this->decimal(
                    $fixedAmount,
                    2
                ),
                'total_amount' => $this->decimal(
                    $totalAmount,
                    2
                ),
                'details_count' => $summary->details->count(),
                'status' => 'generated',
                'calculation_snapshot' => [
                    'piecework_details_count' =>
                        $pieceworkDetailsCount,
                    'fixed_details_count' =>
                        $fixedDetailsCount,
                    'piecework_amount' =>
                        $this->decimal($pieceworkAmount, 2),
                    'fixed_amount' =>
                        $this->decimal($fixedAmount, 2),
                    'total_amount' =>
                        $this->decimal($totalAmount, 2),
                ],
            ]);
        }
    }

    private function resolveSummary(
        PayrollPeriod $period,
        int $employeeId,
        array &$summaryCache
    ): PayrollEmployeeSummary {
        if (isset($summaryCache[$employeeId])) {
            return $summaryCache[$employeeId];
        }

        $summary = PayrollEmployeeSummary::query()
            ->firstOrCreate(
                [
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employeeId,
                ],
                [
                    'payment_type' => 'piecework',
                    'piecework_amount' => 0,
                    'fixed_amount' => 0,
                    'total_amount' => 0,
                    'details_count' => 0,
                    'status' => 'generated',
                    'calculation_snapshot' => null,
                ]
            );

        $summaryCache[$employeeId] = $summary;

        return $summary;
    }

    private function resolveSummaryPaymentType(
        float $pieceworkAmount,
        float $fixedAmount
    ): string {
        if ($pieceworkAmount > 0 && $fixedAmount > 0) {
            return 'mixed';
        }

        if ($fixedAmount > 0) {
            return 'fixed';
        }

        return 'piecework';
    }

    private function operationLogDescription(
        ProductionOperationLog $operationLog
    ): string {
        $operationName = $operationLog->operationProcess?->name
            ?? 'Operación';

        $processName = $operationLog->operationProcess?->process?->name
            ?? 'Proceso';

        return "Pago por {$operationName} en {$processName}.";
    }

    private function ensureNoPeriodOverlap(
        string $frequency,
        string $startDate,
        string $endDate,
        ?int $exceptId = null
    ): void {
        $overlaps = PayrollPeriod::query()
            ->where('frequency', $frequency)
            ->where('status', '!=', 'cancelled')
            ->when(
                $exceptId !== null,
                fn ($query) => $query->whereKeyNot($exceptId)
            )
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' =>
                    'Ya existe un periodo de nómina con la misma frecuencia que se cruza con las fechas indicadas.',
            ]);
        }
    }

    private function inclusiveDays(
        CarbonInterface $startDate,
        CarbonInterface $endDate
    ): int {
        return ((int) $startDate->diffInDays($endDate)) + 1;
    }

    private function loadDetailRelations(
        PayrollPeriod $period
    ): PayrollPeriod {
        return $period->load([
            'createdBy',
            'generatedBy',
            'closedBy',
        ]);
    }

    private function loadFullRelations(
        PayrollPeriod $period
    ): PayrollPeriod {
        return $period->load([
            'createdBy',
            'generatedBy',
            'closedBy',
            'employeeSummaries.employee.area',
            'employeeSummaries.details.productionOperationLog.operationProcess.process',
            'employeeSummaries.details.productionOperationLog.productionMovement',
            'employeeSummaries.details.employeeCompensation',
        ]);
    }

    private function snapshot(
        PayrollPeriod $period
    ): array {
        $period->loadMissing([
            'employeeSummaries.details',
        ]);

        return [
            'id' => $period->id,
            'code' => $period->code,
            'frequency' => $period->frequency,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'payment_date' => $period->payment_date?->toDateString(),
            'status' => $period->status,
            'notes' => $period->notes,
            'generated_at' => $period->generated_at?->toISOString(),
            'closed_at' => $period->closed_at?->toISOString(),
            'summaries_count' => $period->employeeSummaries->count(),
            'details_count' => $period->employeeSummaries
                ->sum(fn ($summary) => $summary->details->count()),
        ];
    }

    private function decimal(
        float $value,
        int $scale
    ): string {
        return number_format(
            round($value, $scale),
            $scale,
            '.',
            ''
        );
    }
}