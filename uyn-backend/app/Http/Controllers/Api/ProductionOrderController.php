<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionOrder\IndexProductionOrderRequest;
use App\Http\Requests\ProductionOrder\StoreProductionOrderRequest;
use App\Http\Requests\ProductionOrder\UpdateProductionOrderRequest;
use App\Http\Resources\ProductionOrderResource;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\ProductionOrderManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderManagementService $productionOrderManagementService
    ) {
    }

    public function index(
        IndexProductionOrderRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $orders = ProductionOrder::query()
            ->with('createdBy:id,name,username')
            ->withCount('garmentCuts')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('order_code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['priority'] ?? null,
                fn ($query, $priority) => $query->where('priority', $priority)
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($query, $dateFrom) => $query->whereDate(
                    'start_date',
                    '>=',
                    $dateFrom
                )
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($query, $dateTo) => $query->whereDate(
                    'start_date',
                    '<=',
                    $dateTo
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ProductionOrderResource::collection($orders);
    }

    public function store(
        StoreProductionOrderRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $order = $this->productionOrderManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionOrderResource($order))
            ->additional([
                'message' => 'Orden de producción registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        ProductionOrder $productionOrder
    ): ProductionOrderResource {
        $productionOrder->load([
            'createdBy:id,name,username',

            'garmentCuts.garmentModel:id,code,name,status',

            'garmentCuts.currentArea:id,name',
        ]);

        $productionOrder->loadCount('garmentCuts');

        return new ProductionOrderResource($productionOrder);
    }

    public function update(
        UpdateProductionOrderRequest $request,
        ProductionOrder $productionOrder
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedOrder = $this->productionOrderManagementService->update(
            productionOrder: $productionOrder,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionOrderResource($updatedOrder))
            ->additional([
                'message' => 'Orden de producción actualizada correctamente.',
            ])
            ->response();
    }
}