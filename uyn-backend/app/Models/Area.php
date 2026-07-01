<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function employees() : HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function currentGarmentCuts(): HasMany
    {
        return $this->hasMany(GarmentCut::class, 'current_area_id');
    }

    public function garmentCutComplements(): HasMany
    {
        return $this->hasMany(
            GarmentCutComplement::class,
            'current_area_id'
        );
    }

    public function specialProcessPieces(): HasMany
    {
        return $this->hasMany(
            SpecialProcessPiece::class,
            'current_area_id'
        );
    }

    public function outgoingProductionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'from_area_id'
        );
    }

    public function incomingProductionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'to_area_id'
        );
    }
}
