<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    use HasFactory;

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id',
        'payment_type',
        'payment_frequency',
        'fixed_amount',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fixed_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}