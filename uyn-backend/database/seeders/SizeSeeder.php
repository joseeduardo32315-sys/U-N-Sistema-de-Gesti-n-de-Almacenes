<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            '2',
            '4',
            '6',
            '8',
            '10',
            '12',
            '14',
            '16',
        ];

        foreach ($sizes as $sizeName) {
            Size::updateOrCreate(
                [
                    'name' => $sizeName,
                ],
                [
                    'description' => "Talla infantil {$sizeName}",
                    'status' => 'active',
                ]
            );
        }
    }
}