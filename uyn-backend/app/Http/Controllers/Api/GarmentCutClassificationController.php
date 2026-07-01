<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GarmentCut\ConfigureGarmentCutClassificationRequest;
use App\Http\Resources\GarmentCutResource;
use App\Models\GarmentCut;
use App\Models\User;
use App\Services\GarmentCutClassificationService;
use Illuminate\Http\JsonResponse;

class GarmentCutClassificationController extends Controller
{
    public function __construct(
        private readonly GarmentCutClassificationService $classificationService
    ) {
    }

    public function show(
        GarmentCut $garmentCut
    ): GarmentCutResource {
        $garmentCut->load([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',

            'complement.currentArea',

            'specialProcessPieces.pieceType',
            'specialProcessPieces.process',
            'specialProcessPieces.currentArea',
        ]);

        return new GarmentCutResource($garmentCut);
    }

    public function update(
        ConfigureGarmentCutClassificationRequest $request,
        GarmentCut $garmentCut
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $classifiedCut = $this->classificationService->configure(
            garmentCut: $garmentCut,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new GarmentCutResource($classifiedCut))
            ->additional([
                'message' => 'Clasificación del corte guardada correctamente.',
            ])
            ->response();
    }
}