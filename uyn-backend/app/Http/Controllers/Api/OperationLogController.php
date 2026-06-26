<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperationLog\IndexOperationLogRequest;
use App\Http\Resources\OperationLogResource;
use App\Models\OperationLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperationLogController extends Controller
{
    public function index(
        IndexOperationLogRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $logs = OperationLog::query()
            ->with('user')
            ->when(
                $filters['user_id'] ?? null,
                fn ($query, $userId) => $query->where('user_id', $userId)
            )
            ->when(
                $filters['module'] ?? null,
                fn ($query, $module) => $query->where('module', $module)
            )
            ->when(
                $filters['action'] ?? null,
                fn ($query, $action) => $query->where('action', $action)
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($query, $dateFrom) => $query->whereDate(
                    'created_at',
                    '>=',
                    $dateFrom
                )
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($query, $dateTo) => $query->whereDate(
                    'created_at',
                    '<=',
                    $dateTo
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return OperationLogResource::collection($logs);
    }
}