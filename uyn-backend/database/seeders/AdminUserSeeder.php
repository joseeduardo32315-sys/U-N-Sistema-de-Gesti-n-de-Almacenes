<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => env('INITIAL_ADMIN_EMAIL', 'admin@uyn.local'),
            ],
            [
                'name' => env('INITIAL_ADMIN_NAME', 'Administrador U&N'),
                'username' => env('INITIAL_ADMIN_USERNAME', 'admin'),
                'password' => Hash::make(
                    env('INITIAL_ADMIN_PASSWORD', 'admin123')
                ),
                'status' => 'active',
            ]
        );

        $admin->syncRoles(['Administrador']);
    }
}