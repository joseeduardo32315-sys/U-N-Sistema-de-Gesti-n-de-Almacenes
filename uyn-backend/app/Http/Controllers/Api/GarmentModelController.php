<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GarmentModel\IndexGarmentModelRequest;
use App\Http\Requests\GarmentModel\StoreGarmentModelRequest;
use App\Http\Requests\GarmentModel\UpdateGarmentModelRequest;
use App\Http\Resources\GarmentModelResource;
use App\Models\GarmentModel;
use App\Models\User;
use App\Services\GarmentModelManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GarmentModelController extends Controller
{
    public function __construct(
        private readonly GarmentModelManagementService $garmentModelManagementService
    ) {
    }

    public function index(
        IndexGarmentModelRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $garmentModels = GarmentModel::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderBy('code')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return GarmentModelResource::collection($garmentModels);
    }

    public function store(
        StoreGarmentModelRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $garmentModel = $this->garmentModelManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new GarmentModelResource($garmentModel))
            ->additional([
                'message' => 'Modelo de prenda registrado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        GarmentModel $garmentModel
    ): GarmentModelResource {
        return new GarmentModelResource($garmentModel);
    }

    public function update(
        UpdateGarmentModelRequest $request,
        GarmentModel $garmentModel
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedGarmentModel = $this->garmentModelManagementService->update(
            garmentModel: $garmentModel,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new GarmentModelResource($updatedGarmentModel))
            ->additional([
                'message' => 'Modelo de prenda actualizado correctamente.',
            ])
            ->response();
    }

    public function deactivate(
        Request $request,
        GarmentModel $garmentModel
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedGarmentModel = $this->garmentModelManagementService->changeStatus(
            garmentModel: $garmentModel,
            status: 'inactive',
            actor: $actor,
            request: $request
        );

        return (new GarmentModelResource($updatedGarmentModel))
            ->additional([
                'message' => 'Modelo de prenda desactivado correctamente.',
            ])
            ->response();
    }

    public function activate(
        Request $request,
        GarmentModel $garmentModel
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedGarmentModel = $this->garmentModelManagementService->changeStatus(
            garmentModel: $garmentModel,
            status: 'active',
            actor: $actor,
            request: $request
        );

        return (new GarmentModelResource($updatedGarmentModel))
            ->additional([
                'message' => 'Modelo de prenda activado correctamente.',
            ])
            ->response();
    }
}