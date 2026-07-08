<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollPeriod\ClosePayrollPeriodRequest;
use App\Http\Requests\PayrollPeriod\GeneratePayrollPeriodRequest;
use App\Http\Requests\PayrollPeriod\IndexPayrollPeriodRequest;
use App\Http\Requests\PayrollPeriod\StorePayrollPeriodRequest;
use App\Http\Requests\PayrollPeriod\UpdatePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollPeriodManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayrollPeriodController extends Controller
{
    public function __construct(
        private readonly PayrollPeriodManagementService $payrollPeriodManagementService
    ) {
    }

    public function index(
        IndexPayrollPeriodRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'all';

        $periods = PayrollPeriod::query()
            ->with([
                'createdBy',
                'generatedBy',
                'closedBy',
            ])
            ->withCount([
                'employeeSummaries',
                'details',
            ])
            ->when(
                $filters['frequency'] ?? null,
                fn ($query, $frequency) => $query->where(
                    'frequency',
                    $frequency
                )
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $filters['from'] ?? null,
                fn ($query, $from) => $query->whereDate(
                    'end_date',
                    '>=',
                    $from
                )
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $to) => $query->whereDate(
                    'start_date',
                    '<=',
                    $to
                )
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%");
                    });
                }
            )
            ->latest('start_date')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return PayrollPeriodResource::collection($periods);
    }

    public function store(
        StorePayrollPeriodRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $period = $this->payrollPeriodManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PayrollPeriodResource($period))
            ->additional([
                'message' =>
                    'Periodo de nómina creado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        PayrollPeriod $payrollPeriod
    ): PayrollPeriodResource {
        $payrollPeriod->load([
            'createdBy',
            'generatedBy',
            'closedBy',
            'employeeSummaries.employee.area',
            'employeeSummaries.details.productionOperationLog.operationProcess.process',
            'employeeSummaries.details.productionOperationLog.productionMovement',
            'employeeSummaries.details.employeeCompensation',
        ]);

        return new PayrollPeriodResource($payrollPeriod);
    }

    public function update(
        UpdatePayrollPeriodRequest $request,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $period = $this->payrollPeriodManagementService->update(
            payrollPeriod: $payrollPeriod,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PayrollPeriodResource($period))
            ->additional([
                'message' =>
                    'Periodo de nómina actualizado correctamente.',
            ])
            ->response();
    }

    public function generate(
        GeneratePayrollPeriodRequest $request,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $period = $this->payrollPeriodManagementService->generate(
            payrollPeriod: $payrollPeriod,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PayrollPeriodResource($period))
            ->additional([
                'message' =>
                    'Periodo de nómina generado correctamente.',
            ])
            ->response();
    }

    public function close(
        ClosePayrollPeriodRequest $request,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $period = $this->payrollPeriodManagementService->close(
            payrollPeriod: $payrollPeriod,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new PayrollPeriodResource($period))
            ->additional([
                'message' =>
                    'Periodo de nómina cerrado correctamente.',
            ])
            ->response();
    }
}