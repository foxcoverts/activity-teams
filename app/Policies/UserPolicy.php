<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOversight();
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $user->hasOversight() || $this->sharesManagedTeam($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasOversight();
    }

    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasOversight() && ! $user->is($model);
    }

    private function sharesManagedTeam(User $user, User $model): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $model->teams()
            ->whereIn('teams.id', $user->managedTeams()->select('teams.id'))
            ->exists();
    }
}
