<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Process\IndexProcessRequest;
use App\Http\Resources\ProcessResource;
use App\Models\Process;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcessController extends Controller
{
    public function index(
        IndexProcessRequest $request
    ): AnonymousResourceCollection {
        $processes = Process::query()
            ->with([
                'operationProcesses' => fn ($query) => $query
                    ->orderBy('flow_order'),
            ])
            ->orderBy('flow_order')
            ->get();

        return ProcessResource::collection($processes);
    }
}