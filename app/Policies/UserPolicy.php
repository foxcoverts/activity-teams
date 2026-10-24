<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOversight() || $user->isSystemAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model)
            || $user->hasOversight()
            || $user->isSystemAdmin()
            || $this->sharesManagedTeam($user, $model);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return ! $model->trashed() && $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->trashed() || $user->isSuspended()) {
            return false;
        }

        if ($user->is($model)) {
            return true;
        }

        return $user->hasOversight();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isSystemAdmin()
            && $model->trashed()
            && filled($model->email);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Suspension is system-wide, so team managers may suspend anyone who shares one of
     * their teams, but only oversight and system admins may suspend those with site-wide roles.
     */
    public function suspend(User $user, User $model): bool
    {
        if ($model->trashed() || $user->isSuspended() || $user->is($model)) {
            return false;
        }

        if ($user->hasOversight() || $user->isSystemAdmin()) {
            return true;
        }

        return ! $model->oversight
            && ! $model->is_system_admin
            && $this->sharesManagedTeam($user, $model);
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
