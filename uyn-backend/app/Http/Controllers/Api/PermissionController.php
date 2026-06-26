<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->withCount('roles')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        return PermissionResource::collection($permissions);
    }
}