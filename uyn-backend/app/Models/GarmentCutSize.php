<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarmentCutSize extends Model
{
    /** @use HasFactory<\Database\Factories\GarmentCutSizeFactory> */
    use HasFactory;

    protected $fillable = [
        'garment_cut_id',
        'size_id',
        'total_pieces',
    ];

    public function garmentCut(): BelongsTo
    {
        return $this->belongsTo(GarmentCut::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }
}
