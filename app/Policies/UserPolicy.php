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

    /**
     * Suspension is system-wide, so team managers may suspend anyone who shares one of
     * their teams, but only oversight and system admins may suspend those with site-wide roles.
     */
    public function suspend(User $user, User $model): bool
    {
        if ($user->isSuspended() || $user->is($model)) {
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
