<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmbroideryPaymentSetting\IndexEmbroideryPaymentSettingRequest;
use App\Http\Requests\EmbroideryPaymentSetting\StoreEmbroideryPaymentSettingRequest;
use App\Http\Requests\EmbroideryPaymentSetting\UpdateEmbroideryPaymentSettingRequest;
use App\Http\Resources\EmbroideryPaymentSettingResource;
use App\Models\EmbroideryPaymentSetting;
use App\Models\User;
use App\Services\EmbroideryPaymentSettingManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EmbroideryPaymentSettingController extends Controller
{
    public function __construct(
        private readonly EmbroideryPaymentSettingManagementService $settingManagementService
    ) {
    }

    public function index(
        IndexEmbroideryPaymentSettingRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'active';

        $settings = EmbroideryPaymentSetting::query()
            ->with([
                'operationProcess.process',
                'createdBy',
            ])
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
            ->latest('effective_from')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return EmbroideryPaymentSettingResource::collection($settings);
    }

    public function store(
        StoreEmbroideryPaymentSettingRequest $request
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $setting = $this->settingManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmbroideryPaymentSettingResource($setting))
            ->additional([
                'message' =>
                    'Configuración de pago de Bordado registrada correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        EmbroideryPaymentSetting $embroideryPaymentSetting
    ): EmbroideryPaymentSettingResource {
        $embroideryPaymentSetting->load([
            'operationProcess.process',
            'createdBy',
        ]);

        return new EmbroideryPaymentSettingResource(
            $embroideryPaymentSetting
        );
    }

    public function update(
        UpdateEmbroideryPaymentSettingRequest $request,
        EmbroideryPaymentSetting $embroideryPaymentSetting
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $setting = $this->settingManagementService->update(
            embroideryPaymentSetting: $embroideryPaymentSetting,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new EmbroideryPaymentSettingResource($setting))
            ->additional([
                'message' =>
                    'Configuración de pago de Bordado actualizada correctamente.',
            ])
            ->response();
    }
}