<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionIncident\IndexProductionIncidentRequest;
use App\Http\Requests\ProductionIncident\ResolveProductionIncidentRequest;
use App\Http\Requests\ProductionIncident\StoreProductionIncidentRequest;
use App\Http\Requests\ProductionIncident\UpdateProductionIncidentRequest;
use App\Http\Resources\ProductionIncidentResource;
use App\Models\ProductionIncident;
use App\Models\User;
use App\Services\ProductionIncidentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\ProductionIncident\ReturnProductionIncidentForReworkRequest;
use App\Http\Resources\ProductionMovementResource;

class ProductionIncidentController extends Controller
{
    public function __construct(
        private readonly ProductionIncidentManagementService $incidentManagementService
    ) {
    }

    public function index(
        IndexProductionIncidentRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $incidents = ProductionIncident::query()
            ->with([
                'garmentCut',

                'productionMovement.garmentCut.currentArea',

                'productionMovement.complement.currentArea',

                'productionMovement.specialProcessPiece.pieceType',
                'productionMovement.specialProcessPiece.process',
                'productionMovement.specialProcessPiece.currentArea',

                'productionMovement.process',
                'productionMovement.fromArea',
                'productionMovement.toArea',

                'responsibleEmployee.area',
                'resolvedBy',

                'reworkMovement.process',
                'reworkMovement.fromArea',
                'reworkMovement.toArea',
            ])
            ->when(
                $filters['garment_cut_id'] ?? null,
                fn ($query, $cutId) => $query->where(
                    'garment_cut_id',
                    $cutId
                )
            )
            ->when(
                $filters['production_movement_id'] ?? null,
                fn ($query, $movementId) => $query->where(
                    'production_movement_id',
                    $movementId
                )
            )
            ->when(
                $filters['responsible_employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'responsible_employee_id',
                    $employeeId
                )
            )
            ->when(
                $filters['incident_type'] ?? null,
                fn ($query, $type) => $query->where(
                    'incident_type',
                    $type
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
                            ->where(
                                'description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'notes',
                                'like',
                                "%{$search}%"
                            )
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

        return ProductionIncidentResource::collection(
            $incidents
        );
    }

    public function store(
        StoreProductionIncidentRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $incident = $this->incidentManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionIncidentResource($incident))
            ->additional([
                'message' =>
                    'Incidencia de producción registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        ProductionIncident $productionIncident
    ): ProductionIncidentResource {
        $productionIncident->load([
            'garmentCut.currentArea',

            'productionMovement.garmentCut.currentArea',

            'productionMovement.complement.currentArea',

            'productionMovement.specialProcessPiece.pieceType',
            'productionMovement.specialProcessPiece.process',
            'productionMovement.specialProcessPiece.currentArea',

            'productionMovement.process',
            'productionMovement.fromArea',
            'productionMovement.toArea',

            'responsibleEmployee.area',
            'resolvedBy',

            'reworkMovement.process',
            'reworkMovement.fromArea',
            'reworkMovement.toArea',
        ]);

        return new ProductionIncidentResource(
            $productionIncident
        );
    }

    public function update(
        UpdateProductionIncidentRequest $request,
        ProductionIncident $productionIncident
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $incident = $this->incidentManagementService->update(
            productionIncident: $productionIncident,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionIncidentResource($incident))
            ->additional([
                'message' =>
                    'Incidencia de producción actualizada correctamente.',
            ])
            ->response();
    }

    public function returnForRework(
        ReturnProductionIncidentForReworkRequest $request,
        ProductionIncident $productionIncident
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $movement = $this->incidentManagementService->returnForRework(
            productionIncident: $productionIncident,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionMovementResource($movement))
            ->additional([
                'message' =>
                    'Devolución para reproceso registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function resolve(
        ResolveProductionIncidentRequest $request,
        ProductionIncident $productionIncident
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $incident = $this->incidentManagementService->resolve(
            productionIncident: $productionIncident,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionIncidentResource($incident))
            ->additional([
                'message' =>
                    'Incidencia de producción resuelta correctamente.',
            ])
            ->response();
    }
}