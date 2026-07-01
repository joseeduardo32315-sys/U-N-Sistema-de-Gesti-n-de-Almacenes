<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Size\IndexSizeRequest;
use App\Http\Resources\SizeResource;
use App\Models\Size;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SizeController extends Controller
{
    public function index(
        IndexSizeRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $status = $filters['status'] ?? 'active';

        $sizes = Size::query()
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->orderByRaw('CAST(name AS UNSIGNED)')
            ->orderBy('name')
            ->get();

        return SizeResource::collection($sizes);
    }
}