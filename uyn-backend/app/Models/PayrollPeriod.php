<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'frequency',
        'start_date',
        'end_date',
        'payment_date',
        'status',
        'notes',
        'generated_at',
        'closed_at',
        'created_by',
        'generated_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'payment_date' => 'date',
            'generated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function employeeSummaries(): HasMany
    {
        return $this->hasMany(PayrollEmployeeSummary::class);
    }

    public function details(): HasManyThrough
    {
        return $this->hasManyThrough(
            PayrollDetail::class,
            PayrollEmployeeSummary::class
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'generated_by'
        );
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }
}