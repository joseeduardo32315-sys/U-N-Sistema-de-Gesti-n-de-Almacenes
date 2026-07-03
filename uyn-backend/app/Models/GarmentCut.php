<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GarmentCut extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'garment_model_id',
        'code',
        'description',
        'total_sizes',
        'base_pieces_per_size',
        'total_pieces',
        'status',
        'current_area_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_sizes' => 'integer',
            'base_pieces_per_size' => 'integer',
            'total_pieces' => 'integer',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function garmentModel(): BelongsTo
    {
        return $this->belongsTo(GarmentModel::class);
    }

    public function currentArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'current_area_id');
    }

    public function cutSizes(): HasMany
    {
        return $this->hasMany(GarmentCutSize::class);
    }

    public function complement(): HasOne
    {
        return $this->hasOne(GarmentCutComplement::class);
    }

    public function specialProcessPieces(): HasMany
    {
        return $this->hasMany(SpecialProcessPiece::class);
    }

    public function productionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class
        );
    }

    public function productionIncidents(): HasMany
    {
        return $this->hasMany(
            ProductionIncident::class
        );
    }
}