<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeCompensation\IndexEmployeeCompensationRequest;
use App\Http\Requests\EmployeeCompensation\StoreEmployeeCompensationRequest;
use App\Http\Requests\EmployeeCompensation\UpdateEmployeeCompensationRequest;
use App\Http\Resources\EmployeeCompensationResource;
use App\Models\EmployeeCompensation;
use App\Models\User;
use App\Services\EmployeeCompensationManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EmployeeCompensationController extends Controller
{
    public function __construct(
        private readonly EmployeeCompensationManagementService $compensationManagementService
    ) {
    }

    public function index(
        IndexEmployeeCompensationRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'active';

        $compensations = EmployeeCompensation::query()
            ->with([
                'employee.area',
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
                $filters['payment_type'] ?? null,
                fn ($query, $paymentType) => $query->where(
                    'payment_type',
                    $paymentType
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
            ->latest('effective_from')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return EmployeeCompensationResource::collection(
            $compensations
        );
    }

    public function store(
        StoreEmployeeCompensationRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $compensation = $this->compensationManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmployeeCompensationResource($compensation))
            ->additional([
                'message' =>
                    'Compensación registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        EmployeeCompensation $employeeCompensation
    ): EmployeeCompensationResource {
        $employeeCompensation->load([
            'employee.area',
            'createdBy',
        ]);

        return new EmployeeCompensationResource(
            $employeeCompensation
        );
    }

    public function update(
        UpdateEmployeeCompensationRequest $request,
        EmployeeCompensation $employeeCompensation
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $compensation = $this->compensationManagementService->update(
            employeeCompensation: $employeeCompensation,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmployeeCompensationResource($compensation))
            ->additional([
                'message' =>
                    'Compensación actualizada correctamente.',
            ])
            ->response();
    }
}