<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ProductionIncidentReportRequest;
use App\Http\Requests\Report\ProductionLossReportRequest;
use App\Http\Requests\Report\ProductionReworkReportRequest;
use App\Http\Resources\Report\ProductionIncidentReportResource;
use App\Http\Resources\Report\ProductionLossReportResource;
use App\Http\Resources\Report\ProductionReworkReportResource;
use App\Services\Reports\ProductionIncidentReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductionIncidentReportController extends Controller
{
    public function __construct(
        private readonly ProductionIncidentReportService $incidentReportService
    ) {
    }

    public function incidents(
        ProductionIncidentReportRequest $request
    ): AnonymousResourceCollection {
        $incidents = $this->incidentReportService->getIncidentReport(
            $request->validated()
        );

        return ProductionIncidentReportResource::collection(
            $incidents
        );
    }

    public function losses(
        ProductionLossReportRequest $request
    ): AnonymousResourceCollection {
        $losses = $this->incidentReportService->getLossReport(
            $request->validated()
        );

        return ProductionLossReportResource::collection($losses);
    }

    public function reworks(
        ProductionReworkReportRequest $request
    ): AnonymousResourceCollection {
        $reworks = $this->incidentReportService->getReworkReport(
            $request->validated()
        );

        return ProductionReworkReportResource::collection($reworks);
    }
}