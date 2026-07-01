<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GarmentCut\IndexGarmentCutRequest;
use App\Http\Requests\GarmentCut\StoreGarmentCutRequest;
use App\Http\Requests\GarmentCut\UpdateGarmentCutRequest;
use App\Http\Resources\GarmentCutResource;
use App\Models\GarmentCut;
use App\Models\User;
use App\Services\GarmentCutManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GarmentCutController extends Controller
{
    public function __construct(
        private readonly GarmentCutManagementService $garmentCutManagementService
    ) {
    }

    public function index(
        IndexGarmentCutRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $cuts = GarmentCut::query()
            ->with([
                'productionOrder:id,order_code,status,priority,start_date,end_date',
                'garmentModel:id,code,name,status',
                'currentArea:id,name',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('garmentModel', function ($modelQuery) use ($search) {
                            $modelQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('productionOrder', function ($orderQuery) use ($search) {
                            $orderQuery->where(
                                'order_code',
                                'like',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->when(
                $filters['production_order_id'] ?? null,
                fn ($query, $orderId) => $query->where(
                    'production_order_id',
                    $orderId
                )
            )
            ->when(
                $filters['garment_model_id'] ?? null,
                fn ($query, $modelId) => $query->where(
                    'garment_model_id',
                    $modelId
                )
            )
            ->when(
                $filters['current_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'current_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return GarmentCutResource::collection($cuts);
    }

    public function store(
        StoreGarmentCutRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $garmentCut = $this->garmentCutManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new GarmentCutResource($garmentCut))
            ->additional([
                'message' => 'Corte registrado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        GarmentCut $garmentCut
    ): GarmentCutResource {
        $garmentCut->load([
            'productionOrder',
            'garmentModel',
            'currentArea',
            'cutSizes.size',
        ]);

        return new GarmentCutResource($garmentCut);
    }

    public function update(
        UpdateGarmentCutRequest $request,
        GarmentCut $garmentCut
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedGarmentCut = $this->garmentCutManagementService->update(
            garmentCut: $garmentCut,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new GarmentCutResource($updatedGarmentCut))
            ->additional([
                'message' => 'Corte actualizado correctamente.',
            ])
            ->response();
    }
}