<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PieceworkRate\IndexPieceworkRateRequest;
use App\Http\Requests\PieceworkRate\StorePieceworkRateRequest;
use App\Http\Requests\PieceworkRate\UpdatePieceworkRateRequest;
use App\Http\Resources\PieceworkRateResource;
use App\Models\PieceworkRate;
use App\Models\User;
use App\Services\PieceworkRateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PieceworkRateController extends Controller
{
    public function __construct(
        private readonly PieceworkRateManagementService $pieceworkRateManagementService
    ) {
    }

    public function index(
        IndexPieceworkRateRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'active';

        $rates = PieceworkRate::query()
            ->with([
                'employee.area',
                'operationProcess.process',
                'createdBy',
            ])
            ->when(
                $filters['employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where(
                    'employee_id',
                    $employeeId
                )
            )
            ->when(
                $filters['operation_process_id'] ?? null,
                fn ($query, $operationId) => $query->where(
                    'operation_process_id',
                    $operationId
                )
            )
            ->when(
                $filters['active_on'] ?? null,
                function ($query, $date) {
                    $query
                        ->where('status', 'active')
                        ->whereDate('effective_from', '<=', $date)
                        ->where(function ($subQuery) use ($date) {
                            $subQuery
                                ->whereNull('effective_to')
                                ->orWhereDate(
                                    'effective_to',
                                    '>=',
                                    $date
                                );
                        });
                }
            )
            ->when(
                ! ($filters['active_on'] ?? null)
                && $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->whereHas(
                                'employee',
                                fn ($employeeQuery) => $employeeQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                            )
                            ->orWhereHas(
                                'operationProcess',
                                function ($operationQuery) use ($search) {
                                    $operationQuery
                                        ->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhereHas(
                                            'process',
                                            fn ($processQuery) => $processQuery
                                                ->where(
                                                    'name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                        );
                                }
                            )
                            ->orWhere(
                                'notes',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->latest('effective_from')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return PieceworkRateResource::collection($rates);
    }

    public function store(
        StorePieceworkRateRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $rate = $this->pieceworkRateManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PieceworkRateResource($rate))
            ->additional([
                'message' =>
                    'Tarifa por destajo registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        PieceworkRate $pieceworkRate
    ): PieceworkRateResource {
        $pieceworkRate->load([
            'employee.area',
            'operationProcess.process',
            'createdBy',
        ]);

        return new PieceworkRateResource($pieceworkRate);
    }

    public function update(
        UpdatePieceworkRateRequest $request,
        PieceworkRate $pieceworkRate
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $rate = $this->pieceworkRateManagementService->update(
            pieceworkRate: $pieceworkRate,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PieceworkRateResource($rate))
            ->additional([
                'message' =>
                    'Tarifa por destajo actualizada correctamente.',
            ])
            ->response();
    }
}