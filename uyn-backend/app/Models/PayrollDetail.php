<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_employee_summary_id',
        'source_type',
        'production_operation_log_id',
        'employee_compensation_id',
        'description',
        'quantity',
        'unit_amount',
        'amount',
        'occurred_at',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'decimal:4',
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'calculation_snapshot' => 'array',
        ];
    }

    public function payrollEmployeeSummary(): BelongsTo
    {
        return $this->belongsTo(
            PayrollEmployeeSummary::class
        );
    }

    public function productionOperationLog(): BelongsTo
    {
        return $this->belongsTo(
            ProductionOperationLog::class
        );
    }

    public function employeeCompensation(): BelongsTo
    {
        return $this->belongsTo(
            EmployeeCompensation::class
        );
    }
}