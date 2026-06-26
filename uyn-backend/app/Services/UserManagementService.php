<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService
    ) {
    }

    public function create(
        array $data,
        User $actor,
        Request $request
    ): User {
        return DB::transaction(function () use ($data, $actor, $request) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
            ]);

            $user->syncRoles([$data['role']]);
            $user->load('roles');

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'users',
                action: 'created',
                subject: $user,
                description: "Se creó el usuario {$user->username}.",
                newValues: $this->snapshot($user),
            );

            return $user;
        });
    }

    public function update(
        User $user,
        array $data,
        User $actor,
        Request $request
    ): User {
        return DB::transaction(function () use ($user, $data, $actor, $request) {
            $target = User::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($user->id);

            $before = $this->snapshot($target);

            $this->ensureAdministratorContinuity(
                target: $target,
                newRole: $data['role'],
                newStatus: $target->status
            );

            $target->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
            ]);

            if (! empty($data['password'])) {
                $target->password = Hash::make($data['password']);
            }

            $target->save();
            $target->syncRoles([$data['role']]);

            $target = $target->fresh(['roles']);

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'users',
                action: 'updated',
                subject: $target,
                description: "Se actualizó el usuario {$target->username}.",
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    public function changeStatus(
        User $user,
        string $status,
        User $actor,
        Request $request
    ): User {
        return DB::transaction(function () use (
            $user,
            $status,
            $actor,
            $request
        ) {
            $target = User::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($user->id);

            if ($actor->is($target) && $status === 'inactive') {
                throw ValidationException::withMessages([
                    'user' => 'No puedes desactivar tu propia cuenta.',
                ]);
            }

            $before = $this->snapshot($target);

            $this->ensureAdministratorContinuity(
                target: $target,
                newRole: null,
                newStatus: $status
            );

            $target->update([
                'status' => $status,
            ]);

            $target = $target->fresh(['roles']);

            $action = $status === 'active'
                ? 'activated'
                : 'deactivated';

            $description = $status === 'active'
                ? "Se activó el usuario {$target->username}."
                : "Se desactivó el usuario {$target->username}.";

            $this->operationLogService->record(
                actor: $actor,
                request: $request,
                module: 'users',
                action: $action,
                subject: $target,
                description: $description,
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    private function ensureAdministratorContinuity(
        User $target,
        ?string $newRole,
        string $newStatus
    ): void {
        $isCurrentActiveAdministrator =
            $target->status === 'active'
            && $target->hasRole('Administrador');

        $willRemainActiveAdministrator =
            $newStatus === 'active'
            && ($newRole ?? 'Administrador') === 'Administrador';

        if (! $isCurrentActiveAdministrator || $willRemainActiveAdministrator) {
            return;
        }

        $activeAdministratorCount = User::role('Administrador')
            ->where('status', 'active')
            ->count();

        if ($activeAdministratorCount <= 1) {
            throw ValidationException::withMessages([
                'role' => 'No se puede retirar, cambiar de rol o desactivar al único administrador activo.',
            ]);
        }
    }

    private function snapshot(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }
}