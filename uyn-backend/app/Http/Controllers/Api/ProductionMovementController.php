<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionMovement\IndexProductionMovementRequest;
use App\Http\Requests\ProductionMovement\ReceiveProductionMovementRequest;
use App\Http\Requests\ProductionMovement\StoreProductionMovementRequest;
use App\Http\Resources\ProductionMovementResource;
use App\Models\ProductionMovement;
use App\Models\User;
use App\Services\ProductionMovementManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductionMovementController extends Controller
{
    public function __construct(
        private readonly ProductionMovementManagementService $movementManagementService
    ) {
    }

    public function index(
        IndexProductionMovementRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $movements = ProductionMovement::query()
            ->with([
                'garmentCut.currentArea',

                'complement.currentArea',

                'specialProcessPiece.pieceType',
                'specialProcessPiece.process',
                'specialProcessPiece.currentArea',

                'process',
                'operationProcess',

                'fromArea',
                'toArea',

                'createdBy',
                'receivedBy',

                'returnIncident',
            ])
            ->withCount('operationLogs')
            ->withSum([
                'productionIncidents as resolved_loss_quantity' => fn ($query) => $query
                ->where('incident_type', 'loss')
                ->where('status', 'resolved')
            ], 'quantity_affected')
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['target_type'] ?? null,
                fn ($query, $targetType) => $query->where(
                    'target_type',
                    $targetType
                )
            )
            ->when(
                $filters['process_id'] ?? null,
                fn ($query, $processId) => $query->where(
                    'process_id',
                    $processId
                )
            )
            ->when(
                $filters['from_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'from_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['to_area_id'] ?? null,
                fn ($query, $areaId) => $query->where(
                    'to_area_id',
                    $areaId
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where(
                    'status',
                    $status
                )
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('notes', 'like', "%{$search}%")
                            ->orWhereHas(
                                'garmentCut',
                                fn ($cutQuery) => $cutQuery->where(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                            );
                    });
                }
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ProductionMovementResource::collection($movements);
    }

    public function store(
        StoreProductionMovementRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $movement = $this->movementManagementService->dispatch(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionMovementResource($movement))
            ->additional([
                'message' => 'Envío de producción registrado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        ProductionMovement $productionMovement
    ): ProductionMovementResource {
        $productionMovement
            ->load([
                'garmentCut.currentArea',

                'complement.currentArea',

                'specialProcessPiece.pieceType',
                'specialProcessPiece.process',
                'specialProcessPiece.currentArea',

                'process',
                'operationProcess',

                'fromArea',
                'toArea',

                'createdBy',
                'receivedBy',

                'operationLogs.employee',
                'operationLogs.operationProcess',

                'returnIncident',
            ])
            ->loadCount('operationLogs');

            $productionMovement->loadSum([
                'productionIncidents as resolved_loss_quantity' => fn ($query) => $query
                    ->where('incident_type', 'loss')
                    ->where('status', 'resolved'),
            ], 'quantity_affected');

        return new ProductionMovementResource($productionMovement);
    }

    public function receive(
        ReceiveProductionMovementRequest $request,
        ProductionMovement $productionMovement
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $movement = $this->movementManagementService->receive(
            productionMovement: $productionMovement,
            actor: $actor,
            request: $request
        );

        return (new ProductionMovementResource($movement))
            ->additional([
                'message' => 'Recepción de producción confirmada correctamente.',
            ])
            ->response();
    }
}