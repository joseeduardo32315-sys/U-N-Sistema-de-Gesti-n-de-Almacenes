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
}
