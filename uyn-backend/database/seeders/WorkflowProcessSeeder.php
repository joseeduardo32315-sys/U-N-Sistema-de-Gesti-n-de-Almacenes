<?php

namespace Database\Seeders;

use App\Models\OperationProcess;
use App\Models\Process;
use Illuminate\Database\Seeder;

class WorkflowProcessSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = [
            [
                'name' => 'Corte',
                'flow_order' => 1,
                'operations' => [
                    [
                        'name' => 'Trazo',
                        'flow_order' => 1,
                    ],
                    [
                        'name' => 'Tendido',
                        'flow_order' => 2,
                    ],
                    [
                        'name' => 'Corte',
                        'flow_order' => 3,
                    ],
                ],
            ],
            [
                'name' => 'Diseño',
                'flow_order' => 2,
                'operations' => [
                    [
                        'name' => 'Diseño y clasificación de piezas',
                        'flow_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Bordado',
                'flow_order' => 3,
                'operations' => [
                    [
                        'name' => 'Bordado',
                        'flow_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Maquila',
                'flow_order' => 4,
                'operations' => [
                    [
                        'name' => 'Confección o maquila',
                        'flow_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Preparación',
                'flow_order' => 5,
                'operations' => [
                    [
                        'name' => 'Preparación',
                        'flow_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Terminado',
                'flow_order' => 6,
                'operations' => [
                    [
                        'name' => 'Terminado',
                        'flow_order' => 1,
                    ],
                ],
            ],
        ];

        foreach ($workflow as $processData) {
            $process = Process::updateOrCreate(
                [
                    'name' => $processData['name'],
                ],
                [
                    'flow_order' => $processData['flow_order'],
                ]
            );

            foreach ($processData['operations'] as $operationData) {
                OperationProcess::updateOrCreate(
                    [
                        'process_id' => $process->id,
                        'name' => $operationData['name'],
                    ],
                    [
                        'flow_order' => $operationData['flow_order'],
                    ]
                );
            }
        }
    }
}