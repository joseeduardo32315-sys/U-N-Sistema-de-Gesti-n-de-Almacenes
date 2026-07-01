<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentCutComplement extends Model
{
    use HasFactory;

    protected $fillable = [
        'garment_cut_id',
        'current_area_id',
        'status',
        'notes',
    ];

    public function garmentCut(): BelongsTo
    {
        return $this->belongsTo(GarmentCut::class);
    }

    public function currentArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'current_area_id');
    }

    public function productionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'complement_id'
        );
    }
}