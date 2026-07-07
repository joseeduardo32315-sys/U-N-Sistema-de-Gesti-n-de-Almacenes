<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbroideryPaymentSetting extends Model
{
    use HasFactory;

    protected $table = 'embroidery_payment_settings';

    protected $fillable = [
        'operation_process_id',
        'stitch_price',
        'application_price',
        'payment_percentage',
        'minimum_payment_per_piece',
        'default_payment_per_piece',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stitch_price' => 'decimal:8',
            'application_price' => 'decimal:4',
            'payment_percentage' => 'decimal:6',
            'minimum_payment_per_piece' => 'decimal:4',
            'default_payment_per_piece' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function operationProcess(): BelongsTo
    {
        return $this->belongsTo(OperationProcess::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}