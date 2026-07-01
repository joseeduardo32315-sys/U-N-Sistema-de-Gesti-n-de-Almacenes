<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    /** @use HasFactory<\Database\Factories\SizeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status'
    ];

    public function garmentCutSizes(): HasMany
    {
        return $this->hasMany(GarmentCutSize::class);
    }
}
