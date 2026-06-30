<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

use App\Http\Resources\AreaResource;

use App\Models\Area;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $areas = Area::query()
            ->orderByRaw("
                CASE
                    WHEN 'Corte' THEN 1
                    WHEN 'Diseño' THEN 2
                    WHEN 'Bordado' THEN 3
                    WHEN 'Maquila' THEN 4
                    WHEN 'Preparación' THEN 5
                    WHEN 'Terminado' THEN 6
                    ELSE 99
                END
            ")
            ->get();

            return AreaResource::collection($areas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
