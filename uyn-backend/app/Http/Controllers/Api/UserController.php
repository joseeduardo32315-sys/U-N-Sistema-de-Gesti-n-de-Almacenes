<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService
    ) {
    }

    public function index(
        IndexUserRequest $request
    ): AnonymousResourceCollection {

        $filters = $request->validated();

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['role'] ?? null,
                fn ($query, $role) => $query->role($role)
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = $request->user();

        $user = $this->userManagementService->create(
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new UserResource($user))
            ->additional([
                'message' => 'Usuario creado correctamente.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load([
            'roles.permissions',
            'permissions',
        ]);

        return new UserResource($user);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        $updatedUser = $this->userManagementService->update(
            user: $user,
            data: $request->validated(),
            actor: $actor,
            request: $request
        );

        return (new UserResource(
            $updatedUser->load(['roles.permissions', 'permissions'])
        ))
            ->additional([
                'message' => 'Usuario actualizado correctamente.',
            ])
            ->response();
    }

    public function deactivate(
        Request $request,
        User $user
    ): JsonResponse {
        $this->authorize('deactivate', $user);

        /** @var User $actor */
        $actor = $request->user();

        $updatedUser = $this->userManagementService->changeStatus(
            user: $user,
            status: 'inactive',
            actor: $actor,
            request: $request
        );

        return (new UserResource(
            $updatedUser->load(['roles.permissions', 'permissions'])
        ))
            ->additional([
                'message' => 'Usuario desactivado correctamente.',
            ])
            ->response();
    }

    public function activate(
        Request $request,
        User $user
    ): JsonResponse {
        $this->authorize('activate', $user);

        /** @var User $actor */
        $actor = $request->user();

        $updatedUser = $this->userManagementService->changeStatus(
            user: $user,
            status: 'active',
            actor: $actor,
            request: $request
        );

        return (new UserResource(
            $updatedUser->load(['roles.permissions', 'permissions'])
        ))
            ->additional([
                'message' => 'Usuario activado correctamente.',
            ])
            ->response();
    }
}