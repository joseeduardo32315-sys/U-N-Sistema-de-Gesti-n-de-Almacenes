<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ProductionCutReportRequest;
use App\Http\Requests\Report\ProductionMovementReportRequest;
use App\Http\Requests\Report\ProductionProcessReportRequest;
use App\Http\Resources\Report\ProductionCutReportResource;
use App\Http\Resources\Report\ProductionMovementReportResource;
use App\Http\Resources\Report\ProductionProcessReportResource;
use App\Services\Reports\ProductionReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductionReportController extends Controller
{
    public function __construct(
        private readonly ProductionReportService $productionReportService
    ) {
    }

    public function cuts(
        ProductionCutReportRequest $request
    ): AnonymousResourceCollection {
        $cuts = $this->productionReportService->getCutReport(
            $request->validated()
        );

        return ProductionCutReportResource::collection($cuts);
    }

    public function processes(
        ProductionProcessReportRequest $request
    ): AnonymousResourceCollection {
        $processes = $this->productionReportService->getProcessReport(
            $request->validated()
        );

        return ProductionProcessReportResource::collection($processes);
    }

    public function movements(
        ProductionMovementReportRequest $request
    ): AnonymousResourceCollection {
        $movements = $this->productionReportService
            ->getMovementReport($request->validated());

        return ProductionMovementReportResource::collection($movements);
    }
}