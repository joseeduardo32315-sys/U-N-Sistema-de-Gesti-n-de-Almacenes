<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEmployeeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'payment_type',
        'piecework_amount',
        'fixed_amount',
        'total_amount',
        'details_count',
        'status',
        'calculation_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'piecework_amount' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }
}