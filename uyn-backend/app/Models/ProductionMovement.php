<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'garment_cut_id',
        'target_type',
        'special_process_piece_id',
        'complement_id',
        'process_id',
        'operation_process_id',
        'from_area_id',
        'to_area_id',
        'quantity',
        'status',
        'start_time',
        'end_time',
        'notes',
        'created_by',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function garmentCut(): BelongsTo
    {
        return $this->belongsTo(GarmentCut::class);
    }

    public function specialProcessPiece(): BelongsTo
    {
        return $this->belongsTo(SpecialProcessPiece::class);
    }

    public function complement(): BelongsTo
    {
        return $this->belongsTo(
            GarmentCutComplement::class,
            'complement_id'
        );
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function operationProcess(): BelongsTo
    {
        return $this->belongsTo(OperationProcess::class);
    }

    public function fromArea(): BelongsTo
    {
        return $this->belongsTo(
            Area::class,
            'from_area_id'
        );
    }

    public function toArea(): BelongsTo
    {
        return $this->belongsTo(
            Area::class,
            'to_area_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(
            ProductionOperationLog::class
        );
    }
}