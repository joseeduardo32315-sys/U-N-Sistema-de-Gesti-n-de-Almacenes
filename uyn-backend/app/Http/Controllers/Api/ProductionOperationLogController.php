<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionOperationLog\IndexProductionOperationLogRequest;
use App\Http\Requests\ProductionOperationLog\StoreProductionOperationLogRequest;
use App\Http\Requests\ProductionOperationLog\UpdateProductionOperationLogRequest;
use App\Http\Resources\ProductionOperationLogResource;
use App\Models\ProductionMovement;
use App\Models\ProductionOperationLog;
use App\Models\User;
use App\Services\ProductionOperationLogManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductionOperationLogController extends Controller
{
    public function __construct(
        private readonly ProductionOperationLogManagementService $operationLogManagementService
    ) {
    }

    public function index(
        IndexProductionOperationLogRequest $request,
        ProductionMovement $productionMovement
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $operationLogs = ProductionOperationLog::query()
            ->with([
                'employee',
                'operationProcess',
                'productionMovement',
            ])
            ->where(
                'production_movement_id',
                $productionMovement->id
            )
            ->when(
                $filters['employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'employee_id',
                    $employeeId
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where(
                    'status',
                    $status
                )
            )
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ProductionOperationLogResource::collection(
            $operationLogs
        );
    }

    public function store(
        StoreProductionOperationLogRequest $request,
        ProductionMovement $productionMovement
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $operationLog = $this->operationLogManagementService->assign(
            productionMovement: $productionMovement,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionOperationLogResource($operationLog))
            ->additional([
                'message' =>
                    'Trabajador asignado a la operación correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateProductionOperationLogRequest $request,
        ProductionOperationLog $productionOperationLog
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $operationLog = $this->operationLogManagementService->updateProgress(
            productionOperationLog: $productionOperationLog,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new ProductionOperationLogResource($operationLog))
            ->additional([
                'message' =>
                    $operationLog->status === 'completed'
                        ? 'Operación completada correctamente.'
                        : 'Avance de operación actualizado correctamente.',
            ])
            ->response();
    }
}