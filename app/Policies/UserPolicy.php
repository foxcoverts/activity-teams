<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOversight();
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model)
            || $user->hasOversight()
            || $this->sharesManagedTeam($user, $model)
            || $this->systemAdminOfCurrentMember($user, $model);
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

    private function systemAdminOfCurrentMember(User $user, User $model): bool
    {
        if (! $user->isSystemAdmin()) {
            return false;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team && $model->isCurrentMemberOf($tenant);
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
