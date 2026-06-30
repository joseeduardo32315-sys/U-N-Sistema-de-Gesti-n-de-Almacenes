<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            'Corte',
            'Diseño',
            'Bordado',
            'Maquila',
            'Preparación',
            'Terminado',
        ];

        foreach ($areas as $area) {
            Area::updateOrCreate([
                'name' => $area
            ]);
        }
    }
}
