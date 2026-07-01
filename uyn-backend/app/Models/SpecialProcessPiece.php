<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialProcessPiece extends Model
{
    use HasFactory;

    protected $fillable = [
        'garment_cut_id',
        'piece_type_id',
        'process_id',
        'current_area_id',
        'status',
        'notes',
    ];

    public function garmentCut(): BelongsTo
    {
        return $this->belongsTo(GarmentCut::class);
    }

    public function pieceType(): BelongsTo
    {
        return $this->belongsTo(PieceType::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function currentArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'current_area_id');
    }

    public function productionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'special_process_piece_id'
        );
    }
}