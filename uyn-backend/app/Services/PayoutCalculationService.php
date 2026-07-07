<?php

namespace App\Services;

use App\Models\EmbroideryPaymentSetting;
use App\Models\EmployeeCompensation;
use App\Models\PieceworkRate;
use App\Models\ProductionOperationLog;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class PayoutCalculationService
{
    public function calculate(
        ProductionOperationLog $operationLog,
        int $quantityProcessed,
        ?int $stitchesCount,
        ?int $applicationsCount,
        CarbonInterface $completedAt
    ): array {
        $operationLog->loadMissing([
            'employee',
            'operationProcess.process',
        ]);

        $employee = $operationLog->employee;
        $operation = $operationLog->operationProcess;
        $referenceDate = $completedAt->toDateString();

        if (! $employee || ! $operation) {
            return $this->pendingConfiguration(
                operationLog: $operationLog,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt,
                reason: 'No fue posible identificar al trabajador o la operación para calcular el pago.'
            );
        }

        $compensation = $this->findCurrentCompensation(
            employeeId: $employee->id,
            referenceDate: $referenceDate
        );

        if (! $compensation) {
            return $this->pendingConfiguration(
                operationLog: $operationLog,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt,
                reason: 'El trabajador no tiene una compensación activa para la fecha de finalización.'
            );
        }

        if ($compensation->payment_type === 'fixed') {
            return [
                'payout_amount' => null,

                'payout_snapshot' => [
                    'payment_status' => 'not_applicable',

                    'reason' => 'El trabajador cuenta con una compensación de pago fijo.',

                    'calculation_date' => $referenceDate,
                    'completed_at' => $completedAt->toISOString(),

                    'payment_type' => 'fixed',
                    'calculation_type' => 'fixed_salary',

                    'compensation_id' => $compensation->id,

                    'employee_id' => $employee->id,
                    'operation_process_id' => $operation->id,
                    'operation_process_name' => $operation->name,

                    'quantity_processed' => $quantityProcessed,

                    'payout_amount' => null,
                ],
            ];
        }

        return match ($operation->payroll_calculation_type) {
            'per_piece' => $this->calculatePerPiecePayout(
                operationLog: $operationLog,
                compensation: $compensation,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt
            ),

            'embroidery_formula' => $this->calculateEmbroideryPayout(
                operationLog: $operationLog,
                compensation: $compensation,
                quantityProcessed: $quantityProcessed,
                stitchesCount: $stitchesCount,
                applicationsCount: $applicationsCount,
                completedAt: $completedAt
            ),

            default => $this->pendingConfiguration(
                operationLog: $operationLog,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt,
                reason: 'La operación no tiene un tipo de cálculo de pago válido.'
            ),
        };
    }

    private function calculatePerPiecePayout(
        ProductionOperationLog $operationLog,
        EmployeeCompensation $compensation,
        int $quantityProcessed,
        CarbonInterface $completedAt
    ): array {
        $referenceDate = $completedAt->toDateString();

        $rate = PieceworkRate::query()
            ->where('employee_id', $operationLog->employee_id)
            ->where(
                'operation_process_id',
                $operationLog->operation_process_id
            )
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $referenceDate)
            ->where(function ($query) use ($referenceDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $referenceDate);
            })
            ->latest('effective_from')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $rate) {
            return $this->pendingConfiguration(
                operationLog: $operationLog,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt,
                reason: 'No existe una tarifa activa por pieza para el trabajador y la operación.'
            );
        }

        $paymentPerPiece = round(
            (float) $rate->amount_per_piece,
            4
        );

        $payoutAmount = round(
            $paymentPerPiece * $quantityProcessed,
            2
        );

        return [
            'payout_amount' => $this->decimal($payoutAmount, 2),

            'payout_snapshot' => [
                'payment_status' => 'calculated',

                'calculation_date' => $referenceDate,
                'completed_at' => $completedAt->toISOString(),

                'payment_type' => 'piecework',
                'calculation_type' => 'per_piece',

                'compensation_id' => $compensation->id,
                'piecework_rate_id' => $rate->id,

                'employee_id' => $operationLog->employee_id,

                'operation_process_id' =>
                    $operationLog->operation_process_id,

                'operation_process_name' =>
                    $operationLog->operationProcess?->name,

                'quantity_processed' => $quantityProcessed,

                'amount_per_piece' => $this->decimal(
                    $paymentPerPiece,
                    4
                ),

                'final_payment_per_piece' => $this->decimal(
                    $paymentPerPiece,
                    4
                ),

                'payout_amount' => $this->decimal(
                    $payoutAmount,
                    2
                ),
            ],
        ];
    }

    private function calculateEmbroideryPayout(
        ProductionOperationLog $operationLog,
        EmployeeCompensation $compensation,
        int $quantityProcessed,
        ?int $stitchesCount,
        ?int $applicationsCount,
        CarbonInterface $completedAt
    ): array {
        $referenceDate = $completedAt->toDateString();

        $setting = EmbroideryPaymentSetting::query()
            ->where(
                'operation_process_id',
                $operationLog->operation_process_id
            )
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $referenceDate)
            ->where(function ($query) use ($referenceDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $referenceDate);
            })
            ->latest('effective_from')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $setting) {
            return $this->pendingConfiguration(
                operationLog: $operationLog,
                quantityProcessed: $quantityProcessed,
                completedAt: $completedAt,
                reason: 'No existe una configuración activa de pago para Bordado.'
            );
        }

        if ($stitchesCount === null || $stitchesCount < 1) {
            throw ValidationException::withMessages([
                'stitches_count' =>
                    'Debes registrar las puntadas por pieza para completar una operación de Bordado con pago por destajo.',
            ]);
        }

        $applicationsCount ??= 0;

        $stitchPrice = (float) $setting->stitch_price;
        $applicationPrice = (float) $setting->application_price;
        $paymentPercentage = (float) $setting->payment_percentage;
        $minimumPayment = (float) $setting->minimum_payment_per_piece;
        $defaultPayment = (float) $setting->default_payment_per_piece;

        $basePaymentPerPiece =
            ($stitchesCount * $stitchPrice)
            + ($applicationsCount * $applicationPrice);

        $formulaPaymentPerPiece = round(
            $basePaymentPerPiece * $paymentPercentage,
            4
        );

        $minimumApplied = $formulaPaymentPerPiece < $minimumPayment;

        $finalPaymentPerPiece = $minimumApplied
            ? $defaultPayment
            : $formulaPaymentPerPiece;

        $payoutAmount = round(
            $finalPaymentPerPiece * $quantityProcessed,
            2
        );

        return [
            'payout_amount' => $this->decimal($payoutAmount, 2),

            'payout_snapshot' => [
                'payment_status' => 'calculated',

                'calculation_date' => $referenceDate,
                'completed_at' => $completedAt->toISOString(),

                'payment_type' => 'piecework',
                'calculation_type' => 'embroidery_formula',

                'compensation_id' => $compensation->id,
                'embroidery_payment_setting_id' => $setting->id,

                'employee_id' => $operationLog->employee_id,

                'operation_process_id' =>
                    $operationLog->operation_process_id,

                'operation_process_name' =>
                    $operationLog->operationProcess?->name,

                'quantity_processed' => $quantityProcessed,

                'stitches_per_piece' => $stitchesCount,
                'applications_per_piece' => $applicationsCount,

                'stitch_price' => $this->decimal(
                    $stitchPrice,
                    8
                ),

                'application_price' => $this->decimal(
                    $applicationPrice,
                    4
                ),

                'payment_percentage' => $this->decimal(
                    $paymentPercentage,
                    6
                ),

                'minimum_payment_per_piece' => $this->decimal(
                    $minimumPayment,
                    4
                ),

                'default_payment_per_piece' => $this->decimal(
                    $defaultPayment,
                    4
                ),

                'base_payment_per_piece' => $this->decimal(
                    $basePaymentPerPiece,
                    4
                ),

                'formula_payment_per_piece' => $this->decimal(
                    $formulaPaymentPerPiece,
                    4
                ),

                'minimum_applied' => $minimumApplied,

                'final_payment_per_piece' => $this->decimal(
                    $finalPaymentPerPiece,
                    4
                ),

                'payout_amount' => $this->decimal(
                    $payoutAmount,
                    2
                ),
            ],
        ];
    }

    private function findCurrentCompensation(
        int $employeeId,
        string $referenceDate
    ): ?EmployeeCompensation {
        return EmployeeCompensation::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $referenceDate)
            ->where(function ($query) use ($referenceDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $referenceDate);
            })
            ->latest('effective_from')
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    private function pendingConfiguration(
        ProductionOperationLog $operationLog,
        int $quantityProcessed,
        CarbonInterface $completedAt,
        string $reason
    ): array {
        return [
            'payout_amount' => null,

            'payout_snapshot' => [
                'payment_status' => 'pending_configuration',

                'reason' => $reason,

                'calculation_date' => $completedAt->toDateString(),
                'completed_at' => $completedAt->toISOString(),

                'employee_id' => $operationLog->employee_id,

                'operation_process_id' =>
                    $operationLog->operation_process_id,

                'operation_process_name' =>
                    $operationLog->operationProcess?->name,

                'quantity_processed' => $quantityProcessed,

                'payout_amount' => null,
            ],
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