<?php

namespace Database\Seeders;

use App\Models\PieceType;
use Illuminate\Database\Seeder;

class PieceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $pieceTypes = [
            [
                'name' => 'Delantero',
                'description' => 'Pieza frontal principal de la prenda.',
            ],
            [
                'name' => 'Espalda',
                'description' => 'Pieza posterior principal de la prenda.',
            ],
            [
                'name' => 'Manga',
                'description' => 'Pieza correspondiente a una manga.',
            ],
            [
                'name' => 'Cuello',
                'description' => 'Pieza correspondiente al cuello de la prenda.',
            ],
            [
                'name' => 'Bolsillo',
                'description' => 'Pieza decorativa o funcional tipo bolsillo.',
            ],
            [
                'name' => 'Puño',
                'description' => 'Pieza correspondiente al puño de una manga.',
            ],
            [
                'name' => 'Pretina',
                'description' => 'Pieza correspondiente a la cintura o pretina.',
            ],
            [
                'name' => 'Capucha',
                'description' => 'Pieza correspondiente a una capucha.',
            ],
            [
                'name' => 'Otro',
                'description' => 'Pieza no contemplada en los tipos predeterminados.',
            ],
        ];

        foreach ($pieceTypes as $pieceType) {
            PieceType::updateOrCreate(
                [
                    'name' => $pieceType['name'],
                ],
                [
                    'description' => $pieceType['description'],
                ]
            );
        }
    }
}