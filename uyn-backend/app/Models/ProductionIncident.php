<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductionIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'garment_cut_id',
        'production_movement_id',
        'incident_type',
        'quantity_affected',
        'description',
        'responsible_employee_id',
        'status',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_affected' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function garmentCut(): BelongsTo
    {
        return $this->belongsTo(GarmentCut::class);
    }

    public function productionMovement(): BelongsTo
    {
        return $this->belongsTo(ProductionMovement::class);
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'responsible_employee_id'
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        );
    }

    public function reworkMovement(): HasOne
    {
        return $this->hasOne(
            ProductionMovement::class,
            'return_incident_id'
        );
    }
}