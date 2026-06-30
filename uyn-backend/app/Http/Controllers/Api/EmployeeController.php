<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\IndexEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeManagementService $employeeManagementService
    ) {
    }

    public function index(
        IndexEmployeeRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $employees = Employee::query()
            ->with('area')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['area_id'] ?? null,
                fn ($query, $areaId) => $query->where('area_id', $areaId)
            )
            ->when(
                $filters['worker_type'] ?? null,
                fn ($query, $workerType) => $query->where(
                    'worker_type',
                    $workerType
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $employee = $this->employeeManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmployeeResource($employee))
            ->additional([
                'message' => 'Trabajador registrado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource(
            $employee->load('area')
        );
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedEmployee = $this->employeeManagementService->update(
            employee: $employee,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmployeeResource($updatedEmployee))
            ->additional([
                'message' => 'Trabajador actualizado correctamente.',
            ])
            ->response();
    }

    public function deactivate(
        Request $request,
        Employee $employee
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedEmployee = $this->employeeManagementService->changeStatus(
            employee: $employee,
            status: 'inactive',
            actor: $actor,
            request: $request
        );

        return (new EmployeeResource($updatedEmployee))
            ->additional([
                'message' => 'Trabajador desactivado correctamente.',
            ])
            ->response();
    }

    public function activate(
        Request $request,
        Employee $employee
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updatedEmployee = $this->employeeManagementService->changeStatus(
            employee: $employee,
            status: 'active',
            actor: $actor,
            request: $request
        );

        return (new EmployeeResource($updatedEmployee))
            ->additional([
                'message' => 'Trabajador activado correctamente.',
            ])
            ->response();
    }
}