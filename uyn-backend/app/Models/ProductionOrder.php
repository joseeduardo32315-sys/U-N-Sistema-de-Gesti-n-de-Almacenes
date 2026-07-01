<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_code',
        'location',
        'status',
        'start_date',
        'end_date',
        'priority',
        'created_by',
        'notes',
    ];

    public function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function garmentCuts(): HasMany
    {
        return $this->hasMany(GarmentCut::class);
    }
}
