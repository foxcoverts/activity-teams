<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->isTeamLeader() || $user->isUserManager();
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, User $model): bool
    {
        if ($model->isTeamLeader() && ! $user->isTeamLeader()) {
            return false;
        }

        return $user->is($model) || $this->manage($user);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->isTeamLeader()) {
            return $user->isTeamLeader() && ! $user->is($model);
        }

        return $user->is($model) || $this->manage($user);
    }
}
