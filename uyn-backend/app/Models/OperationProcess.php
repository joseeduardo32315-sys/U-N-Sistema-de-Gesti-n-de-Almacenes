<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationProcess extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'process_id',
        'name',
        'flow_order',
    ];

    protected function casts(): array
    {
        return [
            'flow_order' => 'integer',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function productionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class
        );
    }

    public function productionOperationLogs(): HasMany
    {
        return $this->hasMany(
            ProductionOperationLog::class
        );
    }
}