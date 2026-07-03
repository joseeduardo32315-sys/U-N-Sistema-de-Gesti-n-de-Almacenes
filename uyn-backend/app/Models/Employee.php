<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area_id',
        'worker_type',
        'phone',
        'status',
        'notes'
    ];

    public function area() : BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function productionOperationLogs(): HasMany
    {
        return $this->hasMany(
            ProductionOperationLog::class
        );
    }

    public function responsibleProductionIncidents(): HasMany
    {
        return $this->hasMany(
            ProductionIncident::class,
            'responsible_employee_id'
        );
    }
}
