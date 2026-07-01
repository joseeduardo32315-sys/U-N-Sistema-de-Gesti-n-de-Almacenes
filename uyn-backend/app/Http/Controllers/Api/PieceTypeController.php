<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PieceType\IndexPieceTypeRequest;
use App\Http\Resources\PieceTypeResource;
use App\Models\PieceType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PieceTypeController extends Controller
{
    public function index(
        IndexPieceTypeRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'active';

        $pieceTypes = PieceType::query()
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->orderBy('name')
            ->get();

        return PieceTypeResource::collection($pieceTypes);
    }
}