<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update');
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $actor->can('users.deactivate')
            && ! $actor->is($user);
    }

    public function activate(User $actor, User $user): bool
    {
        return $actor->can('users.activate');
    }
}