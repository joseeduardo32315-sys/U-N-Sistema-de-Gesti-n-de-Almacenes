<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_movement_id',
        'operation_process_id',
        'employee_id',
        'start_time',
        'end_time',
        'stitches_count',
        'applications_count',
        'quantity_processed',
        'status',
        'notes',
        'payout_amount',
        'payout_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'stitches_count' => 'integer',
            'applications_count' => 'integer',
            'quantity_processed' => 'integer',
            'payout_amount' => 'decimal:2',
            'payout_snapshot' => 'array',
        ];
    }

    public function productionMovement(): BelongsTo
    {
        return $this->belongsTo(
            ProductionMovement::class
        );
    }

    public function operationProcess(): BelongsTo
    {
        return $this->belongsTo(OperationProcess::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}