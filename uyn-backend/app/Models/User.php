<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status', 'username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function createdProductionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'created_by');
    }

    public function createdProductionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'created_by'
        );
    }

    public function receivedProductionMovements(): HasMany
    {
        return $this->hasMany(
            ProductionMovement::class,
            'received_by'
        );
    }

    public function resolvedProductionIncidents(): HasMany
    {
        return $this->hasMany(
            ProductionIncident::class,
            'resolved_by'
        );
    }
}
